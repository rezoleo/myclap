<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\VideoUploadService;
use Illuminate\Http\Request;

class VideoUploadApiController extends Controller
{
    public function __construct(
        private VideoUploadService $uploadService
    ) {}

    public function init(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $this->authorize('update', $video);

        $validated = $request->validate([
            'fileName' => 'required|string',
            'fileSize' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->uploadService->initUpload(
                $video,
                $validated['fileName'],
                $validated['fileSize'],
                $request->user()->username
            );

            return response()->json([
                'success' => true,
                'payload' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function process(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $this->authorize('update', $video);

        $validated = $request->validate([
            'fileChunk' => 'required|file',
            'startIndex' => 'required|integer|min:0',
            'chunkSize' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->uploadService->processChunk(
                $video,
                $request->file('fileChunk'),
                $validated['startIndex'],
                $validated['chunkSize']
            );

            return response()->json([
                'success' => true,
                'payload' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function end(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $this->authorize('update', $video);

        try {
            $this->uploadService->finalizeUpload($video);

            return response()->json([
                'success' => true,
                'payload' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reset(Request $request, string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $this->authorize('update', $video);

        try {
            $this->uploadService->resetUpload($video);

            return response()->json([
                'success' => true,
                'payload' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
