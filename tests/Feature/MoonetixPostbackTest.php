<?php

namespace Tests\Feature;

use App\Models\MoonetixTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every postback here was produced and signed by the Moonetix postback code
 * (tests/Fixtures/moonetix-vectors.json), with fake test keys.
 */
class MoonetixPostbackTest extends TestCase
{
    use RefreshDatabase;

    private array $pb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pb = json_decode(file_get_contents(__DIR__.'/../Fixtures/moonetix-vectors.json'), true)['postbacks'];
        User::factory()->count(2)->create(); // users 1 and 2
    }

    private function balance(int $id): string
    {
        return \App\Support\Moonetix::formatMicro(User::findOrFail($id)->balance_micro);
    }

    public function test_credit_is_applied_once(): void
    {
        $this->get($this->pb['credit_150'])->assertOk()->assertSeeText('Credited');
        $this->assertSame('150', $this->balance(1));

        // Moonetix retries, or the same event arrives twice.
        $this->get($this->pb['credit_150'])->assertOk()->assertSeeText('Already processed');
        $this->assertSame('150', $this->balance(1));
        $this->assertSame(1, MoonetixTransaction::where('transaction_id', 'tx_1001')->count());

        $row = MoonetixTransaction::where('transaction_id', 'tx_1001')->firstOrFail();
        $this->assertSame('1', $row->wall_user_id);
        $this->assertSame(MoonetixTransaction::CREDITED, $row->status);
        $this->assertSame('Bigo Live & "Friends"', $row->offer_name);
    }

    public function test_decimal_rewards_add_up_exactly(): void
    {
        $this->get($this->pb['credit_150'])->assertOk();
        $this->get($this->pb['credit_decimal'])->assertOk();
        $this->get($this->pb['credit_five_decimals'])->assertOk();
        $this->assertSame('151.98456', $this->balance(1));

        $this->get($this->pb['credit_user2'])->assertOk();
        $this->get($this->pb['credit_large'])->assertOk();
        $this->assertSame('1234607.125', $this->balance(2));
        $this->assertSame('151.98456', $this->balance(1));
    }

    public function test_reversal_takes_back_exactly_what_was_credited_once(): void
    {
        $this->get($this->pb['credit_150'])->assertOk();
        $this->get($this->pb['credit_decimal'])->assertOk();

        $this->get($this->pb['reverse_150'])->assertOk()->assertSeeText('Reversed');
        $this->assertSame('0.75', $this->balance(1));

        $this->get($this->pb['reverse_150'])->assertOk()->assertSeeText('Already processed');
        $this->assertSame('0.75', $this->balance(1));

        // A credit that arrives again after the reversal pays nothing.
        $this->get($this->pb['credit_150'])->assertOk()->assertSeeText('Already processed');
        $this->assertSame('0.75', $this->balance(1));
    }

    public function test_reversal_can_take_the_balance_below_zero(): void
    {
        $this->get($this->pb['credit_150'])->assertOk();
        User::whereKey(1)->decrement('balance_micro', 100_000_000); // the user spent 100 coins
        $this->get($this->pb['reverse_150'])->assertOk();
        $this->assertSame('-100', $this->balance(1));
    }

    public function test_reversal_without_credit_changes_nothing_and_blocks_a_late_credit(): void
    {
        $this->get($this->pb['reverse_never_credited'])->assertOk()->assertSeeText('Not credited before');
        $this->assertSame('0', $this->balance(1));

        $this->get($this->pb['late_credit_after_reverse'])->assertOk()->assertSeeText('Already processed');
        $this->assertSame('0', $this->balance(1));
    }

    public function test_dashboard_test_postbacks_are_verified_but_not_credited(): void
    {
        $this->get($this->pb['test_credit'])->assertOk()->assertSeeText('Test postback');
        $this->get($this->pb['test_reverse'])->assertOk()->assertSeeText('Test postback');
        $this->assertSame('0', $this->balance(1));
        $this->assertSame(0, MoonetixTransaction::count());
    }

    public function test_unknown_users_get_200_and_nothing_is_stored(): void
    {
        $this->get($this->pb['unknown_user'])->assertOk()->assertSeeText('Unknown user');
        $this->get($this->pb['not_an_account_id'])->assertOk()->assertSeeText('Unknown user');
        $this->assertSame(0, MoonetixTransaction::count());
    }

    public function test_wrong_key_is_rejected(): void
    {
        $this->get($this->pb['wrong_key'])->assertForbidden();
        $this->assertSame('0', $this->balance(1));
    }

    public function test_tampered_values_are_rejected(): void
    {
        $url = $this->pb['credit_150'];
        $this->get(str_replace('reward=150&', 'reward=1500&', $url))->assertForbidden();
        $this->get(str_replace('user=1&', 'user=2&', $url))->assertForbidden();
        $this->get(str_replace('tx=tx_1001&', 'tx=tx_1002&', $url))->assertForbidden();
        $this->get(str_replace('status=1&', 'status=2&', $url))->assertForbidden();
        $this->assertSame('0', $this->balance(1));
        $this->assertSame('0', $this->balance(2));
    }

    public function test_malformed_hashes_are_rejected(): void
    {
        $url = $this->pb['credit_150'];
        preg_match('/hash=([0-9a-f]{64})/', $url, $m);

        $this->get(str_replace($m[1], strtoupper($m[1]), $url))->assertForbidden();
        $this->get(str_replace('&hash='.$m[1], '', $url))->assertForbidden();
        $this->get(str_replace('hash=', 'hash[]=', $url))->assertForbidden();
        $this->get(str_replace('tx=tx_1001', 'tx[]=tx_1001', $url))->assertForbidden();
        $this->get('/moonetix/postback')->assertForbidden();
        $this->assertSame('0', $this->balance(1));
    }

    public function test_only_get_is_allowed_and_no_session_cookie_is_set(): void
    {
        $this->post($this->pb['credit_150'])->assertStatus(405);

        $response = $this->get($this->pb['credit_150'])->assertOk();
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame('150', $this->balance(1));
    }

    public function test_missing_postback_key_answers_500_so_moonetix_retries(): void
    {
        config(['services.moonetix.postback_key' => null]);
        $this->withoutExceptionHandling();
        $this->expectExceptionMessage('MOONETIX_POSTBACK_KEY is not set');
        $this->get($this->pb['credit_150']);
    }

    public function test_storage_failure_answers_500_and_changes_nothing(): void
    {
        Schema::drop('moonetix_transactions');

        $this->get($this->pb['credit_150'])->assertStatus(500);
        $this->assertSame('0', $this->balance(1));
    }

    public function test_failure_after_insert_rolls_back_the_whole_credit(): void
    {
        // Break the balance update after the ledger insert: nothing may stay half applied.
        Schema::table('users', fn ($t) => $t->dropColumn('balance_micro'));

        $this->get($this->pb['credit_150'])->assertStatus(500);
        $this->assertSame(0, MoonetixTransaction::count());
    }
}
