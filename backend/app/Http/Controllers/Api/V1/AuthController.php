<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Auth\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $req, AdminAuthService $svc): JsonResponse
    {
        $req->merge([
            'account' => trim((string) $req->input('account', $req->input('email', ''))),
        ]);
        $data = $req->validate([
            'account' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $res = $svc->login($data['account'], $data['password']);

        return response()->json([
            'token' => $res['token'],
            'admin' => $this->adminData($res['admin']),
        ]);
    }

    public function me(Request $req): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $req->user();

        return response()->json([
            'admin' => $this->adminData($admin),
        ]);
    }

    public function options(): JsonResponse
    {
        $items = Admin::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Admin $admin): array => $this->adminData($admin))
            ->all();

        return response()->json(['items' => $items]);
    }

    public function logout(Request $req, AdminAuthService $svc): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $req->user();
        $svc->logout($admin);

        return response()->json([
            'message' => '已退出',
        ]);
    }

    /**
     * @return array{id: int, name: string, username: ?string, email: string, status: string}
     */
    private function adminData(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => $admin->status,
        ];
    }
}
