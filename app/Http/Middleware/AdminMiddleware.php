<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(!auth()->check() || auth()->user()->role !== 'admin', 403, 'Anda tidak memiliki akses Admin.');

        return $next($request);
    }
}
