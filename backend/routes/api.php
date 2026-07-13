<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BalanceEventController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinanceLedgerController;
use App\Http\Controllers\Api\V1\LedgerAdjustmentController;
use App\Http\Controllers\Api\V1\ReconcileController;
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
        Route::get('auth/admin-options', [AuthController::class, 'options']);
        Route::get('admins', [AdminController::class, 'index']);
        Route::post('admins', [AdminController::class, 'store']);

        Route::get('sub2api/users', [Sub2ApiDataController::class, 'users']);
        Route::get('sub2api/users/{id}/balance-history', [Sub2ApiDataController::class, 'balanceHistory']);
        Route::get('sub2api/model-stats', [Sub2ApiDataController::class, 'modelStats']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('balance-events', [BalanceEventController::class, 'index']);
        Route::get('balance-events/export', [BalanceEventController::class, 'export']);

        Route::get('ledger-adjustments', [LedgerAdjustmentController::class, 'index']);
        Route::post('ledger-adjustments', [LedgerAdjustmentController::class, 'store']);
        Route::get('finance/cash', [FinanceLedgerController::class, 'cash']);
        Route::get('finance/gifts', [FinanceLedgerController::class, 'gifts']);
        Route::get('finance/expenses', [FinanceLedgerController::class, 'expenses']);
        Route::post('finance/expenses', [FinanceLedgerController::class, 'storeExpense']);
        Route::get('attachments', [AttachmentController::class, 'index']);
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::get('reconciliations', [ReconcileController::class, 'index']);
        Route::post('reconciliations', [ReconcileController::class, 'store']);
        Route::get('reconciliations/{batch}/diffs', [ReconcileController::class, 'diffs']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });
});
