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
            $resolved = $facebook->resolveUserPages($accessToken);
            $pages = $resolved['pages'];

            DB::transaction(function () use ($request, $profile, $accessToken, $expiresIn, $pages, $facebook) {
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

                $this->storeFacebookPages($request->user()->id, $account->id, $pages);
                $this->syncInstagramAccounts($request->user()->id, $account->id, $facebook);
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not connect Facebook: '.$e->getMessage());
        }

        $pageCount = count($pages);
        $igCount = SocialPage::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'instagram')
            ->where('is_connected', true)
            ->count();

        $errorHint = count($resolved['errors'] ?? []) > 0
            ? ' Details: '.implode(' | ', array_slice($resolved['errors'], 0, 2))
            : '';

        return redirect()
            ->route('dashboard')
            ->with('success', $pageCount > 0
                ? "Connected {$pageCount} Facebook page(s) and {$igCount} Instagram account(s)."
                : 'Facebook connected, but no Pages were found.'.(
                    count($resolved['page_ids'] ?? []) > 0
                        ? ' Token has page IDs but page tokens failed.'.$errorHint
                        : ' Token has no page grants.'.$errorHint
                ));
    }

    public function syncPages(Request $request, FacebookService $facebook): RedirectResponse
    {
        $account = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'facebook')
            ->latest()
            ->first();

        if (! $account) {
            return redirect()->route('dashboard')->with('error', 'Connect Facebook first.');
        }

        try {
            $resolved = $facebook->resolveUserPages($account->access_token);
            $pages = $resolved['pages'];

            DB::transaction(function () use ($request, $account, $pages, $facebook) {
                $this->storeFacebookPages($request->user()->id, $account->id, $pages);
                $this->syncInstagramAccounts($request->user()->id, $account->id, $facebook);
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('dashboard')->with('error', 'Could not refresh pages: '.$e->getMessage());
        }

        $pageCount = count($pages);
        $igCount = SocialPage::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'instagram')
            ->where('is_connected', true)
            ->count();

        $errorHint = count($resolved['errors']) > 0
            ? ' Details: '.implode(' | ', array_slice($resolved['errors'], 0, 2))
            : '';

        return redirect()
            ->route('dashboard')
            ->with($pageCount > 0 ? 'success' : 'error', $pageCount > 0
                ? "Synced {$pageCount} Facebook page(s) and {$igCount} Instagram account(s)."
                : 'No pages linked. IDs found: '.count($resolved['page_ids']).'.'.$errorHint);
    }

    public function disconnectPage(Request $request, SocialPage $page): RedirectResponse
    {
        abort_unless($page->user_id === $request->user()->id, 403);

        $page->update(['is_connected' => false]);

        if ($page->provider === 'facebook') {
            SocialPage::query()
                ->where('user_id', $request->user()->id)
                ->where('provider', 'instagram')
                ->where('linked_social_page_id', $page->id)
                ->update(['is_connected' => false]);
        }

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

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    private function storeFacebookPages(int $userId, int $accountId, array $pages): void
    {
        $seenPageIds = [];

        foreach ($pages as $page) {
            $seenPageIds[] = $page['id'];

            SocialPage::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'provider' => 'facebook',
                    'page_id' => $page['id'],
                ],
                [
                    'social_account_id' => $accountId,
                    'name' => $page['name'] ?? ('Page '.$page['id']),
                    'category' => $page['category'] ?? null,
                    'picture_url' => $page['picture']['data']['url'] ?? null,
                    'access_token' => $page['access_token'],
                    'is_connected' => true,
                ]
            );
        }

        if (count($seenPageIds) > 0) {
            SocialPage::query()
                ->where('user_id', $userId)
                ->where('provider', 'facebook')
                ->where('social_account_id', $accountId)
                ->whereNotIn('page_id', $seenPageIds)
                ->update(['is_connected' => false]);
        }
    }

    private function syncInstagramAccounts(int $userId, int $accountId, FacebookService $facebook): void
    {
        $facebookPages = SocialPage::query()
            ->where('user_id', $userId)
            ->where('provider', 'facebook')
            ->where('is_connected', true)
            ->get();

        $seenIgIds = [];

        foreach ($facebookPages as $facebookPage) {
            $ig = $facebook->getInstagramBusinessAccount($facebookPage->page_id, $facebookPage->access_token);

            if (! $ig || empty($ig['id'])) {
                continue;
            }

            $seenIgIds[] = $ig['id'];
            $username = $ig['username'] ?? null;

            SocialPage::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'provider' => 'instagram',
                    'page_id' => $ig['id'],
                ],
                [
                    'social_account_id' => $accountId,
                    'linked_social_page_id' => $facebookPage->id,
                    'name' => $username ? '@'.$username : ($ig['name'] ?? 'Instagram'),
                    'category' => 'Instagram',
                    'picture_url' => $ig['profile_picture_url'] ?? null,
                    'access_token' => $facebookPage->access_token,
                    'is_connected' => true,
                ]
            );
        }

        $query = SocialPage::query()
            ->where('user_id', $userId)
            ->where('provider', 'instagram')
            ->where('social_account_id', $accountId);

        if (count($seenIgIds) > 0) {
            $query->whereNotIn('page_id', $seenIgIds)->update(['is_connected' => false]);
        } else {
            $query->update(['is_connected' => false]);
        }
    }
}
