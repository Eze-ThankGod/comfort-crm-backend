<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can list (scoped by role in controller)
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->isAdminOrManager()) {
            return true;
        }

        return $lead->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        if ($user->isAdminOrManager()) {
            return true;
        }

        return $lead->assigned_to === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    public function assign(User $user): bool
    {
        return $user->isAdminOrManager();
    }

    public function import(User $user): bool
    {
        return $user->isAdminOrManager();
    }
}
