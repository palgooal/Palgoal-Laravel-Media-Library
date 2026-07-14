<?php

namespace Palgoal\MediaLibrary\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Palgoal\MediaLibrary\Http\Resources\MediaResource;
use Palgoal\MediaLibrary\Models\Media;
use Throwable;

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
     * - type=image|video|audio|document|other (filter by file_type; validated
     *   against config('media-library.allowed_types') — anything else is
     *   rejected with a 422 instead of silently querying an arbitrary value)
     * - search=term or q=term       (simple text search)
     * - per_page=40                 (items per page, clamped between 1 and 100)
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        if (! $request->wantsJson()) {
            return view('media-library::media');
        }

        $request->validate([
            'type' => ['nullable', 'string', Rule::in(config('media-library.allowed_types', [
                'image', 'video', 'audio', 'document', 'other',
            ]))],
        ]);

        $perPage = (int) $request->get('per_page', 40);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 40;

        $query = Media::query()->latest();

        if ($type = $request->get('type')) {
            $query->ofType($type);
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
     *
     * Validation relies on Laravel's `mimes` (extension whitelist) AND
     * `mimetypes` (server-side content sniffing via fileinfo) rules
     * together, so a file's *actual* content is checked — not just its
     * client-supplied filename/extension or the browser-reported MIME
     * type, either of which can be trivially spoofed by the uploader.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $mimes     = implode(',', config('media-library.allowed_mimes', ['jpeg', 'jpg', 'png', 'gif', 'webp']));
        $mimetypes = implode(',', config('media-library.allowed_mimetypes', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']));
        $maxKb     = (int) config('media-library.max_upload_size_kb', 10240);

        if ($request->hasFile('files')) {
            $request->validate([
                'files'   => 'required|array',
                'files.*' => "file|mimes:{$mimes}|max:{$maxKb}|mimetypes:{$mimetypes}",
            ]);

            $uploaded = [];

            try {
                foreach ($request->file('files') as $file) {
                    $uploaded[] = $this->saveMediaFile($file);
                }
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'فشل رفع الملفات. لم يتم إنشاء أي سجل لملف تعذّر حفظه في قاعدة البيانات.',
                ], 500);
            }

            return response()->json([
                'uploaded' => MediaResource::collection(collect($uploaded)),
            ], 201);
        }

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => "required|file|mimes:{$mimes}|max:{$maxKb}|mimetypes:{$mimetypes}",
            ]);

            try {
                $media = $this->saveMediaFile($request->file('image'));
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'فشل رفع الملف.',
                ], 500);
            }

            return response()->json(new MediaResource($media), 201);
        }

        return response()->json([
            'message' => 'حقل الصورة مفقود. أرسل "image" (ملف واحد) أو "files[]" (عدة ملفات).',
        ], 422);
    }

    /**
     * Show a single media item as JSON.
     */
    public function show(string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->authorize('view', $media);

        return response()->json(new MediaResource($media));
    }

    /**
     * Edit endpoint. Kept only for backward compatibility with routes/
     * clients that already call GET .../{id}/edit — this package has no
     * server-rendered edit form, so it intentionally behaves exactly like
     * show(). New integrations should call show() (or GET .../{id}) directly.
     */
    public function edit(string $id): JsonResponse
    {
        return $this->show($id);
    }

    /**
     * Update media metadata (does NOT replace the underlying file).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->authorize('update', $media);

        $validated = $request->validate([
            'file_original_name' => 'nullable|string|max:255',
            'alt'         => 'nullable|string|max:255',
            'title'       => 'nullable|string|max:255',
            'caption'     => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $media->update($validated);

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'media'   => new MediaResource($media),
        ]);
    }

    /**
     * Bulk delete multiple media files in a single request.
     *
     * Payload: { "ids": [1, 2, 3, ...] }
     *
     * Every item is authorized against the `delete` ability BEFORE any
     * deletion happens. Previously this endpoint (a) checked a `create`
     * ability that has nothing to do with deleting, and (b) deleted items
     * one at a time while authorizing them in the same loop, so a
     * mid-batch authorization failure left some items deleted and others
     * not. Both are fixed here: all-or-nothing authorization, then delete.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:media,id',
        ]);

        $items = Media::whereIn('id', $request->input('ids'))->get();

        foreach ($items as $media) {
            $this->authorize('delete', $media);
        }

        $deleted = 0;
        foreach ($items as $media) {
            if ($this->deleteMediaRecord($media)) {
                $deleted++;
            }
        }

        return response()->json([
            'message' => 'تم الحذف بنجاح',
            'deleted' => $deleted,
        ]);
    }

    /**
     * Delete a media file from storage AND its database record.
     */
    public function destroy(string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->authorize('delete', $media);

        $this->deleteMediaRecord($media);

        return response()->json(['message' => 'تم الحذف']);
    }

    /**
     * Delete a Media row and its underlying file consistently.
     *
     * Order matters: the database record is removed first (inside a
     * transaction). Only once that succeeds do we attempt to delete the
     * file from storage. Worst-case failure mode is therefore an orphaned
     * file left on disk (a harmless resource leak) rather than a database
     * record pointing at a file we already removed (a broken reference
     * that would 404 for API/UI consumers, which is worse). Storage
     * operations are not part of the database transaction — most disk
     * drivers (local, S3, ...) have no transactional semantics of their
     * own — so this ordering minimizes damage but cannot make the two
     * systems perfectly atomic together.
     */
    private function deleteMediaRecord(Media $media): bool
    {
        $disk = $media->disk ?: 'public';
        $path = $media->file_path;

        $deleted = DB::transaction(fn () => (bool) $media->delete());

        if ($deleted && $path) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $e) {
                // The database record is already gone and the response
                // already reflects a successful delete; failing to purge
                // the physical file must not turn this into a 500. Still
                // report it so the host app's error tracker can surface it.
                report($e);
            }
        }

        return $deleted;
    }

    /**
     * Helper method to store an uploaded file and create the corresponding
     * Media record in the database.
     *
     * If the file is written to storage successfully but the database
     * insert then fails for any reason, the just-written file is deleted
     * before the exception is rethrown — the package never leaves an
     * orphaned file on disk with no matching database row.
     */
    private function saveMediaFile(UploadedFile $file): Media
    {
        $disk = config('media-library.disk', 'public');

        $now  = now();
        $base = trim((string) config('media-library.directory', 'media'), '/');
        $dir  = $base . '/' . $now->format('Y') . '/' . $now->format('m');

        $originalName = $file->getClientOriginalName();

        // The extension is client-supplied, but by the time this method
        // runs the request has already passed the `mimes` (extension
        // whitelist) AND `mimetypes` (server-side content sniffing via
        // fileinfo) validation rules, so it is constrained to the
        // configured allow-list and cannot smuggle path separators
        // (pathinfo()-derived) or an extension that disagrees with the
        // file's real, server-detected content type.
        $extension = strtolower($file->getClientOriginalExtension());

        // getMimeType() (unlike getClientMimeType()) is resolved from the
        // actual file content via the fileinfo extension, not trusted
        // client input.
        $mimeType = $file->getMimeType();

        $hashedName = uniqid('', true) . '.' . $extension;

        $path = Storage::disk($disk)->putFileAs($dir, $file, $hashedName);

        if ($path === false) {
            throw new \RuntimeException("Failed to store uploaded file on disk [{$disk}].");
        }

        $width  = null;
        $height = null;

        if (str_starts_with((string) $mimeType, 'image/')) {
            try {
                $imageSize = @getimagesize($file->getPathname());
                if ($imageSize) {
                    $width  = $imageSize[0] ?? null;
                    $height = $imageSize[1] ?? null;
                }
            } catch (Throwable $e) {
                // Corrupted/unreadable image: keep width/height null
                // instead of failing the whole upload.
            }
        }

        $fileType = $this->detectFileType($mimeType, $extension);

        try {
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
        } catch (Throwable $e) {
            // Roll back the storage write so we never leave an orphaned
            // file with no matching database record.
            Storage::disk($disk)->delete($path);

            throw $e;
        }
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
