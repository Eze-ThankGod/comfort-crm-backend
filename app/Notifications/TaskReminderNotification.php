<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Task Reminder: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have a task due soon:")
            ->line("**{$this->task->title}**")
            ->line("Lead: " . ($this->task->lead?->name ?? 'N/A'))
            ->line("Type: " . str_replace('_', ' ', ucfirst($this->task->type)))
            ->line("Due: " . $this->task->due_date->format('d M Y H:i'))
            ->action('View Task', url("/tasks/{$this->task->id}"))
            ->line('Please complete this task on time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'task_reminder',
            'title'     => "Task Due: {$this->task->title}",
            'message'   => "Your task '{$this->task->title}' is due at " . $this->task->due_date->format('H:i'),
            'task_id'   => $this->task->id,
            'lead_id'   => $this->task->lead_id,
            'lead_name' => $this->task->lead?->name,
            'due_date'  => $this->task->due_date?->toDateTimeString(),
        ];
    }
}
