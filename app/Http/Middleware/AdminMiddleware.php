<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{

    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->role_id != 2) {
            // Use abort(403) or redirect
            abort(403, 'Unauthorized. Admin access required.');
        }

        return $next($request);
    }
} 