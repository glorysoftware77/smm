<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\ZernioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class TikTokConnectController extends Controller
{
    public function redirect(Request $request, ZernioService $zernio): RedirectResponse
    {
        if (! $zernio->isConfigured()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Add ZERNIO_API_KEY to your .env, then click Connect TikTok again.');
        }

        return $this->redirectViaZernio($request, $zernio);
    }

    public function callback(Request $request, ZernioService $zernio): RedirectResponse
    {
        if (! $zernio->isConfigured()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'ZERNIO_API_KEY is missing. Add it to .env before connecting TikTok.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'TikTok connection cancelled: '.$request->string('error_description', $request->string('error')));
        }

        // Zernio OAuth return, or legacy Direct TikTok code (ignored — Direct path disabled).
        if ($request->filled('connected') || $request->filled('accountId') || $request->filled('profileId')) {
            return $this->callbackViaZernio($request, $zernio);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'TikTok connect uses Zernio. Connect TikTok in Zernio (VPN if needed), then click Connect again to import.');
    }

    public function syncAccounts(Request $request, ZernioService $zernio): RedirectResponse
    {
        if (! $zernio->isConfigured()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Add ZERNIO_API_KEY to .env to sync TikTok.');
        }

        return $this->syncViaZernio($request, $zernio);
    }

    public function disconnectAccount(Request $request): RedirectResponse
    {
        SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'tiktok')
            ->delete();

        return redirect()->route('dashboard')->with('success', 'TikTok account disconnected.');
    }

    private function redirectViaZernio(Request $request, ZernioService $zernio): RedirectResponse
    {
        try {
            $existing = $zernio->listTikTokAccounts();

            if ($existing !== []) {
                $count = $this->importZernioTikTokAccounts($request, $zernio, $existing);

                return redirect()
                    ->route('dashboard')
                    ->with('success', "Imported {$count} TikTok account(s) from Zernio.");
            }

            $profileId = $this->resolveZernioProfileId($request, $zernio);
            $request->session()->put('zernio_tiktok_profile_id', $profileId);

            $authUrl = $zernio->connectUrl(
                'tiktok',
                $profileId,
                route('tiktok.callback')
            );

            return redirect()->away($authUrl);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not start Zernio TikTok connect: '.$e->getMessage());
        }
    }

    private function callbackViaZernio(Request $request, ZernioService $zernio): RedirectResponse
    {
        try {
            $profileId = $request->string('profileId')->toString()
                ?: $request->session()->pull('zernio_tiktok_profile_id')
                ?: $this->resolveZernioProfileId($request, $zernio);

            $count = $this->importZernioTikTokAccounts(
                $request,
                $zernio,
                $zernio->listTikTokAccounts($profileId ?: null),
                $profileId ?: null
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not finish Zernio TikTok connect: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with($count > 0 ? 'success' : 'error', $count > 0
                ? "Connected {$count} TikTok account(s) via Zernio."
                : 'Zernio connected, but no TikTok accounts were found. Connect TikTok in Zernio (use VPN if needed), then Sync.');
    }

    private function syncViaZernio(Request $request, ZernioService $zernio): RedirectResponse
    {
        try {
            $profileId = $this->existingZernioProfileId($request);

            $count = $this->importZernioTikTokAccounts(
                $request,
                $zernio,
                $zernio->listTikTokAccounts($profileId),
                $profileId
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Could not sync TikTok from Zernio: '.$e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with($count > 0 ? 'success' : 'error', $count > 0
                ? "Synced {$count} TikTok account(s) from Zernio."
                : 'No TikTok accounts found in Zernio. Connect TikTok in Zernio first.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    private function importZernioTikTokAccounts(
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

            $username = $account['username']
                ?? $account['displayName']
                ?? $account['name']
                ?? ('TikTok '.$accountId);

            $pages[] = [
                'id' => $accountId,
                'name' => str_starts_with((string) $username, '@') ? $username : '@'.$username,
                'category' => 'TikTok (Zernio)',
                'picture_url' => $account['profilePicture'] ?? $account['picture'] ?? null,
            ];
        }

        DB::transaction(function () use ($request, $profileId, $pages) {
            $account = SocialAccount::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'provider' => 'tiktok',
                    'provider_user_id' => 'zernio:'.$profileId,
                ],
                [
                    'access_token' => 'zernio',
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'name' => 'TikTok (Zernio)',
                ]
            );

            $seenPageIds = [];

            foreach ($pages as $page) {
                $seenPageIds[] = $page['id'];

                SocialPage::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'provider' => 'tiktok',
                        'page_id' => $page['id'],
                    ],
                    [
                        'social_account_id' => $account->id,
                        'linked_social_page_id' => null,
                        'name' => $page['name'],
                        'category' => $page['category'],
                        'picture_url' => $page['picture_url'],
                        'access_token' => 'zernio',
                        'is_connected' => true,
                    ]
                );
            }

            $query = SocialPage::query()
                ->where('user_id', $request->user()->id)
                ->where('provider', 'tiktok')
                ->where('social_account_id', $account->id);

            if (count($seenPageIds) > 0) {
                $query->whereNotIn('page_id', $seenPageIds)->update(['is_connected' => false]);
            } else {
                $query->update(['is_connected' => false]);
            }
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

        // Reuse profile from any Zernio-backed social account (LinkedIn or TikTok).
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
}
