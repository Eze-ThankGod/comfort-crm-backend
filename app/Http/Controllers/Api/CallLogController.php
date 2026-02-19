<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use App\Services\ActivityService;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    public function __construct(
        protected ActivityService $activityService,
        protected AutomationService $automationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CallLog::with(['lead:id,name,phone', 'agent:id,name']);

        if (auth()->user()->isAgent()) {
            $query->forAgent(auth()->id());
        } else {
            if ($request->filled('agent_id')) {
                $query->forAgent($request->agent_id);
            }
        }

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }

        if ($request->boolean('today')) {
            $query->today();
        }

        if ($request->filled('date_from')) {
            $query->whereDate('called_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('called_at', '<=', $request->date_to);
        }

        $logs = $query->orderByDesc('called_at')
                      ->paginate($request->integer('per_page', 20));

        return response()->json($logs);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id'  => 'required|exists:leads,id',
            'outcome'  => 'required|in:no_answer,interested,not_interested,callback,voicemail,wrong_number,busy',
            'duration' => 'nullable|integer|min:0',
            'notes'    => 'nullable|string',
            'called_at'=> 'nullable|date',
        ]);

        $lead = Lead::findOrFail($data['lead_id']);

        // Agents can only log calls for their assigned leads
        if (auth()->user()->isAgent() && $lead->assigned_to !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $log = CallLog::create([
            ...$data,
            'agent_id'  => auth()->id(),
            'called_at' => $data['called_at'] ?? now(),
        ]);

        // Update lead last contacted timestamp
        $lead->update(['last_contacted_at' => $log->called_at]);

        // Log activity
        $this->activityService->callLogged($lead, $data['outcome']);

        // Run automation for call outcome
        $this->automationService->run('call_outcome', [
            'lead'    => $lead,
            'outcome' => $data['outcome'],
        ]);

        return response()->json($log->load(['lead:id,name', 'agent:id,name']), 201);
    }

    public function show(CallLog $callLog): JsonResponse
    {
        // Agents can only view their own call logs
        if (auth()->user()->isAgent() && $callLog->agent_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($callLog->load(['lead:id,name,phone', 'agent:id,name']));
    }

    public function update(Request $request, CallLog $callLog): JsonResponse
    {
        if (auth()->user()->isAgent() && $callLog->agent_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'outcome'  => 'sometimes|in:no_answer,interested,not_interested,callback,voicemail,wrong_number,busy',
            'duration' => 'nullable|integer|min:0',
            'notes'    => 'nullable|string',
        ]);

        $callLog->update($data);

        return response()->json($callLog->load(['lead:id,name', 'agent:id,name']));
    }

    public function destroy(CallLog $callLog): JsonResponse
    {
        if (! auth()->user()->isAdminOrManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $callLog->delete();

        return response()->json(['message' => 'Call log deleted']);
    }
}
