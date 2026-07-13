<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BalanceEvents\BalanceEventService;
use App\Support\ChinaDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceEventController extends Controller
{
    public function index(Request $req, BalanceEventService $service): JsonResponse
    {
        [$range, $filters, $page, $pageSize] = $this->params($req, $service);

        return response()->json($service->paginate($range, $filters, $page, $pageSize));
    }

    public function export(Request $req, BalanceEventService $service): StreamedResponse
    {
        [$range, $filters] = $this->params($req, $service, false);
        $rows = $service->exportRows($range, $filters);
        $name = "balance-events-{$range->startDate}-{$range->endDate}.csv";

        return response()->streamDownload(function () use ($rows): void {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['事件时间', '来源', '远端事件ID', '用户ID', '用户邮箱', '用户名', '方向', '金额', '关联状态', '本地单号', '备注']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['event_at'],
                    $row['source'],
                    $row['remote_event_id'],
                    $row['sub2api_user_id'],
                    $row['user_email'],
                    $row['username'],
                    $row['direction'],
                    $row['amount'],
                    $row['link_status'],
                    $row['ledger_no'],
                    $row['notes'],
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function params(Request $req, BalanceEventService $service, bool $withPage = true): array
    {
        $period = trim((string) $req->query('period', 'history'));
        $start = trim((string) $req->query('start_date', ''));
        $end = trim((string) $req->query('end_date', ''));

        if (($start === '') !== ($end === '')) {
            throw ValidationException::withMessages([
                'start_date' => ['start_date 和 end_date 必须同时提供'],
                'end_date' => ['start_date 和 end_date 必须同时提供'],
            ]);
        }

        if ($start === '') {
            [$start, $end] = $service->defaultDates($period);
        }

        $input = [
            'start_date' => $start,
            'end_date' => $end,
            'user_id' => $req->query('user_id'),
            'keyword' => $req->query('keyword'),
            'source' => $req->query('source'),
            'direction' => $req->query('direction'),
            'link_status' => $req->query('link_status'),
            'period' => $period,
        ];
        $rules = [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'keyword' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'in:admin_adjustment,balance_redeem,payment_order'],
            'direction' => ['nullable', 'in:increment,decrement'],
            'link_status' => ['nullable', 'in:linked,audit_orphan,external'],
            'period' => ['required', 'in:history,current,all'],
        ];

        if ($withPage) {
            $input['page'] = $req->query('page', 1);
            $input['page_size'] = $req->query('page_size', 20);
            $rules['page'] = ['required', 'integer', 'min:1'];
            $rules['page_size'] = ['required', 'integer', 'min:1', 'max:100'];
        }

        $data = Validator::make($input, $rules)->validate();
        $params = [
            ChinaDateRange::make($data['start_date'], $data['end_date']),
            [
                'user_id' => $data['user_id'] ?? null,
                'keyword' => $data['keyword'] ?? null,
                'source' => $data['source'] ?? null,
                'direction' => $data['direction'] ?? null,
                'link_status' => $data['link_status'] ?? null,
                'period' => $data['period'],
            ],
        ];

        return $withPage
            ? [...$params, (int) $data['page'], (int) $data['page_size']]
            : $params;
    }
}
