<?php

namespace App\Http\Controllers;

use App\Enums\ContentAccess;
use App\Enums\UploadStatus;
use App\Models\Category;
use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $videos = Video::published()
            ->accessibleBy($user)
            ->orderBy('created_on', 'desc')
            ->limit(12)
            ->get();

        $billboards = [];
        if (Storage::disk('local')->exists('billboard.json')) {
            $content = Storage::disk('local')->get('billboard.json');
            $billboards = json_decode($content, true) ?? [];
        }

        return Inertia::render('Home/Index', [
            'videos' => $videos,
            'billboards' => $billboards,
        ]);
    }

    public function loadVideos(Request $request)
    {
        $user = $request->user();
        $offset = $request->get('offset', 0);
        $limit = min($request->get('limit', 8), 20);

        $videos = Video::published()
            ->accessibleBy($user)
            ->orderBy('created_on', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json(['videos' => $videos, 'total' => $videos->count()]);
    }

    public function playlists(Request $request)
    {
        $user = $request->user();

        // Efficient query to get playlists sorted by pinned, then by name
        $getPlaylistsQuery = fn ($type) => Playlist::where('type', $type)
            ->orderByDesc('pinned')
            ->orderBy('name')
            ->get();

        $broadcastPlaylists = $getPlaylistsQuery(1)
            ->filter(fn ($p) => $this->canViewPlaylist($p, $user))
            ->map(fn ($p) => $this->enrichPlaylist($p, $user));

        $classicPlaylists = $getPlaylistsQuery(0)
            ->filter(fn ($p) => $this->canViewPlaylist($p, $user))
            ->map(fn ($p) => $this->enrichPlaylist($p, $user));

        return Inertia::render('Home/Playlists', [
            'broadcastPlaylists' => $broadcastPlaylists->values(),
            'classicPlaylists' => $classicPlaylists->values(),
        ]);
    }

    public function playlistDetails(Request $request, string $slug)
    {
        $playlist = Playlist::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (! $this->canViewPlaylist($playlist, $user)) {
            abort(403);
        }

        $videos = $playlist->getVideosCollection($user);

        return Inertia::render('Home/PlaylistDetails', [
            'playlist' => $playlist,
            'videos' => $videos,
        ]);
    }

    public function categories(Request $request)
    {
        $categories = Category::orderBy('label')->get();

        return Inertia::render('Home/Categories', [
            'categories' => $categories,
        ]);
    }

    public function categoryDetails(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $videos = $category->publishedVideos($user)
            ->orderBy('created_on', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Home/CategoryDetails', [
            'category' => $category,
            'videos' => $videos,
        ]);
    }

    public function loadCategoryVideos(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $user = $request->user();
        $offset = $request->get('offset', 0);
        $limit = min($request->get('limit', 8), 20);

        $videos = $category->publishedVideos($user)
            ->orderBy('created_on', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json(['videos' => $videos, 'total' => $videos->count()]);
    }

    public function favorites(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return Inertia::render('Home/Favorites', [
                'videos' => [],
                'needsAuth' => true,
            ]);
        }

        $videos = Video::join('video_reaction', 'video.token', '=', 'video_reaction.video_token')
            ->where('video_reaction.username', $user->username)
            ->where('video.upload_status', UploadStatus::UPLOAD_END->value)
            ->whereIn('video.access', [
                ContentAccess::CENTRALIENS->value,
                ContentAccess::PUBLIC->value,
            ])
            ->orderBy('video_reaction.created_on', 'desc')
            ->select('video.*')
            ->get();

        return Inertia::render('Home/Favorites', [
            'videos' => $videos,
            'needsAuth' => false,
        ]);
    }

    private function canViewPlaylist(Playlist $playlist, $user): bool
    {
        $access = ContentAccess::from($playlist->access);

        return match ($access) {
            ContentAccess::PUBLIC, ContentAccess::UNLINKED => true,
            ContentAccess::CENTRALIENS => $user !== null,
            ContentAccess::PRIVATE => $user?->hasPermission('myclap.private') ?? false,
        };
    }

    private function enrichPlaylist(Playlist $playlist, $user): array
    {
        $videos = $playlist->getVideosCollection($user);
        $totalDuration = $videos->sum('duration');

        return array_merge($playlist->toArray(), [
            'video_count' => $videos->count(),
            'total_duration' => $totalDuration,
            'first_video_thumbnail' => $videos->first()?->thumbnail_url,
            'videos_preview' => $videos->take(5)->values(),
        ]);
    }
}
