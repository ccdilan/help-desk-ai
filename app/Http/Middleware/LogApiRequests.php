<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs every API request and its response, including payloads and bodies.
 */
class LogApiRequests
{
    /**
     * Handle an incoming request and log the full exchange.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Log::channel('api')->error('API request failed', $this->context($request, null, $startedAt, $exception));

            throw $exception;
        }

        Log::channel('api')->info('API request handled', $this->context($request, $response, $startedAt));

        return $response;
    }

    /**
     * Build the log context for a request, with either a response or an exception.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, ?Response $response, float $startedAt, ?Throwable $exception = null): array
    {
        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'status' => $response?->getStatusCode() ?? 500,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'request' => $this->requestPayload($request),
            'response' => $response !== null ? $this->responseBody($response) : null,
            'exception' => $exception !== null
                ? ['class' => $exception::class, 'message' => $exception->getMessage()]
                : null,
        ];
    }

    /**
     * Get the request payload, preferring the JSON body over query params.
     *
     * @return array<string, mixed>
     */
    private function requestPayload(Request $request): array
    {
        if ($request->isJson()) {
            return $request->json()->all();
        }

        return $request->query->all();
    }

    /**
     * Decode JSON response bodies so they log as structured data.
     */
    private function responseBody(Response $response): mixed
    {
        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        return $response->getContent();
    }
}
