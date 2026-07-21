<?php

namespace App\Services\Sub2Api;

use App\Exceptions\Sub2ApiStatsException;
use App\Support\ChinaDateRange;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class Sub2ApiAdminClient
{
    public function users(int $page = 1, int $pageSize = 100): array
    {
        return $this->get('/api/v1/admin/users', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function user(int $id): array
    {
        return $this->get('/api/v1/admin/users/'.$id);
    }

    public function updateUserBalance(int $id, string $amount, string $operation, string $notes, string $idempotencyKey): array
    {
        $sub2Op = match ($operation) {
            'increment', 'add' => 'add',
            'decrement', 'subtract' => 'subtract',
            default => $operation,
        };

        $res = $this->http()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/api/v1/admin/users/'.$id.'/balance', [
                'balance' => (float) $amount,
                'operation' => $sub2Op,
                'notes' => $notes,
            ]);

        return $this->json($res);
    }

    public function userBalanceHistory(int $id, int $page = 1, int $pageSize = 20): array
    {
        return $this->get('/api/v1/admin/users/'.$id.'/balance-history', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function redeemCodes(int $page = 1, int $pageSize = 100): array
    {
        return $this->get('/api/v1/admin/redeem-codes', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function paymentOrders(int $page = 1, int $pageSize = 100): array
    {
        return $this->get('/api/v1/admin/payment/orders', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function dashboardUsersRanking(ChinaDateRange $range, int $limit): array
    {
        return $this->stats('/api/v1/admin/dashboard/users-ranking', [
            ...$range->apiParams(),
            'limit' => $limit,
        ], 'ranking', [
            'user_id', 'email', 'actual_cost', 'requests', 'tokens',
        ]);
    }

    public function dashboardModels(ChinaDateRange $range): array
    {
        return $this->stats('/api/v1/admin/dashboard/models', [
            ...$range->apiParams(),
            'model_source' => 'requested',
        ], 'models', [
            'model',
            'requests',
            'input_tokens',
            'output_tokens',
            'cache_creation_tokens',
            'cache_read_tokens',
            'total_tokens',
            'cost',
            'actual_cost',
        ]);
    }

    public function dashboardUserBreakdown(ChinaDateRange $range, int $limit, ?string $model = null): array
    {
        $params = [
            ...$range->apiParams(),
            'model_source' => 'requested',
            'sort_by' => 'total_tokens',
            'limit' => $limit,
        ];

        if ($model !== null && $model !== '') {
            $params['model'] = $model;
        }

        return $this->stats('/api/v1/admin/dashboard/user-breakdown', $params, 'users', [
            'user_id',
            'email',
            'requests',
            'input_tokens',
            'output_tokens',
            'cache_tokens',
            'total_tokens',
            'cost',
            'actual_cost',
        ]);
    }

    public function dashboardStats(ChinaDateRange $range, int $limit): array
    {
        $res = Http::pool(fn (Pool $pool): array => [
            $this->poolHttp($pool, 'trend')->get('/api/v1/admin/dashboard/trend', [
                ...$range->apiParams(),
                'granularity' => 'day',
            ]),
            $this->poolHttp($pool, 'ranking')->get('/api/v1/admin/dashboard/users-ranking', [
                ...$range->apiParams(),
                'limit' => $limit,
            ]),
            $this->poolHttp($pool, 'accounts')->get('/api/v1/admin/users', [
                'page' => 1,
                'page_size' => 100,
            ]),
        ]);

        $accountData = $this->statsResult($res['accounts'], '/api/v1/admin/users', 'items', [
            'role', 'status', 'balance', 'total_recharged',
        ]);
        $accounts = $accountData['items'];
        $pages = (int) ($accountData['pages'] ?? 1);
        for ($page = 2; $page <= $pages; $page++) {
            $pageData = $this->stats('/api/v1/admin/users', [
                'page' => $page,
                'page_size' => 100,
            ], 'items', ['role', 'status', 'balance', 'total_recharged']);
            array_push($accounts, ...$pageData['items']);
        }

        return [
            'trend' => $this->statsResult($res['trend'], '/api/v1/admin/dashboard/trend', 'trend', [
                'date', 'requests', 'input_tokens', 'output_tokens', 'cache_creation_tokens',
                'cache_read_tokens', 'total_tokens', 'cost', 'actual_cost',
            ])['trend'],
            'ranking' => $this->statsResult($res['ranking'], '/api/v1/admin/dashboard/users-ranking', 'ranking', [
                'user_id', 'email', 'actual_cost', 'requests', 'tokens',
            ])['ranking'],
            'accounts' => $accounts,
        ];
    }

    private function get(string $path, array $params = []): array
    {
        return $this->json($this->http()->get($path, $params));
    }

    private function stats(string $path, array $params, string $field, array $requiredFields): array
    {
        try {
            $res = $this->http()->get($path, $params);
        } catch (Throwable $e) {
            return $this->statsResult($e, $path, $field, $requiredFields);
        }

        return $this->statsResult($res, $path, $field, $requiredFields);
    }

    private function statsResult(mixed $res, string $path, string $field, array $requiredFields): array
    {
        if ($res instanceof Throwable) {
            Log::error('sub2api.stats.request_failed', [
                'path' => $path,
                'error_type' => $res::class,
            ]);

            throw new Sub2ApiStatsException('Sub2API 官方统计请求失败', previous: $res);
        }

        if (! $res->successful()) {
            Log::error('sub2api.stats.http_failed', [
                'path' => $path,
                'status' => $res->status(),
            ]);

            throw new Sub2ApiStatsException('Sub2API 官方统计返回 HTTP '.$res->status());
        }

        $json = $res->json();
        $data = is_array($json) ? ($json['data'] ?? null) : null;
        if (! is_array($json) || ($json['code'] ?? null) !== 0 || ! is_array($data) || ! array_key_exists($field, $data) || ! is_array($data[$field])) {
            Log::warning('sub2api.stats.invalid_shape', [
                'path' => $path,
                'shape' => $this->shape($json),
                'expected_field' => $field,
            ]);

            throw new Sub2ApiStatsException('Sub2API 官方统计响应结构异常');
        }

        foreach ($data[$field] as $index => $row) {
            $missing = is_array($row)
                ? array_values(array_filter(
                    $requiredFields,
                    fn (string $key): bool => ! array_key_exists($key, $row),
                ))
                : $requiredFields;

            if (! is_array($row) || $missing !== []) {
                Log::warning('sub2api.stats.invalid_row_shape', [
                    'path' => $path,
                    'field' => $field,
                    'row_index' => $index,
                    'row_type' => get_debug_type($row),
                    'row_keys' => is_array($row) ? array_keys($row) : [],
                    'missing_fields' => $missing,
                ]);

                throw new Sub2ApiStatsException('Sub2API 官方统计响应字段异常');
            }
        }

        return $data;
    }

    private function poolHttp(Pool $pool, string $key): PendingRequest
    {
        [$baseUrl, $apiKey, $timeout] = $this->adminConfig();

        return $pool->as($key)
            ->baseUrl($baseUrl)
            ->timeout($timeout)
            ->acceptJson()
            ->withHeaders(['x-api-key' => $apiKey]);
    }

    private function http(): PendingRequest
    {
        [$baseUrl, $apiKey, $timeout] = $this->adminConfig();

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->acceptJson()
            ->withHeaders(['x-api-key' => $apiKey]);
    }

    private function adminConfig(): array
    {
        $baseUrl = rtrim((string) config('sub2api.admin_api.base_url'), '/');
        $apiKey = (string) config('sub2api.admin_api.key');

        if ($baseUrl === '') {
            throw new RuntimeException('缺少 SUB2API_API_URL');
        }
        if ($apiKey === '') {
            throw new RuntimeException('缺少 SUB2API_ADMIN_API_KEY');
        }

        return [$baseUrl, $apiKey, (int) config('sub2api.admin_api.timeout', 10)];
    }

    private function json(Response $res): array
    {
        $json = $res->json();
        if (! $res->successful()) {
            $msg = is_array($json) ? (string) ($json['message'] ?? '') : '';
            throw new RuntimeException(trim('Sub2API Admin API 调用失败：HTTP '.$res->status().' '.$msg));
        }

        return is_array($json) ? $json : [];
    }

    private function shape(mixed $value): array
    {
        if (! is_array($value)) {
            return ['type' => get_debug_type($value)];
        }

        $data = $value['data'] ?? null;

        return [
            'top_level_keys' => array_keys($value),
            'data_type' => get_debug_type($data),
            'data_keys' => is_array($data) ? array_keys($data) : [],
        ];
    }
}
