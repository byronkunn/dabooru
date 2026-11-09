<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Sticker;
use App\Models\StickerPack;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class StickerPackController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'artist' => ['nullable', 'string', 'max:60'],
            'pack' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'return_chat' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Messages/Stickers', [
            'packs' => $this->catalog($request->user(), $data),
            'filters' => $data,
            'works' => fn () => StickerPack::eligible($request->user())->where('posts.user_id', $request->user()->id)
                ->latest('id')->limit(60)->get(['id', 'title']),
        ]);
    }

    /**
     * JSON feed used by the chat sticker picker.
     *
     * A browser navigating here would otherwise get a blank page showing raw
     * JSON, so a page request is redirected to the sticker library instead.
     */
    public function picker(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->wantsJson()) {
            return redirect()->route('messages.stickers.index');
        }

        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json($this->catalog($request->user(), $data));
    }

    public function store(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80', Rule::unique('sticker_packs')->where('user_id', $viewer->id)],
            'return_chat' => ['nullable', 'integer', 'min:1'],
            'is_public' => ['sometimes', 'boolean'],
            'post_ids' => ['required_without:files', 'array', 'list', 'max:16'],
            'post_ids.*' => ['required', 'integer', 'distinct'],
            'files' => ['required_without:post_ids', 'array', 'list', 'max:16'],
            'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120', 'dimensions:max_width=2048,max_height=2048'],
        ]);
        $postIds = $data['post_ids'] ?? [];
        $files = $request->file('files', []);
        if (count($postIds) + count($files) < 1 || count($postIds) + count($files) > 16) {
            throw ValidationException::withMessages(['files' => 'Choose between 1 and 16 stickers in total.']);
        }
        if (StickerPack::eligible($viewer)->where('posts.user_id', $viewer->id)->whereKey($postIds)->count() !== count($postIds)) {
            throw ValidationException::withMessages(['post_ids' => 'Choose your own published, original, SFW images.']);
        }
        $stored = [];
        try {
            $pack = DB::transaction(function () use ($viewer, $data, $postIds, $files, &$stored) {
                $pack = StickerPack::create([
                    'user_id' => $viewer->id, 'title' => $data['title'], 'post_ids' => $postIds,
                    'is_public' => $data['is_public'] ?? ($files === []),
                ]);
                foreach ($files as $file) {
                    $media = MessageAttachments::store($viewer, $file, true);
                    $stored[] = $media;
                    $pack->stickers()->create([
                        'media_id' => $media->id,
                        'title' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 100, ''),
                    ]);
                }

                return $pack;
            });
        } catch (Throwable $error) {
            foreach ($stored as $media) {
                Storage::disk($media->disk)->deleteDirectory(dirname($media->path));
            }
            throw $error;
        }

        return redirect()->route('messages.stickers.index', [
            'pack' => $pack->id, 'return_chat' => $data['return_chat'] ?? null,
        ])->with('success', 'Sticker pack created.');
    }

    public function update(Request $request, StickerPack $stickerPack): RedirectResponse
    {
        abort_unless($stickerPack->user_id === $request->user()->id, 403);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $stickerPack->update($data);

        return back()->with('success', $data['is_public'] ? 'Sticker pack published for everyone.' : 'Sticker pack is now private.');
    }

    public function image(Request $request, Sticker $sticker): BinaryFileResponse
    {
        abort_unless(StickerPack::availableTo($request->user())->whereKey($sticker->sticker_pack_id)->exists(), 404);
        $media = $sticker->media;
        abort_unless($media && MessageAttachments::isPrivate($media)
            && Storage::disk($media->disk)->exists($media->path), 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, StickerPack $stickerPack): RedirectResponse
    {
        abort_unless($stickerPack->user_id === $request->user()->id, 403);
        $data = $request->validate(['return_chat' => ['nullable', 'integer', 'min:1']]);
        DB::transaction(function () use ($stickerPack) {
            $mediaIds = $stickerPack->stickers()->pluck('media_id');
            $stickerPack->delete();
            foreach ($mediaIds as $mediaId) {
                MessageAttachments::purgeAfterCommit($mediaId);
            }
        });

        return redirect()->route('messages.stickers.index', [
            'return_chat' => $data['return_chat'] ?? null,
        ])->with('success', 'Sticker pack removed.');
    }

    private function catalog(User $viewer, array $filters): array
    {
        $packs = StickerPack::with(['user', 'stickers'])->availableTo($viewer)
            ->when(isset($filters['artist']), fn ($query) => $query->whereHas('user', fn ($artist) => $artist->where('username', $filters['artist'])))
            ->when(! empty($filters['q']), fn ($query) => $query->where(fn ($search) => $search
                ->whereLike('title', '%'.$filters['q'].'%')
                ->orWhereHas('user', fn ($artist) => $artist->whereLike('username', '%'.$filters['q'].'%'))
                ->orWhereHas('stickers', fn ($sticker) => $sticker->whereLike('title', '%'.$filters['q'].'%'))))
            ->when(isset($filters['pack']), fn ($query) => $query->whereKey($filters['pack']))
            ->latest('id')->limit(30)->get();
        $posts = StickerPack::eligible($viewer)->whereKey($packs->pluck('post_ids')->flatten()->unique()->all())
            ->with('media.media')->get()->keyBy('id');

        return $packs->map(fn (StickerPack $pack) => [
            'id' => $pack->id,
            'title' => $pack->title,
            'artist' => $pack->user->username,
            'mine' => $pack->user_id === $viewer->id,
            'is_public' => $pack->is_public,
            'stickers' => collect($pack->post_ids)->map(fn ($id) => $posts->get($id))
                ->filter(fn (?Post $post) => $post && $post->user_id === $pack->user_id && $post->cover())
                ->map(fn (Post $post) => [
                    'id' => $post->id, 'title' => $post->title ?: 'Sticker',
                    'thumbnail' => $post->cover()->thumbUrl() ?: $post->cover()->url(),
                ])->values()->concat($pack->stickers->map(fn (Sticker $sticker) => [
                    'id' => $sticker->id, 'upload_id' => $sticker->id, 'title' => $sticker->title,
                    'thumbnail' => route('messages.stickers.image', $sticker, false),
                ]))->values(),
        ])->all();
    }
}
