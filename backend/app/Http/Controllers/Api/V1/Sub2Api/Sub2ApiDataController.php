<?php

namespace App\Http\Controllers\Api\V1\Sub2Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Services\Stats\ModelStatsService;
use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use App\Support\Sub2ApiNoteTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Sub2ApiDataController extends Controller
{
    public function users(Request $req, Sub2ApiReadRepository $repo): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);
        $data = Validator::make([
            'sort_by' => $req->query('sort_by') ?: null,
            'sort_order' => $req->query('sort_order') ?: null,
            'user_id' => $req->query('user_id') ?: null,
            'emails' => $req->query('emails', []),
        ], [
            'sort_by' => ['nullable', Rule::in(['balance'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'emails' => ['array', 'max:100'],
            'emails.*' => ['string', 'email'],
        ])->validate();

        return response()->json($repo->users([
            'keyword' => $req->query('keyword', ''),
            'user_filter' => $req->query('user_filter', ''),
            'sort_by' => $data['sort_by'] ?? '',
            'sort_order' => $data['sort_order'] ?? '',
            'user_id' => $data['user_id'] ?? null,
            'emails' => $data['emails'] ?? [],
        ], $page, $pageSize));
    }

    public function modelStats(Request $req, ModelStatsService $service): JsonResponse
    {
        $start = trim((string) $req->query('start_date', ''));
        $end = trim((string) $req->query('end_date', ''));

        if (($start === '') !== ($end === '')) {
            throw ValidationException::withMessages([
                'start_date' => ['start_date 和 end_date 必须同时提供'],
                'end_date' => ['start_date 和 end_date 必须同时提供'],
            ]);
        }

        if ($start === '') {
            $today = now(config('ledger.timezone', 'Asia/Shanghai'))->toDateString();
            $start = $today;
            $end = $today;
        }

        $data = Validator::make([
            'start_date' => $start,
            'end_date' => $end,
            'model' => $req->query('model'),
            'limit' => $req->query('limit', 20),
        ], [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'model' => ['nullable', 'string', 'max:200'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return response()->json($service->data(
            ChinaDateRange::make($data['start_date'], $data['end_date']),
            $data['model'] ?? null,
            (int) $data['limit'],
        ));
    }

    public function consumptionRanking(Request $req, ModelStatsService $service): JsonResponse
    {
        $start = trim((string) $req->query('start_date', ''));
        $end = trim((string) $req->query('end_date', ''));

        if (($start === '') !== ($end === '')) {
            throw ValidationException::withMessages([
                'start_date' => ['start_date 和 end_date 必须同时提供'],
                'end_date' => ['start_date 和 end_date 必须同时提供'],
            ]);
        }

        if ($start === '') {
            $today = now(config('ledger.timezone', 'Asia/Shanghai'))->toDateString();
            $start = $today;
            $end = $today;
        }

        $data = Validator::make([
            'start_date' => $start,
            'end_date' => $end,
            'limit' => $req->query('limit', 20),
        ], [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return response()->json($service->consumptionRanking(
            ChinaDateRange::make($data['start_date'], $data['end_date']),
            (int) $data['limit'],
        ));
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
        $sourceIds = $rawItems->pluck('id')->map(fn ($value): int => (int) $value)->filter()->all();
        $keys = $rawItems
            ->map(fn (array $row): ?string => Sub2ApiNoteTag::idempotencyKey($row['notes'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $adjs = ($sourceIds === [] && $keys === [])
            ? collect()
            : LedgerAdjustment::query()
                ->where('sub2api_user_id', $id)
                ->where(function ($query) use ($sourceIds, $keys): void {
                    if ($sourceIds !== []) {
                        $query->whereIn('sub2api_source_id', $sourceIds);
                    }

                    if ($keys !== []) {
                        $method = $sourceIds === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('idempotency_key', $keys);
                    }
                })
                ->get();
        $bySource = $adjs->whereNotNull('sub2api_source_id')->keyBy('sub2api_source_id');
        $byKey = $adjs->whereNull('sub2api_source_id')->keyBy('idempotency_key');
        $adminIds = $adjs->pluck('created_by')->filter()->unique()->values()->all();
        $admins = $adminIds === []
            ? collect()
            : Admin::query()->whereIn('id', $adminIds)->get()->keyBy('id');
        $after = $this->money($user['balance'] ?? null);
        $items = $rawItems
            ->map(function (array $row) use ($bySource, $byKey, $admins, $user, &$after): array {
                $key = Sub2ApiNoteTag::idempotencyKey($row['notes'] ?? null);
                $adj = $bySource->get((int) ($row['id'] ?? 0)) ?? ($key ? $byKey->get($key) : null);
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
        $before = $adj?->before_balance !== null
            ? $this->money($adj->before_balance)
            : $this->money($after === null ? null : (float) $after - $value);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'ledger_adjustment_id' => $adj?->id,
            'ledger_no' => $adj?->ledger_no,
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
            'notes' => Sub2ApiNoteTag::visibleNotes($row['notes'] ?? null),
        ];
    }

    private function money(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        return number_format((float) $val, 2, '.', '');
    }
}
