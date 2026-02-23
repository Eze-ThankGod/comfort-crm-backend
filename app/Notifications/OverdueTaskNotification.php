<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueTaskNotification extends Notification
{

    public function __construct(protected Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠️ Overdue Task: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->error()
            ->line("The following task is **overdue** and has not been completed:")
            ->line("**{$this->task->title}**")
            ->line("Lead: " . ($this->task->lead?->name ?? 'N/A'))
            ->line("Was due: " . $this->task->due_date->format('d M Y H:i'))
            ->action('View Task', url("/tasks/{$this->task->id}"))
            ->line('Please update the task status as soon as possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'overdue_task',
            'title'     => "Overdue Task: {$this->task->title}",
            'message'   => "Your task '{$this->task->title}' is overdue (was due " . $this->task->due_date->format('d M Y H:i') . ')',
            'task_id'   => $this->task->id,
            'lead_id'   => $this->task->lead_id,
            'lead_name' => $this->task->lead?->name,
            'due_date'  => $this->task->due_date?->toDateTimeString(),
        ];
    }
}
