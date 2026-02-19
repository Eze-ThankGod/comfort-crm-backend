<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $token;
    protected string $phoneNumberId;

    public function __construct()
    {
        $this->apiUrl        = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->token         = config('services.whatsapp.token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
    }

    public function send(Lead $lead, string $message, ?int $sentBy = null): WhatsAppMessage
    {
        $record = WhatsAppMessage::create([
            'lead_id'   => $lead->id,
            'sent_by'   => $sentBy ?? auth()->id(),
            'direction' => 'outbound',
            'message'   => $message,
            'status'    => 'pending',
            'sent_at'   => now(),
        ]);

        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $this->normalizePhone($lead->phone),
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $record->update([
                    'status'               => 'sent',
                    'whatsapp_message_id'  => data_get($data, 'messages.0.id'),
                    'metadata'             => $data,
                ]);
            } else {
                $record->update([
                    'status'   => 'failed',
                    'metadata' => $response->json(),
                ]);
                Log::error('WhatsApp send failed', ['response' => $response->json()]);
            }
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed']);
            Log::error('WhatsApp exception: ' . $e->getMessage());
        }

        return $record->fresh();
    }

    public function handleWebhook(array $payload): void
    {
        $entries = data_get($payload, 'entry', []);

        foreach ($entries as $entry) {
            $changes = data_get($entry, 'changes', []);

            foreach ($changes as $change) {
                $messages = data_get($change, 'value.messages', []);

                foreach ($messages as $msg) {
                    $this->handleInboundMessage($msg, $change);
                }

                // Handle status updates
                $statuses = data_get($change, 'value.statuses', []);
                foreach ($statuses as $status) {
                    $this->updateMessageStatus($status);
                }
            }
        }
    }

    private function handleInboundMessage(array $msg, array $change): void
    {
        $from    = $msg['from'] ?? null;
        $text    = data_get($msg, 'text.body', '');
        $msgId   = $msg['id'] ?? null;

        if (! $from) {
            return;
        }

        $lead = Lead::where('phone', 'like', '%' . substr($from, -9))->first();

        if ($lead) {
            WhatsAppMessage::create([
                'lead_id'              => $lead->id,
                'direction'            => 'inbound',
                'message'              => $text,
                'status'               => 'delivered',
                'whatsapp_message_id'  => $msgId,
                'sent_at'              => now(),
                'metadata'             => $change,
            ]);
        }
    }

    private function updateMessageStatus(array $status): void
    {
        $msgId     = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        if ($msgId && $newStatus) {
            WhatsAppMessage::where('whatsapp_message_id', $msgId)
                ->update(['status' => $newStatus]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        // Strip spaces, dashes and leading zeros; keep + prefix
        $phone = preg_replace('/[\s\-()]/', '', $phone);
        if (! str_starts_with($phone, '+')) {
            $phone = '+' . ltrim($phone, '0');
        }
        return $phone;
    }
}
