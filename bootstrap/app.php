<?php

use App\Services\Reservation\RoomUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Para requisições de API, não há rota de login web para redirecionar.
        // Retornar null evita a chamada a route('login'), que lança
        // "Route [login] not defined." quando essa rota não existe.
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') || $request->expectsJson()
                ? null
                : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $wantsJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($wantsJson);

        // Cobre rota inexistente e ModelNotFoundException: o Laravel converte esta
        // última em NotFoundHttpException antes de executar os callbacks de render.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($wantsJson): ?JsonResponse {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json(
                ['message' => 'Recurso não encontrado.'],
                Response::HTTP_NOT_FOUND
            );
        });

        $exceptions->render(function (RoomUnavailableException $e, Request $request) use ($wantsJson): ?JsonResponse {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (DomainException $e, Request $request) use ($wantsJson): ?JsonResponse {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
