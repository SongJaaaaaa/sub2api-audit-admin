<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerAdjustmentService;
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
            'admin_notes' => ['nullable', 'string', 'max:1000000', 'required_if:adjust_reason,异常修正'],
        ]);
        $cash = number_format((float) ($data['cash_amount'] ?? 0), 2, '.', '');
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $isRecharge = $data['operation'] === LedgerAdjustment::OP_INCREMENT && $data['adjust_reason'] === '充值';
        if ($isRecharge && (float) $cash > (float) $amount) {
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
}
