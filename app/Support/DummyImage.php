<?php

namespace App\Support;

class DummyImage
{
    /**
     * Public picsum ids known not to return 404. Kept in one place so the
     * seeder can be reseeded without dead thumbnails.
     */
    public const POOL = [1015, 1027, 1005, 1018, 1035, 1043, 1059, 1062, 1067, 1074, 1080, 1084, 1036, 1040, 1047, 1052, 1076, 1069, 1041, 1039, 1011, 1016, 1020, 1025, 1030, 1038, 1044, 1049, 1053, 1057, 1061, 1065, 1071, 1078, 1082, 1089, 110, 112, 116, 119, 122, 128, 133, 136, 139, 143, 146, 149];

    public static function url(int $seed, int $w, int $h): string
    {
        return 'https://picsum.photos/id/'.self::POOL[$seed % count(self::POOL)].'/'.$w.'/'.$h.'?v='.$seed;
    }

    public static function seedSource(int $seed, int $w, int $h): string
    {
        return 'https://picsum.photos/seed/inkwell'.$seed.'/'.$w.'/'.$h;
    }
}
