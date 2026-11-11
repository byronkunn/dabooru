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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Post $post): Response
    {
        $viewer = $request->user();
        abort_if($viewer && $post->user_id && Block::existsBetween($viewer->id, $post->user_id), 404);

        $post->load(['user', 'media.media', 'chapter.comic', 'tags', 'creditedArtist:id,username,name,banned_at,email_verified_at']);

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
        $mutedTags = $viewer ? \App\Support\CollectionLibrary::mutedFor($viewer) : [];
        $suggestions = fn () => Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->visibleTo($viewer)->visibleRating((bool) $viewer?->show_explicit)
            ->whereNull('posts.moderation_hidden_at')->excludingTags($mutedTags)->whereKeyNot($post->id);
        $more = ! $post->artist_name && ! $post->user_id ? collect() : $suggestions()
            ->when($post->artist_name, fn ($query) => $query->where('artist_name', $post->artist_name),
                fn ($query) => $query->where('user_id', $post->user_id))
            ->latest('id')->take(6)->get();
        $moreFromUploader = $post->artist_name && $post->user_id
            ? $suggestions()->where('user_id', $post->user_id)->latest('id')->take(6)->get()
            : collect();
        $tagIds = $post->tags->where('is_approved', true)->pluck('id');
        $similar = $tagIds->isEmpty() ? collect() : $suggestions()
            ->whereHas('tags', fn ($query) => $query->where('is_approved', true)->whereIn('tags.id', $tagIds))
            ->withCount(['tags as shared_tags_count' => fn ($query) => $query->where('is_approved', true)->whereIn('tags.id', $tagIds)])
            ->orderByDesc('shared_tags_count')->latest('id')->take(6)->get();
        $followingIds = $viewer ? $viewer->following()->pluck('users.id') : collect();
        $followedTagIds = $viewer ? $viewer->followedTags()->where('tags.is_approved', true)->pluck('tags.id') : collect();
        $forYou = $followingIds->isEmpty() && $followedTagIds->isEmpty() ? collect() : $suggestions()
            ->where(fn ($query) => $query->whereIn('user_id', $followingIds)
                ->orWhereHas('tags', fn ($tags) => $tags->where('is_approved', true)->whereIn('tags.id', $followedTagIds)))
            ->withCount(['tags as followed_tags_count' => fn ($query) => $query->where('is_approved', true)->whereIn('tags.id', $followedTagIds)])
            ->orderByDesc('followed_tags_count')->latest('id')->take(6)->get();

        return Inertia::render('Posts/Show', [
            'post' => (new PostResource($post))->resolve(),
            'viewToken' => Crypt::encryptString(json_encode([
                'post' => (int) $post->id,
                'issued' => now()->timestamp, 'source' => \App\Support\StudioTracking::source($request),
            ])),
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
            'moreFromUploader' => PostResource::collection($moreFromUploader)->resolve(),
            'similar' => PostResource::collection($similar)->resolve(),
            'forYou' => PostResource::collection($forYou)->resolve(),
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

    public function track(Request $request, Post $post): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096'], 'event' => ['required', 'in:view,play']]);
        try {
            $token = json_decode(Crypt::decryptString($data['token']), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return response()->json(['recorded' => false], 422);
        }
        $issued = $token['issued'] ?? 0;
        abort_unless(is_int($issued) && ($token['post'] ?? null) === (int) $post->id
            && $issued <= now()->timestamp && $issued >= now()->subHours(12)->timestamp, 422);
        if (($data['event'] === 'view' && now()->timestamp - $issued < 2)
            || ($request->user() && $request->user()->id === $post->user_id)
            || ($request->user() && $post->user_id && Block::existsBetween($request->user()->id, $post->user_id))
            || $post->moderation_hidden_at || ($post->rating === 'explicit' && ! $request->user()?->show_explicit)
            || preg_match('/bot|crawler|spider|headless|preview|slurp/i', $request->userAgent() ?? '')
            || ($data['event'] === 'play' && $post->type !== 'video')) {
            return response()->json(['recorded' => false]);
        }
        $key = $request->user()
            ? 'u'.$request->user()->id
            : 's'.substr(hash_hmac('sha256', $request->session()->getId(), config('app.key')), 0, 63);

        $recorded = DB::transaction(function () use ($post, $key, $token, $data) {
            $row = ['post_id' => $post->id, 'viewer_key' => $key, 'viewed_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()];
            if ($data['event'] === 'play') return DB::table('video_plays')->insertOrIgnore($row);
            $row['source'] = in_array($token['source'] ?? '', ['direct', 'external', 'discover', 'following', 'tags', 'gallery', 'profile', 'comics', 'other'], true) ? $token['source'] : 'direct';
            $inserted = PostView::insertOrIgnore($row);
            if ($inserted) $post->increment('views');
            return $inserted;
        });
        return response()->json(['recorded' => (bool) $recorded]);
    }
}
