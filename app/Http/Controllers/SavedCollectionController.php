<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Bookmark;
use App\Models\Block;
use App\Models\User;
use App\Models\Post;
use App\Models\SavedCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SavedCollectionController extends Controller
{
    public function index(Request $request, ?SavedCollection $collection = null): Response
    {
        $viewer = $request->user();

        if ($collection) {
            $this->authorizeOwner($request, $collection);
        }

        $posts = Post::query()
            ->visibleTo($viewer)
            ->visibleRating((bool) $viewer->show_explicit)
            ->whereHas('bookmarks', fn ($q) => $q->where('user_id', $viewer->id)
                ->when($collection, fn ($q) => $q->whereHas('collections', fn ($lists) => $lists->whereKey($collection->id))))
            ->with(['user', 'media.media', 'chapter.comic'])
            ->latest('id')
            ->simplePaginate(36);

        return Inertia::render('Collections/Index', [
            'collections' => SavedCollection::where('user_id', $viewer->id)->orderBy('name')->get(['id', 'name']),
            'collection' => $collection ? $collection->only(['id', 'name']) + [
                'shareUrl' => $collection->share_token ? route('collections.shared', $collection->share_token) : null,
            ] : null,
            'posts' => PostResource::collection($posts->items())->resolve(),
            'nextPage' => $posts->nextPageUrl(),
            'previousPage' => $posts->previousPageUrl(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $collection = SavedCollection::firstOrCreate([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
        ]);

        return redirect()->route('collections.show', $collection);
    }

    public function update(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('saved_collections')
                ->where('user_id', $request->user()->id)->ignore($collection->id)],
        ]);
        $collection->update($data);

        return back();
    }

    public function destroy(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $collection->delete();

        return redirect()->route('collections.index');
    }

    public function add(Request $request, SavedCollection $collection, Post $post): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $viewer = $request->user();
        abort_unless(Post::query()->visibleTo($viewer)->whereKey($post->id)->exists(), 404);

        DB::transaction(function () use ($collection, $post, $viewer) {
            SavedCollection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $bookmark = Bookmark::firstOrCreate(['user_id' => $viewer->id, 'post_id' => $post->id]);
            DB::table('bookmark_saved_collection')->insertOrIgnore([
                'bookmark_id' => $bookmark->id,
                'saved_collection_id' => $collection->id,
            ]);
        });

        return back()->with('success', 'Added to '.$collection->name.'.');
    }

    public function remove(Request $request, SavedCollection $collection, Post $post): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);

        Bookmark::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->first()?->collections()->detach($collection->id);

        return back();
    }

    public function share(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $data = $request->validate(['shared' => ['required', 'boolean']]);
        DB::transaction(function () use ($collection, $data) {
            $current = SavedCollection::lockForUpdate()->findOrFail($collection->id);
            $current->forceFill(['share_token' => $data['shared'] ? ($current->share_token ?? (string) Str::uuid()) : null])->save();
        });

        return back()->with('success', $data['shared'] ? 'Share link is ready.' : 'Collection is private again.');
    }

    public function shared(Request $request, string $token): Response
    {
        $collection = SavedCollection::where('share_token', $token)->firstOrFail();
        $viewer = $request->user();
        $owner = User::whereNull('banned_at')->findOrFail($collection->user_id);
        abort_if($viewer && Block::existsBetween($viewer->id, $owner->id), 404);

        $posts = Post::visibleTo($viewer)->visibleRating((bool) $viewer?->show_explicit)
            ->whereHas('bookmarks', fn ($query) => $query->where('user_id', $owner->id)
                ->whereHas('collections', fn ($lists) => $lists->whereKey($collection->id)))
            ->with(['user', 'media.media', 'chapter.comic'])->latest('id')->simplePaginate(36);

        return Inertia::render('Collections/Shared', [
            'name' => $collection->name, 'owner' => $owner->username,
            'posts' => PostResource::collection($posts->items())->resolve(),
            'nextPage' => $posts->nextPageUrl(), 'previousPage' => $posts->previousPageUrl(),
        ]);
    }

    private function authorizeOwner(Request $request, SavedCollection $collection): void
    {
        abort_unless($collection->user_id === $request->user()->id, 404);
    }
}
