<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * A sitemap of publicly visible pages.
     *
     * Explicit work is deliberately excluded: it is behind an age gate and is not
     * something that should surface in a search result from someone else's device.
     * Everything here is SFW/suggestive and visible to a signed-out visitor.
     */
    public function index(): Response
    {
        $urls = [
            ['loc' => route('gallery'), 'priority' => '1.0'],
            ['loc' => route('discover'), 'priority' => '0.9'],
            ['loc' => route('artists.index'), 'priority' => '0.8'],
            ['loc' => route('comics.index'), 'priority' => '0.8'],
            ['loc' => route('knowledge.index'), 'priority' => '0.6'],
            ['loc' => route('legal.terms'), 'priority' => '0.3'],
            ['loc' => route('legal.privacy'), 'priority' => '0.3'],
            ['loc' => route('legal.policy'), 'priority' => '0.3'],
        ];

        Post::visibleTo(null)->visibleRating(false)
            ->where('is_draft', false)
            ->latest('updated_at')
            ->limit(5000)
            ->get(['id', 'updated_at'])
            ->each(function (Post $post) use (&$urls) {
                $urls[] = [
                    'loc' => route('posts.show', $post),
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'priority' => '0.7',
                ];
            });

        User::verified()
            ->whereHas('posts', fn ($query) => $query->visibleTo(null)->visibleRating(false)->where('is_draft', false))
            ->orderBy('username')
            ->limit(5000)
            ->get(['username', 'updated_at'])
            ->each(function (User $artist) use (&$urls) {
                $urls[] = [
                    'loc' => route('profile.show', $artist->username),
                    'lastmod' => $artist->updated_at?->toAtomString(),
                    'priority' => '0.6',
                ];
            });

        Comic::visibleTo(null)
            ->whereHas('chapters', fn ($q) => $q->whereNotNull('published_at')->where('published_at', '<=', now())->where('rating', '!=', 'explicit'))
            ->where('rating', '!=', 'explicit')
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->each(function (Comic $comic) use (&$urls) {
                $urls[] = [
                    'loc' => route('comics.show', $comic->slug),
                    'lastmod' => $comic->updated_at?->toAtomString(),
                    'priority' => '0.6',
                ];
            });

        ComicChapter::published()->visibleAfterModeration()
            ->where('rating', '!=', 'explicit')
            ->whereHas('comic', fn ($query) => $query->visibleTo(null)->where('rating', '!=', 'explicit'))
            ->with('comic:id,slug')
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get(['id', 'comic_id', 'number', 'updated_at'])
            ->each(function (ComicChapter $chapter) use (&$urls) {
                $urls[] = [
                    'loc' => route('comics.read', [$chapter->comic->slug, $chapter->number]),
                    'lastmod' => $chapter->updated_at?->toAtomString(),
                    'priority' => '0.5',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
            if (! empty($url['lastmod'])) {
                $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }
            $xml .= '<priority>'.$url['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
