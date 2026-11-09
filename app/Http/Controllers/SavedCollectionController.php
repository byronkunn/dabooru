<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\User;
use App\Support\CollectionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SavedCollectionController extends Controller
{
    public function index(Request $request, ?SavedCollection $collection = null): Response
    {
        if ($collection) {
            $this->authorizeOwner($request, $collection);
        }
        $viewer = $request->user();
        $muted = CollectionLibrary::mutedTags($request);
        $showAll = ! $collection && $request->query('view') === 'all';

        if (! $collection && ! $showAll) {
            return Inertia::render('Collections/Index', [
                'collections' => SavedCollection::where('user_id', $viewer->id)->orderBy('name')->get()
                    ->map(fn ($folder) => CollectionLibrary::summary($viewer->id, $viewer, $folder, $muted)),
                'allSaved' => CollectionLibrary::summary($viewer->id, $viewer, null, $muted),
            ]);
        }

        $editing = $collection && $request->boolean('edit');
        $editItems = [];
        $hiddenBookmarks = [];
        if ($editing) {
            $visible = CollectionLibrary::posts($viewer->id, $viewer, $collection, $muted);
            $editItems = PostResource::collection(CollectionLibrary::ordered(clone $visible, $collection, 'custom')
                ->with(['user', 'media.media', 'chapter.comic'])->get())->resolve();
            $hiddenBookmarks = Bookmark::where('user_id', $viewer->id)
                ->whereHas('collections', fn ($folders) => $folders->whereKey($collection->id))
                ->whereNotIn('post_id', $visible->select('posts.id'))
                ->orderBy('id')->pluck('id')->all();
        }

        return Inertia::render('Collections/Index', $this->viewData($request, $viewer->id, $collection, $muted) + [
            'allSaved' => $showAll ? CollectionLibrary::summary($viewer->id, $viewer, null, $muted) : null,
            'collection' => $collection ? CollectionLibrary::summary($viewer->id, $viewer, $collection, $muted) + [
                'shareUrl' => $collection->share_token ? route('collections.shared', $collection->share_token) : null,
                'publicUrl' => $collection->is_public ? route('collections.public', [$viewer->username, $collection]) : null,
            ] : null,
            'editItems' => $editItems,
            'hiddenBookmarks' => $hiddenBookmarks,
            'editing' => $editing,
            'showAll' => $showAll,
        ]);
    }

    public function updateMutedTags(Request $request): RedirectResponse
    {
        $data = $request->validate(['exclude_tags' => ['present', 'nullable', 'string', 'max:2000']]);
        CollectionLibrary::setMutedTags($request, $data['exclude_tags']);

        return redirect()->to((string) Uri::of(url()->previous())->withoutQuery(['page', 'exclude_tags']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'shared' => ['sometimes', 'boolean'],
        ]);
        $collection = DB::transaction(function () use ($request, $data) {
            $collection = SavedCollection::firstOrCreate(['user_id' => $request->user()->id, 'name' => $data['name']]);
            if ($collection->wasRecentlyCreated && ($data['shared'] ?? false)) {
                $collection->forceFill(['share_token' => (string) Str::uuid()])->save();
            }

            return $collection;
        });

        return redirect()->route('collections.show', $collection);
    }

    public function update(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('saved_collections')->where('user_id', $request->user()->id)->ignore($collection->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'cover_post_id' => ['nullable', 'integer'],
        ]);
        DB::transaction(function () use ($request, $collection, $data) {
            $current = SavedCollection::lockForUpdate()->findOrFail($collection->id);
            if (! empty($data['cover_post_id']) && ! CollectionLibrary::posts($current->user_id, $request->user(), $current)
                ->where('posts.id', $data['cover_post_id'])->exists()) {
                throw ValidationException::withMessages(['cover_post_id' => 'Choose a visible work from this collection.']);
            }
            $current->update($data);
        });

        return back()->with('success', 'Collection updated.');
    }

    public function reorder(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['required', 'integer', 'distinct']]);
        DB::transaction(function () use ($request, $collection, $data) {
            SavedCollection::lockForUpdate()->findOrFail($collection->id);
            $items = CollectionLibrary::ordered(CollectionLibrary::posts($collection->user_id, $request->user(), $collection,
                CollectionLibrary::mutedTags($request)), $collection, 'custom')
                ->addSelect(['saved.id as bookmark_id', 'membership.position as collection_position'])->get();
            if ($items->pluck('id')->sort()->values()->all() !== collect($data['order'])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                throw ValidationException::withMessages(['order' => 'The collection changed. Reload before reordering.']);
            }
            // Only replace visible slots; hidden works keep their position.
            $slots = $items->pluck('collection_position')->all();
            $byId = $items->keyBy('id');
            foreach ($data['order'] as $index => $postId) {
                DB::table('bookmark_saved_collection')->where('saved_collection_id', $collection->id)
                    ->where('bookmark_id', $byId[$postId]->bookmark_id)->update(['position' => $slots[$index]]);
            }
        });

        return back()->with('success', 'Custom order saved.');
    }

    public function destroy(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $collection->delete();

        return redirect()->route('collections.index')->with('success', 'Collection deleted. Its works remain in All Saved.');
    }

    public function add(Request $request, SavedCollection $collection, Post $post): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $viewer = $request->user();
        abort_unless(Post::visibleTo($viewer)->whereKey($post->id)->exists(), 404);
        DB::transaction(function () use ($collection, $post, $viewer) {
            User::lockForUpdate()->findOrFail($viewer->id);
            SavedCollection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $bookmark = Bookmark::firstOrCreate(['user_id' => $viewer->id, 'post_id' => $post->id]);
            CollectionLibrary::attach($collection, $bookmark);
        });

        return back()->with('success', 'Added to '.$collection->name.'.');
    }

    public function remove(Request $request, SavedCollection $collection, Post $post): RedirectResponse
    {
        return $this->removeSavedWork($request, $collection, ['post_id' => $post->id]);
    }

    public function removeBookmark(Request $request, SavedCollection $collection, int $bookmark): RedirectResponse
    {
        return $this->removeSavedWork($request, $collection, ['id' => $bookmark]);
    }

    private function removeSavedWork(Request $request, SavedCollection $collection, array $match): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        DB::transaction(function () use ($request, $collection, $match) {
            $ownerId = $request->user()->id;
            User::lockForUpdate()->findOrFail($ownerId);
            $current = SavedCollection::lockForUpdate()->findOrFail($collection->id);
            $bookmark = Bookmark::where('user_id', $ownerId)->where($match)->first();
            if ($bookmark && $bookmark->collections()->detach($current->id) && $current->cover_post_id === $bookmark->post_id) {
                $current->update(['cover_post_id' => null]);
            }
        });

        return back()->with('success', 'Removed from collection. Still in All Saved.');
    }

    public function share(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $data = $request->validate(['shared' => ['required', 'boolean']]);
        DB::transaction(function () use ($collection, $data) {
            $current = SavedCollection::lockForUpdate()->findOrFail($collection->id);
            $current->forceFill(['share_token' => $data['shared'] ? ($current->share_token ?? (string) Str::uuid()) : null])->save();
        });

        return back()->with('success', $data['shared'] ? 'Share link is ready.' : 'Link sharing is disabled.');
    }

    public function visibility(Request $request, SavedCollection $collection): RedirectResponse
    {
        $this->authorizeOwner($request, $collection);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        DB::transaction(function () use ($collection, $data) {
            $current = SavedCollection::lockForUpdate()->findOrFail($collection->id);
            $current->is_public = $data['is_public'];
            if (! $current->is_public) {
                $current->share_token = null;
            }
            $current->save();
        });

        return back()->with('success', $data['is_public']
            ? 'Collection is public and appears on your profile.'
            : 'Collection is private. Any existing share link has been turned off.');
    }

    public function shared(Request $request, string $token): Response
    {
        $collection = SavedCollection::where('share_token', $token)->firstOrFail();
        $viewer = $request->user();
        $owner = User::whereNull('banned_at')->findOrFail($collection->user_id);
        abort_if($viewer && Block::existsBetween($viewer->id, $owner->id), 404);
        $muted = CollectionLibrary::mutedTags($request);

        return Inertia::render('Collections/Shared', $this->viewData($request, $owner->id, $collection, $muted) + [
            'name' => $collection->name, 'owner' => $owner->username,
            'collection' => CollectionLibrary::summary($owner->id, $viewer, $collection, $muted),
            'ownerUrl' => $viewer?->id === $owner->id ? route('collections.show', ['collection' => $collection, 'edit' => 1]) : null,
        ]);
    }

    public function publicCollection(Request $request, string $username, SavedCollection $collection): Response
    {
        abort_unless($collection->is_public, 404);
        $viewer = $request->user();
        $owner = User::where('username', $username)->whereNull('banned_at')->findOrFail($collection->user_id);
        abort_if($viewer && Block::existsBetween($viewer->id, $owner->id), 404);
        $muted = CollectionLibrary::mutedTags($request);

        return Inertia::render('Collections/Shared', $this->viewData($request, $owner->id, $collection, $muted) + [
            'name' => $collection->name, 'owner' => $owner->username,
            'collection' => CollectionLibrary::summary($owner->id, $viewer, $collection, $muted) + [
                'publicUrl' => route('collections.public', [$owner->username, $collection]),
            ],
            'ownerUrl' => $viewer?->id === $owner->id ? route('collections.show', ['collection' => $collection, 'edit' => 1]) : null,
        ]);
    }

    private function viewData(Request $request, int $ownerId, ?SavedCollection $collection, array $muted): array
    {
        $data = $request->validate(['order' => ['nullable', Rule::in(['recent', 'custom'])]]);
        $order = $data['order'] ?? 'recent';
        $posts = CollectionLibrary::ordered(CollectionLibrary::posts($ownerId, $request->user(), $collection, $muted), $collection, $order)
            ->with(['user', 'media.media', 'chapter.comic'])->simplePaginate(36)->appends($request->except('exclude_tags'));

        return [
            'posts' => PostResource::collection($posts->items())->resolve(),
            'nextPage' => $posts->nextPageUrl(), 'previousPage' => $posts->previousPageUrl(),
            'order' => $order, 'mutedTags' => $muted,
        ];
    }

    private function authorizeOwner(Request $request, SavedCollection $collection): void
    {
        abort_unless($collection->user_id === $request->user()->id, 404);
    }
}
