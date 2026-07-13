<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Stats\DashboardStatsService;
use App\Support\ChinaDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index(Request $req, DashboardStatsService $service): JsonResponse
    {
        [$range, $limit] = $this->params($req);

        return response()->json($service->data($range, $limit));
    }

    private function params(Request $req): array
    {
        $start = trim((string) $req->query('start_date', ''));
        $end = trim((string) $req->query('end_date', ''));

        if (($start === '') !== ($end === '')) {
            throw ValidationException::withMessages([
                'start_date' => ['start_date 和 end_date 必须同时提供'],
                'end_date' => ['start_date 和 end_date 必须同时提供'],
            ]);
        }

        if ($start === '') {
            $today = now(config('ledger.timezone', 'Asia/Shanghai'))->toDateString();
            $start = $today;
            $end = $today;
        }

        $data = Validator::make([
            'start_date' => $start,
            'end_date' => $end,
            'limit' => $req->query('limit', 10),
        ], [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return [
            ChinaDateRange::make($data['start_date'], $data['end_date']),
            (int) $data['limit'],
        ];
    }
}
