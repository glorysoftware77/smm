<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\TikTokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TikTokConnectController extends Controller
{
    public function redirect(Request $request, TikTokService $tiktok): RedirectResponse
    {
        $state = $tiktok->generateState();
        $request->session()->put('tiktok_oauth_state', $state);

        return redirect()->away($tiktok->authorizationUrl($state));
    }

    public function callback(Request $request, TikTokService $tiktok): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'TikTok connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        $state = $request->session()->pull('tiktok_oauth_state');

        if (! $state || $state !== $request->string('state')->toString()) {
            return redirect()->route('dashboard')->with('error', 'Invalid TikTok OAuth state. Please try again.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('dashboard')->with('error', 'TikTok did not return an authorization code.');
        }

        try {
            $token = $tiktok->exchangeCodeForToken($code);
            $accessToken = $token['access_token'];
            $refreshToken = $token['refresh_token'] ?? null;
            $expiresIn = $token['expires_in'] ?? 86400;
            $openId = (string) ($token['open_id'] ?? '');

            $profile = $tiktok->getUserInfo($accessToken);
            $openId = $openId !== '' ? $openId : $profile['open_id'];

            DB::transaction(function () use ($request, $profile, $openId, $accessToken, $refreshToken, $expiresIn) {
                $existing = SocialAccount::query()
                    ->where('user_id', $request->user()->id)
                    ->where('provider', 'tiktok')
                    ->where('provider_user_id', $openId)
                    ->first();

                $account = SocialAccount::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'tiktok',
                        'provider_user_id' => $openId,
                    ],
                    [
                        'access_token' => $accessToken,
                        'refresh_token' => $refreshToken ?: $existing?->refresh_token,
                        'token_expires_at' => Carbon::now()->addSeconds((int) $expiresIn),
                        'name' => $profile['display_name'],
                    ]
                );

                SocialPage::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'tiktok',
                        'page_id' => $openId,
                    ],
                    [
                        'social_account_id' => $account->id,
                        'linked_social_page_id' => null,
                        'name' => '@'.$profile['display_name'],
                        'category' => 'TikTok',
                        'picture_url' => $profile['avatar_url'],
                        'access_token' => $accessToken,
                        'is_connected' => true,
                    ]
                );
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not connect TikTok: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'TikTok connected. Until audit approval, posts must be private (SELF_ONLY) and the TikTok account should be private while testing.');
    }

    public function disconnectAccount(Request $request): RedirectResponse
    {
        SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'tiktok')
            ->delete();

        return redirect()->route('dashboard')->with('success', 'TikTok account disconnected.');
    }
}
