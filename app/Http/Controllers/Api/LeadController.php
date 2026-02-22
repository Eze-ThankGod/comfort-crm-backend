<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\ActivityService;
use App\Services\AutomationService;
use App\Services\LeadImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        protected ActivityService $activityService,
        protected AutomationService $automationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::with(['assignedAgent:id,name,email', 'creator:id,name']);

        // Scope agents to their own leads
        if (auth()->user()->isAgent()) {
            $query->forAgent(auth()->id());
        }

        // Filters
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_to');
        }

        $leads = $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_dir', 'desc'))
                       ->paginate($request->integer('per_page', 20));

        return $this->success($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email',
            'source'        => 'nullable|in:website,csv_import,portal,referral,whatsapp,social_media,cold_call,other',
            'status'        => 'nullable|in:new,contacted,interested,viewing,won,lost',
            'assigned_to'   => 'nullable|exists:users,id',
            'property_type' => 'nullable|string|max:100',
            'finishing_type'=> 'nullable|string|max:100',
            'budget_min'    => 'nullable|numeric|min:0',
            'budget_max'    => 'nullable|numeric|min:0',
            'budget'        => 'nullable|numeric|min:0',
            'location'      => 'nullable|string|max:150',
            'preferred_location' => 'nullable|string|max:150',
            'intent'        => 'nullable|in:invest,move_in',
            'inspection_at' => 'nullable|date',
            'notes'         => 'nullable|string',
            'tags'          => 'nullable|array',
            'tags.*'        => 'string',
        ]);

        $data['created_by'] = auth()->id();

        $lead = Lead::create($data);

        $this->activityService->leadCreated($lead);
        $this->automationService->run('lead_created', ['lead' => $lead]);

        if ($lead->assigned_to) {
            $this->automationService->run('lead_assigned', ['lead' => $lead]);
        }

        return $this->success($lead->load(['assignedAgent:id,name', 'creator:id,name']), 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $lead->load([
            'assignedAgent:id,name,email,phone',
            'creator:id,name',
            'tasks' => fn($q) => $q->orderBy('due_date')->with('assignedAgent:id,name'),
            'callLogs' => fn($q) => $q->orderByDesc('called_at')->with('agent:id,name'),
            'activities' => fn($q) => $q->orderByDesc('created_at')->with('user:id,name'),
        ]);

        return $this->success($lead);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email',
            'source'        => 'nullable|in:website,csv_import,portal,referral,whatsapp,social_media,cold_call,other',
            'status'        => 'nullable|in:new,contacted,interested,viewing,won,lost',
            'assigned_to'   => 'nullable|exists:users,id',
            'property_type' => 'nullable|string|max:100',
            'finishing_type'=> 'nullable|string|max:100',
            'budget_min'    => 'nullable|numeric|min:0',
            'budget_max'    => 'nullable|numeric|min:0',
            'budget'        => 'nullable|numeric|min:0',
            'location'      => 'nullable|string|max:150',
            'preferred_location' => 'nullable|string|max:150',
            'intent'        => 'nullable|in:invest,move_in',
            'inspection_at' => 'nullable|date',
            'notes'         => 'nullable|string',
            'tags'          => 'nullable|array',
        ]);

        $oldStatus    = $lead->status;
        $oldAssignedTo = $lead->assigned_to;

        $lead->update($data);

        // Log activity for status change
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->activityService->leadStatusChanged($lead, $oldStatus, $data['status']);
            $this->automationService->run('lead_status_changed', [
                'lead'       => $lead,
                'old_status' => $oldStatus,
                'new_status' => $data['status'],
            ]);
        }

        // Log activity for assignment change
        if (isset($data['assigned_to']) && $data['assigned_to'] !== $oldAssignedTo) {
            $this->activityService->leadAssigned($lead, $oldAssignedTo, $data['assigned_to']);
            $this->automationService->run('lead_assigned', ['lead' => $lead]);
        }

        if (! isset($data['status']) && ! isset($data['assigned_to'])) {
            $this->activityService->leadUpdated($lead, $data);
        }

        return $this->success($lead->load(['assignedAgent:id,name', 'creator:id,name']));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->json(['status' => 'success', 'message' => 'Lead deleted successfully']);
    }

    public function assign(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('assign', Lead::class);

        $data = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $oldAgent = $lead->assigned_to;
        $lead->update(['assigned_to' => $data['assigned_to']]);

        $this->activityService->leadAssigned($lead, $oldAgent, $data['assigned_to']);
        $this->automationService->run('lead_assigned', ['lead' => $lead]);

        return $this->success([
            'message' => 'Lead assigned successfully',
            'lead'    => $lead->load('assignedAgent:id,name'),
        ]);
    }

    public function import(Request $request, LeadImportService $importService): JsonResponse
    {
        $this->authorize('import', Lead::class);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $importService->importCsv($request->file('file'), auth()->user());

        if ($result['imported'] > 0) {
            $this->activityService->leadsImported($result['imported']);
            $this->automationService->run('lead_imported', ['count' => $result['imported']]);
        }

        return $this->success($result, $result['imported'] > 0 ? 200 : 422);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('assign', Lead::class);

        $data = $request->validate([
            'lead_ids'    => 'required|array',
            'lead_ids.*'  => 'integer|exists:leads,id',
            'assigned_to' => 'required|exists:users,id',
        ]);

        Lead::whereIn('id', $data['lead_ids'])
            ->update(['assigned_to' => $data['assigned_to']]);

        return $this->success([
            'message' => count($data['lead_ids']) . ' leads assigned successfully',
        ]);
    }
}
