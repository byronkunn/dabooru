<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DmcaController as AdminDmcaController;
use App\Http\Controllers\Admin\ModerationLogController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\SiteLogController;
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
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SavedCollectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ArtistCreditController;
use App\Http\Controllers\ChapterInboxController;
use App\Http\Controllers\CuratedShelfController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\StudioMediaController;
use Illuminate\Support\Facades\Route;

/* ---------------- public ---------------- */

Route::get('/age-gate', [AgeGateController::class, 'show'])->name('age-gate');
Route::post('/age-gate', [AgeGateController::class, 'confirm'])->name('age-gate.confirm');
Route::post('/age-gate/leave', [AgeGateController::class, 'leave'])->name('age-gate.leave');

Route::get('/discover', [CuratedShelfController::class, 'index'])->name('shelves.index');
Route::get('/shared/collections/{token}', [SavedCollectionController::class, 'shared'])->whereUuid('token')->name('collections.shared');
Route::get('/work-media/{media}/{variant}', [StudioMediaController::class, 'show'])
    ->whereNumber('media')->whereIn('variant', ['original', 'thumb'])->name('studio.media');
Route::get('/', [GalleryController::class, 'index'])->name('gallery');
Route::get('/p/{post}', [PostController::class, 'show'])->name('posts.show');
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');

Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:120,1')
    ->name('search.suggest');
Route::get('/search/trending', [SearchController::class, 'trending'])
    ->middleware('throttle:120,1')
    ->name('search.trending');

Route::get('/tags', [KnowledgeController::class, 'index'])->name('knowledge.index');
Route::get('/tags/autocomplete', [KnowledgeController::class, 'autocomplete'])->middleware('throttle:120,1')->name('knowledge.autocomplete');
Route::get('/community/changes', [KnowledgeController::class, 'changes'])->name('knowledge.changes');
Route::get('/community/revisions/{revision}', [KnowledgeController::class, 'revision'])->name('knowledge.revision');
Route::get('/community/{type}/{id}', [KnowledgeController::class, 'show'])
    ->where('type', 'tag|wiki|post')->whereNumber('id')->name('knowledge.show');
