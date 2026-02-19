<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Returning null means Laravel will throw an AuthenticationException
     * instead of trying to redirect to a named 'login' route (which
     * doesn't exist in a pure API application).
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
