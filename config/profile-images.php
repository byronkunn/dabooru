<?php

return [
    'avatar_max_mb' => max(1, (int) env('PROFILE_AVATAR_MAX_MB', 5)),
    'banner_max_mb' => max(1, (int) env('PROFILE_BANNER_MAX_MB', 10)),
];
