<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $rules = AutomationRule::with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'description'  => 'nullable|string',
            'trigger_type' => 'required|in:lead_created,lead_status_changed,task_missed,lead_assigned,no_contact_days,call_outcome',
            'conditions'   => 'nullable|array',
            'conditions.*.field'    => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string|in:=,!=,>,<,contains,in',
            'conditions.*.value'    => 'required_with:conditions',
            'actions'      => 'required|array|min:1',
            'actions.*.type' => 'required|string|in:create_task,assign_lead,change_status,send_whatsapp',
            'is_active'    => 'boolean',
        ]);

        $rule = AutomationRule::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        return $this->success($rule, 201);
    }

    public function show(AutomationRule $automationRule): JsonResponse
    {
        return $this->success($automationRule->load('creator:id,name'));
    }

    public function update(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:150',
            'description'  => 'nullable|string',
            'trigger_type' => 'sometimes|in:lead_created,lead_status_changed,task_missed,lead_assigned,no_contact_days,call_outcome',
            'conditions'   => 'nullable|array',
            'actions'      => 'sometimes|array|min:1',
            'is_active'    => 'boolean',
        ]);

        $automationRule->update($data);

        return $this->success($automationRule);
    }

    public function destroy(AutomationRule $automationRule): JsonResponse
    {
        $automationRule->delete();

        return response()->json(['status' => 'success', 'message' => 'Automation rule deleted']);
    }

    public function toggle(AutomationRule $automationRule): JsonResponse
    {
        $automationRule->update(['is_active' => ! $automationRule->is_active]);

        return $this->success([
            'message'   => 'Rule ' . ($automationRule->is_active ? 'activated' : 'deactivated'),
            'is_active' => $automationRule->is_active,
        ]);
    }
}
