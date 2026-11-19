<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\InstagramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class InstagramConnectController extends Controller
{
    public function redirect(Request $request, InstagramService $instagram): RedirectResponse
    {
        $state = $instagram->generateState();
        $request->session()->put('instagram_oauth_state', $state);

        return redirect()->away($instagram->authorizationUrl($state));
    }

    public function callback(Request $request, InstagramService $instagram): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Instagram connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        $state = $request->session()->pull('instagram_oauth_state');

        if (! $state || $state !== $request->string('state')->toString()) {
            return redirect()->route('dashboard')->with('error', 'Invalid Instagram OAuth state. Please try again.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('dashboard')->with('error', 'Instagram did not return an authorization code.');
        }

        try {
            $shortLived = $instagram->exchangeCodeForToken($code);
            $longLived = $instagram->exchangeForLongLivedToken($shortLived['access_token']);
            $accessToken = $longLived['access_token'];
            $expiresIn = $longLived['expires_in'] ?? null;

            $profile = $instagram->getProfile($accessToken);
            $igUserId = (string) ($profile['user_id'] ?? $profile['id'] ?? $shortLived['user_id'] ?? '');

            if ($igUserId === '') {
                throw new \RuntimeException('Could not determine Instagram user id.');
            }

            $username = $profile['username'] ?? null;

            DB::transaction(function () use ($request, $igUserId, $accessToken, $expiresIn, $username, $profile) {
                $account = SocialAccount::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'instagram',
                        'provider_user_id' => $igUserId,
                    ],
                    [
                        'access_token' => $accessToken,
                        'token_expires_at' => $expiresIn ? Carbon::now()->addSeconds((int) $expiresIn) : null,
                        'name' => $username ? '@'.$username : ($profile['name'] ?? 'Instagram'),
                    ]
                );

                SocialPage::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'instagram',
                        'page_id' => $igUserId,
                    ],
                    [
                        'social_account_id' => $account->id,
                        'linked_social_page_id' => null,
                        'name' => $username ? '@'.$username : ($profile['name'] ?? 'Instagram'),
                        'category' => 'Instagram',
                        'picture_url' => $profile['profile_picture_url'] ?? null,
                        'access_token' => $accessToken,
                        'is_connected' => true,
                    ]
                );
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not connect Instagram: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Instagram connected successfully.');
    }

    public function disconnectAccount(Request $request): RedirectResponse
    {
        SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'instagram')
            ->delete();

        return redirect()->route('dashboard')->with('success', 'Instagram account disconnected.');
    }
}
