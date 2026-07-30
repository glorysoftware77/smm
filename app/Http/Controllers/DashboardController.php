<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $pages = $request->user()
            ->socialPages()
            ->where('provider', 'facebook')
            ->where('is_connected', true)
            ->orderBy('name')
            ->get();

        $instagramAccounts = $request->user()
            ->socialPages()
            ->where('provider', 'instagram')
            ->where('is_connected', true)
            ->orderBy('name')
            ->get();

        $youtubeChannels = $request->user()
            ->socialPages()
            ->where('provider', 'youtube')
            ->where('is_connected', true)
            ->orderBy('name')
            ->get();

        $tiktokAccounts = $request->user()
            ->socialPages()
            ->where('provider', 'tiktok')
            ->where('is_connected', true)
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'pages' => $pages,
            'instagramAccounts' => $instagramAccounts,
            'youtubeChannels' => $youtubeChannels,
            'tiktokAccounts' => $tiktokAccounts,
            'hasFacebookAccount' => $request->user()->socialAccounts()->where('provider', 'facebook')->exists(),
            'hasInstagramAccount' => $request->user()->socialAccounts()->where('provider', 'instagram')->exists(),
            'hasYouTubeAccount' => $request->user()->socialAccounts()->where('provider', 'youtube')->exists(),
            'hasTikTokAccount' => $request->user()->socialAccounts()->where('provider', 'tiktok')->exists(),
        ]);
    }
}
