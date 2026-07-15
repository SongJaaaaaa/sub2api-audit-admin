<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_adjustments', function (Blueprint $table): void {
            $table->string('business_source', 40)->nullable()->after('idempotency_key');
            $table->string('business_id', 80)->nullable()->after('business_source');
            $table->timestamp('request_started_at')->nullable()->after('created_by');
            $table->unique(['business_source', 'business_id']);
        });

        Schema::table('cash_entries', function (Blueprint $table): void {
            $table->unique('ledger_adjustment_id');
        });

        Schema::table('gift_quota_entries', function (Blueprint $table): void {
            $table->unique('ledger_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::table('gift_quota_entries', function (Blueprint $table): void {
            $table->dropUnique(['ledger_adjustment_id']);
        });

        Schema::table('cash_entries', function (Blueprint $table): void {
            $table->dropUnique(['ledger_adjustment_id']);
        });

        Schema::table('ledger_adjustments', function (Blueprint $table): void {
            $table->dropUnique(['business_source', 'business_id']);
            $table->dropColumn(['business_source', 'business_id', 'request_started_at']);
        });
    }
};
