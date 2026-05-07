<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'The given data was invalid.',
                'meta' => [],
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Unauthenticated.',
                'meta' => [],
                'errors' => [],
            ], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'This action is unauthorized.',
                'meta' => [],
                'errors' => [],
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Resource not found.',
                'meta' => [],
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : Response::$statusTexts[$exception->getStatusCode()],
                'meta' => [],
                'errors' => [],
            ], $exception->getStatusCode());
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if (
                $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException
                || $exception instanceof HttpExceptionInterface
            ) {
                return null;
            }

            report($exception);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => (bool) config('app.debug')
                    ? $exception->getMessage()
                    : 'An unexpected error occurred.',
                'meta' => [],
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
