# Laravel MediaUploader

A complete media management solution for Laravel that provides a beautiful drag-and-drop uploader, media library, automatic thumbnail generation, image optimization, and a dependency-free JavaScript frontend.

MediaUploader is designed to integrate seamlessly into Laravel applications while remaining flexible enough to support custom storage disks, multi-tenancy, and custom upload workflows.

---

## Features

- 📁 Media Library
- 🖼 Automatic thumbnail generation
- 🚀 Dependency-free JavaScript uploader
- 📤 Drag & Drop uploads
- 🔍 Media search and pagination
- 🎨 Blade components
- ☁️ Supports Local, Public and S3 storage
- 👤 User ownership support
- 🏢 Multi-tenant ready
- 🔒 Authentication & CSRF protection
- ⚡ Automatic image optimization
- 📄 Supports images, documents, audio, video and archives
- 🔧 Fully customizable

---

# Requirements

- PHP 8.2+
- Laravel 11+
- Node.js 20+
- npm

---

# Installation

MediaUploader consists of two packages:

- **Laravel backend package**
- **Vanilla JavaScript uploader**

Both packages are required.

---

## 1. Install the Laravel Package

```bash
composer require stormcelltech/mediauploader
```

---

## 2. Install the JavaScript Uploader

Install the frontend uploader.

```bash
npm install stormcelltech-fileuploader
```

Import it once inside your application's JavaScript.

```javascript
// resources/js/app.js

import "stormcelltech-fileuploader";
```

Compile your assets.

```bash
npm run dev
```

or

```bash
npm run build
```

> **Important**
>
> The uploader interface will not function until the JavaScript package has been installed and imported.

---

## 3. Publish Package Resources

Run the installer.

```bash
php artisan mediauploader:install
```

The installer publishes:

```
config/media-upload.php

database/migrations/xxxx_xx_xx_create_media_table.php

resources/views/components/uploader.blade.php

resources/views/components/gallery.blade.php
```

Run the migrations.

```bash
php artisan migrate
```

---

# Quick Start

After installing both packages, you can immediately use the uploader inside any Blade view.

```blade
<x-media-upload::uploader
    id="logo-uploader"
    name="logo_id"
    text="Upload Logo"
/>
```

The component automatically renders the uploader and synchronizes the selected Media ID with a hidden input.

No additional JavaScript is required beyond importing the uploader package.

---

# Next Steps

The following sections explain:

- Blade Components
- JavaScript Uploader
- Configuration
- Routes
- Controller Integration
- API Resources
- Media Collections
- Storage Configuration
- Custom Upload Endpoints
- MediaUploader Service

# Blade Components

MediaUploader ships with reusable Blade components that automatically render the JavaScript uploader and hidden input fields.

No additional HTML is required.

---

# Single Upload

Use the uploader to store a single media ID.

```blade
<x-media-upload::uploader
    id="logo-uploader"
    name="logo_id"
    :value="$settings->logo_id"
    text="Upload Logo"
/>
```

When submitted, Laravel receives:

```php
[
    "logo_id" => 15
]
```

---

# Multiple Uploads

Store multiple media IDs.

```blade
<x-media-upload::uploader
    id="gallery-uploader"
    name="gallery"
    type="multiple"
    text="Upload Gallery"
 />
```

Generated inputs:

```html
<input type="hidden" name="gallery[]" value="5" />
<input type="hidden" name="gallery[]" value="18" />
<input type="hidden" name="gallery[]" value="42" />
```

Laravel receives

```php
[
    "gallery" => [
        5,
        18,
        42
    ]
]
```

---

# Preloading Existing Media

Pass an existing media ID using the `value` property.

```blade
<x-media-upload::uploader
    id="avatar"
    name="avatar_id"
    :value="$user->avatar_id"
/>
```

The uploader automatically loads the media from the server and displays the preview.

---

# Upload Without Preview

```blade
<x-media-upload::uploader
    id="document"
    name="document_id"
    preview="false"
/>
```

