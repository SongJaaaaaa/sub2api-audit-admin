<?php

namespace App\Services\Auth;

use App\Services\Sub2Api\Sub2ApiUserAuthClient;

class UnifiedAuthService
{
    public function __construct(
        private readonly Sub2ApiUserAuthClient $client,
        private readonly AdminAuthService $adminAuth,
    ) {}

    public function login(string $account, string $password): array
    {
        $remote = $this->client->authenticate($account, $password)['user'];

        if (($remote['role'] ?? null) !== 'admin') {
            abort(403, '仅 Sub2API 管理员可登录');
        }

        return [
            'identity_type' => 'admin',
            ...$this->adminAuth->loginRemote($remote),
        ];
    }
}
