<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationBatch;
use App\Services\Reconcile\ReconcileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconcileController extends Controller
{
    public function index(Request $req, ReconcileService $service): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);

        return response()->json($service->list([
            'start_date' => $req->query('start_date', ''),
            'end_date' => $req->query('end_date', ''),
            'status' => $req->query('status', ''),
            'has_external' => $req->query('has_external', ''),
            'has_orphan' => $req->query('has_orphan', ''),
            'created_by' => $req->query('created_by', 0),
        ], $page, $pageSize));
    }

    public function store(Request $req, ReconcileService $service): JsonResponse
    {
        $data = $req->validate([
            'biz_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $batch = $service->create($req->user(), $data['biz_date']);

        return response()->json(['batch' => $service->row($batch), 'message' => '对账批次已生成'], 201);
    }

    public function diffs(ReconciliationBatch $batch, ReconcileService $service): JsonResponse
    {
        return response()->json(['items' => $service->diffs($batch)]);
    }
}
