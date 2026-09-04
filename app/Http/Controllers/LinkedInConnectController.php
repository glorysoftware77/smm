<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\LinkedInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class LinkedInConnectController extends Controller
{
    public function redirect(Request $request, LinkedInService $linkedin): RedirectResponse
    {
        $state = $linkedin->generateState();
        $request->session()->put('linkedin_oauth_state', $state);

        return redirect()->away($linkedin->authorizationUrl($state));
    }

    public function callback(Request $request, LinkedInService $linkedin): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'LinkedIn connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        $state = $request->session()->pull('linkedin_oauth_state');

        if (! $state || $state !== $request->string('state')->toString()) {
            return redirect()->route('dashboard')->with('error', 'Invalid LinkedIn OAuth state. Please try again.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('dashboard')->with('error', 'LinkedIn did not return an authorization code.');
        }

        $pages = [];

        try {
            $token = $linkedin->exchangeCodeForToken($code);
            $accessToken = $token['access_token'];
            $refreshToken = $token['refresh_token'] ?? null;
            $expiresIn = $token['expires_in'] ?? 5184000;

            $profile = $linkedin->getMemberProfile($accessToken);
            $pages = $linkedin->resolveOrganizationPages($accessToken);

            DB::transaction(function () use ($request, $profile, $accessToken, $refreshToken, $expiresIn, $pages) {
                $existing = SocialAccount::query()
                    ->where('user_id', $request->user()->id)
                    ->where('provider', 'linkedin')
                    ->where('provider_user_id', $profile['id'])
                    ->first();

                $account = SocialAccount::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'linkedin',
                        'provider_user_id' => $profile['id'],
                    ],
                    [
                        'access_token' => $accessToken,
                        'refresh_token' => $refreshToken ?: $existing?->refresh_token,
                        'token_expires_at' => Carbon::now()->addSeconds((int) $expiresIn),
                        'name' => $profile['name'] ?: 'LinkedIn',
                    ]
                );

                $this->storeLinkedInPages($request->user()->id, $account->id, $accessToken, $pages);
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not connect LinkedIn: '.$e->getMessage());
        }

        $pageCount = count($pages);

        return redirect()
            ->route('dashboard')
            ->with($pageCount > 0 ? 'success' : 'error', $pageCount > 0
                ? "Connected {$pageCount} LinkedIn Page(s)."
                : 'LinkedIn connected, but no Pages were found. Ensure you are an admin of a Company Page.');
    }

    public function syncPages(Request $request, LinkedInService $linkedin): RedirectResponse
    {
        $account = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'linkedin')
            ->latest()
            ->first();

        if (! $account) {
            return redirect()->route('dashboard')->with('error', 'Connect LinkedIn first.');
        }

        try {
            $accessToken = $account->access_token;

            if ($account->refresh_token && $account->token_expires_at?->isBefore(now()->addMinutes(5))) {
                $refreshed = $linkedin->refreshAccessToken($account->refresh_token);
                $accessToken = $refreshed['access_token'];
                $account->update([
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshed['refresh_token'] ?? $account->refresh_token,
                    'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 5184000)),
                ]);
            }

            $pages = $linkedin->resolveOrganizationPages($accessToken);

            DB::transaction(function () use ($request, $account, $accessToken, $pages) {
                $this->storeLinkedInPages($request->user()->id, $account->id, $accessToken, $pages);
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('dashboard')->with('error', 'Could not refresh LinkedIn pages: '.$e->getMessage());
        }

        $pageCount = count($pages);

        return redirect()
            ->route('dashboard')
            ->with($pageCount > 0 ? 'success' : 'error', $pageCount > 0
                ? "Synced {$pageCount} LinkedIn Page(s)."
                : 'No LinkedIn Pages found for this account.');
    }

    public function disconnectPage(Request $request, SocialPage $page): RedirectResponse
    {
        abort_unless($page->user_id === $request->user()->id, 403);
        abort_unless($page->provider === 'linkedin', 404);

        $page->update(['is_connected' => false]);

        return redirect()->route('dashboard')->with('success', "Disconnected {$page->name}.");
    }

    public function disconnectAccount(Request $request): RedirectResponse
    {
        SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'linkedin')
            ->delete();

        return redirect()->route('dashboard')->with('success', 'LinkedIn account disconnected.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    private function storeLinkedInPages(int $userId, int $accountId, string $accessToken, array $pages): void
    {
        $seenPageIds = [];

        foreach ($pages as $page) {
            $seenPageIds[] = $page['id'];

            SocialPage::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'provider' => 'linkedin',
                    'page_id' => $page['id'],
                ],
                [
                    'social_account_id' => $accountId,
                    'linked_social_page_id' => null,
                    'name' => $page['name'] ?? ('Page '.$page['id']),
                    'category' => $page['category'] ?? 'LinkedIn Page',
                    'picture_url' => $page['picture_url'] ?? null,
                    'access_token' => $accessToken,
                    'is_connected' => true,
                ]
            );
        }

        $query = SocialPage::query()
            ->where('user_id', $userId)
            ->where('provider', 'linkedin')
            ->where('social_account_id', $accountId);

        if (count($seenPageIds) > 0) {
            $query->whereNotIn('page_id', $seenPageIds)->update(['is_connected' => false]);
        } else {
            $query->update(['is_connected' => false]);
        }
    }
}
