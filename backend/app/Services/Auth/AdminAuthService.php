<?php

namespace App\Services\Auth;

use App\Models\Admin;

class AdminAuthService
{
    /**
     * @return array{admin: Admin, token: string}
     */
    public function loginRemote(array $remote): array
    {
        $id = (int) ($remote['id'] ?? 0);
        $email = strtolower(trim((string) ($remote['email'] ?? '')));
        if ($id <= 0 || $email === '') {
            abort(502, 'Sub2API 管理员资料不完整');
        }

        $admin = Admin::query()->where('sub2api_user_id', $id)->first()
            ?? Admin::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            ?? new Admin;
        $username = trim((string) ($remote['username'] ?? ''));
        $name = trim((string) ($remote['name'] ?? $username));

        $admin->fill([
            'sub2api_user_id' => $id,
            'name' => $name !== '' ? $name : strstr($email, '@', true),
            'username' => $username !== '' ? strtolower($username) : null,
            'email' => $email,
            'status' => ($remote['status'] ?? null) === Admin::STATUS_ACTIVE
                ? Admin::STATUS_ACTIVE
                : Admin::STATUS_DISABLED,
        ])->save();

        if (! $admin->isActive()) {
            abort(403, '账号已被禁用');
        }

        return [
            'admin' => $admin,
            'token' => $admin->createToken('admin-token')->plainTextToken,
        ];
    }

    public function logout(Admin $admin): void
    {
        $token = $admin->currentAccessToken();

        if ($token) {
            $token->delete();
        }
    }
}
