<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Auth\AdminAuthService;
use App\Services\Auth\UnifiedAuthService;
use App\Support\RebatePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $req, UnifiedAuthService $auth): JsonResponse
    {
        $req->merge([
            'account' => trim((string) $req->input('account', $req->input('email', ''))),
        ]);
        $data = $req->validate([
            'account' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $res = $auth->login($data['account'], $data['password']);

        if ($res['identity_type'] === 'admin') {
            return response()->json([
                'identity_type' => 'admin',
                'token' => $res['token'],
                'admin' => $this->adminData($res['admin']),
            ]);
        }

        return response()->json([
            'identity_type' => 'affiliate',
            'token' => $res['token'],
            'user' => RebatePresenter::user($res['user']),
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
     * @return array{id: int, sub2api_user_id: ?int, name: string, username: ?string, email: string, status: string}
     */
    private function adminData(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'sub2api_user_id' => $admin->sub2api_user_id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => $admin->status,
        ];
    }
}
