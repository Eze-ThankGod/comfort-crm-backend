<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\OverdueTaskNotification;
use App\Notifications\TaskReminderNotification;
use App\Services\ActivityService;
use Illuminate\Console\Command;

class ProcessTaskReminders extends Command
{
    protected $signature   = 'crm:task-reminders';
    protected $description = 'Send reminders for upcoming tasks and mark overdue tasks';

    public function __construct(protected ActivityService $activityService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sendUpcomingReminders();
        $this->markOverdueTasks();

        return Command::SUCCESS;
    }

    private function sendUpcomingReminders(): void
    {
        // Tasks due in the next 30 minutes that haven't had a reminder sent
        $upcoming = Task::with(['assignedAgent', 'lead'])
            ->where('status', 'pending')
            ->where('reminder_sent', false)
            ->whereBetween('due_date', [now(), now()->addMinutes(30)])
            ->get();

        foreach ($upcoming as $task) {
            try {
                $task->assignedAgent?->notify(new TaskReminderNotification($task));
                $task->update(['reminder_sent' => true]);
                $this->info("Reminder sent for task #{$task->id}: {$task->title}");
            } catch (\Throwable $e) {
                $this->error("Failed for task #{$task->id}: " . $e->getMessage());
            }
        }

        $this->info("Processed {$upcoming->count()} upcoming task reminders.");
    }

    private function markOverdueTasks(): void
    {
        // Tasks that are past due and still pending
        $overdue = Task::with(['assignedAgent', 'lead'])
            ->where('status', 'pending')
            ->where('due_date', '<', now()->subMinutes(5)) // 5 min grace period
            ->get();

        foreach ($overdue as $task) {
            try {
                $task->update(['status' => 'missed']);
                $task->assignedAgent?->notify(new OverdueTaskNotification($task));
                $this->activityService->taskMissed($task);
                $this->info("Task #{$task->id} marked as missed.");
            } catch (\Throwable $e) {
                $this->error("Failed for task #{$task->id}: " . $e->getMessage());
            }
        }

        $this->info("Processed {$overdue->count()} overdue tasks.");
    }
}
