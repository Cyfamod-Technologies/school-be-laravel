<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use Illuminate\Support\Arr;


class Handler extends ExceptionHandler
{
    // …

    public function render(Request $request, Throwable $e): JsonResponse|\Illuminate\Http\Response
    {
        // If this is an API call (prefix api/*), X-Requested-With header is present, or the client expects JSON...
        $isApiRequest = $request->is('api/*') 
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();

        if ($isApiRequest) {
            // Determine status
            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            // Base payload
            $payload = [
                'message' => $status === 500 && !config('app.debug')
                    ? 'Server Error'
                    : $e->getMessage(),
            ];

            // In debug mode, add exception class & trace
            if (config('app.debug')) {
                $payload['exception'] = get_class($e);
                $payload['trace']     = collect($e->getTrace())->map(fn($frame) => Arr::except($frame, ['args']))->all();
            }

            return response()->json($payload, $status);
        }

        // Otherwise, fall back to the normal HTML error page
        return parent::render($request, $e);
    }
}
