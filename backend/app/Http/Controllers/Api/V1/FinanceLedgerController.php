<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ledger\FinanceHistoryService;
use App\Services\Ledger\FinanceLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'from' => $req->query('from', ''),
            'to' => $req->query('to', ''),
            'created_by' => $req->query('created_by', 0),
            'min_amount' => $req->query('min_amount', ''),
            'max_amount' => $req->query('max_amount', ''),
            'keyword' => $req->query('keyword', ''),
        ], $this->page($req), $this->pageSize($req)));
    }

    public function storeExpense(Request $req, FinanceLedgerService $service): JsonResponse
    {
        $data = $req->validate([
            'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date_format:Y-m-d'],
            'remark' => ['nullable', 'string', 'max:500'],
            'content_html' => ['nullable', 'string', 'max:10000000'],
        ]);

        $row = $service->createExpense($req->user(), $data);

        return response()->json(['expense' => $service->expenseRow($row), 'message' => '支出已记录'], 201);
    }

    public function history(Request $req, FinanceHistoryService $service): JsonResponse
    {
        $filters = $this->historyFilters($req);

        return response()->json($service->paginate($filters, $this->page($req), $this->pageSize($req)));
    }

    public function exportHistory(Request $req, FinanceHistoryService $service): StreamedResponse
    {
        $filters = $this->historyFilters($req);
        $rows = $service->all($filters);
        $start = $filters['start_date'] ?: 'all';
        $end = $filters['end_date'] ?: 'all';

        return response()->streamDownload(function () use ($rows): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['业务日期', '类型', '账单号', '用户ID', '用户邮箱', '分类', '金额', '操作人', '备注', '创建时间']);
            foreach ($rows as $row) {
                $type = ['income' => '收入', 'expense' => '支出', 'gift' => '赠送'][$row['type']];
                $amount = (float) $row['amount'] === 0.0
                    ? '0.00'
                    : ($row['type'] === 'expense' ? '-' : '+').$row['amount'];
                fputcsv($output, [
                    $row['biz_date'],
                    $type,
                    $row['bill_no'],
                    $row['sub2api_user_id'],
                    $row['sub2api_user_email'],
                    $row['category'],
                    $amount,
                    $row['operator_name'] ?: $row['operator_email'],
                    $row['remark'],
                    $row['created_at'],
                ]);
            }
            fclose($output);
        }, "finance-history-{$start}-{$end}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $req): array
    {
        return [
            'sub2api_user_id' => $req->query('sub2api_user_id', 0),
            'sub2api_user_email' => $req->query('sub2api_user_email', ''),
            'start_date' => $req->query('start_date', ''),
            'end_date' => $req->query('end_date', ''),
            'created_by' => $req->query('created_by', 0),
            'business_no' => $req->query('business_no', ''),
            'link_status' => $req->query('link_status', ''),
        ];
    }

    private function historyFilters(Request $req): array
    {
        $data = $req->validate([
            'type' => ['nullable', Rule::in(['income', 'expense', 'gift'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'sub2api_user_id' => ['nullable', 'integer', 'min:1'],
            'created_by' => ['nullable', 'integer', 'min:1'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'type' => $data['type'] ?? '',
            'start_date' => $data['start_date'] ?? '',
            'end_date' => $data['end_date'] ?? '',
            'sub2api_user_id' => $data['sub2api_user_id'] ?? 0,
            'created_by' => $data['created_by'] ?? 0,
            'keyword' => $data['keyword'] ?? '',
        ];
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
