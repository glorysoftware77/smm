<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\FacebookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class FacebookConnectController extends Controller
{
    public function redirect(Request $request, FacebookService $facebook): RedirectResponse
    {
        $state = $facebook->generateState();
        $request->session()->put('facebook_oauth_state', $state);

        return redirect()->away($facebook->authorizationUrl($state));
    }

    public function callback(Request $request, FacebookService $facebook): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Facebook connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        $state = $request->session()->pull('facebook_oauth_state');

        if (! $state || $state !== $request->string('state')->toString()) {
            return redirect()->route('dashboard')->with('error', 'Invalid Facebook OAuth state. Please try again.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('dashboard')->with('error', 'Facebook did not return an authorization code.');
        }

        try {
            $shortLived = $facebook->exchangeCodeForToken($code);
            $longLived = $facebook->exchangeForLongLivedToken($shortLived['access_token']);
            $accessToken = $longLived['access_token'];
            $expiresIn = $longLived['expires_in'] ?? null;

            $profile = $facebook->getUserProfile($accessToken);
            $pages = $facebook->getUserPages($accessToken);

            DB::transaction(function () use ($request, $profile, $accessToken, $expiresIn, $pages) {
                $account = SocialAccount::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'facebook',
                        'provider_user_id' => $profile['id'],
                    ],
                    [
                        'access_token' => $accessToken,
                        'token_expires_at' => $expiresIn ? Carbon::now()->addSeconds((int) $expiresIn) : null,
                        'name' => $profile['name'] ?? null,
                    ]
                );

                $seenPageIds = [];

                foreach ($pages as $page) {
                    $seenPageIds[] = $page['id'];

                    SocialPage::query()->updateOrCreate(
                        [
                            'user_id' => $request->user()->id,
                            'provider' => 'facebook',
                            'page_id' => $page['id'],
                        ],
                        [
                            'social_account_id' => $account->id,
                            'name' => $page['name'],
                            'category' => $page['category'] ?? null,
                            'picture_url' => $page['picture']['data']['url'] ?? null,
                            'access_token' => $page['access_token'],
                            'is_connected' => true,
                        ]
                    );
                }

                if (count($seenPageIds) > 0) {
                    SocialPage::query()
                        ->where('user_id', $request->user()->id)
                        ->where('provider', 'facebook')
                        ->where('social_account_id', $account->id)
                        ->whereNotIn('page_id', $seenPageIds)
                        ->update(['is_connected' => false]);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not connect Facebook. Check app credentials and try again.');
        }

        $pageCount = count($pages);

        return redirect()
            ->route('dashboard')
            ->with('success', $pageCount > 0
                ? "Facebook connected. {$pageCount} page(s) linked."
                : 'Facebook connected, but no Pages were found for this account.');
    }

    public function disconnectPage(Request $request, SocialPage $page): RedirectResponse
    {
        abort_unless($page->user_id === $request->user()->id, 403);

        $page->update(['is_connected' => false]);

        return redirect()->route('dashboard')->with('success', "Disconnected {$page->name}.");
    }

    public function disconnectAccount(Request $request): RedirectResponse
    {
        SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'facebook')
            ->delete();

        return redirect()->route('dashboard')->with('success', 'Facebook account disconnected.');
    }
}
