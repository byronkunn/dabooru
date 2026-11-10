<?php

return [
    // Enable only after public videos are transcoded and metadata is removed.
    'video_uploads_enabled' => (bool) env('UPLOAD_VIDEO_ENABLED', false),
];
