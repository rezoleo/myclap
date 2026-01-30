<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoStatService
{
    public function initView(Video $video, Request $request): ?string
    {
        $phpSid = session()->getId();

        // Anti-spam: max 50 inits per session and day
        $hasReachedLimit = VideoView::where('video_token', $video->token)
            ->where('php_sid', $phpSid)
            ->whereBetween('created_on', [now()->startOfDay(), now()->endOfDay()])
            ->skip(49)
            ->exists();

        if ($hasReachedLimit) {
            return null;
        }

        // If already counted as view in this session in the pase hour, don't count again
        $alreadyCounted = VideoView::where('video_token', $video->token)
            ->where('php_sid', $phpSid)
            ->whereBetween('created_on', [now()->subHour(), now()])
            ->where('count_as_view', 1)
            ->exists();

        if ($alreadyCounted) {
            return null;
        }

        $playbackSid = Str::random(30);

        VideoView::create([
            'video_token' => $video->token,
            'php_sid' => $phpSid,
            'playback_sid' => $playbackSid,
            'username' => auth()->user()?->username,
            'view_source' => $request->input('view_source'),
            'device_type' => $request->input('device_type'),
            'browser' => $request->input('browser'),
            'os' => $request->input('os'),
        ]);

        return $playbackSid;
    }

    public function updateView(Video $video, string $playbackSid, Request $request): void
    {
        $phpSid = session()->getId();

        $view = VideoView::where('video_token', $video->token)
            ->where('php_sid', $phpSid)
            ->where('playback_sid', $playbackSid)
            ->first();

        if (! $view) {
            return;
        }

        $watch_time = $request->input('watch_time');
        if ($watch_time !== null) {
            $view->watch_time = (int) $watch_time;
        }

        if (! $view->count_as_view && $view->created_on->diffInSeconds(now()) >= 25) {
            $view->count_as_view = true;
            $video->increment('views');
        }

        $view->updated_on = now();
        $view->save();
    }
}
