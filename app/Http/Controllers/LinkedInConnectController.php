<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\LinkedInService;
use App\Services\ZernioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class LinkedInConnectController extends Controller
{
    public function redirect(Request $request, LinkedInService $linkedin, ZernioService $zernio): RedirectResponse
    {
        // LinkedIn Direct org scopes are not approved yet — only Zernio can connect Pages.
        if (! $zernio->isConfigured()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'LinkedIn Direct API is not approved yet. Add ZERNIO_API_KEY to your .env, then click Connect again.');
        }

        return $this->redirectViaZernio($request, $zernio);
    }

    public function callback(Request $request, LinkedInService $linkedin, ZernioService $zernio): RedirectResponse
    {
        // Zernio returns connected / accountId query params after OAuth.
        if ($request->filled('connected') || $request->filled('accountId') || $request->filled('profileId')) {
            if (! $zernio->isConfigured()) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'ZERNIO_API_KEY is missing. Add it to .env before connecting LinkedIn.');
            }

            return $this->callbackViaZernio($request, $zernio);
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'LinkedIn connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        // Legacy Direct LinkedIn OAuth callback (only if explicitly enabled after approval).
        if (! filter_var(config('services.linkedin.use_direct'), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'LinkedIn Direct connect is disabled. Use Zernio (set ZERNIO_API_KEY).');
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

    public function syncPages(Request $request, LinkedInService $linkedin, ZernioService $zernio): RedirectResponse
    {
        if (! $zernio->isConfigured()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Add ZERNIO_API_KEY to .env to sync LinkedIn Pages.');
        }

        return $this->syncViaZernio($request, $zernio);
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

    private function redirectViaZernio(Request $request, ZernioService $zernio): RedirectResponse
    {
        try {
            // Already connected in Zernio dashboard? Import LinkedIn accounts immediately.
            $existing = $zernio->listLinkedInAccounts();

            if ($existing !== []) {
                $pageCount = $this->importZernioLinkedInAccounts($request, $zernio, $existing);

                return redirect()
                    ->route('dashboard')
                    ->with('success', "Imported {$pageCount} LinkedIn Page(s) from Zernio.");
            }

            $profileId = $this->resolveZernioProfileId($request, $zernio);
            $request->session()->put('zernio_linkedin_profile_id', $profileId);

            $authUrl = $zernio->connectUrl(
                'linkedin',
                $profileId,
                route('linkedin.callback')
            );

            return redirect()->away($authUrl);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not start Zernio LinkedIn connect: '.$e->getMessage());
        }
    }

    private function callbackViaZernio(Request $request, ZernioService $zernio): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'LinkedIn connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        try {
            $profileId = $request->string('profileId')->toString()
                ?: $request->session()->pull('zernio_linkedin_profile_id')
                ?: $this->resolveZernioProfileId($request, $zernio);

            $pageCount = $this->importZernioLinkedInAccounts(
                $request,
                $zernio,
                $zernio->listLinkedInAccounts($profileId ?: null),
                $profileId ?: null
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not finish Zernio LinkedIn connect: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with($pageCount > 0 ? 'success' : 'error', $pageCount > 0
                ? "Connected {$pageCount} LinkedIn Page(s) via Zernio."
                : 'Zernio connected, but no LinkedIn Pages were found. Connect a Company Page in Zernio, then Sync.');
    }

    private function syncViaZernio(Request $request, ZernioService $zernio): RedirectResponse
    {
        try {
            $profileId = $this->existingZernioProfileId($request);

            $pageCount = $this->importZernioLinkedInAccounts(
                $request,
                $zernio,
                $zernio->listLinkedInAccounts($profileId),
                $profileId
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not sync LinkedIn pages from Zernio: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with($pageCount > 0 ? 'success' : 'error', $pageCount > 0
                ? "Synced {$pageCount} LinkedIn Page(s) from Zernio."
                : 'No LinkedIn Pages found in Zernio. Connect a Page in Zernio first.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    private function importZernioLinkedInAccounts(
        Request $request,
        ZernioService $zernio,
        array $accounts,
        ?string $profileId = null
    ): int {
        $profileId = $profileId ?: $this->resolveZernioProfileId($request, $zernio);

        $pages = [];

        foreach ($accounts as $account) {
            $accountId = (string) ($account['_id'] ?? '');

            if ($accountId === '') {
                continue;
            }

            $pages[] = [
                'id' => $accountId,
                'name' => $account['username']
                    ?? $account['displayName']
                    ?? $account['name']
                    ?? ('LinkedIn '.$accountId),
                'category' => 'LinkedIn Page (Zernio)',
                'picture_url' => $account['profilePicture'] ?? $account['picture'] ?? null,
            ];
        }

        DB::transaction(function () use ($request, $profileId, $pages) {
            $account = SocialAccount::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'provider' => 'linkedin',
                    'provider_user_id' => 'zernio:'.$profileId,
                ],
                [
                    'access_token' => 'zernio',
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'name' => 'LinkedIn (Zernio)',
                ]
            );

            $this->storeLinkedInPages($request->user()->id, $account->id, 'zernio', $pages);
        });

        return count($pages);
    }

    private function resolveZernioProfileId(Request $request, ZernioService $zernio): string
    {
        $existingId = $this->existingZernioProfileId($request);

        if ($existingId) {
            return $existingId;
        }

        if (filled(config('services.zernio.profile_id'))) {
            return (string) config('services.zernio.profile_id');
        }

        return $zernio->ensureProfile('SMM User '.$request->user()->id)['id'];
    }

    private function existingZernioProfileId(Request $request): ?string
    {
        if (filled(config('services.zernio.profile_id'))) {
            return (string) config('services.zernio.profile_id');
        }

        $account = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider_user_id', 'like', 'zernio:%')
            ->latest()
            ->first();

        if (! $account) {
            return null;
        }

        return substr((string) $account->provider_user_id, strlen('zernio:')) ?: null;
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
