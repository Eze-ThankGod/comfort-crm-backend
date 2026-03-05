<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification
{

    public function __construct(protected Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'fcm'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Lead Assigned: {$this->lead->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new lead has been assigned to you:")
            ->line("**{$this->lead->name}**")
            ->line("Phone: " . ($this->lead->phone ?? 'N/A'))
            ->line("Source: " . ucfirst($this->lead->source))
            ->action('View Lead', url("/leads/{$this->lead->id}"))
            ->line('Please follow up with this lead promptly.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'lead_assigned',
            'title'     => "New Lead Assigned: {$this->lead->name}",
            'message'   => "Lead '{$this->lead->name}' has been assigned to you.",
            'lead_id'   => $this->lead->id,
            'lead_name' => $this->lead->name,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'to' => $notifiable->device_token,
            'notification' => [
                'title' => "New Lead: {$this->lead->name}",
                'body'  => "A new lead has been assigned to you. Phone: " . ($this->lead->phone ?? 'N/A'),
            ],
            'data' => [
                'type'    => 'lead_assigned',
                'lead_id' => (string) $this->lead->id,
            ],
        ];
    }
}
