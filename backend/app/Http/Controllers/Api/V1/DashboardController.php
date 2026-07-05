<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Stats\DashboardStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $req, DashboardStatsService $service): JsonResponse
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $from = $req->query('from')
            ? CarbonImmutable::parse((string) $req->query('from'), $tz)->utc()
            : CarbonImmutable::now($tz)->subDays(7)->startOfDay()->utc();
        $to = $req->query('to')
            ? CarbonImmutable::parse((string) $req->query('to'), $tz)->utc()
            : CarbonImmutable::now($tz)->endOfDay()->utc();
        $limit = min(max((int) $req->query('limit', 10), 1), 50);
        $group = (string) $req->query('model_group', 'all');

        return response()->json($service->data($from, $to, $group, $limit));
    }
}