---

# Hide the Media Library

Allow users to upload files without browsing existing media.

```blade
<x-media-upload::uploader
    id="avatar"
    name="avatar_id"
    hideMediaTab="true"
/>
```

---

# Custom Upload Button Text

```blade
<x-media-upload::uploader
    id="cover"
    name="cover_id"
    text="Choose Cover Image"
/>
```

---

# Gallery Component

The package also includes a gallery component.

```blade
<x-media-upload::gallery />
```

The gallery automatically:

- Lists uploaded media
- Supports pagination
- Supports searching
- Allows selecting media
- Allows deleting media

---

# Component Properties

| Property     | Type              | Default     | Description          |
| ------------ | ----------------- | ----------- | -------------------- |
| id           | string            | Required    | Unique uploader ID   |
| name         | string            | Required    | Hidden input name    |
| value        | int/array         | null        | Existing Media ID(s) |
| type         | single / multiple | single      | Upload mode          |
| text         | string            | Select File | Upload button text   |
| preview      | bool              | true        | Show preview         |
| hideMediaTab | bool              | false       | Hide media library   |

---

# Generated HTML

The component generates HTML similar to:

```html
<div
  id="logo-uploader"
  class="uploader"
  data-fileinputname="logo_id"
  data-uploadtext="Upload Logo"
  data-uploadtype="single"
  data-preview="true"
  data-hasfile="15"
></div>
```

The JavaScript uploader reads these data attributes automatically.

---

# Hidden Inputs

MediaUploader stores **Media IDs**, not file paths.

Example

```html
<input type="hidden" name="logo_id" value="15" />
```

For multiple uploads

```html
<input type="hidden" name="gallery[]" value="3" />
<input type="hidden" name="gallery[]" value="9" />
<input type="hidden" name="gallery[]" value="12" />
```

This allows your controllers to simply save the media IDs in your database.

---

# Example Form

```blade
<form action="{{ route('products.store') }}" method="POST">

    @csrf

    <x-media-upload::uploader
        id="featured-image"
        name="featured_image_id"
        text="Featured Image"
    />

    <x-media-upload::uploader
        id="gallery"
        name="gallery"
        type="multiple"
        text="Product Gallery"
    />

    <button
        type="submit"
        class="btn btn-primary">
        Save Product
    </button>

</form>
```

When submitted, Laravel receives:

```php
[
    "featured_image_id" => 18,

    "gallery" => [
        12,
        24,
        31
    ]
]
```

Your application only stores Media IDs, while the package manages the underlying files automatically.

# Controller Integration

MediaUploader gives you complete control over how media is uploaded, retrieved, searched, and deleted. The package provides the `MediaUploader` service, allowing you to integrate it into your own controllers.

---

# Routes

A typical route definition looks like this.

```php
use App\Http\Controllers\MediaController;

Route::prefix('media')
    ->middleware(['auth'])
    ->group(function () {

        // Upload media
        Route::post('/upload', [MediaController::class, 'upload']);

        // List media
        Route::get('/list', [MediaController::class, 'GetImagesJson']);

        // Search media
        Route::get('/search/{keyword}', [MediaController::class, 'search']);

        // Retrieve a single media item
        Route::get('/{media}/get', [MediaController::class, 'getById']);

        // Update media details
        Route::put('/{medium}', [MediaController::class, 'update']);

        // Delete media
        Route::delete('/{medium}/delete', [MediaController::class, 'destroy']);
    });
```

---

# Upload Controller

Inject the `MediaUploader` service into your controller.

```php
use App\Http\Resources\Media\MediaResource;
use StormcellTech\MediaUploader;


public function upload(Request $request, MediaUploader $uploader)
{
    $validator = Validator::make($request->all(), [
        'file' => 'required|file|mimes:jpeg,png,webp,gif,svg|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 400,
            'message' => $validator->errors()->first(),
        ], Response::HTTP_BAD_REQUEST);
    }

    $directory = "/uploads/media";

    $media = $uploader->store(
        $request->file('file'),
       'public' // which disk are you saving the files
        $directory,
        auth()->id() // user uploading file
    );

    return response()->json([
        'success' => true,
        'data' => new MediaResource($media),
    ]);
}
```

