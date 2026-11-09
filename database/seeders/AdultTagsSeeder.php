<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class AdultTagsSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
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

        foreach ($tags as $category => $names) {
            foreach ($names as $name) {
                Tag::updateOrCreate(['name' => $name], ['category' => $category, 'is_approved' => true]);
            }
        }
    }
}
