<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reconciliation_diffs');
        Schema::dropIfExists('reconciliation_batches');
    }

    public function down(): void
    {
        Schema::create('reconciliation_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_no', 32)->unique();
            $table->date('biz_date')->unique();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->decimal('cash_total', 18, 2)->default(0);
            $table->decimal('quota_total', 18, 2)->default(0);
            $table->decimal('gift_total', 18, 2)->default(0);
            $table->decimal('sub2api_delta_total', 18, 2)->default(0);
            $table->decimal('diff_amount', 18, 2)->default(0);
            $table->unsignedInteger('local_success_count')->default(0);
            $table->decimal('local_adjustment_net', 20, 8)->default(0);
            $table->unsignedInteger('remote_matched_count')->default(0);
            $table->decimal('remote_matched_net', 20, 8)->default(0);
            $table->unsignedInteger('external_count')->default(0);
            $table->decimal('external_net', 20, 8)->default(0);
            $table->unsignedInteger('audit_orphan_count')->default(0);
            $table->decimal('audit_orphan_net', 20, 8)->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->string('status', 20)->index();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliation_diffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_batch_id')->constrained('reconciliation_batches')->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('title', 200);
            $table->decimal('amount', 18, 2);
            $table->text('reason')->nullable();
            $table->foreignId('local_adjustment_id')->nullable()->constrained('ledger_adjustments')->nullOnDelete();
            $table->unsignedBigInteger('remote_event_id')->nullable()->index();
            $table->unsignedBigInteger('sub2api_user_id')->nullable()->index();
            $table->string('direction', 20)->nullable();
            $table->decimal('local_amount', 20, 8)->nullable();
            $table->decimal('remote_amount', 20, 8)->nullable();
            $table->timestamps();
        });
    }
};
