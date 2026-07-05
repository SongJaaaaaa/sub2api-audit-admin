<?php

namespace App\Http\Controllers\Api\V1\Sub2Api;

use App\Http\Controllers\Controller;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Sub2ApiDataController extends Controller
{
    public function users(Request $req, Sub2ApiReadRepository $repo): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);

        return response()->json($repo->users([
            'keyword' => $req->query('keyword', ''),
        ], $page, $pageSize));
    }

    public function modelStats(Request $req, Sub2ApiReadRepository $repo): JsonResponse
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $from = $req->query('from')
            ? CarbonImmutable::parse((string) $req->query('from'), $tz)->utc()
            : CarbonImmutable::now($tz)->subDays(7)->startOfDay()->utc();
        $to = $req->query('to')
            ? CarbonImmutable::parse((string) $req->query('to'), $tz)->utc()
            : CarbonImmutable::now($tz)->endOfDay()->utc();
        $limit = min(max((int) $req->query('limit', 20), 1), 100);

        return response()->json([
            'summary' => $repo->usageSummary($from, $to, [
                'model' => $req->query('model', ''),
            ]),
            'models' => $repo->modelRanking($from, $to, [
                'model' => $req->query('model', ''),
            ], $limit),
            'sources' => $repo->rechargeSourceSummary(),
            'range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
        ]);
    }
}
