<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceAlgorithmSelection
{
    private const TTL_SECONDS = 600; // 10 minutes

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $next($request);
        }

        // Don't redirect the selection route itself, or you'll get a loop
        if ($request->routeIs('algorithm.select') || $request->routeIs('algorithm.select.store')) {
            return $next($request);
        }

        $selectedAt = session('algorithm_selected_at');
        $algorithm = session('recommendation_algorithm');

        $expired = ! $selectedAt || (now()->timestamp - $selectedAt) > self::TTL_SECONDS;

        if (! $algorithm || $expired) {
            session()->forget(['recommendation_algorithm', 'algorithm_selected_at']);

            return redirect()->route('algorithm.select');
        }

        return $next($request);
    }
}
