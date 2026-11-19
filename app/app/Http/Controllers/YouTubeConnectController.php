<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\YouTubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class YouTubeConnectController extends Controller
{
    public function redirect(Request $request, YouTubeService $youtube): RedirectResponse
    {
        $state = $youtube->generateState();
        $request->session()->put('youtube_oauth_state', $state);

        return redirect()->away($youtube->authorizationUrl($state));
    }

    public function callback(Request $request, YouTubeService $youtube): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'YouTube connection cancelled: '.$request->string('error'));
        }

        $state = $request->session()->pull('youtube_oauth_state');

        if (! $state || $state !== $request->string('state')->toString()) {
            return redirect()->route('dashboard')->with('error', 'Invalid YouTube OAuth state. Please try again.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('dashboard')->with('error', 'YouTube did not return an authorization code.');
        }

        try {
            $token = $youtube->exchangeCodeForToken($code);
            $accessToken = $token['access_token'];
            $refreshToken = $token['refresh_token'] ?? null;
            $expiresIn = $token['expires_in'] ?? 3600;

            $channel = $youtube->getMyChannel($accessToken);

            DB::transaction(function () use ($request, $channel, $accessToken, $refreshToken, $expiresIn) {
                $existing = SocialAccount::query()
                    ->where('user_id', $request->user()->id)
                    ->where('provider', 'youtube')
                    ->where('provider_user_id', $channel['channel_id'])
                    ->first();

                $account = SocialAccount::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'youtube',
                        'provider_user_id' => $channel['channel_id'],
                    ],
                    [
                        'access_token' => $accessToken,
                        'refresh_token' => $refreshToken ?: $existing?->refresh_token,
                        'token_expires_at' => Carbon::now()->addSeconds((int) $expiresIn),
                        'name' => $channel['title'],
                    ]
                );

                SocialPage::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'youtube',
                        'page_id' => $channel['channel_id'],
                    ],
                    [
                        'social_account_id' => $account->id,
                        'linked_social_page_id' => null,
                        'name' => $channel['title'],
                        'category' => 'YouTube Channel',
                        'picture_url' => $channel['thumbnail'],
                        'access_token' => $accessToken,
                        'is_connected' => true,
                    ]
                );
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not connect YouTube: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'YouTube channel connected. Until Google audit approval, uploads stay private.');
    }

    public function disconnectAccount(Request $request): RedirectResponse
    {
        SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'youtube')
            ->delete();

        return redirect()->route('dashboard')->with('success', 'YouTube account disconnected.');
    }
}
