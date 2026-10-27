<?php

return [
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'compression_timeout' => (int) env('VIDEO_COMPRESSION_TIMEOUT', 1800),
];
