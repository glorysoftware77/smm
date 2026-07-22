<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookConnectController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/facebook/redirect', [FacebookConnectController::class, 'redirect'])->name('facebook.redirect');
    Route::get('/facebook/callback', [FacebookConnectController::class, 'callback'])->name('facebook.callback');
    Route::post('/facebook/sync', [FacebookConnectController::class, 'syncPages'])->name('facebook.sync');
    Route::delete('/facebook/pages/{page}', [FacebookConnectController::class, 'disconnectPage'])->name('facebook.pages.disconnect');
    Route::delete('/facebook/disconnect', [FacebookConnectController::class, 'disconnectAccount'])->name('facebook.disconnect');

    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
});

require __DIR__.'/auth.php';
