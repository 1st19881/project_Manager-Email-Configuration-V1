<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class EnsureNotificationApiKey
{
    /**
     * Reject notification requests that do not carry the shared internal key.
     *
     * @param  Closure(Request): BaseResponse  $next
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $expected = (string) config('notification.api_key');

        if ($expected === '') {
            return response()->json([
                'message' => 'The notification API key is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $provided = (string) $request->header('X-API-KEY', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
