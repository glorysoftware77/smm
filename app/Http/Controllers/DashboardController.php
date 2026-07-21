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

        $hasFacebookAccount = $request->user()
            ->socialAccounts()
            ->where('provider', 'facebook')
            ->exists();

        return view('dashboard', [
            'pages' => $pages,
            'hasFacebookAccount' => $hasFacebookAccount,
        ]);
    }
}
