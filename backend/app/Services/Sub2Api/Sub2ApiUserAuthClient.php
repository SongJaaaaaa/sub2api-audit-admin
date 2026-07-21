<?php

namespace App\Services\Sub2Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Sub2ApiUserAuthClient
{
    public function authenticate(string $account, string $password): array
    {
        try {
            $login = $this->http()->post('/api/v1/auth/login', [
                'email' => $account,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            abort(502, 'Sub2API 登录服务暂不可用');
        }
        $this->ensureLoginSucceeded($login);

        $user = data_get($login->json(), 'data.user');
        if (! is_array($user) || ! is_numeric($user['id'] ?? null)) {
            abort(502, 'Sub2API 登录响应缺少用户资料');
        }

        return [
            'user' => $user,
        ];
    }

    private function http(): PendingRequest
    {
        $baseUrl = rtrim((string) config('sub2api.user_api.base_url'), '/');
        $apiKey = (string) config('sub2api.user_api.key');
        if ($baseUrl === '') {
            throw new RuntimeException('缺少 SUB2API_API_URL');
        }
        if ($apiKey === '') {
            throw new RuntimeException('缺少 SUB2API_ADMIN_API_KEY');
        }

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('sub2api.user_api.timeout', 10))
            ->acceptJson()
            ->withHeaders(['x-api-key' => $apiKey]);
    }

    private function ensureLoginSucceeded(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if (in_array($response->status(), [400, 401, 403, 422], true)) {
            abort(401, '账号或密码错误');
        }

        abort(502, 'Sub2API 登录接口调用失败：HTTP '.$response->status());
    }
}
