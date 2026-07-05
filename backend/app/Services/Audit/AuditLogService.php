<?php

namespace App\Services\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(?Admin $admin, string $action, string $targetType, ?int $targetId, ?array $before, ?array $after): AuditLog
    {
        $req = request();

        return AuditLog::query()->create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_value' => $before,
            'after_value' => $after,
            'ip' => $req instanceof Request ? $req->ip() : null,
            'user_agent' => $req instanceof Request ? substr((string) $req->userAgent(), 0, 500) : null,
        ]);
    }

    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = AuditLog::query();

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $adminId = (int) ($filters['admin_id'] ?? 0);
        if ($adminId > 0) {
            $query->where('admin_id', $adminId);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'admin_id' => $log->admin_id,
                'admin_name' => $log->admin_name,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'before_value' => $log->before_value,
                'after_value' => $log->after_value,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])->all();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
