<?php

namespace App\Services\Sub2Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Sub2ApiAdminClient
{
    public function me(string $token): array
    {
        $res = $this->http(false)->withToken($token)->get('/api/v1/auth/me');

        return $this->json($res->status(), $res->successful(), $res->json());
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

        return $this->json($res->status(), $res->successful(), $res->json());
    }

    public function userBalanceHistory(int $id, int $page = 1, int $pageSize = 20): array
    {
        return $this->get('/api/v1/admin/users/'.$id.'/balance-history', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    private function get(string $path, array $params = []): array
    {
        $res = $this->http()->get($path, $params);

        return $this->json($res->status(), $res->successful(), $res->json());
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

    private function json(int $status, bool $ok, ?array $json): array
    {
        if (! $ok) {
            $msg = (string) data_get($json, 'message', '');
            throw new RuntimeException(trim('Sub2API Admin API 调用失败：HTTP '.$status.' '.$msg));
        }

        return $json ?? [];
    }
}
