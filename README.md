# Laravel MediaUploader

A complete media management solution for Laravel featuring automatic image optimization, thumbnail generation, a built-in media library, Blade components, and a dependency-free JavaScript uploader.

The package is designed to work out of the box while remaining fully customizable.

---

# Features

- 📁 File management system
- 🖼 Automatic image thumbnail generation
- 📤 Drag & Drop uploader
- 📚 Built-in media library
- 🔍 Search and pagination
- 🎯 Blade components
- 🚀 Zero-build vanilla JavaScript uploader
- 🔒 Laravel authentication support
- 🔐 CSRF protected
- ☁️ Supports Local, Public and S3 disks
- 📄 Supports images, documents, audio, video and archives
- ⚡ Automatic image optimization
- 🎨 Tailwind CSS UI

---

# Requirements

- PHP 8.2+
- Laravel 11+
- Intervention Image v3

---

# Installation

Install via Composer.

```bash
composer require stormcelltech/mediauploader
```

Run the installer.

```bash
php artisan mediauploader:install
```

The installer publishes:

```
config/media-upload.php

database/migrations/create_media_table.php

resources/views/components/uploader.blade.php

resources/views/components/gallery.blade.php
```

Run migrations.

```bash
php artisan migrate
```

---

# Quick Start

Single uploader

```blade
<x-media-upload::uploader
    id="logo-uploader"
    name="logo_id"
    :value="$settings->logo_id"
    text="Upload Logo"
/>
```

Gallery uploader

```blade
<x-media-upload::gallery
    id="gallery"
    name="gallery"
/>
```

---

# Blade Component Options

| Property     | Description        |
| ------------ | ------------------ |
| id           | Unique uploader ID |
| name         | Hidden input name  |
| value        | Existing Media ID  |
| text         | Upload button text |
| type         | single or multiple |
| preview      | Show preview       |
| hideMediaTab | Hide media library |

Example

```blade
<x-media-upload::uploader
    id="avatar"
    name="avatar_id"
    :value="$user->avatar_id"
    type="single"
    text="Upload Avatar"
    preview="true"
/>
```

---

# Configuration

The package configuration is located at:

```
config/media-upload.php
```

## Storage

```php
'disk' => 'public',

'directory' => 'uploads',
```

Supports any Laravel filesystem.

- public
- local
- s3
- custom disks

---

## Image Processing

```php
'images' => [

    'max_width' => 4000,

    'max_height' => 4000,

    'thumbnails' => [

        [300,300],

        [500,500],

    ],

    'jpeg_quality' => 85,

    'webp_quality' => 80,

]
```

Images are automatically resized while preserving aspect ratio.

---

## File Size Limits

```php
'file_size_limits' => [

    'image' => 50 MB,

    'document' => 100 MB,

    'archive' => 500 MB,

    'audio' => 200 MB,

    'video' => 1 GB,

]
```

---

## Allowed File Types

Supports

### Images

- PNG
- JPG
- JPEG
- GIF
- WEBP
- SVG

### Documents

- PDF
- DOC
- DOCX
- XLS
- XLSX
- PPT
- PPTX
- TXT
- CSV
- ODT
- ODS
- ODP

### Archives

- ZIP
- RAR
- 7Z
- GZIP

### Audio

- MP3
- WAV
- AAC
- OGG
- FLAC

### Video

- MP4
- WEBM
- AVI
- MOV

---

# JavaScript Uploader

The package ships with a vanilla JavaScript uploader.

Example

```html
<div
  id="logo-uploader"
  class="uploader"
  data-fileinputname="logo_id"
  data-uploadtext="Upload Logo"
  data-uploadtype="single"
></div>
```

Initialize automatically

```javascript
import "stormcelltech-fileuploader";
```

---

# MediaUploader Service

The package exposes a MediaUploader service for storing files programmatically.

```php
use StormcellTech\MediaUploader\Services\MediaUploader;

public function store(MediaUploader $uploader)
{
    $media = $uploader->store(
        request()->file('file'),
        'public',
        'uploads',
        auth()->id()
    );

    return $media;
}
```

---

# Media Model

Every upload returns a Media model.

```php
$media->id;

$media->filename;

$media->mime_type;

$media->size;

$media->disk;

$media->original_path;

$media->thumbnails;
```

Useful helper methods

```php
$media->getUrl();

$media->getUrl('300x300');

$media->getReadableSize();
```

---

# API Routes

The package automatically registers the following endpoints.

| Method | Endpoint                | Description    |
| ------ | ----------------------- | -------------- |
| GET    | /media/list             | List media     |
| GET    | /media/search/{keyword} | Search media   |
| GET    | /media/{id}/get         | Retrieve media |
| POST   | /media/upload           | Upload file    |
| DELETE | /media/{id}/delete      | Delete media   |

---

# Upload Response

```json
{
  "status": 200,
  "message": "File uploaded successfully",
  "data": {
    "id": 15,
    "filename": "logo.png",
    "mime_type": "image/png",
    "thumb": "...",
    "url": "..."
  }
}
```

---

# Retrieve Media

```
GET /media/{id}/get
```

Returns

```json
{
  "status": 200,
  "data": {
    "id": 15,
    "filename": "logo.png",
    "thumb": "..."
  }
}
```

---

# Delete Media

```
DELETE /media/{id}/delete
```

Returns

```json
{
  "status": 200,
  "message": "Media deleted successfully"
}
```

---

# Searching Media

```
GET /media/search/logo
```

Supports pagination.

```
GET /media/list?page=2&per_page=20
```

---

# Storage Structure

```
storage/

    app/

        public/

            uploads/

                original/

                300x300/

                500x500/
```

---

# Events

The JavaScript uploader dispatches

```javascript
file: selected;
```

Example

```javascript
document
  .getElementById("logo-uploader")
  .addEventListener("file:selected", (event) => {
    console.log(event.detail);
  });
```

---

# Security

- Authentication protected
- CSRF protected
- User ownership validation
- MIME type validation
- File size validation

---

# Customization

You can customize:

- Storage disk
- Upload directory
- Thumbnail sizes
- Image quality
- Maximum dimensions
- Allowed MIME types
- Maximum upload sizes

without modifying package code.

---

# Browser Support

Supports all modern browsers.

- Chrome
- Firefox
- Safari
- Edge

---

# License

MIT License

Copyright (c) 2026 Storm Cell Tech

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software.
