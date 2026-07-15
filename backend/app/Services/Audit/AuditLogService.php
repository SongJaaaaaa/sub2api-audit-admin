<?php

namespace App\Services\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\ChinaTime;
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

        $targetType = trim((string) ($filters['target_type'] ?? ''));
        if ($targetType !== '') {
            $query->where('target_type', $targetType);
        }
        $targetId = (int) ($filters['target_id'] ?? 0);
        if ($targetId > 0) {
            $query->where('target_id', $targetId);
        }
        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $query->where('ip', 'like', '%'.$ip.'%');
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword): void {
                $sub->where('action', 'like', '%'.$keyword.'%')
                    ->orWhere('target_type', 'like', '%'.$keyword.'%')
                    ->orWhere('before_value', 'like', '%'.$keyword.'%')
                    ->orWhere('after_value', 'like', '%'.$keyword.'%');
            });
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', date('Y-m-d 00:00:00', strtotime($filters['to'].' +1 day')));
        }
        $riskActions = ['admin.create', 'ledger_adjustment.create', 'ledger_adjustment.succeeded', 'ledger_adjustment.voided', 'ledger_adjustment.exception', 'operation_expense.create', 'reconcile.run', 'profit_settlement.confirm', 'profit_settlement.reverse'];
        if (($filters['risk'] ?? '') === 'high') {
            $query->whereIn('action', $riskActions);
        }

        $total = (clone $query)->count();
        $summary = [
            'record_count' => $total,
            'operator_count' => (clone $query)->whereNotNull('admin_id')->distinct()->count('admin_id'),
            'action_count' => (clone $query)->distinct()->count('action'),
            'target_count' => (clone $query)->select(['target_type', 'target_id'])->distinct()->get()->count(),
            'high_risk_count' => (clone $query)->whereIn('action', $riskActions)->count(),
            'actions' => (clone $query)->selectRaw('action, COUNT(*) as record_count')
                ->groupBy('action')->orderByDesc('record_count')->get()
                ->map(fn ($row): array => ['action' => $row->action, 'record_count' => (int) $row->record_count])->all(),
        ];
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
                'created_at' => ChinaTime::fmt($log->created_at),
            ])->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ];
    }
}
