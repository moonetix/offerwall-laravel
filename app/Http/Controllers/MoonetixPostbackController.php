<?php

namespace App\Http\Controllers;

use App\Models\MoonetixTransaction;
use App\Models\User;
use App\Support\Moonetix;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET /moonetix/postback?tx={transaction_id}&user={user_id}&reward={reward}&status={status}&hash={hash}
 *
 * Answers 200 only after the change is committed. Any exception rolls back and
 * Laravel answers 500, so Moonetix retries the event later.
 */
class MoonetixPostbackController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Values exactly as they arrived. A key sent as an array (tx[]=...) becomes '' and fails the hash.
        $q = fn (string $key): string => is_string($v = $request->query($key)) ? $v : '';
        [$tx, $userId, $reward, $status] = [$q('tx'), $q('user'), $q('reward'), $q('status')];

        // 1. The hash first, in constant time. Nothing else runs before this check.
        if (! Moonetix::validPostbackHash($tx, $userId, $reward, $status, $q('hash'))) {
            return $this->answer(403, 'Invalid hash');
        }

        // 2. Signed values, now check their shape.
        $rewardMicro = Moonetix::toMicro($reward);
        if ($tx === '' || strlen($tx) > 191 || strlen($userId) > 100 || $rewardMicro === null
            || ($status !== '1' && $status !== '2')) {
            return $this->answer(400, 'Bad parameters');
        }

        // 3. Tests from the dashboard always have a transaction_id that starts with test_.
        if (str_starts_with($tx, 'test_')) {
            Log::info('Moonetix test postback: hash OK, nothing credited', compact('tx', 'userId', 'reward', 'status'));

            return $this->answer(200, 'Test postback: hash OK, nothing credited');
        }

        // 4. Apply it in one database transaction.
        $offer = mb_substr($q('offer'), 0, 200) ?: null;
        $result = DB::transaction(fn () => $status === '1'
            ? $this->credit($tx, $userId, $rewardMicro, $offer)
            : $this->reverse($tx, $userId, $rewardMicro, $offer));

        Log::info('Moonetix postback', compact('tx', 'userId', 'reward', 'status', 'result'));

        // 5. Committed. 200 also for repeats and ignored events, so Moonetix stops retrying.
        return $this->answer(200, $result);
    }

    private function credit(string $tx, string $userId, int $rewardMicro, ?string $offer): string
    {
        $user = $this->findUser($userId);
        if ($user === null) {
            Log::warning('Moonetix credit for an unknown user', ['tx' => $tx, 'user' => $userId]);

            return 'Unknown user, nothing credited';
        }

        // The unique key on transaction_id decides: only the first insert credits.
        $inserted = DB::table('moonetix_transactions')->insertOrIgnore([
            'transaction_id' => $tx,
            'wall_user_id' => $userId,
            'reward_micro' => $rewardMicro,
            'status' => MoonetixTransaction::CREDITED,
            'offer_name' => $offer,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted !== 1) {
            return 'Already processed';
        }

        User::whereKey($user->getKey())->increment('balance_micro', $rewardMicro);

        return 'Credited';
    }

    private function reverse(string $tx, string $userId, int $rewardMicro, ?string $offer): string
    {
        // Flips 1 to 2 at most once, also when two copies arrive at the same time.
        $flipped = DB::table('moonetix_transactions')
            ->where('transaction_id', $tx)
            ->where('status', MoonetixTransaction::CREDITED)
            ->update(['status' => MoonetixTransaction::REVERSED, 'updated_at' => now()]);

        if ($flipped === 1) {
            // Take back exactly what was credited, from the user who got it.
            $row = DB::table('moonetix_transactions')->where('transaction_id', $tx)->first();
            $user = $this->findUser($row->wall_user_id);
            $user?->decrement('balance_micro', (int) $row->reward_micro);

            return 'Reversed';
        }

        // Never credited: remember it, so a late credit for this transaction is never paid.
        $inserted = DB::table('moonetix_transactions')->insertOrIgnore([
            'transaction_id' => $tx,
            'wall_user_id' => $userId,
            'reward_micro' => $rewardMicro,
            'status' => MoonetixTransaction::REVERSED,
            'offer_name' => $offer,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted === 1 ? 'Not credited before, nothing taken back' : 'Already processed';
    }

    /**
     * The user_id in the link is our users.id. Anything else (for example test_user) is not ours.
     * Using UUIDs? Change the pattern and drop the (int) cast.
     */
    private function findUser(string $userId): ?User
    {
        if (! preg_match('/^[1-9][0-9]{0,17}$/', $userId)) {
            return null;
        }

        return User::find((int) $userId);
    }

    private function answer(int $code, string $body): Response
    {
        return response($body, $code, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
