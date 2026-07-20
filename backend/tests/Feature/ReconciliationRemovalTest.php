<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReconciliationRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_tables_and_routes_are_removed(): void
    {
        $this->assertFalse(Schema::hasTable('reconciliation_batches'));
        $this->assertFalse(Schema::hasTable('reconciliation_diffs'));

        $this->getJson('/api/v1/reconciliations')->assertNotFound();
        $this->postJson('/api/v1/reconciliations', ['biz_date' => '2026-07-20'])->assertNotFound();
        $this->getJson('/api/v1/reconciliations/1/diffs')->assertNotFound();
    }
}
