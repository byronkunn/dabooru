<?php

return [
    'ffmpeg' => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
    'preset' => env('VIDEO_TRANSCODE_PRESET', 'veryfast'),
    'max_duration_seconds' => (int) env('VIDEO_MAX_DURATION_SECONDS', 1800),
];
