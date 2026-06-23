<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\FocusSession;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * GET /api/tasks
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $queue = Task::where('user_id', $userId)
            ->where('status', '!=', 'completed')
            ->orderBy('created_at')
            ->with('focusSessions')
            ->get()
            ->map(fn ($t) => $this->format($t));

        $completedToday = Task::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->orderByDesc('completed_at')
            ->with('focusSessions')
            ->get()
            ->map(fn ($t) => $this->format($t));

        return response()->json([
            'queue' => $queue,
            'completed_today' => $completedToday,
        ]);
    }

    /**
     * POST /api/tasks
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'estimated_minutes' => 'required|integer|min:5|max:480',
            'start_now' => 'boolean',
        ]);

        $userId = Auth::id();

        $task = DB::transaction(function () use ($userId, $data) {

            $task = Task::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'estimated_minutes' => $data['estimated_minutes'],
                'status' => 'queued',
            ]);

            if (!empty($data['start_now'])) {
                $this->pauseActiveTasksForUser($userId, $task->id);
                $this->activateTask($task);
            }

            return $task;
        });

        return response()->json(
            $this->format($task->load('focusSessions')),
            201
        );
    }

    /**
     * GET /api/tasks/{task}
     */
    public function show(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        return response()->json(
            $this->format($task->load('focusSessions'))
        );
    }

    /**
     * PATCH /api/tasks/{task}
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
            'estimated_minutes' => 'sometimes|integer|min:5|max:480',
            'status' => 'sometimes|in:queued,active,paused,completed',
        ]);

        DB::transaction(function () use ($task, $data) {

            if (isset($data['status'])) {

                if ($data['status'] === 'active') {
                    $this->pauseActiveTasksForUser(Auth::id(), $task->id);
                    $this->activateTask($task);
                }

                if ($data['status'] === 'paused') {
                    $this->closeActiveFocusSession($task);
                    $task->update(['status' => 'paused']);
                }

                if ($data['status'] === 'completed') {
                    $this->closeActiveFocusSession($task, true);

                    $task->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }

                unset($data['status']);
            }

            if (!empty($data)) {
                $task->update($data);
            }
        });

        return response()->json(
            $this->format($task->fresh('focusSessions'))
        );
    }

    /**
     * POST /api/tasks/{task}/start
     */
    public function start(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        DB::transaction(function () use ($task) {
            $this->pauseActiveTasksForUser(Auth::id(), $task->id);
            $this->activateTask($task);
        });

        return response()->json(
            $this->format($task->fresh('focusSessions'))
        );
    }

    /**
     * POST /api/tasks/{task}/pause
     */
    public function pause(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        DB::transaction(function () use ($task) {
            $this->closeActiveFocusSession($task);
            $task->update(['status' => 'paused']);
        });

        return response()->json(
            $this->format($task->fresh('focusSessions'))
        );
    }

    /**
     * POST /api/tasks/{task}/complete
     */
    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $data = $request->validate([
            'was_sprint_finished' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($task, $data) {

            $this->closeActiveFocusSession(
                $task,
                $data['was_sprint_finished'] ?? false
            );

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        return response()->json(
            $this->format($task->fresh('focusSessions'))
        );
    }

    /**
     * DELETE /api/tasks/{task}
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $task->delete();

        return response()->json([
            'message' => 'Task deleted.'
        ]);
    }

    // =========================
    // PRIVATE METHODS
    // =========================

    private function authorizeTask(Task $task): void
    {
        abort_if($task->user_id !== Auth::id(), 403, 'Forbidden');
    }

    /**
     * 🔥 SAFE: uses user settings for sprint duration
     */
    private function getSprintDurationSeconds(): int
    {
        $settings = UserSetting::where('user_id', Auth::id())->first();

        $minutes = $settings?->preferred_sprint_duration ?? 25;

        return $minutes * 60;
    }

    private function activateTask(Task $task): void
    {
        $task->update([
            'status' => 'active',
            'started_at' => $task->started_at ?? now(),
        ]);

        FocusSession::create([
            'task_id' => $task->id,
            'started_at' => now(),
        ]);
    }

    private function closeActiveFocusSession(Task $task, bool $isCompletedSprint = false): void
    {
        $session = FocusSession::where('task_id', $task->id)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if (!$session) return;

        $endedAt = now();

        $session->update([
            'ended_at' => $endedAt,
            'duration_seconds' => $endedAt->diffInSeconds($session->started_at),
            'is_completed_sprint' => $isCompletedSprint,
        ]);
    }

    private function pauseActiveTasksForUser(int $userId, int $excludeTaskId): void
    {
        $tasks = Task::where('user_id', $userId)
            ->where('status', 'active')
            ->where('id', '!=', $excludeTaskId)
            ->get();

        foreach ($tasks as $task) {
            $this->closeActiveFocusSession($task);
            $task->update(['status' => 'paused']);
        }
    }

    /**
     * FIXED FORMAT WITH USER SPRINT CONFIG
     */
    private function format(Task $task): array
    {
        $sprintDuration = $this->getSprintDurationSeconds();

        $totalSeconds = $task->focusSessions->sum('duration_seconds');

        $completedSprints = $sprintDuration > 0
            ? intdiv($totalSeconds, $sprintDuration)
            : 0;

        $currentSprintSeconds = $sprintDuration > 0
            ? $totalSeconds % $sprintDuration
            : 0;

        $currentSprintProgress = $sprintDuration > 0
            ? round(($currentSprintSeconds / $sprintDuration) * 100)
            : 0;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'notes' => $task->notes,
            'estimated_minutes' => $task->estimated_minutes,

            'actual_minutes' => round($totalSeconds / 60, 2),

            // USER CONFIG BASED
            'sprint_duration_minutes' => $sprintDuration / 60,

            'completed_sprints' => $completedSprints,
            'current_sprint_seconds' => $currentSprintSeconds,
            'current_sprint_progress' => $currentSprintProgress,

            'status' => $task->status,

            'started_at' => optional($task->started_at)?->toIso8601String(),
            'completed_at' => optional($task->completed_at)?->toIso8601String(),
            'created_at' => optional($task->created_at)?->toIso8601String(),
        ];
    }
}