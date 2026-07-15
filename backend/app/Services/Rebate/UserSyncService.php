<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateUser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserSyncService
{
    public function sync(array $user, ?array $parent = null): RebateUser
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('Sub2API 用户 ID 无效');
        }

        return DB::transaction(function () use ($user, $parent, $userId): RebateUser {
            $parentId = (int) ($parent['id'] ?? $user['parent_user_id'] ?? 0);
            if ($parentId === $userId) {
                $parentId = 0;
            }

            if ($parentId > 0) {
                if ($parent) {
                    $this->syncUser($parent, RebateUser::STATUS_INACTIVE);
                } else {
                    RebateUser::query()->firstOrCreate(
                        ['id' => $parentId],
                        ['status' => RebateUser::STATUS_INACTIVE],
                    );
                }
            }

            $model = $this->syncUser($user, RebateUser::STATUS_INACTIVE);
            RebateReferral::query()->updateOrCreate(
                ['user_id' => $userId],
                ['parent_user_id' => $parentId > 0 ? $parentId : null],
            );

            return $model->refresh();
        });
    }

    public function syncMany(array $users): int
    {
        foreach ($users as $user) {
            $this->sync($user, is_array($user['parent'] ?? null) ? $user['parent'] : null);
        }

        return count($users);
    }

    private function syncUser(array $data, string $fallbackStatus): RebateUser
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Sub2API 用户 ID 无效');
        }

        $status = (string) ($data['status'] ?? $fallbackStatus);

        $affCode = trim((string) ($data['aff_code'] ?? ''));

        return RebateUser::query()->updateOrCreate(
            ['id' => $id],
            [
                'username' => $data['username'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $status === RebateUser::STATUS_ACTIVE
                    ? RebateUser::STATUS_ACTIVE
                    : RebateUser::STATUS_INACTIVE,
                'aff_code' => $affCode !== '' ? $affCode : null,
                'last_synced_at' => now(),
            ],
        );
    }
}
