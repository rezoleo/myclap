<?php

namespace App\Http\Controllers;

use App\Enums\ContentAccess;
use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WatchController extends Controller
{
    public function show(Request $request, string $token)
    {
        $video = Video::with('categories')
            ->where('token', $token)
            ->published()
            ->firstOrFail();

        $access = ContentAccess::from($video->access);
        $user = $request->user();

        if ($access === ContentAccess::CENTRALIENS && ! $user) {
            return Inertia::render('Watch/LoginRequired', [
                'video' => $video,
            ]);
        }

        if ($access === ContentAccess::PRIVATE && (! $user || ! $user->hasPermission('myclap.private'))) {
            return Inertia::render('Watch/PrivateVideo', [
                'video' => $video,
            ]);
        }

        $userDidLike = false;
        if ($user) {
            $userDidLike = $video->videoReactions()
                ->where('username', $user->username)
                ->exists();
        }

        return Inertia::render('Watch/Index', [
            'video' => $video,
            'userDidLike' => $userDidLike,
        ]);
    }

    public function showInPlaylist(Request $request, string $playlistSlug, string $token)
    {
        $playlist = Playlist::where('slug', $playlistSlug)->firstOrFail();
        $video = Video::with('categories')->where('token', $token)->published()->firstOrFail();

        // Check access - show login page instead of 403 for Centraliens videos
        $access = ContentAccess::from($video->access);
        $user = $request->user();

        if ($access === ContentAccess::CENTRALIENS && ! $user) {
            return Inertia::render('Watch/LoginRequired', [
                'video' => $video,
            ]);
        }

        if ($access === ContentAccess::PRIVATE && (! $user || ! $user->hasPermission('myclap.private'))) {
            return Inertia::render('Watch/PrivateVideo', [
                'video' => $video,
            ]);
        }

        // Check if video is in playlist
        $playlistTokens = $playlist->getVideoTokens();
        if (! in_array($token, $playlistTokens)) {
            abort(404);
        }

        $videos = $playlist->getVideosCollection($user);
        $currentIndex = $videos->search(fn ($v) => $v->token === $token);

        $userDidLike = false;
        if ($user) {
            $userDidLike = $video->videoReactions()
                ->where('username', $user->username)
                ->exists();
        }

        return Inertia::render('Watch/Playlist', [
            'playlist' => $playlist,
            'video' => $video,
            'videos' => $videos->values(),
            'currentIndex' => $currentIndex !== false ? $currentIndex : 0,
            'userDidLike' => $userDidLike,
        ]);
    }

    public function download(string $token)
    {
        return redirect()->route('watch.media.video.download', ['token' => $token]);
    }
}
