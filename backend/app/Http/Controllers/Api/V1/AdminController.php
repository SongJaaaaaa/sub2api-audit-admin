<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\ChinaTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    private function row(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'sub2api_user_id' => $admin->sub2api_user_id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => $admin->status,
            'created_at' => ChinaTime::fmt($admin->created_at),
        ];
    }
}
