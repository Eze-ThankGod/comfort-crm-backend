<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isAgent()) {
            return $this->success($this->agentDashboard($user));
        }

        return $this->success($this->adminDashboard());
    }

    private function agentDashboard($user): array
    {
        $leadQuery = Lead::forAgent($user->id);
        $taskQuery = Task::forAgent($user->id);

        return [
            'leads' => [
                'total'      => (clone $leadQuery)->count(),
                'new'        => (clone $leadQuery)->byStatus('new')->count(),
                'contacted'  => (clone $leadQuery)->byStatus('contacted')->count(),
                'interested' => (clone $leadQuery)->byStatus('interested')->count(),
                'viewing'    => (clone $leadQuery)->byStatus('viewing')->count(),
                'won'        => (clone $leadQuery)->byStatus('won')->count(),
                'lost'       => (clone $leadQuery)->byStatus('lost')->count(),
            ],
            'tasks' => [
                'today'    => (clone $taskQuery)->dueToday()->count(),
                'pending'  => (clone $taskQuery)->pending()->count(),
                'overdue'  => (clone $taskQuery)->overdue()->count(),
                'completed_today' => Task::forAgent($user->id)
                    ->where('status', 'completed')
                    ->whereDate('completed_at', today())
                    ->count(),
            ],
            'calls' => [
                'today'           => CallLog::forAgent($user->id)->today()->count(),
                'interested_today'=> CallLog::forAgent($user->id)->today()->where('outcome', 'interested')->count(),
            ],
            'today_tasks' => Task::forAgent($user->id)
                ->dueToday()
                ->with('lead:id,name,phone')
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'recent_activities' => Activity::forAgent($user->id)
                ->with('lead:id,name')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }

    private function adminDashboard(): array
    {
        // Lead stats
        $leadStats = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Calls today
        $callsToday = CallLog::today()->count();

        // Calls by agent today
        $callsByAgent = CallLog::today()
            ->join('users', 'call_logs.agent_id', '=', 'users.id')
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as calls_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('calls_count')
            ->get();

        // Task stats
        $taskStats = [
            'today'     => Task::dueToday()->count(),
            'overdue'   => Task::overdue()->count(),
            'pending'   => Task::pending()->count(),
            'completed_today' => Task::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];

        // Agent performance (last 30 days)
        $agentPerformance = User::where('role', 'agent')
            ->where('is_active', true)
            ->withCount([
                'assignedLeads as total_leads',
                'callLogs as calls_30d' => fn($q) => $q->where('called_at', '>=', now()->subDays(30)),
                'tasks as tasks_completed' => fn($q) => $q->where('status', 'completed'),
                'tasks as tasks_pending'   => fn($q) => $q->where('status', 'pending'),
                'assignedLeads as leads_won' => fn($q) => $q->where('status', 'won'),
            ])
            ->orderByDesc('calls_30d')
            ->get(['id', 'name', 'email']);

        // Leads contacted vs not contacted
        $contactedLeads    = Lead::whereNotNull('last_contacted_at')->count();
        $notContactedLeads = Lead::whereNull('last_contacted_at')->count();

        // Follow-ups
        $followUpStats = [
            'completed' => Task::where('type', 'follow_up')->where('status', 'completed')->count(),
            'missed'    => Task::where('type', 'follow_up')->where('status', 'missed')->count(),
            'pending'   => Task::where('type', 'follow_up')->where('status', 'pending')->count(),
        ];

        // Today's tasks list
        $todayTasks = Task::dueToday()
            ->with(['lead:id,name,phone', 'assignedAgent:id,name'])
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        return [
            'leads' => [
                'total'          => array_sum($leadStats),
                'by_status'      => $leadStats,
                'contacted'      => $contactedLeads,
                'not_contacted'  => $notContactedLeads,
            ],
            'tasks'            => $taskStats,
            'follow_ups'       => $followUpStats,
            'calls_today'      => $callsToday,
            'calls_by_agent'   => $callsByAgent,
            'agent_performance'=> $agentPerformance,
            'today_tasks'      => $todayTasks,
            'recent_activities'=> Activity::with(['lead:id,name', 'user:id,name'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
        ];
    }
}
