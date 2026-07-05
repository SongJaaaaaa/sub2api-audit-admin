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
        app(AuditLogService::class)->record($admin, 'reconcile.create', 'reconciliation_batch', 1, null, ['status' => 'balanced']);

        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->getJson('/api/v1/audit-logs?action=reconcile.create')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.admin_id', $admin->id)
            ->assertJsonPath('items.0.action', 'reconcile.create');
    }
}
