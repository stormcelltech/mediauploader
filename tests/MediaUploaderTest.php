<?php

namespace StormcellTech\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use enshrined\svgSanitize\Sanitizer;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use StormcellTech\MediaUploader;
use StormcellTech\Models\Media;

class MediaUploaderTest extends TestCase
{
    private ImageManager $imageManager;
    private Sanitizer $sanitizer;
    private MediaUploader $uploader;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->imageManager = new ImageManager(new GdDriver());
        $this->sanitizer = new Sanitizer();
        $this->uploader = new MediaUploader($this->imageManager, $this->sanitizer);
    }

    public function test_it_can_upload_a_common_raster_image_and_generate_thumbnails(): void
    {
        $file = UploadedFile::fake()->image('avatar.png', 100, 100);

        $media = $this->uploader->store($file, 'public', 'uploads');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertEquals('images', $media->category);
        $this->assertEquals('png', $media->extension);

        Storage::disk('public')->assertExists($media->original_path);

        $this->assertArrayHasKey('300x300_jpg', $media->thumbnails);
        $this->assertArrayHasKey('300x300_webp', $media->thumbnails);
        $this->assertArrayHasKey('500x500_jpg', $media->thumbnails);
        $this->assertArrayHasKey('500x500_webp', $media->thumbnails);

        foreach ($media->thumbnails as $thumbPath) {
            Storage::disk('public')->assertExists($thumbPath);
        }
    }

    public function test_it_sanitizes_and_stores_svg_files_safely_without_making_thumbnails(): void
    {
        $rawSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><script>alert("xss")</script><circle cx="50" cy="50" r="40"/></svg>';
        $file = UploadedFile::fake()->createWithContent('vector.svg', $rawSvg)->mimeType('image/svg+xml');

        $media = $this->uploader->store($file, 'public', 'uploads');

        $this->assertEquals('svg', $media->extension);
        $this->assertEmpty($media->thumbnails);
        Storage::disk('public')->assertExists($media->original_path);

        $savedContent = Storage::disk('public')->get($media->original_path);
        $this->assertStringNotContainsString('<script>', $savedContent);
    }

    public function test_it_can_store_a_document_without_running_image_engine_logic(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $media = $this->uploader->store($file, 'public', 'documents');

        $this->assertEquals('documents', $media->category);
        $this->assertEquals('pdf', $media->extension);
        $this->assertEmpty($media->thumbnails);
        Storage::disk('public')->assertExists($media->original_path);
    }

    public function test_it_reconciles_limits_and_throws_an_exception_if_file_exceeds_max_file_limits(): void
    {
        $oversizedFile = UploadedFile::fake()->create('massive_photo.jpg', 51 * 1024, 'image/jpeg');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds limit');

        $this->uploader->store($oversizedFile);
    }

    public function test_it_rejects_forbidden_file_types_completely(): void
    {
        $file = UploadedFile::fake()->create('malicious.exe', 10, 'application/x-msdownload');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("File type 'application/x-msdownload' is not allowed.");

        $this->uploader->store($file);
    }

    public function test_it_can_delete_media_assets_and_clean_up_associated_thumbnail_records(): void
    {
        $file = UploadedFile::fake()->image('gallery.jpg', 100, 100);
        $media = $this->uploader->store($file, 'public', 'uploads');

        Storage::disk('public')->assertExists($media->original_path);

        $this->uploader->deleteMedia($media->original_path, $media->thumbnails, 'public');


        Storage::disk('public')->assertMissing($media->original_path);
        foreach ($media->thumbnails as $thumbPath) {
            Storage::disk('public')->assertMissing($thumbPath);
        }
    }
}
