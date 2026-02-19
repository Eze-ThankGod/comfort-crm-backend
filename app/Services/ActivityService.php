<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;

class ActivityService
{
    public function log(
        string $type,
        string $description,
        ?Lead $lead = null,
        ?User $user = null,
        array $properties = []
    ): Activity {
        return Activity::create([
            'lead_id'     => $lead?->id,
            'user_id'     => $user?->id ?? auth()->id(),
            'type'        => $type,
            'description' => $description,
            'properties'  => $properties,
        ]);
    }

    public function leadCreated(Lead $lead): void
    {
        $this->log('lead_created', "Lead '{$lead->name}' was created", $lead, properties: [
            'lead_name' => $lead->name,
            'source'    => $lead->source,
        ]);
    }

    public function leadUpdated(Lead $lead, array $changes): void
    {
        $this->log('lead_updated', "Lead '{$lead->name}' was updated", $lead, properties: [
            'changes' => $changes,
        ]);
    }

    public function leadStatusChanged(Lead $lead, string $oldStatus, string $newStatus): void
    {
        $this->log('lead_status_changed', "Lead '{$lead->name}' status changed from '{$oldStatus}' to '{$newStatus}'", $lead, properties: [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }

    public function leadAssigned(Lead $lead, ?int $oldAgentId, int $newAgentId): void
    {
        $newAgent = User::find($newAgentId);
        $this->log('lead_assigned', "Lead '{$lead->name}' assigned to '{$newAgent?->name}'", $lead, properties: [
            'old_agent_id' => $oldAgentId,
            'new_agent_id' => $newAgentId,
        ]);
    }

    public function callLogged(Lead $lead, string $outcome): void
    {
        $this->log('call_logged', "Call logged for lead '{$lead->name}' - outcome: {$outcome}", $lead, properties: [
            'outcome' => $outcome,
        ]);
    }

    public function taskCreated(Task $task): void
    {
        $this->log('task_created', "Task '{$task->title}' created for lead", $task->lead, properties: [
            'task_id'   => $task->id,
            'task_type' => $task->type,
            'due_date'  => $task->due_date?->toDateTimeString(),
        ]);
    }

    public function taskCompleted(Task $task): void
    {
        $this->log('task_completed', "Task '{$task->title}' was completed", $task->lead, properties: [
            'task_id'      => $task->id,
            'completed_at' => now()->toDateTimeString(),
        ]);
    }

    public function taskMissed(Task $task): void
    {
        $this->log('task_missed', "Task '{$task->title}' was missed (overdue)", $task->lead, properties: [
            'task_id'  => $task->id,
            'due_date' => $task->due_date?->toDateTimeString(),
        ]);
    }

    public function noteAdded(Lead $lead, string $note): void
    {
        $this->log('note_added', "Note added to lead '{$lead->name}'", $lead, properties: [
            'note_preview' => substr($note, 0, 100),
        ]);
    }

    public function whatsappSent(Lead $lead, string $message): void
    {
        $this->log('whatsapp_sent', "WhatsApp message sent to lead '{$lead->name}'", $lead, properties: [
            'message_preview' => substr($message, 0, 100),
        ]);
    }

    public function leadsImported(int $count): void
    {
        $this->log('lead_imported', "{$count} leads were imported", properties: [
            'count' => $count,
        ]);
    }
}
