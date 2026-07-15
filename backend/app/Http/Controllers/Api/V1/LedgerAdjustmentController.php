<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerAdjustmentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LedgerAdjustmentController extends Controller
{
    public function index(Request $req, LedgerAdjustmentService $service): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);

        return response()->json($service->list([
            'status' => $req->query('status', LedgerAdjustment::STATUS_SUCCEEDED),
            'sub2api_user_id' => $req->query('sub2api_user_id', 0),
            'sub2api_user_email' => $req->query('sub2api_user_email', ''),
            'created_by' => $req->query('created_by', 0),
            'start_date' => $req->query('start_date', ''),
            'end_date' => $req->query('end_date', ''),
            'min_amount' => $req->query('min_amount', ''),
            'max_amount' => $req->query('max_amount', ''),
        ], $page, $pageSize));
    }

    public function store(Request $req, LedgerAdjustmentService $service): JsonResponse
    {
        $data = $req->validate([
            'sub2api_user_id' => ['required', 'integer', 'min:1'],
            'operation' => ['required', Rule::in([LedgerAdjustment::OP_INCREMENT, LedgerAdjustment::OP_DECREMENT])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'gift_quota_amount' => ['nullable', 'numeric', 'min:0'],
            'adjust_reason' => ['required', Rule::in(['充值', '补发', '人工扣减', '异常修正'])],
            'admin_notes' => ['nullable', 'string', 'max:10000000', 'required_if:adjust_reason,异常修正'],
        ]);
        $cash = Money::fmt($data['cash_amount'] ?? 0);
        $amount = Money::fmt($data['amount']);
        $isRecharge = $data['operation'] === LedgerAdjustment::OP_INCREMENT && $data['adjust_reason'] === '充值';
        if ($isRecharge && bccomp($cash, $amount, 2) > 0) {
            throw new ValidationException(Validator::make([], []), response()->json([
                'message' => '入账金额不能大于 Sub2API 金额调整',
                'errors' => ['cash_amount' => ['入账金额不能大于 Sub2API 金额调整']],
            ], 422));
        }

        $adj = $service->adjust($req->user(), $data);
        $body = [
            'adjustment' => $service->row($adj),
            'message' => $adj->status === LedgerAdjustment::STATUS_SUCCEEDED
                ? 'Sub2API 已入账并确认成功'
                : 'Sub2API 未确认成功，已进入异常或作废状态',
        ];

        return response()->json($body, $adj->status === LedgerAdjustment::STATUS_SUCCEEDED ? 201 : 409);
    }

    public function batchGift(Request $req, LedgerAdjustmentService $service): JsonResponse
    {
        $data = $req->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'admin_notes' => ['nullable', 'string', 'max:10000000'],
        ]);

        $items = [];
        $notes = trim((string) ($data['admin_notes'] ?? '')) ?: '管理员赠送';
        foreach ($data['user_ids'] as $userId) {
            $adj = $service->adjust($req->user(), [
                'sub2api_user_id' => (int) $userId,
                'operation' => LedgerAdjustment::OP_INCREMENT,
                'amount' => $data['amount'],
                'cash_amount' => '0',
                'gift_quota_amount' => $data['amount'],
                'adjust_reason' => '管理员赠送',
                'admin_notes' => $notes,
            ]);

            $items[] = [
                'user_id' => (int) $userId,
                'status' => $adj->status,
                'adjustment' => $service->row($adj),
                'message' => $adj->status === LedgerAdjustment::STATUS_SUCCEEDED
                    ? '赠送成功'
                    : ($adj->exception_reason ?: '赠送未确认成功'),
            ];
        }

        $success = collect($items)->where('status', LedgerAdjustment::STATUS_SUCCEEDED)->count();
        $failed = count($items) - $success;

        return response()->json([
            'items' => $items,
            'success_count' => $success,
            'failed_count' => $failed,
            'message' => "批量赠送完成：成功 {$success} 个，失败 {$failed} 个",
        ], $failed > 0 ? 207 : 201);
    }
}