Route::middleware(['auth', 'throttle:20,1'])->group(function () {
    Route::post('/community/pages', [KnowledgeController::class, 'create'])->name('knowledge.create');
    Route::post('/community/{type}/{id}/edits', [KnowledgeController::class, 'propose'])->whereNumber('id')->name('knowledge.propose');
    Route::post('/community/tag/{id}/bulk', [KnowledgeController::class, 'bulk'])->whereNumber('id')->name('knowledge.bulk');
    Route::post('/community/{type}/{id}/watch', [KnowledgeController::class, 'watch'])->whereNumber('id')->name('knowledge.watch');
    Route::post('/community/{type}/{id}/discussion', [KnowledgeController::class, 'discuss'])->whereNumber('id')->name('knowledge.discuss');
    Route::post('/community/reports', [KnowledgeController::class, 'report'])->name('knowledge.report');
});
Route::middleware(['auth', 'admin:curator', 'throttle:60,1'])->prefix('admin/community')->group(function () {
    Route::get('/', [KnowledgeController::class, 'moderate'])->name('admin.knowledge');
    Route::post('/revisions/{revision}', [KnowledgeController::class, 'review'])->name('admin.knowledge.review');
    Route::post('/{type}/{id}/lock', [KnowledgeController::class, 'lock'])->whereNumber('id')->name('admin.knowledge.lock');
    Route::post('/users/{user}/restrict', [KnowledgeController::class, 'restrict'])->name('admin.knowledge.restrict');
    Route::post('/reports/{id}', [KnowledgeController::class, 'resolveReport'])->whereNumber('id')->name('admin.knowledge.report');
});

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

    Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::get('/saved-searches/{savedSearch}', [SavedSearchController::class, 'index'])->whereNumber('savedSearch')->name('saved-searches.show');
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
    Route::get('/chapter-inbox', [ChapterInboxController::class, 'index'])->name('chapters.inbox');
    Route::post('/chapter-inbox/{chapter}/read', [ChapterInboxController::class, 'markRead'])->name('chapters.mark-read');
    Route::get('/studio', [StudioController::class, 'index'])->name('studio.index');
    Route::patch('/studio/works/{work}', [StudioController::class, 'updateWork'])->whereNumber('work')->name('studio.works.update');
    Route::patch('/studio/chapters/{chapter}', [StudioController::class, 'updateChapter'])->whereNumber('chapter')->name('studio.chapters.update');
    Route::delete('/studio/works/{work}', [StudioController::class, 'discardWork'])->whereNumber('work')->name('studio.works.discard');
    Route::delete('/studio/chapters/{chapter}', [StudioController::class, 'discardChapter'])->whereNumber('chapter')->name('studio.chapters.discard');
    Route::post('/p/{post}/credit-claim', [ArtistCreditController::class, 'store'])->middleware('throttle:10,1')->name('credits.store');

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

    Route::get('/saved', [SavedCollectionController::class, 'index'])->name('collections.index');
    Route::get('/saved/{collection}', [SavedCollectionController::class, 'index'])->name('collections.show');
    Route::post('/saved', [SavedCollectionController::class, 'store'])->name('collections.store');
    Route::patch('/saved/{collection}', [SavedCollectionController::class, 'update'])->name('collections.update');
    Route::patch('/saved/{collection}/share', [SavedCollectionController::class, 'share'])->name('collections.share');
    Route::delete('/saved/{collection}', [SavedCollectionController::class, 'destroy'])->name('collections.destroy');
    Route::post('/saved/{collection}/posts/{post}', [SavedCollectionController::class, 'add'])->name('collections.posts.add');
    Route::delete('/saved/{collection}/posts/{post}', [SavedCollectionController::class, 'remove'])->name('collections.posts.remove');

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
    Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])->name('settings.account.destroy');

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

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('admin:curator')->group(function () {
            Route::get('/shelves', [CuratedShelfController::class, 'manage'])->name('shelves');
            Route::post('/shelves', [CuratedShelfController::class, 'save'])->name('shelves.store');
            Route::patch('/shelves/{shelf}', [CuratedShelfController::class, 'save'])->name('shelves.update');
            Route::get('/credit-claims', [ArtistCreditController::class, 'index'])->name('credit-claims');
            Route::post('/credit-claims/{claim}', [ArtistCreditController::class, 'review'])->name('credit-claims.review');
        });
        Route::middleware('admin:moderator')->group(function () {
            Route::get('/', [AdminReportController::class, 'index'])->name('reports');
            Route::post('/reports/{report}/remove', [AdminReportController::class, 'remove'])->name('reports.remove');
            Route::post('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->name('reports.dismiss');
            Route::post('/reports/{report}/assign', [AdminReportController::class, 'assign'])->name('reports.assign');
            Route::post('/reports/{report}/rating', [AdminReportController::class, 'rating'])->name('reports.rating');
            Route::post('/works/{post}/restore', [AdminPostController::class, 'restore'])->whereNumber('post')->name('works.restore');
        });
        Route::middleware('admin')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index'])->name('users');
            Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
            Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])->name('users.unban');
            Route::patch('/users/{user}/roles', [AdminUserController::class, 'roles'])->name('users.roles');
            Route::get('/log', [ModerationLogController::class, 'index'])->name('log');
            Route::get('/dmca', [AdminDmcaController::class, 'index'])->name('dmca');
            Route::post('/dmca/{notice}', [AdminDmcaController::class, 'update'])->name('dmca.update');
        });
        Route::get('/site-logs', [SiteLogController::class, 'index'])->middleware('admin:operator')->name('site-logs');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->middleware('admin:analyst')->name('analytics');
    });
});

// Logged-in users land on the gallery, not a dashboard.
Route::get('/dashboard', fn () => redirect()->route('gallery'))->middleware('auth')->name('dashboard');

require __DIR__.'/auth.php';
