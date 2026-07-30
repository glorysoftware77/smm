<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookConnectController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\InstagramConnectController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TikTokConnectController;
use App\Http\Controllers\YouTubeConnectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/data-deletion', 'data-deletion')->name('data-deletion');

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

    Route::get('/instagram/redirect', [InstagramConnectController::class, 'redirect'])->name('instagram.redirect');
    Route::get('/instagram/callback', [InstagramConnectController::class, 'callback'])->name('instagram.callback');
    Route::delete('/instagram/disconnect', [InstagramConnectController::class, 'disconnectAccount'])->name('instagram.disconnect');

    Route::get('/youtube/redirect', [YouTubeConnectController::class, 'redirect'])->name('youtube.redirect');
    Route::get('/youtube/callback', [YouTubeConnectController::class, 'callback'])->name('youtube.callback');
    Route::delete('/youtube/disconnect', [YouTubeConnectController::class, 'disconnectAccount'])->name('youtube.disconnect');

    Route::get('/tiktok/redirect', [TikTokConnectController::class, 'redirect'])->name('tiktok.redirect');
    Route::get('/tiktok/callback', [TikTokConnectController::class, 'callback'])->name('tiktok.callback');
    Route::delete('/tiktok/disconnect', [TikTokConnectController::class, 'disconnectAccount'])->name('tiktok.disconnect');

    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('/posts/{post}/insights', [PostController::class, 'refreshInsights'])->name('posts.insights.refresh');

    Route::get('/insights', [InsightsController::class, 'index'])->name('insights.index');
    Route::post('/insights/refresh', [InsightsController::class, 'refreshAll'])->name('insights.refresh');
    Route::post('/insights/posts/{post}', [InsightsController::class, 'refreshPost'])->name('insights.posts.refresh');
});

require __DIR__.'/auth.php';
