<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');
        $configuredKey = config('services.api.key');

        if (empty($configuredKey)) {
            return response()->json(['message' => 'Internal Server Error. API Key not configured.'], 500);
        }

        if ($apiKey !== $configuredKey) {
            return response()->json(['message' => 'Unauthorized. Invalid.'], 401);
        }

        return $next($request);
    }
}
