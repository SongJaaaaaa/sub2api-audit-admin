<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliateAuthController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliateController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BalanceEventController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinanceLedgerController;
use App\Http\Controllers\Api\V1\LedgerAdjustmentController;
use App\Http\Controllers\Api\V1\ProfitController;
use App\Http\Controllers\Api\V1\RebateAdmin\RebateAdminController;
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
    Route::post('affiliate/auth/login', [AffiliateAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
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
        Route::post('ledger-adjustments/batch-gift', [LedgerAdjustmentController::class, 'batchGift']);
        Route::get('finance/cash', [FinanceLedgerController::class, 'cash']);
        Route::get('finance/gifts', [FinanceLedgerController::class, 'gifts']);
        Route::get('finance/expenses', [FinanceLedgerController::class, 'expenses']);
        Route::post('finance/expenses', [FinanceLedgerController::class, 'storeExpense']);
        Route::get('profit/summary', [ProfitController::class, 'summary']);
        Route::get('profit/details', [ProfitController::class, 'details']);
        Route::get('profit/settlements', [ProfitController::class, 'index']);
        Route::post('profit/settlements', [ProfitController::class, 'store']);
        Route::get('profit/settlements/{settlement}/items', [ProfitController::class, 'items']);
        Route::post('profit/settlements/{settlement}/reverse', [ProfitController::class, 'reverse']);
        Route::get('attachments', [AttachmentController::class, 'index']);
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::get('reconciliations', [ReconcileController::class, 'index']);
        Route::post('reconciliations', [ReconcileController::class, 'store']);
        Route::get('reconciliations/{batch}/diffs', [ReconcileController::class, 'diffs']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);

        Route::prefix('rebate/admin')->group(function (): void {
            Route::get('dashboard', [RebateAdminController::class, 'dashboard']);
            Route::get('relationships', [RebateAdminController::class, 'relationships']);
            Route::get('config', [RebateAdminController::class, 'config']);
            Route::put('config', [RebateAdminController::class, 'updateConfig']);
            Route::get('withdrawals', [RebateAdminController::class, 'withdrawals']);
            Route::post('withdrawals/{withdrawal}/approve', [RebateAdminController::class, 'approve']);
            Route::post('withdrawals/{withdrawal}/reject', [RebateAdminController::class, 'reject']);
            Route::post('withdrawals/{withdrawal}/retry', [RebateAdminController::class, 'retry']);
        });
    });

    Route::middleware(['auth:sanctum', 'affiliate'])->prefix('affiliate')->group(function (): void {
        Route::get('auth/me', [AffiliateAuthController::class, 'me']);
        Route::post('auth/logout', [AffiliateAuthController::class, 'logout']);
        Route::get('dashboard', [AffiliateController::class, 'dashboard']);
        Route::get('team', [AffiliateController::class, 'team']);
        Route::get('promotion', [AffiliateController::class, 'promotion']);
        Route::get('rebate-records', [AffiliateController::class, 'rebateRecords']);
        Route::get('withdrawals', [AffiliateController::class, 'withdrawals']);
        Route::post('withdrawals', [AffiliateController::class, 'storeWithdrawal']);
    });
});
