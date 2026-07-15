<?php

namespace App\Http\Middleware;

use App\Models\Rebate\RebateUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof RebateUser || $user->status !== RebateUser::STATUS_ACTIVE) {
            abort(403, '无权访问推广接口');
        }

        return $next($request);
    }
}
