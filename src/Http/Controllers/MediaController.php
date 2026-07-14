<?php

namespace Palgoal\MediaLibrary\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Palgoal\MediaLibrary\Http\Resources\MediaResource;
use Palgoal\MediaLibrary\Models\Media;

/**
 * Class MediaController
 *
 * Centralized controller for managing media files.
 * Responsibilities:
 * - List media items with filters and pagination (API + Blade view)
 * - Upload media (single & multiple)
 * - Show/update media metadata
 * - Delete media from storage and database
 */
class MediaController extends BaseController
{
    use AuthorizesRequests;

    /**
     * Display a list of media items (with filtering, search, and pagination).
     *
     * Behavior:
     * - If the request does NOT expect JSON -> returns the Blade view (media page).
     * - If the request expects JSON (AJAX/API) -> returns paginated JSON.
     *
     * Query params:
     * - type=image|video|document|other (filter by file_type)
     * - search=term or q=term       (simple text search)
     * - per_page=40                 (items per page, clamped between 1 and 100)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Media::class);

        if (! $request->wantsJson()) {
            return view('media-library::media');
        }

        $perPage = (int) $request->get('per_page', 40);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 40;

        $query = Media::query()->latest();

        if ($type = $request->get('type')) {
            $query->where('file_type', $type);
        }

        $term = $request->get('search') ?? $request->get('q');

        if ($term) {
            $query->search($term);
        }

        $paginator = $query->paginate($perPage);
        $paginator = $paginator->through(fn ($media) => new MediaResource($media));

        return response()->json($paginator);
    }

    /**
     * Handle media upload requests.
     *
     * Supported payloads:
     * - Multiple files: "files[]" (e.g. from drag & drop or multi-select input)
     * - Single file: "image"
     */
    public function store(Request $request)
    {
        $this->authorize('create', Media::class);

        $mimes      = implode(',', config('media-library.allowed_mimes', ['jpeg', 'jpg', 'png', 'gif', 'webp', 'svg']));
        $mimetypes  = implode(',', config('media-library.allowed_mimetypes', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']));
        $maxKb      = (int) config('media-library.max_upload_size_kb', 10240);

        if ($request->hasFile('files')) {
            $request->validate([
                'files'   => 'required|array',
                'files.*' => "file|mimes:{$mimes}|max:{$maxKb}|mimetypes:{$mimetypes}",
            ]);

            $uploaded = [];
            foreach ($request->file('files') as $file) {
                $uploaded[] = $this->saveMediaFile($file);
            }

            return response()->json([
                'uploaded' => MediaResource::collection(collect($uploaded)),
            ], 201);
        }

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => "required|file|mimes:{$mimes}|max:{$maxKb}|mimetypes:{$mimetypes}",
            ]);

            $media = $this->saveMediaFile($request->file('image'));

            return response()->json(new MediaResource($media), 201);
        }

        return response()->json([
            'message' => 'حقل الصورة مفقود. أرسل "image" (ملف واحد) أو "files[]" (عدة ملفات).',
        ], 422);
    }

    /**
     * Show a single media item as JSON.
     */
    public function show($id)
    {
        $this->authorize('viewAny', Media::class);
        $media = Media::findOrFail($id);

        return response()->json(new MediaResource($media));
    }

    /**
     * Edit endpoint (kept for compatibility). Proxies to show().
     */
    public function edit($id)
    {
        $this->authorize('viewAny', Media::class);
        $media = Media::findOrFail($id);

        return response()->json(new MediaResource($media));
    }

    /**
     * Update media metadata (does NOT replace the underlying file).
     */
    public function update(Request $request, $id)
    {
        $media = Media::findOrFail($id);
        $this->authorize('update', $media);

        $request->validate([
            'file_original_name' => 'nullable|string|max:255',
            'alt'         => 'nullable|string|max:255',
            'title'       => 'nullable|string|max:255',
            'caption'     => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $data = $request->only([
            'file_original_name',
            'alt',
            'title',
            'caption',
            'description',
        ]);

        $media->update($data);

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'media'   => new MediaResource($media),
        ]);
    }

    /**
     * Bulk delete multiple media files in a single request.
     *
     * Payload: { "ids": [1, 2, 3, ...] }
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorize('create', Media::class); // reuse create gate for bulk actions

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:media,id',
        ]);

        $items = Media::whereIn('id', $request->ids)->get();

        foreach ($items as $media) {
            $this->authorize('delete', $media);
            if ($media->file_path) {
                Storage::disk($media->disk ?: 'public')->delete($media->file_path);
            }
            $media->delete();
        }

        return response()->json([
            'message' => 'تم الحذف بنجاح',
            'deleted' => $items->count(),
        ]);
    }

    /**
     * Delete a media file from storage AND its database record.
     */
    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        $this->authorize('delete', $media);

        if ($media->file_path) {
            $disk = $media->disk ?: 'public';
            Storage::disk($disk)->delete($media->file_path);
        }

        $media->delete();

        return response()->json(['message' => 'تم الحذف']);
    }

    /**
     * Helper method to store an uploaded file and create the corresponding
     * Media record in the database.
     */
    private function saveMediaFile(UploadedFile $file): Media
    {
        $disk = config('media-library.disk', 'public');

        $now  = now();
        $base = trim((string) config('media-library.directory', 'media'), '/');
        $dir  = $base . '/' . $now->format('Y') . '/' . $now->format('m');

        $originalName = $file->getClientOriginalName();
        $extension    = strtolower($file->getClientOriginalExtension());
        $mimeType     = $file->getMimeType();

        $hashedName = uniqid('', true) . '.' . $extension;

        $path = Storage::disk($disk)->putFileAs($dir, $file, $hashedName);

        $width  = null;
        $height = null;

        if (str_starts_with((string) $mimeType, 'image/')) {
            try {
                $imageSize = getimagesize($file->getPathname());
                if ($imageSize) {
                    $width  = $imageSize[0] ?? null;
                    $height = $imageSize[1] ?? null;
                }
            } catch (\Throwable $e) {
                // Silently ignore any error while reading dimensions
            }
        }

        $fileType = $this->detectFileType($mimeType, $extension);

        return Media::create([
            'file_name'          => $hashedName,
            'file_original_name' => $originalName,
            'file_path'          => $path,
            'file_extension'     => $extension,
            'mime_type'          => $mimeType,
            'size'               => $file->getSize(),
            'file_type'          => $fileType,
            'disk'               => $disk,
            'width'              => $width,
            'height'             => $height,
            'uploader_id'        => Auth::id(),
        ]);
    }

    /**
     * Determine the logical file_type value based on MIME type and extension.
     */
    private function detectFileType(?string $mimeType, ?string $extension): string
    {
        $mimeType  = strtolower((string) $mimeType);
        $extension = strtolower((string) $extension);

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        if (in_array($extension, $documentExtensions, true)) {
            return 'document';
        }

        return 'other';
    }
}
