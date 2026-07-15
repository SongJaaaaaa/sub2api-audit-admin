<?php

namespace App\Services\Auth;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthService
{
    /**
     * @return array{admin: Admin, token: string}
     */
    public function login(string $account, string $pwd): array
    {
        $account = strtolower(trim($account));
        $admin = Admin::query()
            ->where('username', $account)
            ->orWhere('email', $account)
            ->first();

        if (! $admin || ! Hash::check($pwd, $admin->password) || ! $admin->isActive()) {
            throw ValidationException::withMessages([
                'account' => ['账号或密码错误'],
            ]);
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
