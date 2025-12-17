<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // If it's not an API request, fall back to the default HTML render.
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return parent::render($request, $e);
        }

        // ---------- Validation (422) ----------
        if ($e instanceof ValidationException) {
            return response()->json([
                'ok'    => false,
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Please check the form fields.',
                    'fields'  => $e->errors(),
                ],
            ], 422);
        }

        // ---------- Auth / Access ----------
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'ok'    => false,
                'error' => [
                    'code'    => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'ok'    => false,
                'error' => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You are not allowed to perform this action.',
                ],
            ], 403);
        }

        // ---------- Not Found (models & routes) ----------
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'ok'    => false,
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Resource not found.',
                ],
            ], 404);
        }

        // ---------- Method Not Allowed ----------
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'ok'    => false,
                'error' => [
                    'code'    => 'METHOD_NOT_ALLOWED',
                    'message' => 'HTTP method not allowed for this endpoint.',
                ],
            ], 405);
        }

        // ---------- Throttled / Rate limited ----------
        if ($e instanceof ThrottleRequestsException) {
            return response()->json([
                'ok'    => false,
                'error' => [
                    'code'    => 'TOO_MANY_REQUESTS',
                    'message' => 'Too many requests. Please slow down.',
                ],
            ], 429);
        }

        // ---------- Generic HttpException (with status) ----------
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $payload = [
                'ok'    => false,
                'error' => [
                    'code'    => 'HTTP_'.$status,
                    'message' => $this->messageForStatus($status),
                ],
            ];

            // In dev, include the exception message for faster debugging
            if (config('app.debug')) {
                $payload['error']['debug'] = $e->getMessage();
            }

            return response()->json($payload, $status);
        }

        // ---------- Fallback: 500 ----------
        $payload = [
            'ok'    => false,
            'error' => [
                'code'    => 'SERVER_ERROR',
                'message' => 'Something went wrong. Try again.',
            ],
        ];

        // In dev, include exception message + trace hint
        if (config('app.debug')) {
            $payload['error']['debug'] = [
                'message' => $e->getMessage(),
                'type'    => class_basename($e),
            ];
        }

        return response()->json($payload, 500);
    }

    /**
     * Small helper to map common HTTP status codes to human messages.
     */
    protected function messageForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'You are not allowed to perform this action.',
            404 => 'Resource not found.',
            405 => 'HTTP method not allowed for this endpoint.',
            409 => 'Conflict.',
            422 => 'Validation failed.',
            429 => 'Too many requests.',
            default => 'Unexpected error.',
        };
    }
}
