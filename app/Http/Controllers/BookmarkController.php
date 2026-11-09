<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\User;
use App\Support\CollectionLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookmarkController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        abort_unless(Post::visibleTo($request->user())->whereKey($post->id)->exists(), 404);
        DB::transaction(function () use ($request, $post) {
            User::lockForUpdate()->findOrFail($request->user()->id);
            Bookmark::firstOrCreate(['user_id' => $request->user()->id, 'post_id' => $post->id]);
        });

        return back()->with('success', 'Saved to All Saved.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $snapshot = DB::transaction(function () use ($request, $post) {
            User::lockForUpdate()->findOrFail($request->user()->id);
            $bookmark = Bookmark::where('user_id', $request->user()->id)->where('post_id', $post->id)->first();
            if (! $bookmark) {
                throw ValidationException::withMessages(['save' => 'This work is no longer saved.']);
            }
            $memberships = $bookmark->collections()->where('user_id', $request->user()->id)
                ->orderBy('saved_collections.id')->lockForUpdate()->get()
                ->map(fn ($collection) => [
                    'id' => $collection->id, 'position' => $collection->pivot->position,
                    'added_at' => $collection->pivot->added_at,
                    'added_order' => $collection->pivot->added_order,
                    'was_cover' => $collection->cover_post_id === $post->id,
                ])->all();
            $snapshot = ['user_id' => $request->user()->id, 'post_id' => $post->id,
                'created_at' => $bookmark->created_at->toDateTimeString(), 'saved_order' => $bookmark->saved_order ?? $bookmark->id,
                'memberships' => $memberships, 'expires' => now()->addMinutes(5)->timestamp];
            SavedCollection::whereIn('id', array_column($memberships, 'id'))
                ->where('cover_post_id', $post->id)->update(['cover_post_id' => null]);
            $bookmark->delete();

            return $snapshot;
        });
        $token = (string) Str::uuid();
        $request->session()->put('bookmark_undo', $snapshot + ['token' => $token]);

        return back()->with('success', 'Unsaved from All Saved and all collections.')
            ->with('bookmarkUndo', ['token' => $token, 'postId' => $post->id]);
    }

    public function undo(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $snapshot = $request->session()->get('bookmark_undo');
        if (! $snapshot || $snapshot['user_id'] !== $request->user()->id || ! hash_equals($snapshot['token'], $data['token']) || $snapshot['expires'] < now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'Undo has expired. Save the work again from its page.']);
        }
        abort_unless(Post::visibleTo($request->user())->whereKey($snapshot['post_id'])->exists(), 404);
        DB::transaction(function () use ($snapshot) {
            User::lockForUpdate()->findOrFail($snapshot['user_id']);
            $bookmark = Bookmark::firstOrCreate(['user_id' => $snapshot['user_id'], 'post_id' => $snapshot['post_id']]);
            if ($bookmark->wasRecentlyCreated) {
                $bookmark->forceFill([
                    'created_at' => $snapshot['created_at'],
                    'saved_order' => $snapshot['saved_order'] ?? null,
                ])->save();
            }
            $memberships = collect($snapshot['memberships'])->keyBy('id');
            $collections = SavedCollection::where('user_id', $snapshot['user_id'])
                ->whereIn('id', $memberships->keys())->orderBy('id')->lockForUpdate()->get();
            foreach ($collections as $collection) {
                $membership = $memberships[$collection->id];
                $existing = DB::table('bookmark_saved_collection')->where('saved_collection_id', $collection->id);
                if ((clone $existing)->where('bookmark_id', $bookmark->id)->exists()) {
                    continue;
                }
                foreach (['position', 'added_order'] as $column) {
                    $slot = $membership[$column] ?? null;
                    if ($slot !== null && (clone $existing)->where($column, $slot)->exists()) {
                        (clone $existing)->where($column, '>=', $slot)->increment($column);
                    }
                }
                DB::table('bookmark_saved_collection')->insertOrIgnore([
                    'bookmark_id' => $bookmark->id, 'saved_collection_id' => $collection->id,
                    'position' => $membership['position'], 'added_at' => $membership['added_at'],
                    'added_order' => $membership['added_order'] ?? null,
                ]);
                if (($membership['was_cover'] ?? false) && $collection->cover_post_id === null) {
                    $collection->update(['cover_post_id' => $bookmark->post_id]);
                }
            }
        });
        $request->session()->forget('bookmark_undo');

        return back()->with('success', 'Save and collection memberships restored.');
    }

    public function collections(Request $request, Post $post): JsonResponse
    {
        abort_unless(Post::visibleTo($request->user())->whereKey($post->id)->exists(), 404);
        $bookmark = Bookmark::where('user_id', $request->user()->id)->where('post_id', $post->id)->first();

        return response()->json([
            'collections' => SavedCollection::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name']),
            'selected' => $bookmark?->collections()->where('user_id', $request->user()->id)->pluck('saved_collections.id')->all() ?? [],
        ]);
    }

    public function updateCollections(Request $request, Post $post): RedirectResponse
    {
        abort_unless(Post::visibleTo($request->user())->whereKey($post->id)->exists(), 404);
        $data = $request->validate([
            'collections' => ['present', 'array'],
            'collections.*' => ['integer', 'distinct'],
            'new_name' => ['nullable', 'string', 'max:80'],
        ]);
        DB::transaction(function () use ($request, $post, $data) {
            $userId = $request->user()->id;
            User::lockForUpdate()->findOrFail($userId);
            $collections = SavedCollection::where('user_id', $userId)->whereIn('id', $data['collections'])->orderBy('id')->lockForUpdate()->get();
            if ($collections->count() !== count($data['collections'])) {
                throw ValidationException::withMessages(['collections' => 'Choose only your own collections. Reload the picker if a collection was deleted.']);
            }
            if (trim($data['new_name'] ?? '') !== '') {
                $created = SavedCollection::firstOrCreate(['user_id' => $userId, 'name' => trim($data['new_name'])]);
                if (! $created->wasRecentlyCreated) {
                    throw ValidationException::withMessages(['new_name' => 'That name already exists. Select the existing collection or choose a new name.']);
                }
                $collections->push($created);
            }
            $bookmark = Bookmark::firstOrCreate(['user_id' => $userId, 'post_id' => $post->id]);
            $removeIds = $bookmark->collections()->where('user_id', $userId)
                ->whereNotIn('saved_collections.id', $collections->pluck('id'))->pluck('saved_collections.id');
            SavedCollection::whereKey($removeIds->all())
                ->where('cover_post_id', $post->id)->update(['cover_post_id' => null]);
            $bookmark->collections()->detach($removeIds);
            foreach ($collections as $collection) {
                CollectionLibrary::attach($collection, $bookmark);
            }
        });

        return back()->with('success', 'Saved to All Saved and selected collections.');
    }
}
