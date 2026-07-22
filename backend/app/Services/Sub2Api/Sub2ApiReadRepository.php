<?php

namespace App\Services\Sub2Api;

use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use App\Support\Sub2ApiNoteTag;
use Carbon\CarbonImmutable;

class Sub2ApiReadRepository
{
    public function __construct(private readonly Sub2ApiAdminClient $client) {}

    public function users(array $filters, int $page, int $pageSize): array
    {
        $userId = (int) ($filters['user_id'] ?? 0);
        $emails = $filters['emails'] ?? [];
        if ($userId > 0) {
            return $this->userPage(array_filter([$this->user($userId)]), $page, $pageSize);
        }
        if ($emails !== []) {
            return $this->emailUsers($emails, $page, $pageSize);
        }

        $sortBy = ($filters['sort_by'] ?? '') === 'balance' ? 'balance' : 'created_at';
        $sortOrder = in_array($filters['sort_order'] ?? '', ['asc', 'desc'], true)
            ? $filters['sort_order']
            : 'desc';
        $res = $this->client->users($page, $pageSize, [
            'search' => trim((string) ($filters['keyword'] ?? '')),
            'status' => ($filters['user_filter'] ?? '') === 'disabled' ? 'disabled' : '',
            'include_subscriptions' => false,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ]);
        $data = data_get($res, 'data', []);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $total = (int) ($data['total'] ?? count($items));

        return [
            'items' => array_map(fn (array $row): array => $this->userRow($row), $items),
            'total' => $total,
            'page' => (int) ($data['page'] ?? $page),
            'page_size' => (int) ($data['page_size'] ?? $pageSize),
            'summary' => ['user_count' => $total],
        ];
    }

    public function user(int $id): ?array
    {
        $row = data_get($this->client->user($id), 'data');

        return is_array($row) ? $this->userRow($row) : null;
    }

