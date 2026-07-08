<?php

return [
    /**
     * Storage disk configuration
     * Default: 'public' - stores files in storage/app/public
     * Options: 'public', 'local', 's3', or any custom disk
     */
    'disk' => env('MEDIA_UPLOAD_DISK', 'public'),

    /**
     * Default upload directory
     */
    'directory' => env('MEDIA_UPLOAD_DIRECTORY', 'uploads'),

    /**
     * User model class
     * Change this if you use a custom User model
     */
    'user_model' => 'App\Models\User',

    /**
     * Image processing options
     */
    'images' => [
        'max_width' => 4000,
        'max_height' => 4000,
        'thumbnails' => [
            [300, 300],
            [500, 500],
        ],
        'jpeg_quality' => 85,
        'webp_quality' => 80,
    ],

    /**
     * File size limits (in bytes)
     */
    'file_size_limits' => [
        'image' => 50 * 1024 * 1024,        // 50 MB
        'document' => 100 * 1024 * 1024,    // 100 MB
        'archive' => 500 * 1024 * 1024,     // 500 MB
        'audio' => 200 * 1024 * 1024,       // 200 MB
        'video' => 1024 * 1024 * 1024,      // 1 GB
        'default' => 50 * 1024 * 1024,      // 50 MB
    ],

    /**
     * Allowed MIME types
     */
    'allowed_mimes' => [
        // Images
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/svg+xml',
        'image/gif',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip',
        // Audio
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'audio/aac',
        'audio/flac',
        // Video
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-msvideo',
    ],


    /**
     * Delete related files when media is deleted
     */
    'cascade_delete' => true,

    /**
     * Enable automatic thumbnail generation
     */
    'auto_thumbnails' => true,
];
