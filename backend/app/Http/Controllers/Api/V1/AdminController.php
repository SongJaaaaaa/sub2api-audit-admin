<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Audit\AuditLogService;
use App\Support\ChinaTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $req): JsonResponse
    {
        $page = max((int) $req->query('page', 1), 1);
        $pageSize = min(max((int) $req->query('page_size', 20), 1), 100);
        $query = Admin::query();
        $keyword = trim((string) $req->query('keyword', ''));
        $status = trim((string) $req->query('status', ''));

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword): void {
                $sub->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('email', 'like', '%'.$keyword.'%');
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $summary = [
            'admin_count' => $total,
            'active_count' => (clone $query)->where('status', Admin::STATUS_ACTIVE)->count(),
            'disabled_count' => (clone $query)->where('status', Admin::STATUS_DISABLED)->count(),
        ];
        $items = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn (Admin $admin): array => $this->row($admin))
            ->all();

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ]);
    }

    public function store(Request $req, AuditLogService $audit): JsonResponse
    {
        $req->merge([
            'name' => trim((string) $req->input('name')),
            'email' => strtolower(trim((string) $req->input('email'))),
        ]);
        $data = $req->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'status' => ['required', Rule::in([Admin::STATUS_ACTIVE, Admin::STATUS_DISABLED])],
        ]);

        unset($data['password_confirmation']);
        $admin = Admin::query()->create($data);
        $row = $this->row($admin);
        $audit->record($req->user(), 'admin.create', 'admin', $admin->id, null, $row);

        return response()->json([
            'message' => '管理员账号已创建',
            'admin' => $row,
        ], 201);
    }

    private function row(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => $admin->status,
            'created_at' => ChinaTime::fmt($admin->created_at),
        ];
    }
}
