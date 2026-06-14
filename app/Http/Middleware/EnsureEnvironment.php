<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEnvironment
{
    public function handle(Request $request, Closure $next, string ...$environments): Response
    {
        if (! in_array(app()->environment(), $environments, true)) {
            abort(404);
        }

        return $next($request);
    }
}
