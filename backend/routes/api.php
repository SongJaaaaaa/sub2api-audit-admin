<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LedgerAdjustmentController;
use App\Http\Controllers\Api\V1\Sub2Api\Sub2ApiDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', function () {
        $tz = config('ledger.timezone');

        return response()->json([
            'status' => 'ok',
            'timezone' => $tz,
            'time' => now($tz)->format('Y-m-d H:i:s'),
        ]);
    });

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('sub2api/users', [Sub2ApiDataController::class, 'users']);
        Route::get('sub2api/model-stats', [Sub2ApiDataController::class, 'modelStats']);

        Route::get('ledger-adjustments', [LedgerAdjustmentController::class, 'index']);
        Route::post('ledger-adjustments', [LedgerAdjustmentController::class, 'store']);
    });
});
