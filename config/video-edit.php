<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Video Edits module (spec 001-video-edit)
|--------------------------------------------------------------------------
|
| Every limit and default the module enforces. Decision references point at
| specs/001-video-edit/clarify.md (D = resolved by default, Q/P = user).
|
*/

return [

    'limits' => [
        // Minimum clips per mode lives in VideoEditMode::minimumSources().
        'max_sources' => 10,                                   // D2
        'max_file_bytes' => 2 * 1024 * 1024 * 1024,            // D2 — 2 GB
        'size_tolerance_ratio' => 0.01,                        // declared vs stored size
        'max_total_duration_seconds' => 90 * 60,               // D2
        'max_manual_ranges' => 500,                            // D9
        'max_range_note_length' => 120,
        'allowed_extensions' => ['mp4', 'mov', 'webm', 'mkv'],
        'allowed_mime_types' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska'],
        // FFprobe `format_name` tokens accepted after upload (OWASP §8 content check).
        'allowed_containers' => ['mov', 'mp4', 'webm', 'matroska'],
    ],

    'silence' => [
        'default_threshold_seconds' => 1.0,                    // US-2
        'min_threshold_seconds' => 0.3,                        // D4
        'max_threshold_seconds' => 10.0,                       // D4
        'noise_floor_db' => -30,                               // D4 — system setting
        'padding_ms' => 150,                                   // D5
    ],

    'cuts' => [
        'min_kept_fragment_ms' => 250,                         // D6
        'min_output_ms' => 1000,                               // D6
    ],

    'output' => [
        'max_width' => 1920,                                   // D3
        'max_height' => 1080,                                  // D3
        'max_frame_rate' => 60,                                // D3
        'video_codec' => 'libx264',
        'pixel_format' => 'yuv420p',
        'crf' => 20,
        'preset' => 'medium',
        'intermediate_crf' => 14,                              // AD-4
        'intermediate_preset' => 'veryfast',                   // AD-4
        'audio_codec' => 'aac',
        'audio_bitrate_kbps' => 192,
        'audio_sample_rate' => 48000,                          // D3
        'audio_channels' => 2,                                 // D3
    ],

    'queue' => [
        // Queue CONNECTION name from config/queue.php; phpunit.xml overrides it with `sync`.
        'connection' => env('VIDEO_EDIT_QUEUE_CONNECTION', 'video-edits'),  // P3 / AD-15
        'name' => env('VIDEO_EDIT_QUEUE', 'video-edits'),
    ],

    'retention' => [
        'failed_sources_hours' => 24,                          // Q3c / FR-10
        'draft_hours' => 24,                                   // D17
        'stale_processing_minutes' => 65,                      // AD-14
    ],

    'urls' => [
        'upload_ttl_minutes' => 60,                            // AD-1
        'download_ttl_minutes' => 15,                          // D12
    ],

    'storage' => [
        // Object keys live under this prefix on the R2 disk behind StoragePort.
        'path_prefix' => 'video-edits',
    ],

    'workspace' => [
        'disk' => 'video-edit-workspace',                      // AD-12
        // Free space required before download, as a multiple of the total source size (R5).
        'free_space_multiplier' => 3,
    ],

    'progress' => [
        'min_percent_step' => 2,
        'min_interval_seconds' => 5,
    ],

];
