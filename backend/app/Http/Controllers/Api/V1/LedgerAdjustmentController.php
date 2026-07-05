<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LedgerAdjustmentController extends Controller
{
    public function index(Request $req, LedgerAdjustmentService $service): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);

        return response()->json($service->list([
            'status' => $req->query('status', LedgerAdjustment::STATUS_SUCCEEDED),
            'sub2api_user_id' => $req->query('sub2api_user_id', 0),
        ], $page, $pageSize));
    }

    public function store(Request $req, LedgerAdjustmentService $service): JsonResponse
    {
        $data = $req->validate([
            'sub2api_user_id' => ['required', 'integer', 'min:1'],
            'operation' => ['required', Rule::in([LedgerAdjustment::OP_INCREMENT, LedgerAdjustment::OP_DECREMENT])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'adjust_reason' => ['required', 'string', 'max:500'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

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
