<?php

use App\Http\Middleware\AttachRequestId;
use App\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', AttachRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::validation(
                errors: $exception->errors(),
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id')
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'unauthenticated',
                message: 'Authentication is required.',
                status: Response::HTTP_UNAUTHORIZED,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id')
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'forbidden',
                message: 'You are not authorized to perform this action.',
                status: Response::HTTP_FORBIDDEN,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id')
            );
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'forbidden',
                message: 'You are not authorized to perform this action.',
                status: Response::HTTP_FORBIDDEN,
                requestId: $request->attributes->get('request_id')
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'not_found',
                message: 'The requested resource was not found.',
                status: Response::HTTP_NOT_FOUND,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id')
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'not_found',
                message: 'The requested resource was not found.',
                status: Response::HTTP_NOT_FOUND,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id')
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'method_not_allowed',
                message: 'The HTTP method is not allowed for this endpoint.',
                status: Response::HTTP_METHOD_NOT_ALLOWED,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id'),
                headers: $exception->getHeaders()
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                Response::HTTP_TOO_MANY_REQUESTS => 'too_many_requests',
                Response::HTTP_NOT_FOUND => 'not_found',
                Response::HTTP_FORBIDDEN => 'forbidden',
                Response::HTTP_UNAUTHORIZED => 'unauthenticated',
                Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
                default => 'http_error',
            };

            $message = match ($code) {
                'too_many_requests' => 'Too many requests. Please try again later.',
                'not_found' => 'The requested resource was not found.',
                'forbidden' => 'You are not authorized to perform this action.',
                'unauthenticated' => 'Authentication is required.',
                'method_not_allowed' => 'The HTTP method is not allowed for this endpoint.',
                default => $exception->getMessage() ?: 'The request could not be completed.',
            };

            return ApiErrorResponse::make(
                code: $code,
                message: $message,
                status: $status,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id'),
                headers: $exception->getHeaders()
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                code: 'server_error',
                message: config('app.debug') ? $exception->getMessage() : 'An unexpected server error occurred.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                requestId: $request->attributes->get('request_id') ?: $request->headers->get('X-Request-Id')
            );
        });
    })->create();
