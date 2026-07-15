<?php

namespace App\Services\Auth;

use App\Models\Rebate\RebateUser;
use App\Services\Rebate\UserSyncService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Services\Sub2Api\Sub2ApiUserAuthClient;

class AffiliateAuthService
{
    public function __construct(
        private readonly Sub2ApiUserAuthClient $client,
        private readonly Sub2ApiReadRepository $read,
        private readonly UserSyncService $sync,
    ) {}

    public function login(string $account, string $password): array
    {
        $remote = $this->client->authenticate($account, $password)['user'];
        $userData = $this->read->affiliateUser((int) $remote['id']);
        if ($userData === null) {
            abort(502, 'Sub2API 用户资料不存在');
        }

        $parent = isset($userData['parent_user_id'])
            ? $this->read->affiliateUser((int) $userData['parent_user_id'])
            : null;
        $user = $this->sync->sync($userData, $parent);
        if (! $user->isActive()) {
            abort(403, '账号已被禁用');
        }

        return [
            'token' => $user->createToken('affiliate-token')->plainTextToken,
            'user' => $user,
        ];
    }

    public function logout(RebateUser $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
