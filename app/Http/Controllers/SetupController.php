<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use App\Support\Rating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The short, skippable setup shown once after registration.
 *
 * A brand new account follows nobody and has liked nothing, so Discover has no
 * signals to work with. Three cheap choices fix that immediately: a few tags to
 * follow, a few artists, and how much explicit work to show.
 */
class SetupController extends Controller
{
    /** Tags offered first: the ones with the most work behind them. */
    private const SUGGESTED_TAGS = 24;

    /** Artists offered first. */
    private const SUGGESTED_ARTISTS = 12;

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // already set up (or skipped) — no reason to show it again
        if ($user->setup_completed_at !== null) {
            return redirect()->route('gallery');
        }

        $tags = Tag::query()
            ->where('is_approved', true)
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->take(self::SUGGESTED_TAGS)
            ->get(['id', 'name'])
            ->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'works' => $tag->posts_count])
            ->all();

        $artists = User::query()
            ->whereKeyNot($user->id)
            ->whereNotNull('username')
            ->where('is_unsorted', false)
            ->whereNotIn('id', $user->blockedUserIds() ?: [0])
            ->withCount(['posts' => fn ($q) => $q->visibleTo($user)])
            ->has('posts')
            ->orderByDesc('posts_count')
            ->take(self::SUGGESTED_ARTISTS)
            ->get()
            ->map(fn ($artist) => [
                'username' => $artist->username,
                'name' => $artist->name,
                'avatar' => $artist->avatarUrl(),
                'works' => $artist->posts_count,
            ])
            ->all();

        return Inertia::render('Setup/Index', [
            'suggestedTags' => $tags,
            'suggestedArtists' => $artists,
            'ratingOptions' => collect(Rating::LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'showExplicit' => (bool) $user->show_explicit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:40'],
            'artists' => ['array', 'max:30'],
            'artists.*' => ['string', 'max:30', 'exists:users,username'],
            'show_explicit' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        // follow the chosen tags
        $tagIds = Tag::query()
            ->where('is_approved', true)
            ->whereIn('name', collect($data['tags'] ?? [])->map(fn ($n) => Tag::canonicalName($n))->all())
            ->pluck('id');
        if ($tagIds->isNotEmpty()) {
            $user->followedTags()->syncWithoutDetaching($tagIds->all());
        }

        // follow the chosen artists
        $artistIds = User::whereIn('username', $data['artists'] ?? [])->whereKeyNot($user->id)->pluck('id');
        if ($artistIds->isNotEmpty()) {
            $user->following()->syncWithoutDetaching($artistIds->all());
        }

        if ($request->has('show_explicit')) {
            $user->forceFill(['show_explicit' => $request->boolean('show_explicit')])->save();
        }

        $this->complete($user);

        return redirect()->route('gallery')->with('success', 'Welcome — your feed will start filling up now.');
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->complete($request->user());

        return redirect()->route('gallery');
    }

    private function complete(User $user): void
    {
        $user->forceFill(['setup_completed_at' => $user->setup_completed_at ?? now()])->save();
    }
}
