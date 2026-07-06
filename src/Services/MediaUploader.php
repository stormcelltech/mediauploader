<?php

namespace StormcellTech\MediaUploader\Services;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use StormcellTech\MediaUploader\Models\Media;

class MediaUploader
{
    // File type categories
    private const IMAGE_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/svg+xml',
        'image/gif',
    ];

    private const DOCUMENT_TYPES = [
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
    ];

    private const ARCHIVE_TYPES = [
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip',
    ];

    private const AUDIO_TYPES = [
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'audio/aac',
        'audio/flac',
    ];

    private const VIDEO_TYPES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-msvideo',
    ];

    // Define all allowed types
    private const ALLOWED = [
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
    ];

    private const MAX_WIDTH  = 4000;
    private const MAX_HEIGHT = 4000;

    private const THUMB_SIZES = [
        [300, 300],
        [500, 500],
    ];

    // File size limits (in bytes)
    private const FILE_SIZE_LIMITS = [
        'image'    => 50 * 1024 * 1024,      // 50 MB
        'document' => 100 * 1024 * 1024,     // 100 MB
        'archive'  => 500 * 1024 * 1024,     // 500 MB
        'audio'    => 200 * 1024 * 1024,     // 200 MB
        'video'    => 1024 * 1024 * 1024,    // 1 GB
        'default'  => 50 * 1024 * 1024,      // 50 MB
    ];

    private ImageManager $images;
    private Sanitizer $svg;

    public function __construct(
        ?ImageManager $images = null,
        ?Sanitizer $svg = null,
    ) {
        $this->images = $images ?? new ImageManager(new GdDriver());
        $this->svg = $svg ?? new Sanitizer();
    }

    /**
     * Store an uploaded file
     * 
     * @throws \RuntimeException
     */
    public function store(
        UploadedFile $file,
        string $disk = 'public',
        string $dir = 'uploads',
        ?int $userId = null,
        ?string $mediableType = null,
        ?int $mediableId = null
    ): Media {
        try {
            $mime = $file->getMimeType();

            // Validate MIME type
            if (!in_array($mime, self::ALLOWED, true)) {
                throw new \RuntimeException("File type '{$mime}' is not allowed.");
            }

            // Validate file size
            $this->validateFileSize($file, $mime);

            // Determine file category and extension
            $category = $this->getFileCategory($mime);
            $ext = $this->getExtension($mime);

            // Generate unique filename ONCE
            $uuid = Str::uuid()->toString();
            $filename = $uuid . '.' . $ext;

            // Original name for display
            $originalName = Str::slug(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                '_'
            ) . '.' . $ext;

            // Prepare directory
            $fileDir = trim($dir, '/') . '/' . $category;
            $fullPath = $fileDir . '/' . $filename;

            $thumbs = [];

            // Process based on file type
            if ($this->isImage($mime)) {
                $thumbs = $this->processImage($file, $mime, $disk, $fileDir, $filename);
            } else {
                // For non-image files, store directly
                $this->storeFile($file, $fullPath, $disk);
            }

            // Create media record in database
            $media = Media::create([
                'disk'          => $disk,
                'directory'     => $dir,
                'category'      => $category,
                'name'          => $originalName,
                'filename'      => $filename,
                'mime_type'     => $mime,
                'extension'     => $ext,
                'size'          => $file->getSize(),
                'original_path' => $fullPath,
                'thumbnails'    => $thumbs,
                'user_id'       => $userId ?? Auth::id(),
            ]);

            // Associate with mediable if provided
            if ($mediableType && $mediableId) {
                $media->mediable_type = $mediableType;
                $media->mediable_id = $mediableId;
                $media->save();
            }

            Log::info("File uploaded successfully", [
                'media_id' => $media->id,
                'filename' => $filename,
                'path' => $fullPath,
                'size' => $file->getSize(),
            ]);

            return $media;
        } catch (\Exception $e) {
            Log::error("File upload failed: " . $e->getMessage(), [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            throw $e;
        }
    }

    /**
     * Store file to disk
     */
    private function storeFile(UploadedFile $file, string $path, string $disk): void
    {
        try {
            // Ensure directory exists
            $dir = dirname($path);
            $this->ensureDirectoryExists($dir, $disk);

            // Get file content
            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                throw new \RuntimeException("Failed to read uploaded file");
            }

            // Write file
            if (!Storage::disk($disk)->put($path, $content)) {
                throw new \RuntimeException("Failed to store file at path: $path on disk: $disk");
            }

            Log::debug("File stored successfully", [
                'disk' => $disk,
                'path' => $path,
                'size' => strlen($content),
            ]);
        } catch (\Exception $e) {
            Log::error("File storage failed", [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Ensure directory exists on disk
     */
    private function ensureDirectoryExists(string $dir, string $disk): void
    {
        // For local disks, create directory directly in filesystem
        if (in_array($disk, ['local', 'public'])) {
            $basePath = Storage::disk($disk)->path('');
            $fullPath = $basePath . '/' . trim($dir, '/');

            if (!is_dir($fullPath)) {
                @mkdir($fullPath, 0755, true);

                if (!is_dir($fullPath)) {
                    Log::warning("Failed to create directory", [
                        'path' => $fullPath,
                        'disk' => $disk,
                    ]);
                }
            }
        }
    }

    /**
     * Process image files (resize, generate thumbnails)
     */
    private function processImage(UploadedFile $file, string $mime, string $disk, string $fileDir, string $filename): array
    {
        try {
            $realPath = $file->getRealPath();
            $thumbs = [];

            // Handle SVG separately
            if ($mime === 'image/svg+xml') {
                return $this->processSvg($file, $disk, $fileDir, $filename);
            }

            // Store the main image file
            $this->storeFile($file, $fileDir . '/' . $filename, $disk);

            // Handle GIF without processing
            if ($mime === 'image/gif') {
                return $this->makeThumbnails($realPath, $disk, $fileDir, Str::beforeLast($filename, '.'));
            }

            // Process raster images (PNG, JPEG, WebP)
            $thumbs = $this->processRasterImage($file, $mime, $disk, $fileDir, $filename);

            return $thumbs;
        } catch (\Exception $e) {
            Log::error("Image processing failed: " . $e->getMessage(), [
                'filename' => $filename,
                'mime_type' => $mime,
            ]);
            throw new \RuntimeException("Failed to process image: " . $e->getMessage());
        }
    }

    /**
     * Process raster images (PNG, JPEG, WebP)
     */
    private function processRasterImage(UploadedFile $file, string $mime, string $disk, string $fileDir, string $filename): array
    {
        try {
            $realPath = $file->getRealPath();

            /** @var ImageInterface $img */
            $img = $this->images->decodePath($realPath);

            // Resize if too large
            if ($img->width() > self::MAX_WIDTH || $img->height() > self::MAX_HEIGHT) {
                $img->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);
            }

            // Determine format
            $format = match ($mime) {
                'image/png'  => Format::PNG,
                'image/jpeg' => Format::JPEG,
                'image/webp' => Format::WEBP,
                default      => Format::JPEG,
            };

            $quality = $mime === 'image/png' ? null : 85;
            $encoded = $quality
                ? $img->encodeUsingFormat($format, quality: $quality)
                : $img->encodeUsingFormat($format);

            // Store processed image
            $storagePath = $fileDir . '/' . $filename;
            if (!Storage::disk($disk)->put($storagePath, (string) $encoded, ['ContentType' => $mime])) {
                throw new \RuntimeException("Failed to store processed image");
            }

            // Generate thumbnails
            $baseFilename = Str::beforeLast($filename, '.');
            $thumbs = $this->makeThumbnails($realPath, $disk, $fileDir, $baseFilename);

            return $thumbs;
        } catch (\Exception $e) {
            Log::error("Raster image processing failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process SVG files (sanitize)
     */
    private function processSvg(UploadedFile $file, string $disk, string $fileDir, string $filename): array
    {
        try {
            $dirty = file_get_contents($file->getRealPath());

            if ($dirty === false) {
                throw new \RuntimeException("Failed to read SVG file");
            }

            $clean = $this->svg->sanitize($dirty);

            if ($clean === false) {
                throw new \RuntimeException('Invalid or unsafe SVG content detected');
            }

            // Remove data URIs and foreignObject elements
            $clean = preg_replace('#(?i)\s+xlink:href\s*=\s*"data:[^"]+"#', '', $clean);
            $clean = preg_replace('#(?is)<\s*foreignObject.*?</\s*foreignObject\s*>#', '', $clean);

            $path = $fileDir . '/' . $filename;

            if (!Storage::disk($disk)->put($path, $clean, ['ContentType' => 'image/svg+xml'])) {
                throw new \RuntimeException("Failed to store SVG file");
            }

            return [];
        } catch (\Exception $e) {
            Log::error("SVG processing failed: " . $e->getMessage());
            throw new \RuntimeException("Failed to process SVG: " . $e->getMessage());
        }
    }

    /**
     * Generate thumbnails for images
     */
    private function makeThumbnails(string $realPath, string $disk, string $fileDir, string $baseFilename): array
    {
        $thumbDir = $fileDir . '/thumbnails';
        $paths = [];

        try {
            foreach (self::THUMB_SIZES as [$w, $h]) {
                /** @var ImageInterface $thumb */
                $thumb = $this->images->decodePath($realPath)->cover($w, $h, 'center');

                // JPG thumbnail
                $jpgName = $baseFilename . "-{$w}x{$h}.jpg";
                $jpgPath = $thumbDir . '/' . $jpgName;

                $jpgContent = (string) $thumb->encodeUsingFormat(Format::JPEG, quality: 85);
                if (!Storage::disk($disk)->put($jpgPath, $jpgContent, ['ContentType' => 'image/jpeg'])) {
                    Log::warning("Failed to store JPG thumbnail", ['path' => $jpgPath]);
                    continue;
                }
                $paths["{$w}x{$h}_jpg"] = $jpgPath;

                // WebP thumbnail
                $webpName = $baseFilename . "-{$w}x{$h}.webp";
                $webpPath = $thumbDir . '/' . $webpName;

                $webpContent = (string) $thumb->encodeUsingFormat(Format::WEBP, quality: 80);
                if (!Storage::disk($disk)->put($webpPath, $webpContent, ['ContentType' => 'image/webp'])) {
                    Log::warning("Failed to store WebP thumbnail", ['path' => $webpPath]);
                    continue;
                }
                $paths["{$w}x{$h}_webp"] = $webpPath;
            }
        } catch (\Exception $e) {
            Log::error("Thumbnail generation failed: " . $e->getMessage());
            // Don't fail completely if thumbnails fail, return what we have
        }

        return $paths;
    }

    /**
     * Delete media and all associated thumbnails
     */
    public function deleteMedia(string $file, array $thumbnails = [], string $disk = 'public'): void
    {
        try {
            // Delete main file
            if (Storage::disk($disk)->exists($file)) {
                Storage::disk($disk)->delete($file);
                Log::info("Deleted file: $file");
            }

            // Delete thumbnails
            foreach ($thumbnails as $thumb) {
                if (Storage::disk($disk)->exists($thumb)) {
                    Storage::disk($disk)->delete($thumb);
                    Log::info("Deleted thumbnail: $thumb");
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete media: " . $e->getMessage(), [
                'file' => $file,
            ]);
            throw new \RuntimeException("Failed to delete media files: " . $e->getMessage());
        }
    }

    /**
     * Get file category based on MIME type
     */
    private function getFileCategory(string $mime): string
    {
        return match (true) {
            $this->isImage($mime) => 'images',
            $this->isDocument($mime) => 'documents',
            $this->isArchive($mime) => 'archives',
            $this->isAudio($mime) => 'audio',
            $this->isVideo($mime) => 'videos',
            default => 'files',
        };
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
            'application/vnd.oasis.opendocument.presentation' => 'odp',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/gzip' => 'gz',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            'audio/flac' => 'flac',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            default => 'bin',
        };
    }

    /**
     * Check if MIME type is an image
     */
    private function isImage(string $mime): bool
    {
        return in_array($mime, self::IMAGE_TYPES, true);
    }

    /**
     * Check if MIME type is a document
     */
    private function isDocument(string $mime): bool
    {
        return in_array($mime, self::DOCUMENT_TYPES, true);
    }

    /**
     * Check if MIME type is an archive
     */
    private function isArchive(string $mime): bool
    {
        return in_array($mime, self::ARCHIVE_TYPES, true);
    }

    /**
     * Check if MIME type is audio
     */
    private function isAudio(string $mime): bool
    {
        return in_array($mime, self::AUDIO_TYPES, true);
    }

    /**
     * Check if MIME type is video
     */
    private function isVideo(string $mime): bool
    {
        return in_array($mime, self::VIDEO_TYPES, true);
    }

    /**
     * Validate file size based on file category
     */
    private function validateFileSize(UploadedFile $file, string $mime): void
    {
        $category = $this->getFileCategory($mime);
        $limit = self::FILE_SIZE_LIMITS[$category] ?? self::FILE_SIZE_LIMITS['default'];
        $size = $file->getSize();

        if ($size > $limit) {
            $limitMB = round($limit / (1024 * 1024), 2);
            $sizeMB = round($size / (1024 * 1024), 2);
            throw new \RuntimeException(
                "File size ({$sizeMB} MB) exceeds limit of {$limitMB} MB for {$category} files."
            );
        }
    }

    /**
     * Get allowed MIME types
     */
    public static function getAllowedMimeTypes(): array
    {
        return self::ALLOWED;
    }

    /**
     * Get allowed MIME types by category
     */
    public static function getMimeTypesByCategory(string $category): array
    {
        return match ($category) {
            'image' => self::IMAGE_TYPES,
            'document' => self::DOCUMENT_TYPES,
            'archive' => self::ARCHIVE_TYPES,
            'audio' => self::AUDIO_TYPES,
            'video' => self::VIDEO_TYPES,
            default => [],
        };
    }

    /**
     * Check if file exists on disk
     */
    public function fileExists(string $path, string $disk = 'public'): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $path, string $disk = 'public'): string
    {
        return Storage::disk($disk)->url($path);
    }
}
