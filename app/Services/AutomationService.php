<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    public function __construct(protected ActivityService $activityService) {}

    public function run(string $triggerType, array $context = []): void
    {
        $rules = AutomationRule::active()->forTrigger($triggerType)->get();

        foreach ($rules as $rule) {
            try {
                if ($this->evaluateConditions($rule->conditions ?? [], $context)) {
                    $this->executeActions($rule->actions, $context);
                }
            } catch (\Throwable $e) {
                Log::error("Automation rule #{$rule->id} failed: " . $e->getMessage());
            }
        }
    }

    private function evaluateConditions(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field    = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value    = $condition['value'] ?? null;
            $actual   = data_get($context, $field);

            $result = match ($operator) {
                '='        => $actual == $value,
                '!='       => $actual != $value,
                '>'        => $actual > $value,
                '<'        => $actual < $value,
                'contains' => str_contains((string)$actual, (string)$value),
                'in'       => in_array($actual, (array)$value),
                default    => false,
            };

            if (! $result) {
                return false;
            }
        }

        return true;
    }

    private function executeActions(array $actions, array $context): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? null;

            match ($action['type'] ?? '') {
                'create_task'      => $this->createTask($action, $context),
                'assign_lead'      => $this->assignLead($action, $context),
                'change_status'    => $this->changeLeadStatus($action, $context),
                'send_whatsapp'    => $this->scheduleWhatsApp($action, $context),
                default            => Log::warning("Unknown automation action: {$type}"),
            };
        }
    }

    private function createTask(array $action, array $context): void
    {
        $lead = $context['lead'] ?? null;
        if (! $lead instanceof Lead) {
            return;
        }

        $assignedTo = $action['assigned_to'] ?? $lead->assigned_to;
        if (! $assignedTo) {
            return;
        }

        $task = Task::create([
            'lead_id'     => $lead->id,
            'assigned_to' => $assignedTo,
            'created_by'  => $lead->created_by,
            'type'        => $action['task_type'] ?? 'follow_up',
            'title'       => $action['title'] ?? 'Follow up',
            'description' => $action['description'] ?? null,
            'due_date'    => now()->addHours($action['delay_hours'] ?? 24),
            'status'      => 'pending',
        ]);

        $this->activityService->taskCreated($task);
    }

    private function assignLead(array $action, array $context): void
    {
        $lead = $context['lead'] ?? null;
        if (! $lead instanceof Lead) {
            return;
        }

        $strategy = $action['strategy'] ?? 'specific';

        if ($strategy === 'round_robin') {
            $agent = User::where('role', 'agent')
                         ->where('is_active', true)
                         ->inRandomOrder()
                         ->first();
        } else {
            $agent = User::find($action['agent_id'] ?? null);
        }

        if ($agent) {
            $oldAgent = $lead->assigned_to;
            $lead->update(['assigned_to' => $agent->id]);
            $this->activityService->leadAssigned($lead, $oldAgent, $agent->id);
        }
    }

    private function changeLeadStatus(array $action, array $context): void
    {
        $lead = $context['lead'] ?? null;
        if (! $lead instanceof Lead) {
            return;
        }

        $oldStatus = $lead->status;
        $newStatus = $action['status'] ?? null;

        if ($newStatus && $oldStatus !== $newStatus) {
            $lead->update(['status' => $newStatus]);
            $this->activityService->leadStatusChanged($lead, $oldStatus, $newStatus);
        }
    }

    private function scheduleWhatsApp(array $action, array $context): void
    {
        $lead = $context['lead'] ?? null;
        if (! $lead instanceof Lead || ! $lead->phone) {
            return;
        }

        // Dispatch a job for actual sending (handled by WhatsApp service)
        \App\Jobs\SendWhatsAppMessage::dispatch($lead, $action['message'] ?? '');
    }
}
