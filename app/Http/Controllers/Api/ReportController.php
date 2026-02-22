<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function agentPerformance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'agent_id'  => 'nullable|exists:users,id',
        ]);

        $dateFrom = $data['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo   = $data['date_to']   ?? now()->toDateString();

        $query = User::where('role', 'agent')->where('is_active', true);

        if (! empty($data['agent_id'])) {
            $query->where('id', $data['agent_id']);
        }

        $agents = $query->withCount([
            'callLogs as calls_count' => fn($q) => $q->whereBetween('called_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']),
            'tasks as tasks_completed' => fn($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$dateFrom, $dateTo]),
            'tasks as tasks_missed'    => fn($q) => $q->where('status', 'missed'),
            'assignedLeads as leads_won' => fn($q) => $q->where('status', 'won'),
        ])->get(['id', 'name', 'email']);

        return $this->success([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'agents'    => $agents,
        ]);
    }

    public function leadsBySource(Request $request): JsonResponse
    {
        $data = Lead::selectRaw('source, status, COUNT(*) as count')
            ->groupBy('source', 'status')
            ->orderBy('source')
            ->get();

        $grouped = $data->groupBy('source')->map(function ($items, $source) {
            return [
                'source'   => $source,
                'total'    => $items->sum('count'),
                'by_status'=> $items->pluck('count', 'status'),
            ];
        })->values();

        return $this->success($grouped);
    }

    public function callOutcomes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'agent_id'  => 'nullable|exists:users,id',
        ]);

        $query = CallLog::selectRaw('outcome, COUNT(*) as count');

        if (! empty($data['agent_id'])) {
            $query->where('agent_id', $data['agent_id']);
        }

        if (! empty($data['date_from'])) {
            $query->whereDate('called_at', '>=', $data['date_from']);
        }

        if (! empty($data['date_to'])) {
            $query->whereDate('called_at', '<=', $data['date_to']);
        }

        $outcomes = $query->groupBy('outcome')->pluck('count', 'outcome');

        return $this->success($outcomes);
    }

    public function taskCompletion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'agent_id'  => 'nullable|exists:users,id',
        ]);

        $query = Task::selectRaw('type, status, COUNT(*) as count');

        if (! empty($data['agent_id'])) {
            $query->where('assigned_to', $data['agent_id']);
        }

        $stats = $query->groupBy('type', 'status')->get();

        $grouped = $stats->groupBy('type')->map(function ($items) {
            return $items->pluck('count', 'status');
        });

        return $this->success($grouped);
    }

    public function leadFunnel(): JsonResponse
    {
        $statuses = ['new', 'contacted', 'interested', 'viewing', 'won', 'lost'];

        $data = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $funnel = collect($statuses)->map(fn($s) => [
            'status' => $s,
            'count'  => $data->get($s, 0),
        ]);

        return $this->success($funnel);
    }

    public function callsOverTime(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days'     => 'nullable|integer|min:7|max:90',
            'agent_id' => 'nullable|exists:users,id',
        ]);

        $days  = $data['days'] ?? 30;
        $query = CallLog::selectRaw('DATE(called_at) as date, COUNT(*) as count')
            ->where('called_at', '>=', now()->subDays($days));

        if (! empty($data['agent_id'])) {
            $query->where('agent_id', $data['agent_id']);
        }

        $series = $query->groupBy('date')
                        ->orderBy('date')
                        ->pluck('count', 'date');

        return $this->success($series);
    }
}
