<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Post;
use App\Models\StickerPack;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StickerPackController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'artist' => ['nullable', 'string', 'max:60'],
            'pack' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Messages/Stickers', [
            'packs' => $this->catalog($request->user(), $data),
            'works' => fn () => StickerPack::eligible($request->user())->where('posts.user_id', $request->user()->id)
                ->latest('id')->limit(60)->get(['id', 'title']),
        ]);
    }

    public function picker(Request $request): JsonResponse
    {
        return response()->json($this->catalog($request->user(), []));
    }

    public function store(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80', Rule::unique('sticker_packs')->where('user_id', $viewer->id)],
            'post_ids' => ['required', 'array', 'list', 'min:1', 'max:16'],
            'post_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        if (StickerPack::eligible($viewer)->where('posts.user_id', $viewer->id)->whereKey($data['post_ids'])->count() !== count($data['post_ids'])) {
            throw ValidationException::withMessages(['post_ids' => 'Choose your own published, original, SFW images.']);
        }
        $pack = StickerPack::firstOrCreate(['user_id' => $viewer->id, 'title' => $data['title']], ['post_ids' => $data['post_ids']]);
        if (! $pack->wasRecentlyCreated) {
            throw ValidationException::withMessages(['title' => 'You already have a pack with this name.']);
        }

        return redirect()->route('messages.stickers.index', ['pack' => $pack->id])->with('success', 'Sticker pack published.');
    }

    public function destroy(Request $request, StickerPack $stickerPack): RedirectResponse
    {
        abort_unless($stickerPack->user_id === $request->user()->id, 403);
        $stickerPack->delete();

        return redirect()->route('messages.stickers.index')->with('success', 'Sticker pack removed.');
    }

    private function catalog(User $viewer, array $filters): array
    {
        $packs = StickerPack::with('user')->whereNotIn('user_id', Block::counterpartIds($viewer->id))
            ->whereHas('user', fn ($query) => $query->whereNull('banned_at')
                ->when(isset($filters['artist']), fn ($artist) => $artist->where('username', $filters['artist'])))
            ->when(isset($filters['pack']), fn ($query) => $query->whereKey($filters['pack']))
            ->latest('id')->limit(30)->get();
        $posts = StickerPack::eligible($viewer)->whereKey($packs->pluck('post_ids')->flatten()->unique()->all())
            ->with('media.media')->get()->keyBy('id');

        return $packs->map(fn (StickerPack $pack) => [
            'id' => $pack->id,
            'title' => $pack->title,
            'artist' => $pack->user->username,
            'mine' => $pack->user_id === $viewer->id,
            'stickers' => collect($pack->post_ids)->map(fn ($id) => $posts->get($id))
                ->filter(fn (?Post $post) => $post && $post->user_id === $pack->user_id && $post->cover())
                ->map(fn (Post $post) => [
                    'id' => $post->id, 'title' => $post->title ?: 'Sticker',
                    'thumbnail' => $post->cover()->thumbUrl() ?: $post->cover()->url(),
                ])->values(),
        ])->all();
    }
}
