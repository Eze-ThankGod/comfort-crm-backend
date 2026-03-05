<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    public function __construct(protected Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'fcm'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Task Assigned: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new task has been assigned to you:")
            ->line("**{$this->task->title}**")
            ->line("Type: " . ucfirst(str_replace('_', ' ', $this->task->type)))
            ->line("Lead: " . ($this->task->lead?->name ?? 'N/A'))
            ->line("Due: " . $this->task->due_date?->format('d M Y H:i'))
            ->action('View Task', url("/tasks/{$this->task->id}"))
            ->line('Please complete this task before the due date.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'task_assigned',
            'title'     => "New Task: {$this->task->title}",
            'message'   => "A task has been assigned to you: '{$this->task->title}' due " . $this->task->due_date?->format('d M Y H:i'),
            'task_id'   => $this->task->id,
            'lead_id'   => $this->task->lead_id,
            'lead_name' => $this->task->lead?->name,
            'due_date'  => $this->task->due_date?->toDateTimeString(),
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'to' => $notifiable->device_token,
            'notification' => [
                'title' => "New Task: {$this->task->title}",
                'body'  => "Due " . $this->task->due_date?->format('d M Y H:i') . " — " . ($this->task->lead?->name ?? 'No lead'),
            ],
            'data' => [
                'type'    => 'task_assigned',
                'task_id' => (string) $this->task->id,
                'lead_id' => (string) $this->task->lead_id,
            ],
        ];
    }
}
