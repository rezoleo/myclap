<?php

namespace App\Http\Controllers\Manager;

use App\Enums\ContentAccess;
use App\Enums\UploadStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Video;
use App\Models\VideoUpload;
use App\Services\ThumbnailService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VideoController extends Controller
{
    public function __construct(
        private readonly VideoService $videoService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermissionGroup('manager.video')) {
            abort(403);
        }

        $videos = Video::orderBy('uploaded_on', 'desc')->get();

        return Inertia::render('Manager/Videos/Index', [
            'videos' => $videos,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermission('manager.video.upload')) {
            abort(403);
        }

        $categories = Category::orderBy('label')->get();

        return Inertia::render('Manager/Videos/Create', [
            'categories' => $categories,
            'accessOptions' => ContentAccess::options(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermission('manager.video.upload')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:75',
            'description' => 'nullable|string|max:1000',
            'created_on' => 'required|date',
            'categories' => 'nullable|array',
            'access' => 'required|integer|in:0,1,2,3',
            'thumbnail' => 'nullable|image|max:10240',
        ]);

        // Generate unique token
        $token = Str::random(6);
        if (Video::where('token', $token)->exists()) {
            abort(503); // Very unlikely
        }

        $thumbnailIdentifier = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailService = app(ThumbnailService::class);
            try {
                $thumbnailIdentifier = $thumbnailService->store($request->file('thumbnail'));
            } catch (\Exception $e) {
                return back()->withErrors(['thumbnail' => $e->getMessage()]);
            }
        }

        $video = Video::create([
            'token' => $token,
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'access' => $validated['access'],
            'thumbnail_identifier' => $thumbnailIdentifier,
            'uploaded_by' => $user->username,
            'created_on' => $validated['created_on'],
        ]);

        // Sync categories
        $categorySlugs = $validated['categories'] ?? [];
        $video->syncCategories($categorySlugs);

        return redirect()->route('manager.videos.upload', $video->token)
            ->with('success', 'Vidéo créée. Vous pouvez maintenant envoyer le fichier vidéo.');
    }

    public function edit(Request $request, string $token)
    {
        $video = Video::with('categories')->where('token', $token)->firstOrFail();

        $this->authorize('update', $video);

        // Check and update duration if file might have changed
        if ($video->file_identifier) {
            $this->videoService->checkAndUpdateDuration($video);
        }

        $categories = Category::orderBy('label')->get();

        // Get video's current category slugs
        $videoCategorySlugs = $video->categories->pluck('slug')->toArray();

        return Inertia::render('Manager/Videos/Edit', [
            'video' => $video,
            'videoCategorySlugs' => $videoCategorySlugs,
            'categories' => $categories,
            'accessOptions' => ContentAccess::options(),
        ]);
    }

    public function update(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $this->authorize('update', $video);

        $validated = $request->validate([
            'name' => 'required|string|max:75',
            'description' => 'nullable|string|max:1000',
            'created_on' => 'required|date',
            'categories' => 'nullable|array',
            'access' => 'required|integer|in:0,1,2,3',
            'thumbnail' => 'nullable|image|max:10240',
        ]);

        $video->name = $validated['name'];
        $video->description = $validated['description'] ?: null;
        $video->created_on = $validated['created_on'];
        $video->access = $validated['access'];

        // Sync categories
        $categorySlugs = $validated['categories'] ?? [];
        $video->syncCategories($categorySlugs);

        if ($request->hasFile('thumbnail')) {
            $thumbnailService = app(ThumbnailService::class);

            // Delete old thumbnails
            if ($video->thumbnail_identifier) {
                $thumbnailService->delete($video->thumbnail_identifier);
            }

            try {
                $video->thumbnail_identifier = $thumbnailService->store($request->file('thumbnail'));
            } catch (\Exception $e) {
                return back()->withErrors(['thumbnail' => $e->getMessage()]);
            }
        }

        $video->save();

        return back()->with('success', 'Les changements ont bien été sauvegardés !');
    }

    public function upload(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();
        $user = $request->user();

        $this->authorize('update', $video);

        if ($video->upload_status === UploadStatus::UPLOAD_END->value) {
            return redirect()->route('manager.videos.edit', $token)
                ->with('info', 'La vidéo a déjà été uploadée.');
        }

        $uploadProgress = null;
        if ($video->upload_status === UploadStatus::UPLOAD_INIT->value) {
            $upload = VideoUpload::where('video_token', $video->token)->first();
            if ($upload) {
                $path = Storage::disk('local')->path($upload->file_identifier);
                $currentSize = file_exists($path) ? filesize($path) : 0;
                $uploadProgress = [
                    'fileName' => $upload->file_name,
                    'fileSize' => $upload->file_size,
                    'uploadedSize' => $currentSize,
                    'percentage' => $upload->file_size > 0
                        ? round(($currentSize / $upload->file_size) * 100, 2)
                        : 0,
                ];
            }
        }

        return Inertia::render('Manager/Videos/Upload', [
            'video' => $video,
            'uploadProgress' => $uploadProgress,
        ]);
    }

    public function destroy(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $this->authorize('delete', $video);

        // Delete video file
        if ($video->file_identifier && Storage::disk('local')->exists($video->file_identifier)) {
            Storage::disk('local')->delete($video->file_identifier);
        }

        // Delete thumbnails
        if ($video->thumbnail_identifier) {
            $thumbnailService = app(ThumbnailService::class);
            $thumbnailService->delete($video->thumbnail_identifier);
        }

        // Delete upload if exists
        VideoUpload::where('video_token', $video->token)->delete();

        $video->delete();

        return redirect()->route('manager.videos.index')
            ->with('success', 'La vidéo a bien été supprimée');
    }
}
