<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with(['lead:id,name', 'user:id,name']);

        if (auth()->user()->isAgent()) {
            $query->forAgent(auth()->id());
        } else {
            if ($request->filled('user_id')) {
                $query->forAgent($request->user_id);
            }
        }

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('today')) {
            $query->today();
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $activities = $query->orderByDesc('created_at')
                            ->paginate($request->integer('per_page', 30));

        return $this->success($activities);
    }
}
