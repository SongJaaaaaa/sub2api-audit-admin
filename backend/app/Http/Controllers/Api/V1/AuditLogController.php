<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $req, AuditLogService $service): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);

        return response()->json($service->list([
            'action' => $req->query('action', ''),
            'admin_id' => $req->query('admin_id', 0),
            'from' => $req->query('from', ''),
            'to' => $req->query('to', ''),
        ], $page, $pageSize));
    }
}
