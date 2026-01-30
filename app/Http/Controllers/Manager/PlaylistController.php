<?php

namespace App\Http\Controllers\Manager;

use App\Enums\ContentAccess;
use App\Enums\PlaylistType;
use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = Playlist::withCount('videos')
            ->orderByDesc('pinned')
            ->orderBy('name')
            ->get();

        return Inertia::render('Manager/Playlists/Index', [
            'playlists' => $playlists,
        ]);
    }

    public function create()
    {
        return Inertia::render('Manager/Playlists/Create', [
            'typeOptions' => PlaylistType::options(),
            'accessOptions' => ContentAccess::options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:75',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|integer|in:0,1',
            'access' => 'required|integer|in:0,1,2,3',
            'pinned' => 'boolean',
            'videos' => 'nullable|array',
        ]);

        // Generate unique slug
        $slugBase = Str::slug($validated['name']);
        $slug = $slugBase;
        $acc = 1;
        while (Playlist::where('slug', $slug)->exists()) {
            $acc++;
            $slug = $slugBase.'-'.$acc;
        }

        // Validate video tokens
        $videoTokens = $validated['videos'] ?? [];
        $validTokens = Video::whereIn('token', $videoTokens)->pluck('token')->toArray();
        $orderedTokens = array_values(array_filter($videoTokens, fn ($t) => in_array($t, $validTokens)));

        $playlist = Playlist::create([
            'slug' => $slug,
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'type' => $validated['type'],
            'access' => $validated['access'],
            'pinned' => $validated['pinned'] ?? false,
            'modified_by' => $request->user()->username,
        ]);

        $playlist->syncVideosWithOrder($orderedTokens);

        return redirect()->route('manager.playlists.edit', $playlist->slug)
            ->with('success', 'La playlist a été créée avec succès');
    }

    public function edit(string $slug)
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        // Get the videos in order via relationship
        $playlistVideos = $playlist->videos()->get();

        return Inertia::render('Manager/Playlists/Edit', [
            'playlist' => $playlist,
            'playlistVideos' => $playlistVideos,
            'typeOptions' => PlaylistType::options(),
            'accessOptions' => ContentAccess::options(),
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:75',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|integer|in:0,1',
            'access' => 'required|integer|in:0,1,2,3',
            'pinned' => 'boolean',
            'videos' => 'nullable|array',
            'created_on' => 'required|date',
        ]);

        // Validate video tokens
        $videoTokens = $validated['videos'] ?? [];
        $validTokens = Video::whereIn('token', $videoTokens)->pluck('token')->toArray();
        // Maintain order from request
        $orderedTokens = array_values(array_filter($videoTokens, fn ($t) => in_array($t, $validTokens)));

        $playlist->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'type' => $validated['type'],
            'access' => $validated['access'],
            'pinned' => $validated['pinned'] ?? false,
            'modified_by' => $request->user()->username,
            'created_on' => $validated['created_on'],
            'modified_on' => now(),
        ]);

        // Sync videos with order
        $playlist->syncVideosWithOrder($orderedTokens);

        return back()->with('success', 'Les changements ont bien été sauvegardés');
    }

    public function destroy(string $slug)
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();
        $playlist->delete();

        return redirect()->route('manager.playlists.index')
            ->with('success', 'La playlist a bien été supprimée');
    }

    public function searchVideos(Request $request)
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 5);
        $exclude = $request->get('exclude', []);

        if (is_string($exclude)) {
            $exclude = json_decode($exclude, true) ?? [];
        }

        $videos = Video::published()
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('token', 'ILIKE', "%{$query}%");
            })
            ->when(! empty($exclude), function ($q) use ($exclude) {
                $q->whereNotIn('token', $exclude);
            })
            ->orderBy('created_on', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'videos' => $videos,
            'total' => $videos->count(),
        ]);
    }
}
