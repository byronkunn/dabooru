<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\CuratedShelf;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CuratedShelfController extends Controller
{
    public function manage()
    {
        $shelves = CuratedShelf::latest()->orderByDesc('id')->simplePaginate(15);

        return Inertia::render('Admin/Shelves', [
            'shelves' => $shelves->getCollection()->map(fn ($shelf) => $shelf->only(['id', 'title', 'description', 'published']) + [
                'entryText' => collect($shelf->entries)->map(fn ($entry) => $entry['kind'].':'.$entry['id'])->implode("\n"),
            ]),
            'nextPage' => $shelves->nextPageUrl(), 'previousPage' => $shelves->previousPageUrl(),
        ]);
    }

    public function save(Request $request, ?CuratedShelf $shelf = null)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'published' => ['required', 'boolean'],
            'entryText' => ['required', 'string', 'max:4000'],
        ]);
        $lines = collect(preg_split('/\R/', $data['entryText']))->map(fn ($line) => trim($line))->filter()->values();
        if ($lines->isEmpty() || $lines->count() > 12) {
            throw ValidationException::withMessages(['entryText' => 'Enter between one and twelve items.']);
        }

        $entries = $lines->map(function ($line, $index) {
            $entry = $this->resolveEntry($line);
            if (! $entry) {
                throw ValidationException::withMessages(['entryText' => 'Line '.($index + 1).' does not match a published work, comic, or artist.']);
            }

            return $entry;
        })->unique(fn ($entry) => $entry['kind'].':'.$entry['id'])->values()->all();

        DB::transaction(function () use ($request, $shelf, $data, $entries) {
            $record = $shelf ? CuratedShelf::lockForUpdate()->findOrFail($shelf->id) : new CuratedShelf;
            $record->fill([
                'title' => $data['title'], 'description' => $data['description'] ?? null,
                'published' => $data['published'], 'entries' => $entries,
            ])->save();
            ModerationAction::record($request->user(), 'update_curated_shelf', CuratedShelf::class, $record->id, null,
                $data['published'] ? 'Shelf published' : 'Shelf saved as draft');
        });

        return back()->with('success', 'Shelf saved.');
    }

    private function resolveEntry(string $line): ?array
    {
        if (preg_match('/^(work|comic|artist):([1-9][0-9]*)$/D', $line, $parts)) {
            [$kind, $id] = [$parts[1], (int) $parts[2]];
            $exists = match ($kind) {
                'work' => Post::whereKey($id)->exists(),
                'comic' => Comic::whereKey($id)->exists(),
                'artist' => User::whereKey($id)->whereNull('banned_at')->where('is_unsorted', false)->exists(),
            };

            return $exists ? ['kind' => $kind, 'id' => $id] : null;
        }
        $url = parse_url($line);
        if (! $url || isset($url['host']) && strcasecmp($url['host'], (string) parse_url(config('app.url'), PHP_URL_HOST)) !== 0) {
            return null;
        }

        $path = $url['path'] ?? '';
        if (preg_match('~^/p/([1-9][0-9]*)/?$~D', $path, $parts)) {
            return $this->resolveEntry('work:'.$parts[1]);
        }
        if (preg_match('~^/comics/([a-z0-9-]+)/?$~D', $path, $parts)) {
            $id = Comic::where('slug', $parts[1])->value('id');

            return $id ? ['kind' => 'comic', 'id' => $id] : null;
        }
        if (preg_match('~^/u/([^/]+)/?$~D', $path, $parts)) {
            $id = User::where('username', rawurldecode($parts[1]))->whereNull('banned_at')->where('is_unsorted', false)->value('id');

            return $id ? ['kind' => 'artist', 'id' => $id] : null;
        }

        return null;
    }
}
