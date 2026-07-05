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
        $data = $req->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $res = $svc->login($data['email'], $data['password']);

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
     * @return array{id: int, name: string, email: string, status: string}
     */
    private function adminData(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => $admin->status,
        ];
    }
}
