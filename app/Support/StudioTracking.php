<?php

namespace App\Support;

use App\Models\ComicChapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudioTracking
{
    public static function source(Request $request): string
    {
        $referrer = parse_url($request->headers->get('referer', ''));
        if (! $referrer || ! isset($referrer['host'])) return 'direct';
        if (strcasecmp($referrer['host'], $request->getHost()) !== 0) return 'external';
        $path = $referrer['path'] ?? '/';
        if ($path === '/discover') return 'discover';
        if ($path === '/following') return 'following';
        if ($path === '/search') return 'search';
        if ($path === '/') {
            parse_str($referrer['query'] ?? '', $query);
            return ! empty($query['tag']) || ! empty($query['tags']) ? 'tags' : 'gallery';
        }
        if (str_starts_with($path, '/u/') || str_starts_with($path, '/artists')) return 'profile';
        if (str_starts_with($path, '/community/') || $path === '/tags') return 'tags';
        if (str_starts_with($path, '/comics')) return 'comics';
        return 'other';
    }

    public static function reader(Request $request, ComicChapter $chapter, int $page): void
    {
        $key = $request->user() ? 'u'.$request->user()->id
            : 's'.hash_hmac('sha256', $request->session()->getId(), config('app.key'));
        DB::transaction(function () use ($chapter, $key, $page) {
            DB::table('chapter_readers')->insertOrIgnore([
                'comic_chapter_id' => $chapter->id, 'viewer_key' => $key,
                'max_page' => $page, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $reader = DB::table('chapter_readers')->where('comic_chapter_id', $chapter->id)
                ->where('viewer_key', $key)->lockForUpdate()->first();
            $pages = $chapter->pages()->count();
            DB::table('chapter_readers')->where('id', $reader->id)->update([
                'max_page' => max($reader->max_page, $page),
                'completed_at' => $reader->completed_at ?? ($pages > 0 && $page >= $pages ? now() : null),
                'updated_at' => now(),
            ]);
        });
    }
}
