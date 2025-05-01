<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log request information
        $requestData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'params' => $request->all(),
        ];

        // Log to general channel
        Log::channel('general')->info('Incoming request', $requestData);

        try {
            $response = $next($request);

            // Log response information
            $responseData = [
                'status' => $response->getStatusCode(),
                'duration' => defined('LARAVEL_START') ? (microtime(true) - LARAVEL_START) : 0,
                'content' => $response->getContent(),
            ];

            if ($response->getStatusCode() >= 400) {
                // Log error responses to errors channel
                Log::channel('errors')->error('Error response', array_merge($requestData, $responseData));
            } else {
                // Log successful responses to general channel
                Log::channel('general')->info('Response sent', $responseData);
            }

            return $response;
        } catch (\Throwable $exception) {
            // Log detailed error information
            Log::channel('errors')->error('Request failed', [
                'request' => $requestData,
                'error' => [
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ],
            ]);

            throw $exception;
        }
    }
} 