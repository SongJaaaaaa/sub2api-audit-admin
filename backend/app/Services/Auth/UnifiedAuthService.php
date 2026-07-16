<?php

namespace App\Services\Auth;

use App\Services\Sub2Api\Sub2ApiUserAuthClient;

class UnifiedAuthService
{
    public function __construct(
        private readonly Sub2ApiUserAuthClient $client,
        private readonly AdminAuthService $adminAuth,
        private readonly AffiliateAuthService $affiliateAuth,
    ) {}

    public function login(string $account, string $password): array
    {
        $remote = $this->client->authenticate($account, $password)['user'];

        return match ($remote['role'] ?? null) {
            'admin' => [
                'identity_type' => 'admin',
                ...$this->adminAuth->loginRemote($remote),
            ],
            'user' => [
                'identity_type' => 'affiliate',
                ...$this->affiliateAuth->loginRemote($remote),
            ],
            default => abort(403, '该账号角色不支持登录'),
        };
    }
}
