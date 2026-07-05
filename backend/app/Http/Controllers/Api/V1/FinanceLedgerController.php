<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ledger\FinanceLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceLedgerController extends Controller
{
    public function cash(Request $req, FinanceLedgerService $service): JsonResponse
    {
        return response()->json($service->cash($this->filters($req), $this->page($req), $this->pageSize($req)));
    }

    public function gifts(Request $req, FinanceLedgerService $service): JsonResponse
    {
        return response()->json($service->gifts($this->filters($req), $this->page($req), $this->pageSize($req)));
    }

    public function expenses(Request $req, FinanceLedgerService $service): JsonResponse
    {
        return response()->json($service->expenses([
            'category' => $req->query('category', ''),
        ], $this->page($req), $this->pageSize($req)));
    }

    public function storeExpense(Request $req, FinanceLedgerService $service): JsonResponse
    {
        $data = $req->validate([
            'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date_format:Y-m-d'],
            'remark' => ['nullable', 'string', 'max:500'],
            'content_html' => ['nullable', 'string', 'max:10000'],
        ]);

        $row = $service->createExpense($req->user(), $data);

        return response()->json(['expense' => $service->expenseRow($row), 'message' => '经营账已记录'], 201);
    }

    private function filters(Request $req): array
    {
        return ['sub2api_user_id' => $req->query('sub2api_user_id', 0)];
    }

    private function page(Request $req): int
    {
        return max((int) $req->query('page', 1), 1);
    }

    private function pageSize(Request $req): int
    {
        return min(max((int) $req->query('page_size', 20), 1), 100);
    }
}
