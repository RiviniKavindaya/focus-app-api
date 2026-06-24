<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\FocusSession;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;


class ProgressController extends Controller
{
   public function index(): JsonResponse
{
    \Log::info('Progress call');
    try {

        $userId = Auth::id();

        \Log::info('Progress Debug - User', [
            'user_id' => $userId,
        ]);

        $settings = UserSetting::where('user_id', $userId)->first();

        \Log::info('Progress Debug - Settings', [
            'settings' => $settings,
        ]);

        $dailyTargetMinutes  = $settings?->daily_target_minutes ?? 120;
        $weeklyTargetMinutes = $settings?->weekly_target_minutes ?? 600;

        \Log::info('Progress Debug - Targets', [
            'daily_target' => $dailyTargetMinutes,
            'weekly_target' => $weeklyTargetMinutes,
        ]);

        $taskIds = Task::where('user_id', $userId)->pluck('id');

        \Log::info('Progress Debug - Task IDs', [
            'task_ids' => $taskIds->toArray(),
        ]);

        $todaySeconds = FocusSession::whereIn('task_id', $taskIds)
            ->whereNotNull('ended_at')
            ->whereDate('started_at', today())
            ->sum('duration_seconds');

        \Log::info('Progress Debug - Today Seconds', [
            'today_seconds' => $todaySeconds,
        ]);

        $weekSeconds = FocusSession::whereIn('task_id', $taskIds)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->sum('duration_seconds');

        \Log::info('Progress Debug - Week Seconds', [
            'week_seconds' => $weekSeconds,
        ]);

        $tasksCompletedToday = Task::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        \Log::info('Progress Debug - Completed Today', [
            'tasks_completed_today' => $tasksCompletedToday,
        ]);

        $tasksTotalToday = Task::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        \Log::info('Progress Debug - Created Today', [
            'tasks_total_today' => $tasksTotalToday,
        ]);

        $todayMinutes = round($todaySeconds / 60, 1);
        $weekMinutes  = round($weekSeconds / 60, 1);

        \Log::info('Progress Debug - Final Values', [
            'today_minutes' => $todayMinutes,
            'week_minutes' => $weekMinutes,
        ]);

        return response()->json([
            'focus_minutes_today' => $todayMinutes,
            'focus_minutes_week' => $weekMinutes,
            'daily_target_minutes' => $dailyTargetMinutes,
            'weekly_target_minutes' => $weeklyTargetMinutes,
            'tasks_completed_today' => $tasksCompletedToday,
            'tasks_created_today' => $tasksTotalToday,
            'daily_progress_pct' => $dailyTargetMinutes > 0
                ? min(100, round(($todayMinutes / $dailyTargetMinutes) * 100))
                : 0,
            'weekly_progress_pct' => $weeklyTargetMinutes > 0
                ? min(100, round(($weekMinutes / $weeklyTargetMinutes) * 100))
                : 0,
        ]);

    } catch (\Exception $e) {

        \Log::error('Progress API Error', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
