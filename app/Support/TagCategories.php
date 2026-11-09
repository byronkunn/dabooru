<?php

namespace App\Support;

class TagCategories
{
    public static function forName(string $name): string
    {
        $groups = [
            'content warnings' => ['nudity', 'sexual content', 'flashing imagery'],
            'relationships' => ['romance', 'couple', 'embrace', 'romantic scene'],
            'anatomy' => ['hands', 'bare feet', 'bare shoulders', 'brown skin', 'pale skin tones', 'warm skin tones', 'skin tones', 'greenish skin tones'],
            'position' => ['seated', 'standing', 'kneeling', 'crouching', 'lying down', 'folded arms', 'crossed arms', 'raised arms', 'raised hand', 'arm raised', 'hand raised', 'hands raised', 'hands on floor', 'hand on knee', 'hand at side', 'hand at waist', 'hand near waist', 'hands near face', 'hand near face', 'hand near head', 'hand near chest', 'hand on hat', 'head tilt', 'tilted head', 'looking back', 'looking forward', 'looking upward', 'looking at viewer', 'looking at camera', 'looking aside', 'looking at each other', 'side glance', 'hand gesture', 'peace sign', 'wave', 'riding', 'horseback', 'dance', 'hand reaching', 'holding flower', 'holding food', 'holding object', 'relaxed pose', 'playful pose', 'frontal pose', 'frontal view', 'front view', 'rear view', 'side pose', 'side view', 'side profile', 'profile', 'three-quarter view', 'pose', 'character pose', 'dramatic pose', 'posed', 'posed portrait', 'posed figure'],
            'medium' => ['digital painting', 'watercolor', 'photography', 'portrait photography', 'cosplay photography', 'outdoor photography', 'indoor photography', 'object photography', 'model photography', 'video', 'animation', 'illustration', 'line drawing', 'sketch', 'three-dimensional', 'character render', 'character sculpture', 'statue', 'figurine', 'painted figure', 'animated characters', 'character animation', 'scene animation', 'figure animation', 'portrait video', 'scene video', 'video test', 'test video', 'video game scene'],
            'style' => ['anime style', 'cartoon style', 'chibi style', 'pixel art', 'digital art', 'painterly', 'realistic', 'realistic rendering', 'realistic rendering', 'stylized', 'flat colors', 'monochrome', 'grayscale', 'loose sketch', 'angular style', 'cartoon shading', 'soft shading', 'flat shading', 'sharp shading', 'textured shading', 'line art', 'black outlines', 'thin lines', 'delicate lines', 'minimal shading', 'subtle shading', 'strong shading'],
            'character' => ['original character', 'loki', 'cloud strife', 'tifa lockhart', 'aerith', 'cell', 'hercules', 'megara', 'superhero', 'magician', 'witch', 'spellcaster', 'cowboy', 'animal character', 'monster', 'male character'],
            'series' => ['marvel', 'final fantasy', 'dragon ball', 'street fighter', 'disney', 'adventure time'],
            'themes' => ['fan art', 'cosplay', 'artistic nudity', 'halloween', 'summer theme', 'western', 'holiday theme', 'dark fantasy', 'magic', 'technology theme', 'elemental theme', 'cartoon crossover', 'memorial', 'celebratory setting', 'cheerful atmosphere', 'cheerful expression', 'playful expression', 'thoughtful expression', 'stern expression', 'intense expression', 'expressive face', 'expressive faces', 'facial expression', 'smile', 'blush', 'winking'],
            'composition' => ['portrait', 'group portrait', 'group scene', 'group', 'two characters', 'three characters', 'solo', 'solo person', 'solo portraits', 'full body', 'upper body', 'close-up', 'cropped portrait', 'cropped face', 'shoulder view', 'low angle', 'high angle', 'wide composition', 'vertical composition', 'horizontal composition', 'diagonal composition', 'symmetrical composition', 'symmetrical pose', 'tilted composition', 'cinematic composition', 'framed composition', 'layered composition', 'layered portrait', 'overlapping figures', 'character lineup', 'ensemble', 'scale contrast', 'collage', 'split background', 'white border', 'high contrast', 'dramatic lighting', 'cinematic lighting', 'soft lighting', 'warm lighting', 'cool lighting', 'natural lighting', 'bright lighting', 'dim lighting', 'backlight', 'sunlight', 'window light', 'glowing light', 'bokeh', 'shallow depth of field', 'background blur', 'blurred background', 'blurred detail', 'gray shadows', 'black shadows', 'shadow', 'dark outlines', 'color artwork', 'color animation', 'color photography', 'color swatches', 'test pattern', 'color bars', 'typography', 'text', 'title text', 'text overlay', 'english text', 'japanese text', 'captioned artwork', 'graphic design', 'graphic layout', 'book cover', 'cover design', 'publication art', 'magazine style'],
            'setting' => ['interior', 'outdoors', 'outdoor setting', 'outdoor scene', 'indoor scene', 'room background', 'domestic scene', 'domestic setting', 'domestic objects', 'background objects', 'background characters', 'background foliage', 'environment', 'scene', 'street', 'town', 'western town', 'saloon', 'bar', 'graveyard', 'balcony', 'hospital room', 'medical setting', 'park', 'display room', 'interior exterior boundary', 'sky', 'blue sky', 'yellow sky', 'clouds', 'ocean', 'water', 'trees', 'greenery', 'grass', 'leaves', 'plant', 'flowers', 'white flowers', 'pink flowers', 'purple flowers', 'yellow flowers', 'cherry blossoms', 'forest colors', 'rocky landscape', 'wooden floor', 'wooden wall', 'wooden furniture', 'wooden buildings', 'buildings', 'bark', 'log', 'wood', 'earth', 'tombstone', 'stone', 'gray stone', 'ladder', 'shelves', 'shelf', 'wall corner', 'wall decoration', 'railing', 'window', 'door frame', 'white door', 'bed', 'table', 'pedestal', 'display base', 'toy collection', 'plush toys', 'shelf display', 'background greenery', 'night sky', 'stars', 'daylight', 'afternoon scene', 'horizon', 'landscape background', 'dark background', 'simple background', 'pale background', 'light background', 'white background', 'gray background', 'gray wall', 'white wall', 'white walls', 'natural background', 'green background', 'blue background', 'yellow background', 'red background', 'pink background', 'purple background', 'lavender background', 'brown background', 'cream background', 'teal background', 'neutral background', 'decorative background', 'floral background', 'ornamental background', 'glowing background', 'detailed background', 'colorful background', 'space-like background', 'sparkling background', 'sky background', 'simple sky background', 'purple clouds'],
        ];
        foreach ($groups as $category => $names) {
            if (in_array($name, $names, true)) {
                return $category;
            }
        }
        if (preg_match('/\b(shirt|shirts|top|dress|skirt|shorts|trousers|jacket|jackets|coat|hoodie|hood|hat|cap|beret|headwear|clothing|outfit|costume|uniform|collar|collared|tie|necktie|neckties|vest|ribbon|bow|gloves|glove|boots|sneakers|shoes|tights|choker|belt|straps|sleeves|sleeveless|swimwear|lingerie|armor|cape|fabric|frills|frilled|cuffs|maid|pajamas|leather|goggles|glasses|sunglasses|headset|neck accessory|hair accessory|hair accessories|hair ornament|hair ornaments|hair ties|hair ties|wristbands|wrist accessory|bracelets|jewelry|ornament|headwear|mask)\b/', $name)) {
            return 'clothing';
        }
        if (preg_match('/\b(hair|hairstyle|ponytail|ponytails|braids|bangs|eyes|eye|pupils|ears|horns|tail|fur|nose|face|mouth|teeth|eyebrows|hairline|curls|wings)\b/', $name)) {
            return 'appearance';
        }
        if (preg_match('/\b(palette|colors|accent|lighting|composition|silhouette|texture|shading|outlines|stripes|shapes|pattern|speckles|border|frame)\b/', $name)) {
            return 'composition';
        }

        return 'general';
    }
}
