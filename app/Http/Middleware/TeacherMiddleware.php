<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
public function handle(Request $request, Closure $next)
{
    if (auth()->user() && auth()->user()->role === 'teacher') {
        return $next($request);
    }
    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
}
}
