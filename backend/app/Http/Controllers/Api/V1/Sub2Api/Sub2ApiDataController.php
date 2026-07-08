<?php

namespace App\Http\Controllers\Api\V1\Sub2Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Sub2ApiDataController extends Controller
{
    public function users(Request $req, Sub2ApiReadRepository $repo): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);

        return response()->json($repo->users([
            'keyword' => $req->query('keyword', ''),
        ], $page, $pageSize));
    }

    public function modelStats(Request $req, Sub2ApiReadRepository $repo): JsonResponse
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $from = $req->query('from')
            ? CarbonImmutable::parse((string) $req->query('from'), $tz)->utc()
            : CarbonImmutable::now($tz)->subDays(7)->startOfDay()->utc();
        $to = $req->query('to')
            ? CarbonImmutable::parse((string) $req->query('to'), $tz)->utc()
            : CarbonImmutable::now($tz)->endOfDay()->utc();
        $limit = min(max((int) $req->query('limit', 20), 1), 100);

        $filters = [
            'model' => $req->query('model', ''),
            'user_id' => $req->query('user_id', 0),
            'user_keyword' => $req->query('user_keyword', ''),
        ];

        return response()->json([
            'summary' => $repo->usageSummary($from, $to, $filters),
            'models' => $repo->modelRanking($from, $to, $filters, $limit),
            'user_models' => $repo->userModelRanking($from, $to, $filters, $limit),
            'sources' => $repo->rechargeSourceSummary(),
            'range' => [
                'from' => ChinaTime::fmt($from),
                'to' => ChinaTime::fmt($to),
            ],
        ]);
    }

    public function balanceHistory(Request $req, Sub2ApiAdminClient $client, int $id): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);
        $res = $client->userBalanceHistory($id, $page, $pageSize);
        $userRes = $client->user($id);
        $user = is_array($userRes['data'] ?? null) ? $userRes['data'] : $userRes;
        $data = is_array($res['data'] ?? null) ? $res['data'] : $res;
        $rawItems = collect($data['items'] ?? []);
        $ledgerNos = $rawItems
            ->map(fn (array $row): string => $this->ledgerNo($row['notes'] ?? null))
            ->filter()
            ->values()
            ->all();
        $adjs = empty($ledgerNos)
            ? collect()
            : LedgerAdjustment::query()->whereIn('ledger_no', $ledgerNos)->get()->keyBy('ledger_no');
        $adminIds = $adjs->pluck('created_by')->filter()->unique()->values()->all();
        $admins = empty($adminIds)
            ? collect()
            : Admin::query()->whereIn('id', $adminIds)->get()->keyBy('id');
        $after = $this->money($user['balance'] ?? null);
        $items = $rawItems
            ->map(function (array $row) use ($adjs, $admins, $user, &$after): array {
                $ledgerNo = $this->ledgerNo($row['notes'] ?? null);
                $adj = $ledgerNo !== '' ? $adjs->get($ledgerNo) : null;
                $history = $this->historyRow($row, $adj, $admins->get($adj?->created_by), $user, $after);
                $after = $history['before_balance'];

                return $history;
            })
            ->all();

        return response()->json([
            'items' => $items,
            'total' => (int) ($data['total'] ?? count($items)),
            'page' => (int) ($data['page'] ?? $page),
            'page_size' => (int) ($data['page_size'] ?? $pageSize),
            'total_recharged' => $data['total_recharged'] ?? null,
        ]);
    }

    private function historyRow(array $row, ?LedgerAdjustment $adj, ?Admin $admin, array $user, ?string $fallbackAfter): array
    {
        $value = (float) ($row['value'] ?? 0);
        $after = $adj?->after_balance !== null ? $this->money($adj->after_balance) : $fallbackAfter;
        $before = $adj?->before_balance !== null ? $this->money($adj->before_balance) : $this->money($after === null ? null : (float) $after - $value);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'ledger_adjustment_id' => $adj?->id,
            'ledger_no' => $adj?->ledger_no ?? $this->ledgerNo($row['notes'] ?? null),
            'type' => (string) ($row['type'] ?? ''),
            'value' => number_format($value, 2, '.', ''),
            'operation' => $value < 0 ? 'decrement' : 'increment',
            'operator_name' => $admin?->name ?? 'Sub2API',
            'operator_email' => $admin?->email,
            'adjusted_account' => $user['email'] ?? $user['username'] ?? ('#'.($user['id'] ?? '')),
            'adjusted_user_id' => (int) ($user['id'] ?? 0),
            'before_balance' => $before,
            'after_balance' => $after,
            'adjust_reason' => $adj?->adjust_reason,
            'admin_notes' => $adj?->admin_notes,
            'status' => $row['status'] ?? null,
            'used_at' => ChinaTime::fmt($row['used_at'] ?? null),
            'created_at' => ChinaTime::fmt($row['created_at'] ?? null),
            'notes' => $row['notes'] ?? null,
        ];
    }

    private function ledgerNo(mixed $notes): string
    {
        preg_match('/ledger_no=([A-Z0-9]+)/', (string) $notes, $m);

        return $m[1] ?? '';
    }

    private function money(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        return number_format((float) $val, 2, '.', '');
    }
}
