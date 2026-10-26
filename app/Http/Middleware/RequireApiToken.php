<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional bearer-token gate for the management API.
 * No API_TOKEN configured = open (local demo). Otherwise the header must match.
 */
class RequireApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('shortener.api_token');

        if ($expected === '') {
            return $next($request);
        }

        // hash_equals compares in constant time so the token can't be guessed byte by byte.
        if (! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Invalid or missing API token.'], 401)
                ->header('WWW-Authenticate', 'Bearer');
        }

        return $next($request);
    }
}
