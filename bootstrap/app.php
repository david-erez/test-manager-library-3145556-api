<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\JwtAuthenticate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => JwtAuthenticate::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // api-contract.md §1: every error, from any source, uses the same body.
        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Hay campos inválidos en la petición.',
                'details' => collect($e->errors())->map(fn ($issues, $field) => [
                    'field' => $field,
                    'issue' => $issues[0] ?? 'inválido',
                ])->values(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'No autenticado.',
                'details' => [],
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El recurso solicitado no existe.',
                'details' => [],
            ], 404);
        });
    })->create();
