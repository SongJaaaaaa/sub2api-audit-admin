<?php

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Rebate\RebateUser;
use App\Services\Auth\AffiliateAuthService;
use App\Support\RebatePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateAuthController extends Controller
{
    public function login(Request $request, AffiliateAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $result = $auth->login(trim($data['account']), $data['password']);

        return response()->json([
            'token' => $result['token'],
            'user' => RebatePresenter::user($result['user']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var RebateUser $user */
        $user = $request->user();

        return response()->json(['user' => RebatePresenter::user($user)]);
    }

    public function logout(Request $request, AffiliateAuthService $auth): JsonResponse
    {
        /** @var RebateUser $user */
        $user = $request->user();
        $auth->logout($user);

        return response()->json(['message' => '已退出']);
    }
}
