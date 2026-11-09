<?php

namespace App\Support;

class TagCategories
{
    public static function forName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $groups = [
            'genre' => ['gay', 'straight', 'bisexual', 'lesbian', 'queer', 'interracial', 'romance', 'erotic-drama', 'fetish', 'fantasy'],
            'characters' => ['adult-man', 'adult-woman', 'couple', 'threesome', 'group', 'dominant', 'submissive', 'muscular', 'curvy', 'androgynous'],
            'franchise' => ['original', 'anime', 'manga', 'comic', 'superhero', 'fantasy-series', 'sci-fi-series', 'video-game', 'visual-novel', 'parody'],
            'anatomies' => ['athletic-build', 'muscular-build', 'slim-build', 'curvy-build', 'plus-size', 'mature', 'body-hair', 'tattooed', 'pierced', 'intersex'],
            'poses' => ['standing', 'seated', 'kneeling', 'lying-down', 'face-to-face', 'side-by-side', 'behind', 'embrace', 'pin-up', 'solo-pose'],
            'activity' => ['kissing', 'cuddling', 'dancing', 'flirting', 'massage', 'roleplay', 'seduction', 'striptease', 'solo', 'partnered'],
            'substance' => ['alcohol', 'cannabis', 'tobacco', 'vaping', 'caffeine', 'prescription-medication', 'recreational-drugs', 'sober', 'intoxication', 'substance-use'],
            'settings' => ['bedroom', 'hotel', 'home', 'office', 'nightclub', 'beach', 'outdoors', 'art-studio', 'fantasy-world', 'spaceship'],
            'language' => ['english', 'spanish', 'french', 'german', 'japanese', 'korean', 'mandarin', 'portuguese', 'italian', 'hindi'],
            'entity' => ['human', 'fictional-character', 'humanoid', 'alien', 'android', 'supernatural', 'creature', 'avatar', 'animal', 'mythical-being'],
            'artist' => ['independent-artist', 'studio', 'animator', 'illustrator', 'photographer', 'director', 'performer', 'voice-actor', 'creator', 'guest-artist'],
        ];
        foreach ($groups as $category => $names) {
            if (in_array($name, $names, true)) return $category;
        }
        if (preg_match('/\b(gay|straight|bisexual|lesbian|queer|romance|fetish|fantasy|erotic)\b/', $name)) return 'genre';
        if (preg_match('/\b(artist|animator|illustrator|photographer|director|performer|creator|voice actor|studio)\b/', $name)) return 'artist';
        if (preg_match('/\b(english|spanish|french|german|japanese|korean|mandarin|portuguese|italian|hindi)\b/', $name)) return 'language';
        if (preg_match('/\b(alcohol|cannabis|tobacco|vaping|caffeine|drug|intoxication|sober)\b/', $name)) return 'substance';
        if (preg_match('/\b(bedroom|hotel|home|office|nightclub|beach|outdoors|studio|room|setting|background)\b/', $name)) return 'settings';
        if (preg_match('/\b(kiss|cuddle|dance|flirt|massage|roleplay|seduction|striptease|sex|oral|solo|partnered)\b/', $name)) return 'activity';
        if (preg_match('/\b(standing|seated|kneeling|lying|pose|embrace|facing|position)\b/', $name)) return 'poses';
        if (preg_match('/\b(anatomy|body|muscular|curvy|slim|hair|tattoo|piercing|intersex|plus-size)\b/', $name)) return 'anatomies';
        if (preg_match('/\b(franchise|series|anime|manga|comic|superhero|video game|visual novel|parody)\b/', $name)) return 'franchise';
        if (preg_match('/\b(character|couple|group|human|alien|android|creature|avatar|entity)\b/', $name)) return 'characters';
        return 'entity';
    }
}
