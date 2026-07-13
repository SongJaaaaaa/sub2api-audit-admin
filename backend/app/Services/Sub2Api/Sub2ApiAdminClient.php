<?php

namespace App\Services\Sub2Api;

use App\Exceptions\Sub2ApiStatsException;
use App\Support\ChinaDateRange;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class Sub2ApiAdminClient
{
    public function me(string $token): array
    {
        $res = $this->http(false)->withToken($token)->get('/api/v1/auth/me');

        return $this->json($res);
    }

    public function users(int $page, int $pageSize): array
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

    public function dashboardTrend(ChinaDateRange $range): array
    {
        return $this->stats('/api/v1/admin/dashboard/trend', [
            ...$range->apiParams(),
            'granularity' => 'day',
        ], 'trend', [
            'date',
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

    public function dashboardUsersRanking(ChinaDateRange $range, int $limit): array
    {
        return $this->stats('/api/v1/admin/dashboard/users-ranking', [
            ...$range->apiParams(),
            'limit' => $limit,
        ], 'ranking', [
            'user_id',
            'email',
            'actual_cost',
            'requests',
            'tokens',
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

    private function get(string $path, array $params = []): array
    {
        return $this->json($this->http()->get($path, $params));
    }

    private function stats(string $path, array $params, string $field, array $requiredFields): array
    {
        try {
            $res = $this->http()->get($path, $params);
        } catch (Throwable $e) {
            Log::error('sub2api.stats.request_failed', [
                'path' => $path,
                'error_type' => $e::class,
            ]);

            throw new Sub2ApiStatsException('Sub2API 官方统计请求失败', previous: $e);
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

    private function http(bool $withKey = true): PendingRequest
    {
        $baseUrl = rtrim((string) config('sub2api.admin_api.base_url'), '/');
        $key = (string) config('sub2api.admin_api.key');

        if ($baseUrl === '') {
            throw new RuntimeException('缺少 SUB2API_ADMIN_API_URL');
        }

        $http = Http::baseUrl($baseUrl)
            ->timeout((int) config('sub2api.admin_api.timeout', 10))
            ->acceptJson();

        if (! $withKey) {
            return $http;
        }

        if ($key === '') {
            throw new RuntimeException('缺少 SUB2API_ADMIN_API_KEY');
        }

        return $http->withHeaders(['x-api-key' => $key]);
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
