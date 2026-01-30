<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoReaction;
use Illuminate\Http\Request;

class VideoReactionController extends Controller
{
    public function toggle(Request $request, string $token)
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $video = Video::where('token', $token)->published()->firstOrFail();

        $this->authorize('view', $video);

        $username = $request->user()->username;

        $reaction = VideoReaction::where('video_token', $token)
            ->where('username', $username)
            ->first();

        if ($reaction) {
            $reaction->delete();
            $video->decrement('reactions');
            $active = false;
        } else {
            VideoReaction::create([
                'video_token' => $token,
                'username' => $username,
                'created_on' => now(),
            ]);
            $video->increment('reactions');
            $active = true;
        }

        return response()->json(['active' => $active]);
    }
}
