<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * GET /api/tasks
     * Return the authenticated user's task queue (pending + today's completed).
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $queue = Task::forUser($userId)
            ->pending()
            ->orderBy('queue_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn($t) => $this->format($t));

        $completedToday = Task::forUser($userId)
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn($t) => $this->format($t));

        return response()->json([
            'queue'           => $queue,
            'completed_today' => $completedToday,
        ]);
    }

    /**
     * POST /api/tasks
     * Create a new task. Optionally start it immediately.
     *
     * Body: { title, notes?, estimated_minutes, start_now? }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'notes'             => 'nullable|string',
            'estimated_minutes' => 'required|integer|min:5|max:480',
            'start_now'         => 'boolean',
        ]);

        $userId = Auth::id();

        // Determine queue position (append to end)
        $maxOrder = Task::forUser($userId)->pending()->max('queue_order') ?? 0;

        $task = Task::create([
            'user_id'           => $userId,
            'title'             => $data['title'],
            'notes'             => $data['notes'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'],
            'actual_minutes'    => 0,
            'status'            => 'queued',
            'queue_order'       => $maxOrder + 1,
        ]);

        // "Add and Start Task" — activate immediately
        if (!empty($data['start_now'])) {
            $this->activateTask($task);
        }

        return response()->json($this->format($task), 201);
    }

    /**
     * GET /api/tasks/{task}
     */
    public function show(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        return response()->json($this->format($task));
    }

    /**
     * PATCH /api/tasks/{task}
     * Update title, notes, estimated_minutes, or queue_order.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $data = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'notes'             => 'nullable|string',
            'estimated_minutes' => 'sometimes|integer|min:5|max:480',
            'queue_order'       => 'sometimes|integer|min:0',
            'status'            => 'sometimes|in:queued,active,paused,completed',
        ]);

        // Handle status transitions
        if (isset($data['status'])) {
            $this->handleStatusTransition($task, $data['status']);
            unset($data['status']); // handled separately
        }

        $task->update($data);

        return response()->json($this->format($task->fresh()));
    }

    /**
     * POST /api/tasks/{task}/start
     * Start (or resume) a specific task. Pauses any other active task.
     */
    public function start(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        // Pause any currently active task for this user
        Task::forUser(Auth::id())
            ->where('status', 'active')
            ->where('id', '!=', $task->id)
            ->each(fn($t) => $this->handleStatusTransition($t, 'paused'));

        $this->activateTask($task);

        return response()->json($this->format($task->fresh()));
    }

    /**
     * POST /api/tasks/{task}/pause
     */
    public function pause(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $this->handleStatusTransition($task, 'paused');

        return response()->json($this->format($task->fresh()));
    }

    /**
     * POST /api/tasks/{task}/complete
     * Mark task completed and record actual elapsed minutes.
     *
     * Body: { actual_minutes? }
     */
    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $data = $request->validate([
            'actual_minutes' => 'sometimes|integer|min:0',
        ]);

        $task->update([
            'status'         => 'completed',
            'completed_at'   => now(),
            'actual_minutes' => $data['actual_minutes'] ?? $task->actual_minutes,
        ]);

        return response()->json($this->format($task->fresh()));
    }

    /**
     * DELETE /api/tasks/{task}
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorizeTask($task);

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    // ── Private helpers ────────────────────────────────────────────

    private function authorizeTask(Task $task): void
    {
        abort_if($task->user_id !== Auth::id(), 403, 'Forbidden');
    }

    private function activateTask(Task $task): void
    {
        $task->update([
            'status'     => 'active',
            'started_at' => $task->started_at ?? now(),
        ]);
    }

    private function handleStatusTransition(Task $task, string $newStatus): void
    {
        $updates = ['status' => $newStatus];

        match ($newStatus) {
            'active'    => $updates['started_at'] = $task->started_at ?? now(),
            'completed' => $updates['completed_at'] = now(),
            default     => null,
        };

        $task->update($updates);
    }

    private function format(Task $task): array
    {
        return [
            'id'                => $task->id,
            'title'             => $task->title,
            'notes'             => $task->notes,
            'estimated_minutes' => $task->estimated_minutes,
            'actual_minutes'    => $task->actual_minutes,
            'sprint_count'      => $task->sprintCount(),
            'status'            => $task->status,
            'queue_order'       => $task->queue_order,
            'started_at'        => $task->started_at?->toIso8601String(),
            'completed_at'      => $task->completed_at?->toIso8601String(),
            'created_at'        => $task->created_at->toIso8601String(),
        ];
    }
}