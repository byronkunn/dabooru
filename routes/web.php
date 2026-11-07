<?php

use App\Http\Controllers\Admin\ModerationLogController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AgeGateController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ComicChapterController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DmcaController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FollowingController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/* ---------------- public ---------------- */

Route::get('/age-gate', [AgeGateController::class, 'show'])->name('age-gate');
Route::post('/age-gate', [AgeGateController::class, 'confirm'])->name('age-gate.confirm');
Route::post('/age-gate/leave', [AgeGateController::class, 'leave'])->name('age-gate.leave');

Route::get('/', [GalleryController::class, 'index'])->name('gallery');
Route::get('/p/{post}', [PostController::class, 'show'])->name('posts.show');
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');

Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:120,1')
    ->name('search.suggest');

/* legal: public, reachable even before the age gate */
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/content-policy', [LegalController::class, 'policy'])->name('legal.policy');
Route::get('/dmca', [DmcaController::class, 'show'])->name('dmca');
Route::post('/dmca', [DmcaController::class, 'submit'])->middleware('throttle:5,1')->name('dmca.submit');
Route::get('/dmca/sent', [DmcaController::class, 'submitted'])->name('dmca.submitted');

Route::get('/comics', [ComicController::class, 'index'])->name('comics.index');

// /comics/new must be registered before the {comic} wildcard below.
Route::middleware('auth')->group(function () {
    Route::get('/comics/new', [ComicChapterController::class, 'create'])->name('comics.chapters.create');
    Route::post('/comics/new', [ComicChapterController::class, 'storeSeries'])->name('comics.series.store');
});

Route::get('/comics/{comic}', [ComicController::class, 'show'])->where('comic', '[a-z0-9\-]+')->name('comics.show');
Route::get('/comics/{comic}/{number}', [ComicController::class, 'read'])
    ->where(['comic' => '[a-z0-9\-]+', 'number' => '[0-9]+'])
    ->name('comics.read');

/* ---------------- authenticated ---------------- */

Route::middleware('auth')->group(function () {
    Route::get('/following', [FollowingController::class, 'index'])->name('following');

    // creating content
    Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
    Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:20,1')->name('upload.store');

    Route::post('/comics/{comic}/chapters', [ComicChapterController::class, 'storeChapter'])->name('comics.chapters.store');

    // engagement
    Route::post('/p/{post}/like', [LikeController::class, 'store'])->name('posts.like');
    Route::delete('/p/{post}/like', [LikeController::class, 'destroy'])->name('posts.unlike');
    Route::post('/p/{post}/save', [BookmarkController::class, 'store'])->name('posts.save');
    Route::delete('/p/{post}/save', [BookmarkController::class, 'destroy'])->name('posts.unsave');
    Route::post('/p/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:30,1')->name('comments.store');
    Route::post('/u/{user}/follow', [FollowController::class, 'store'])->where('user', '[^/]+')->name('users.follow');
    Route::delete('/u/{user}/follow', [FollowController::class, 'destroy'])->where('user', '[^/]+')->name('users.unfollow');

    Route::post('/comics/{comic}/follow', [ComicController::class, 'follow'])->name('comics.follow');
    Route::post('/comics/{comic}/progress', [ComicController::class, 'progress'])->name('comics.progress');

    // notifications and messages
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');

    // settings
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // owning your own content
    Route::get('/p/{post}/edit', [ContentController::class, 'edit'])->name('posts.edit');
    Route::patch('/p/{post}', [ContentController::class, 'update'])->name('posts.update');
    Route::delete('/p/{post}', [ContentController::class, 'destroy'])->name('posts.destroy');
    Route::delete('/comments/{comment}', [ContentController::class, 'destroyComment'])->name('comments.destroy');
    Route::delete('/messages/message/{message}', [ContentController::class, 'destroyMessage'])->name('messages.destroy');

    // blocking
    Route::get('/settings/blocked', [BlockController::class, 'index'])->name('blocks.index');
    Route::post('/u/{user}/block', [BlockController::class, 'store'])->where('user', '[^/]+')->name('users.block');
    Route::delete('/u/{user}/block', [BlockController::class, 'destroy'])->where('user', '[^/]+')->name('users.unblock');

    // moderation entry point (anyone can report)
    Route::post('/report', [ReportController::class, 'store'])->middleware('throttle:10,1')->name('reports.store');

    // admin: reports, users, moderation log
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminReportController::class, 'index'])->name('reports');
        Route::post('/reports/{report}/remove', [AdminReportController::class, 'remove'])->name('reports.remove');
        Route::post('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->name('reports.dismiss');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
        Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])->name('users.unban');
        Route::get('/log', [ModerationLogController::class, 'index'])->name('log');
    });
});

// Logged-in users land on the gallery, not a dashboard.
Route::get('/dashboard', fn () => redirect()->route('gallery'))->middleware('auth')->name('dashboard');

require __DIR__.'/auth.php';
