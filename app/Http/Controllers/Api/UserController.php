<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->select(['id', 'name', 'email', 'role', 'phone', 'is_active', 'avatar', 'created_at'])
                       ->orderBy('name')
                       ->paginate($request->integer('per_page', 20));

        return $this->success($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:admin,manager,agent',
            'phone'    => 'nullable|string|max:20',
            'is_active'=> 'boolean',
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        return $this->success($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->loadCount(['assignedLeads', 'tasks', 'callLogs']);

        return $this->success($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8|confirmed',
            'role'     => ['sometimes', 'in:admin,manager,agent', Rule::when(! auth()->user()->isAdmin(), ['prohibited'])],
            'phone'    => 'nullable|string|max:20',
            'is_active'=> ['sometimes', 'boolean', Rule::when(! auth()->user()->isAdmin(), ['prohibited'])],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $this->success($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['status' => 'success', 'message' => 'User deleted successfully']);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        return $this->success([
            'message'   => 'User status updated',
            'is_active' => $user->is_active,
        ]);
    }

    public function agents(): JsonResponse
    {
        $agents = User::where('role', 'agent')
                      ->where('is_active', true)
                      ->select(['id', 'name', 'email', 'phone'])
                      ->orderBy('name')
                      ->get();

        return $this->success($agents);
    }
}
