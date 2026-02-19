<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        protected Lead $lead,
        protected string $message,
        protected ?int $sentBy = null,
    ) {}

    public function handle(WhatsAppService $service): void
    {
        $service->send($this->lead, $this->message, $this->sentBy);
    }
}
