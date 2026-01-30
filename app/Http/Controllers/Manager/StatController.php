<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StatController extends Controller
{
    public function index()
    {
        $totalViews = Video::sum('views');
        $totalReactions = Video::sum('reactions');
        $totalDuration = Video::sum('duration');
        $totalVideos = Video::published()->count();

        $topVideos = Video::published()
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        $recentViewsData = VideoView::select(
            DB::raw('DATE(created_on) as date'),
            DB::raw('COUNT(*) as views')
        )
            ->where('count_as_view', 1)
            ->where('created_on', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Global breakdowns
        $viewSources = VideoView::select('view_source', DB::raw('COUNT(*) as count'))
            ->where('count_as_view', 1)
            ->whereNotNull('view_source')
            ->groupBy('view_source')
            ->orderByDesc('count')
            ->get();

        $deviceTypes = VideoView::select('device_type', DB::raw('COUNT(*) as count'))
            ->where('count_as_view', 1)
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();

        $browsers = VideoView::select('browser', DB::raw('COUNT(*) as count'))
            ->where('count_as_view', 1)
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->get();

        $operatingSystems = VideoView::select('os', DB::raw('COUNT(*) as count'))
            ->where('count_as_view', 1)
            ->whereNotNull('os')
            ->groupBy('os')
            ->orderByDesc('count')
            ->get();

        // Recent views globally
        $recentViews = VideoView::with('video:token,name,uploaded_by')
            ->where('count_as_view', 1)
            ->orderByDesc('created_on')
            ->limit(50)
            ->get();

        return Inertia::render('Manager/Stats/Index', [
            'totalViews' => $totalViews,
            'totalReactions' => $totalReactions,
            'totalDuration' => $totalDuration,
            'totalVideos' => $totalVideos,
            'topVideos' => $topVideos,
            'recentViewsData' => $recentViewsData,
            'viewSources' => $viewSources,
            'deviceTypes' => $deviceTypes,
            'browsers' => $browsers,
            'operatingSystems' => $operatingSystems,
            'recentViews' => $recentViews,
        ]);
    }

    public function video(string $token)
    {
        $video = Video::where('token', $token)->firstOrFail();

        $viewsData = VideoView::select(
            DB::raw('DATE(created_on) as date'),
            DB::raw('COUNT(*) as views')
        )
            ->where('video_token', $token)
            ->where('count_as_view', 1)
            ->where('created_on', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $uniqueViewers = VideoView::where('video_token', $token)
            ->where('count_as_view', 1)
            ->whereNotNull('username')
            ->distinct('username')
            ->count('username');

        $averageWatchTime = VideoView::where('video_token', $token)
            ->where('count_as_view', 1)
            ->whereNotNull('watch_time')
            ->avg('watch_time') ?? 0;

        // View sources breakdown
        $viewSources = VideoView::select('view_source', DB::raw('COUNT(*) as count'))
            ->where('video_token', $token)
            ->where('count_as_view', 1)
            ->whereNotNull('view_source')
            ->groupBy('view_source')
            ->orderByDesc('count')
            ->get();

        // Device types breakdown
        $deviceTypes = VideoView::select('device_type', DB::raw('COUNT(*) as count'))
            ->where('video_token', $token)
            ->where('count_as_view', 1)
            ->whereNotNull('device_type')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();

        // Browsers breakdown
        $browsers = VideoView::select('browser', DB::raw('COUNT(*) as count'))
            ->where('video_token', $token)
            ->where('count_as_view', 1)
            ->whereNotNull('browser')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->get();

        // OS breakdown
        $operatingSystems = VideoView::select('os', DB::raw('COUNT(*) as count'))
            ->where('video_token', $token)
            ->where('count_as_view', 1)
            ->whereNotNull('os')
            ->groupBy('os')
            ->orderByDesc('count')
            ->get();

        // Recent views with details
        $recentViews = VideoView::where('video_token', $token)
            ->where('count_as_view', 1)
            ->orderByDesc('created_on')
            ->limit(50)
            ->get();

        return Inertia::render('Manager/Stats/Video', [
            'video' => $video,
            'viewsData' => $viewsData,
            'uniqueViewers' => $uniqueViewers,
            'averageWatchTime' => (int) $averageWatchTime,
            'viewSources' => $viewSources,
            'deviceTypes' => $deviceTypes,
            'browsers' => $browsers,
            'operatingSystems' => $operatingSystems,
            'recentViews' => $recentViews,
        ]);
    }
}
