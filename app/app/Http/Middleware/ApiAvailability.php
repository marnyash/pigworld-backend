<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAvailability
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_file(storage_path('framework/api-disabled'))) {
            return response()->json(
                ['message' => 'The API is temporarily unavailable.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Retry-After' => '60'],
            );
        }

        return $next($request);
    }
}
