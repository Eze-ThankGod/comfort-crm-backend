<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Lead;
use App\Services\ActivityService;
use App\Services\AutomationService;
use App\Notifications\TaskReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected ActivityService $activityService,
        protected AutomationService $automationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::with(['lead:id,name,phone', 'assignedAgent:id,name']);

        if (auth()->user()->isAgent()) {
            $query->forAgent(auth()->id());
        } else {
            if ($request->filled('assigned_to')) {
                $query->forAgent($request->assigned_to);
            }
        }

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('today')) {
            $query->dueToday();
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        $tasks = $query->orderBy('due_date')
                       ->paginate($request->integer('per_page', 20));

        return $this->success($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'lead_id'     => 'required|exists:leads,id',
            'assigned_to' => 'required|exists:users,id',
            'type'        => 'required|in:call,follow_up,site_visit,whatsapp',
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'due_date'    => 'required|date|after:now',
        ]);

        // Ensure agent can only create tasks on their own leads
        if (auth()->user()->isAgent()) {
            $lead = Lead::find($data['lead_id']);
            if ($lead && $lead->assigned_to !== auth()->id()) {
                return $this->error('Forbidden', 403);
            }
            $data['assigned_to'] = auth()->id();
        }

        $data['created_by'] = auth()->id();

        $task = Task::create($data);

        $this->activityService->taskCreated($task);

        return $this->success($task->load(['lead:id,name', 'assignedAgent:id,name']), 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load(['lead:id,name,phone', 'assignedAgent:id,name', 'creator:id,name']);

        return $this->success($task);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'assigned_to' => 'sometimes|exists:users,id',
            'type'        => 'sometimes|in:call,follow_up,site_visit,whatsapp',
            'title'       => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'due_date'    => 'sometimes|date',
            'status'      => 'sometimes|in:pending,completed,missed',
        ]);

        $wasCompleted = isset($data['status']) && $data['status'] === 'completed' && $task->status !== 'completed';

        if ($wasCompleted) {
            $data['completed_at'] = now();
        }

        $task->update($data);

        if ($wasCompleted) {
            $this->activityService->taskCompleted($task);
        }

        return $this->success($task->load(['lead:id,name', 'assignedAgent:id,name']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['status' => 'success', 'message' => 'Task deleted successfully']);
    }

    public function complete(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->status === 'completed') {
            return $this->error('Task already completed', 400);
        }

        $task->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        $this->activityService->taskCompleted($task);

        return response()->json([
            'status'  => 'success',
            'message' => 'Task marked as completed',
            'data'    => $task,
        ]);
    }
}