    public function consumptionRanking(ChinaDateRange $range, int $limit): array
    {
        return collect($this->client->dashboardUsersRanking($range, $limit)['ranking'])
            ->map(fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'email' => $row['email'] ?? null,
                'request_count' => (int) $row['requests'],
                'total_tokens' => (int) $row['tokens'],
                'actual_cost' => $this->decimal($row['actual_cost'], 10),
            ])->all();
    }

    public function findAdminAdjustmentSources(int $userId, string $idempotencyKey): array
    {
        return collect($this->redeemCodes())
            ->filter(fn (array $row): bool => strtolower((string) ($row['type'] ?? '')) === 'admin_balance'
                && strtolower((string) ($row['status'] ?? '')) === 'used'
                && (int) ($row['used_by'] ?? 0) === $userId
                && Sub2ApiNoteTag::idempotencyKey($row['notes'] ?? null) === $idempotencyKey)
            ->sortBy('id')
            ->map(fn (array $row): array => $this->redeemRow($row))
            ->values()
            ->all();
    }

    public function adminAdjustmentEvents(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        return $this->redeemEvents('admin_balance', $startUtc, $endUtc, 'admin_adjustment');
    }

    public function balanceRedeemEvents(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        return $this->redeemEvents('balance', $startUtc, $endUtc, 'balance_redeem');
    }

    public function paymentOrderEvents(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        return collect($this->allItems(fn (int $page): array => $this->client->paymentOrders($page)))
            ->filter(fn (array $row): bool => strtolower((string) ($row['order_type'] ?? '')) === 'balance'
                && strtolower((string) ($row['status'] ?? '')) === 'completed'
                && (float) ($row['amount'] ?? 0) !== 0.0
                && $this->inRange($row['completed_at'] ?? null, $startUtc, $endUtc))
            ->map(function (array $row): array {
                $user = is_array($row['user'] ?? null) ? $row['user'] : [];

                return [
                    'source' => 'payment_order',
                    'remote_event_id' => (int) $row['id'],
                    'sub2api_user_id' => (int) $row['user_id'],
                    'user_email' => $user['email'] ?? $row['user_email'] ?? null,
                    'username' => $user['username'] ?? $row['user_name'] ?? null,
                    'value' => (string) $row['amount'],
                    'notes' => trim((string) ($row['out_trade_no'] ?? '')),
                    'event_at' => $row['completed_at'],
                    'created_at' => $row['created_at'] ?? null,
                ];
            })->values()->all();
    }

    private function redeemEvents(string $type, CarbonImmutable $startUtc, CarbonImmutable $endUtc, string $source): array
    {
        return collect($this->redeemCodes())
            ->filter(fn (array $row): bool => strtolower((string) ($row['type'] ?? '')) === $type
                && strtolower((string) ($row['status'] ?? '')) === 'used'
                && (int) ($row['used_by'] ?? 0) > 0
                && $this->inRange($row['used_at'] ?? null, $startUtc, $endUtc))
            ->map(function (array $row) use ($source): array {
                $user = is_array($row['user'] ?? null) ? $row['user'] : [];

                return [
                    'source' => $source,
                    'remote_event_id' => (int) $row['id'],
                    'sub2api_user_id' => (int) $row['used_by'],
                    'user_email' => $user['email'] ?? $row['email'] ?? null,
                    'username' => $user['username'] ?? $row['username'] ?? null,
                    'value' => (string) $row['value'],
                    'notes' => $row['notes'] ?? null,
                    'event_at' => $row['used_at'],
                    'created_at' => $row['created_at'] ?? null,
                ];
            })->values()->all();
    }

    private function redeemCodes(): array
    {
        return $this->allItems(fn (int $page): array => $this->client->redeemCodes($page));
    }

    private function allItems(callable $load): array
    {
        $data = data_get($load(1), 'data', []);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $pages = max(1, (int) ($data['pages'] ?? 1));

        for ($page = 2; $page <= $pages; $page++) {
            $next = data_get($load($page), 'data.items', []);
            if (is_array($next)) {
                array_push($items, ...$next);
            }
        }

        return $items;
    }

    private function emailUsers(array $emails, int $page, int $pageSize): array
    {
        $wanted = array_map('mb_strtolower', $emails);
        $rows = collect();
        foreach ($wanted as $email) {
            $items = data_get($this->client->users(1, 100, [
                'search' => $email,
                'include_subscriptions' => false,
            ]), 'data.items', []);
            foreach (is_array($items) ? $items : [] as $row) {
                if (mb_strtolower((string) ($row['email'] ?? '')) === $email) {
                    $rows->put((int) ($row['id'] ?? 0), $row);
                }
            }
        }

        return $this->userPage($rows->values()->all(), $page, $pageSize);
    }

    private function userPage(array $rows, int $page, int $pageSize): array
    {
        $rows = array_values($rows);
        $total = count($rows);

        return [
            'items' => array_map(
                fn (array $row): array => $this->userRow($row),
                array_slice($rows, ($page - 1) * $pageSize, $pageSize),
            ),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => ['user_count' => $total],
        ];
    }

    private function redeemRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'used_by' => (int) $row['used_by'],
            'value' => (string) $row['value'],
            'notes' => $row['notes'] ?? null,
            'used_at' => $row['used_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function inRange(mixed $value, CarbonImmutable $startUtc, CarbonImmutable $endUtc): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $time = CarbonImmutable::parse((string) $value, 'UTC')->utc();

        return $time->greaterThanOrEqualTo($startUtc) && $time->lessThan($endUtc);
    }

    private function userRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'email' => $row['email'] ?? null,
            'username' => $row['username'] ?? null,
            'role' => $row['role'] ?? null,
            'balance' => $this->decimal($row['balance'] ?? 0, 8),
            'total_recharged' => $this->decimal($row['total_recharged'] ?? 0, 8),
            'status' => $row['status'] ?? null,
            'created_at' => ChinaTime::fmtUtc($row['created_at'] ?? null),
            'updated_at' => ChinaTime::fmtUtc($row['updated_at'] ?? null),
            'last_used_at' => ChinaTime::fmtUtc($row['last_used_at'] ?? null),
        ];
    }

    private function decimal(mixed $value, int $scale): string
    {
        $text = number_format((float) $value, $scale, '.', '');

        return rtrim(rtrim($text, '0'), '.') ?: '0';
    }
}
