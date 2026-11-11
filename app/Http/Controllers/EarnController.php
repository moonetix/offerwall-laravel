<?php

namespace App\Http\Controllers;

use App\Models\MoonetixTransaction;
use App\Support\Moonetix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Earn page. The link is signed here, on the server, for the signed-in user only.
 */
class EarnController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        // Users must be 16 or older. No link is built before they confirm it.
        $wallUrl = $user->age_confirmed_at ? Moonetix::wallUrl((string) $user->getKey()) : null;

        $recent = MoonetixTransaction::query()
            ->where('wall_user_id', (string) $user->getKey())
            ->latest('id')
            ->limit(10)
            ->get();

        return response()
            ->view('earn', [
                'user' => $user,
                'wallUrl' => $wallUrl,
                'balance' => Moonetix::formatMicro($user->balance_micro),
                'recent' => $recent,
            ])
            // A signed link is personal. Never let a cache serve it to someone else.
            ->header('Cache-Control', 'private, no-store');
    }

    public function confirmAge(Request $request): RedirectResponse
    {
        $request->validate(['age_16_or_older' => ['accepted']]);

        $user = $request->user();
        if ($user->age_confirmed_at === null) {
            $user->forceFill(['age_confirmed_at' => now()])->save();
        }

        return redirect()->route('earn');
    }
}
