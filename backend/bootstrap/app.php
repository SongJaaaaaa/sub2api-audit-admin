<?php

use App\Exceptions\LedgerCutoverException;
use App\Exceptions\Sub2ApiStatsException;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAffiliate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'affiliate' => EnsureAffiliate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (LedgerCutoverException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => LedgerCutoverException::CODE,
                    'message' => $e->getMessage(),
                ], 409);
            }

            return null;
        });

        $exceptions->render(function (Sub2ApiStatsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => Sub2ApiStatsException::CODE,
                    'message' => 'Sub2API 官方统计暂不可用',
                ], 502);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });
    })->create();
