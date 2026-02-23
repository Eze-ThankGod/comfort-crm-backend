<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WhatsAppMessage;
use App\Services\ActivityService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected ActivityService $activityService,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'message' => 'required|string|max:4096',
        ]);

        $lead = Lead::findOrFail($data['lead_id']);

        if (auth()->user()->isAgent() && $lead->assigned_to !== auth()->id()) {
            return $this->error('Forbidden', 403);
        }

        if (! $lead->phone) {
            return $this->error('Lead has no phone number', 400);
        }

        $message = $this->whatsAppService->send($lead, $data['message']);

        $this->activityService->whatsappSent($lead, $data['message']);

        return $this->success($message, 201);
    }

    public function history(Request $request, Lead $lead): JsonResponse
    {
        if (auth()->user()->isAgent() && $lead->assigned_to !== auth()->id()) {
            return $this->error('Forbidden', 403);
        }

        $messages = $lead->whatsappMessages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 50));

        return $this->success($messages);
    }

    public function webhook(Request $request): JsonResponse
    {
        // WhatsApp webhook verification (GET)
        if ($request->isMethod('GET')) {
            $mode      = $request->query('hub_mode');
            $token     = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
                return response()->json((int)$challenge);
            }

            return $this->error('Forbidden', 403);
        }

        // Handle incoming messages (POST)
        $this->whatsAppService->handleWebhook($request->all());

        return response()->json(['status' => 'ok']);
    }
}
