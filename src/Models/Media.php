<?php

namespace StormcellTech\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    /**
     * Mass-assignable attributes
     */
    protected $fillable = [
        'disk',
        'directory',
        'filename',
        'name',
        'mime_type',
        'extension',
        'size',
        'original_path',
        'thumbnails',
        'user_id',
        'mediable_type',
        'mediable_id',
    ];

    /**
     * Cast thumbnails JSON column into array
     */
    protected $casts = [
        'thumbnails' => 'array',
    ];

    /**
     * Get the owning user.
     */
    public function user()
    {
        $userModel = config('media-upload.user_model', 'App\Models\User');
        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Get all of the owning mediable models.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /**
     * Get media URL
     *
     * @param string|null $size Thumbnail size (e.g. 300x300, 500x500)
     * @return string Fully-qualified URL
     */
    public function getUrl(?string $size = null): string
    {
        $disk = $this->disk ?? 'public';

        if ($size === null) {
            return Storage::disk($disk)->url($this->original_path);
        }

        $thumbs = is_array($this->thumbnails) ? $this->thumbnails : [];

        // Exact match (e.g. 300x300)
        if (isset($thumbs[$size])) {
            return Storage::disk($disk)->url($thumbs[$size]);
        }

        // Variant fallback keys (e.g. 300x300_webp, 300x300_jpg)
        foreach (["{$size}_webp", "{$size}_jpg", "{$size}_jpeg", "{$size}_png"] as $key) {
            if (isset($thumbs[$key])) {
                return Storage::disk($disk)->url($thumbs[$key]);
            }
        }

        // Fallback: return the first available thumbnail if any exist
        if (!empty($thumbs)) {
            $first = reset($thumbs);
            if ($first) {
                return Storage::disk($disk)->url($first);
            }
        }

        // Absolute final fallback: original image
        return Storage::disk($disk)->url($this->original_path);
    }

    /**
     * Get the path to the media file.
     *
     * @return string
     */
    public function getPath(?string $size = null): string
    {
        $disk = $this->disk ?? 'public';

        if ($size === null) {
            return Storage::disk($disk)->path($this->original_path);
        }

        $thumbs = is_array($this->thumbnails) ? $this->thumbnails : [];

        if (isset($thumbs[$size])) {
            return Storage::disk($disk)->path($thumbs[$size]);
        }

        foreach (["{$size}_webp", "{$size}_jpg", "{$size}_jpeg", "{$size}_png"] as $key) {
            if (isset($thumbs[$key])) {
                return Storage::disk($disk)->path($thumbs[$key]);
            }
        }

        return Storage::disk($disk)->path($this->original_path);
    }

    /**
     * Check if media is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if media is a document
     */
    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
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
        ]);
    }

    /**
     * Check if media is a video
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if media is audio
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Get human-readable file size
     */
    public function getReadableSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
