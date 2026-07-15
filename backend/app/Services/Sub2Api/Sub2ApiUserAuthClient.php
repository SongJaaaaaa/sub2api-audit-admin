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

        $token = (string) (
            data_get($login->json(), 'data.access_token')
            ?: data_get($login->json(), 'data.token')
            ?: data_get($login->json(), 'access_token')
            ?: data_get($login->json(), 'token')
        );
        if ($token === '') {
            abort(502, 'Sub2API 登录响应缺少 Token');
        }

        try {
            try {
                $me = $this->http()->withToken($token)->get('/api/v1/auth/me');
            } catch (ConnectionException) {
                abort(502, 'Sub2API 当前用户服务暂不可用');
            }
            if (! $me->successful()) {
                abort(502, 'Sub2API 当前用户接口调用失败：HTTP '.$me->status());
            }

            $user = data_get($me->json(), 'data.user')
                ?: data_get($me->json(), 'data')
                ?: data_get($me->json(), 'user');
            if (! is_array($user) || ! is_numeric($user['id'] ?? null)) {
                abort(502, 'Sub2API 当前用户响应结构异常');
            }
        } finally {
            try {
                $this->http()->withToken($token)->post('/api/v1/auth/logout');
            } catch (ConnectionException) {
                // 登录凭据已验证，上游临时 Token 由其过期策略回收。
            }
        }

        return [
            'user' => $user,
        ];
    }

    private function http(): PendingRequest
    {
        $baseUrl = rtrim((string) config('sub2api.user_api.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('缺少 SUB2API_USER_API_URL');
        }

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('sub2api.user_api.timeout', 10))
            ->acceptJson();
    }

    private function ensureLoginSucceeded(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if (in_array($response->status(), [401, 403, 422], true)) {
            abort(401, '账号或密码错误');
        }

        abort(502, 'Sub2API 登录接口调用失败：HTTP '.$response->status());
    }
}