---

# List Media

Return a paginated collection.

```php
public function GetImagesJson(Request $request)
{
    $media = Media::when($request->search, function ($query) use ($request) {
            $query->where('name', 'like', "%{$request->search}%");
        })
        ->latest()
        ->paginate(100);

    return response()->json([
        'status' => 200,
        'message' => 'successful',
        'data' => new MediaCollection($media),
    ]);
}
```

---

# Search Media

```php
public function search(Request $request, string $keyword)
{
    $media = Media::where('user_id', auth()->id())
        ->where('name', 'like', "%{$keyword}%")
        ->latest()
        ->paginate(100);

    return response()->json([
        'status' => 200,
        'message' => 'successful',
        'data' => new MediaCollection($media),
    ]);
}
```

---

# Retrieve a Media Item

```php
public function getById(Media $media)
{
    return response()->json([
        'status' => 200,
        'message' => 'successful',
        'data' => new MediaResource($media),
    ]);
}
```

---

# Update Media

The package doesn't dictate how you manage metadata. For example, renaming a file:

```php
public function update(Request $request, Media $medium)
{
    $request->validate([
        'name' => ['required', 'string'],
    ]);

    $medium->update([
        'name' => $request->name,
    ]);

    return response()->json([
        'status' => 200,
        'message' => 'successful',
        'data' => new MediaResource($medium->refresh()),
    ]);
}
```

---

# Delete Media

Delete both the physical files and the database record.

```php
use StormcellTech\MediaUploader;

public function destroy(Media $medium, MediaUploader $uploader)
{
    $uploader->deleteMedia(
        $medium->filename,
        $medium->thumbnails ?? [],
        $medium->disk ?? 'public'
    );

    $medium->delete();

    return response()->json([
        'status' => 200,
        'message' => 'Image deleted successfully',
    ]);
}
```

---

# Media Resource

The JavaScript uploader consumes a JSON resource similar to the following.

```php
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'filename' => $this->name,

            'mime' => $this->mime_type,

            'extension' => $this->extension,

            'full_url' => $this->getUrl(),

            'thumb' => $this->getUrl('300x300'),

            'size' => Number::fileSize($this->size ?? 0, 2),

            'created_at' => (string) $this->created_at->shortAbsoluteDiffForHumans(),

            'updated_at' => (string) $this->updated_at->shortAbsoluteDiffForHumans(),
        ];
    }
}
```

---

# Media Collection

Media library responses should return a paginated collection.

```php
class MediaCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [

            'current_page' => $this->currentPage(),

            'data' => $this->collection,

            'first_page_url' => $this->url(1),

            'from' => $this->firstItem(),

            'last_page' => $this->lastPage(),

            'last_page_url' => $this->url($this->lastPage()),

            'next_page_url' => $this->nextPageUrl(),

            'path' => $this->path(),

            'per_page' => $this->perPage(),

            'prev_page_url' => $this->previousPageUrl(),

            'to' => $this->lastItem(),

            'total' => $this->total(),
        ];
    }
}
```

---

# Upload Response

A successful upload should return a resource similar to the following.

```json
{
  "success": true,
  "data": {
    "id": 15,
    "filename": "logo.png",
    "mime": "image/png",
    "extension": "png",
    "full_url": "https://example.com/storage/uploads/logo.png",
    "thumb": "https://example.com/storage/uploads/300x300/logo.png",
    "size": "324 KB",
    "created_at": "2 seconds ago",
    "updated_at": "2 seconds ago"
  }
}
```

The JavaScript uploader uses this response to automatically update previews, hidden input values, and the media library without requiring additional JavaScript.
