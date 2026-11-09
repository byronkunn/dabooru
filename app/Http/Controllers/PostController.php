<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\CommentResource;
use App\Models\ArtistCreditClaim;
use App\Models\Block;
use App\Models\Post;
use App\Models\PostView;
use App\Models\SavedCollection;
use App\Support\KnowledgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Post $post): Response
    {
        $viewer = $request->user();
        abort_if($viewer && $post->user_id && Block::existsBetween($viewer->id, $post->user_id), 404);

        $post->load(['user', 'media.media', 'chapter.comic', 'tags', 'creditedArtist:id,username,name,banned_at,email_verified_at']);
        $this->recordView($request, $post);

        // readers can choose newest first or the thread with the most replies on top
        $commentSort = in_array($request->query('comment_sort'), ['newest', 'top'], true)
            ? $request->query('comment_sort') : 'newest';

        $comments = $post->comments()->withTrashed()
            ->whereNull('parent_id')
            ->where(fn ($query) => $query->whereNull('deleted_at')->orWhereHas('replies'))
            ->with(['user', 'post', 'replies' => fn ($query) => $query->with(['user', 'post'])->oldest('id')])
            ->when($commentSort === 'top', fn ($q) => $q->withCount('replies')->orderByDesc('replies_count')->orderByDesc('id'))
            ->when($commentSort === 'newest', fn ($q) => $q->latest('id'))
            ->simplePaginate(20, ['*'], 'comment_page')->withQueryString();
        $starSummary = $post->starRatings()->selectRaw('COUNT(*) AS votes, AVG(stars) AS average')->first();
        $more = Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->when($post->artist_name, fn ($query) => $query->where('artist_name', $post->artist_name),
                fn ($query) => $query->where('user_id', $post->user_id))
            ->visibleTo($viewer)->visibleRating((bool) $viewer?->show_explicit)
            ->whereKeyNot($post->id)
            ->latest()
            ->take(8)
            ->get();

        return Inertia::render('Posts/Show', [
            'post' => (new PostResource($post))->resolve(),
            'tagEditing' => [
                'version' => KnowledgeService::version($post),
                'canContribute' => $viewer && KnowledgeService::canContribute($viewer),
                'trusted' => $viewer && KnowledgeService::trusted($viewer),
            ],
            'creditClaim' => $viewer && $viewer->email_verified_at && $post->isExternalArtist() && ! $post->credited_artist_id
                ? ['status' => ArtistCreditClaim::where('user_id', $viewer->id)->where('post_id', $post->id)->value('status')]
                : null,
            'comments' => CommentResource::collection($comments->items())->resolve(),
            'commentSort' => $commentSort,
            'commentPagination' => [
                'nextPage' => $comments->nextPageUrl() ? $comments->nextPageUrl().'#comments' : null,
                'previousPage' => $comments->previousPageUrl() ? $comments->previousPageUrl().'#comments' : null,
            ],
            'canComment' => CommentController::canComment($request, $post),
            'more' => PostResource::collection($more)->resolve(),
            'liked' => $viewer ? $post->likes()->where('user_id', $viewer->id)->exists() : false,
            'starRating' => [
                'average' => $starSummary->average === null ? null : round((float) $starSummary->average, 1),
                'count' => (int) $starSummary->votes,
                'mine' => $viewer ? $post->starRatings()->where('user_id', $viewer->id)->value('stars') : null,
                'canRate' => $viewer && ! $viewer->isBanned(),
            ],
            'collections' => $viewer
                ? SavedCollection::where('user_id', $viewer->id)->orderBy('name')->get(['id', 'name'])
                : [],
            'saved' => $viewer ? $post->bookmarkedBy()->where('users.id', $viewer->id)->exists() : false,
            'followedAuthor' => $viewer ? $viewer->following()->whereKey($post->user_id)->exists() : false,
        ]);
    }

    private function recordView(Request $request, Post $post): void
    {
        $key = $request->user()
            ? 'u'.$request->user()->id
            : 'a'.substr(hash_hmac('sha256', $request->ip().'|'.now()->toDateString(), config('app.key')), 0, 32);

        // insertOrIgnore makes the unique (post, viewer, day) index the lock,
        // so two concurrent requests can't both count the same view.
        $inserted = PostView::insertOrIgnore([
            'post_id' => $post->id,
            'viewer_key' => $key,
            'source' => \App\Support\StudioTracking::source($request),
            'viewed_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted) {
            $post->increment('views');
        }
    }
}
