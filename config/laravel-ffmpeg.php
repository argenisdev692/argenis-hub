<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),

        // false lets FFmpeg pick the thread count from the available cores.
        'threads' => env('FFMPEG_THREADS', false),
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
    ],

    // Hard ceiling for ONE ffmpeg process (spec 001 D13). A 40-minute merge
    // re-encodes every kept frame in one filtergraph pass, so this has to cover
    // the slowest render, not the typical one — 3600 s was reached at 59 % on a
    // 40-minute source. Stays below the ProcessVideoEditJob timeout (14400 s),
    // which is the outer envelope for the whole pipeline.
    'timeout' => (int) env('FFMPEG_TIMEOUT', 10_800),

    'log_channel' => env('LOG_CHANNEL', 'stack'),   // set to false to completely disable logging

    'temporary_files_root' => env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir()),

    'temporary_files_encrypted_hls' => env('FFMPEG_TEMPORARY_ENCRYPTED_HLS', env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir())),
];
