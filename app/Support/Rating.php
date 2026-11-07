<?php

namespace App\Support;

/**
 * The three content ratings used site-wide.
 *
 * A post marked explicit is hidden (blurred / filtered) for anyone who
 * has not opted in to explicit content. Suggestive is visible to
 * logged-out visitors who passed the age gate.
 */
final class Rating
{
    public const SFW = 'sfw';

    public const SUGGESTIVE = 'suggestive';

    public const EXPLICIT = 'explicit';

    public const ALL = [self::SFW, self::SUGGESTIVE, self::EXPLICIT];

    public const LABELS = [
        self::SFW => 'SFW',
        self::SUGGESTIVE => 'Suggestive',
        self::EXPLICIT => 'Explicit',
    ];

    public static function label(string $rating): string
    {
        return self::LABELS[$rating] ?? strtoupper($rating);
    }

    public static function isExplicit(string $rating): bool
    {
        return $rating === self::EXPLICIT;
    }
}
