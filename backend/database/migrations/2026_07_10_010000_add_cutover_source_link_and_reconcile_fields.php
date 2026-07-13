<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->string('value', 100);
            $table->timestamp('locked_at');
            $table->timestamps();
        });

        Schema::table('ledger_adjustments', function (Blueprint $table): void {
            $table->unsignedBigInteger('sub2api_source_id')->nullable()->unique();
        });

        Schema::table('reconciliation_batches', function (Blueprint $table): void {
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->unsignedInteger('local_success_count')->default(0);
            $table->decimal('local_adjustment_net', 20, 8)->default(0);
            $table->unsignedInteger('remote_matched_count')->default(0);
            $table->decimal('remote_matched_net', 20, 8)->default(0);
            $table->unsignedInteger('external_count')->default(0);
            $table->decimal('external_net', 20, 8)->default(0);
            $table->unsignedInteger('audit_orphan_count')->default(0);
            $table->decimal('audit_orphan_net', 20, 8)->default(0);
            $table->unsignedInteger('issue_count')->default(0);
        });

        Schema::table('reconciliation_diffs', function (Blueprint $table): void {
            $table->foreignId('local_adjustment_id')->nullable()->constrained('ledger_adjustments')->nullOnDelete();
            $table->unsignedBigInteger('remote_event_id')->nullable()->index();
            $table->unsignedBigInteger('sub2api_user_id')->nullable()->index();
            $table->string('direction', 20)->nullable();
            $table->decimal('local_amount', 20, 8)->nullable();
            $table->decimal('remote_amount', 20, 8)->nullable();
            $table->index('type');
        });

        DB::table('reconciliation_batches')->where('status', 'balanced')->update(['status' => 'ok']);
        DB::table('reconciliation_batches')->where('status', 'diff')->update(['status' => 'error']);
    }

    public function down(): void
    {
        DB::table('reconciliation_batches')->where('status', 'ok')->update(['status' => 'balanced']);
        DB::table('reconciliation_batches')->whereIn('status', ['warning', 'error'])->update(['status' => 'diff']);

        Schema::table('reconciliation_diffs', function (Blueprint $table): void {
            $table->dropForeign(['local_adjustment_id']);
            $table->dropIndex(['remote_event_id']);
            $table->dropIndex(['sub2api_user_id']);
            $table->dropIndex(['type']);
            $table->dropColumn([
                'local_adjustment_id',
                'remote_event_id',
                'sub2api_user_id',
                'direction',
                'local_amount',
                'remote_amount',
            ]);
        });

        Schema::table('reconciliation_batches', function (Blueprint $table): void {
            $table->dropColumn([
                'period_start',
                'period_end',
                'local_success_count',
                'local_adjustment_net',
                'remote_matched_count',
                'remote_matched_net',
                'external_count',
                'external_net',
                'audit_orphan_count',
                'audit_orphan_net',
                'issue_count',
            ]);
        });

        Schema::table('ledger_adjustments', function (Blueprint $table): void {
            $table->dropUnique(['sub2api_source_id']);
            $table->dropColumn('sub2api_source_id');
        });

        Schema::dropIfExists('system_settings');
    }
};
