<?php

use App\Support\SlackErrorAlerts;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e): void {
            SlackErrorAlerts::notifyIfReportable($e);
        });

        $exceptions->renderable(function (AuthenticationException $exception, $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()->guest('/login');
        });

        // Consistent JSON error responses for API; do not expose exception details in production
        $exceptions->renderable(function (Throwable $e, Request $request): ?Response {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Named limiters with Limit::response() throw HttpResponseException (empty message, no status on the
            // exception type). Our handler below would otherwise treat that as HTTP 500.
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            $debug = config('app.debug');

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }

            $status = method_exists($e, 'getStatusCode')
                ? $e->getStatusCode()
                : 500;

            $payload = [
                'message' => $status >= 500
                    ? 'An unexpected error occurred. Please try again later.'
                    : $e->getMessage(),
            ];

            if ($debug && $status >= 500) {
                $payload['error'] = $e->getMessage();
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            if ($status >= 500) {
                report($e);
            }

            return response()->json($payload, $status);
        });
    })->create();
