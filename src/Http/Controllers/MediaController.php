<?php

namespace StormcellTech\MediaUploader\Http\Controllers;

use StormcellTech\MediaUploader\Models\Media;
use StormcellTech\MediaUploader\Services\MediaUploader;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
    public function __construct(private MediaUploader $uploader) {}

    /**
     * List media with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $media = Media::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 200,
            'message' => 'Media retrieved successfully',
            'data' => $media->through(fn(Media $m) => $this->formatMedia($m)),
        ]);
    }

    /**
     * Search media by filename or name
     */
    public function search(Request $request, string $keyword)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $media = Media::where('user_id', Auth::id())
            ->where(function ($query) use ($keyword) {
                $query->where('filename', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 200,
            'message' => 'Search results',
            'data' => $media->through(fn(Media $m) => $this->formatMedia($m)),
        ]);
    }

    /**
     * Get single media by ID
     */
    public function show(Media $media)
    {
        if ($media->user_id !== Auth::id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Media retrieved successfully',
            'data' => $this->formatMedia($media),
        ]);
    }

    /**
     * Upload a new file
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $disk = $request->get('disk', config('media-upload.disk', 'public'));
            $directory = $request->get('directory', config('media-upload.directory', 'uploads'));

            $media = $this->uploader->store(
                $request->file('file'),
                $disk,
                $directory,
                Auth::id()
            );

            return response()->json([
                'status' => 200,
                'message' => 'File uploaded successfully',
                'data' => $this->formatMedia($media),
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 422,
                'message' => $e->getMessage(),
                'errors' => ['file' => $e->getMessage()],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred during upload',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete media
     */
    public function destroy(Media $media)
    {
        if ($media->user_id !== Auth::id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $disk = $media->disk ?? 'public';
            $thumbs = $media->thumbnails ? array_values($media->thumbnails) : [];

            $this->uploader->deleteMedia($media->original_path, $thumbs, $disk);
            $media->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to delete media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format media for response
     */
    private function formatMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'filename' => $media->filename,
            'category' => $media->category,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'size_readable' => $media->getReadableSize(),
            'url' => $media->getUrl(),
            'thumb' => $media->getUrl('300x300'),
            'thumbnails' => $media->thumbnails ?? [],
            'created_at' => $media->created_at,
            'updated_at' => $media->updated_at,
        ];
    }
}
