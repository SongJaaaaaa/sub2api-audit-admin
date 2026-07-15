<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProfitSettlement;
use App\Services\Profit\ProfitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfitController extends Controller
{
    public function summary(Request $req, ProfitService $service): JsonResponse
    {
        $data = $req->validate($this->dateRules());

        return response()->json($service->summary($data['start_date'], $data['end_date']));
    }

    public function details(Request $req, ProfitService $service): JsonResponse
    {
        $data = $req->validate(['biz_date' => ['required', 'date_format:Y-m-d']]);

        return response()->json($service->details($data['biz_date']));
    }

    public function store(Request $req, ProfitService $service): JsonResponse
    {
        $data = $req->validate($this->dateRules());
        $batch = $service->settle($req->user(), $data['start_date'], $data['end_date']);

        return response()->json(['settlement' => $service->row($batch), 'message' => '分账已确认'], 201);
    }

    public function index(Request $req, ProfitService $service): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);
        $req->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in([ProfitSettlement::STATUS_CONFIRMED, ProfitSettlement::STATUS_REVERSED])],
        ]);

        return response()->json($service->settlements([
            'start_date' => $req->query('start_date', ''),
            'end_date' => $req->query('end_date', ''),
            'status' => $req->query('status', ''),
        ], $page, $pageSize));
    }

    public function items(ProfitSettlement $settlement, ProfitService $service): JsonResponse
    {
        return response()->json(['items' => $service->items($settlement)]);
    }

    public function reverse(Request $req, ProfitSettlement $settlement, ProfitService $service): JsonResponse
    {
        $batch = $service->reverse($req->user(), $settlement);

        return response()->json(['settlement' => $service->row($batch), 'message' => '分账已撤销']);
    }

    private function dateRules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
