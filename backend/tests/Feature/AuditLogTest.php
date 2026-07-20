<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_audit_logs(): void
    {
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        app(AuditLogService::class)->record($admin, 'attachment.upload', 'attachment', 1, null, ['name' => 'receipt.png']);

        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->getJson('/api/v1/audit-logs?action=attachment.upload&ip=127.0.0')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.admin_id', $admin->id)
            ->assertJsonPath('items.0.action', 'attachment.upload')
            ->assertJsonPath('summary.record_count', 1)
            ->assertJsonPath('summary.operator_count', 1)
            ->assertJsonPath('summary.action_count', 1)
            ->assertJsonPath('summary.target_count', 1)
            ->assertJsonPath('summary.high_risk_count', 0)
            ->assertJsonPath('summary.actions.0.action', 'attachment.upload')
            ->assertJsonPath('summary.actions.0.record_count', 1);
    }

    public function test_high_risk_filter_includes_succeeded_adjustments(): void
    {
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        $service = app(AuditLogService::class);
        $service->record($admin, 'ledger_adjustment.succeeded', 'ledger_adjustment', 1, null, ['status' => 'succeeded']);
        $service->record($admin, 'attachment.upload', 'attachment', 1, null, ['name' => 'receipt.png']);

        $token = $admin->createToken('admin-token')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('summary.record_count', 2)
            ->assertJsonPath('summary.high_risk_count', 1);

        $this->withToken($token)
            ->getJson('/api/v1/audit-logs?risk=high')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.action', 'ledger_adjustment.succeeded')
            ->assertJsonPath('summary.high_risk_count', 1);
    }
}
