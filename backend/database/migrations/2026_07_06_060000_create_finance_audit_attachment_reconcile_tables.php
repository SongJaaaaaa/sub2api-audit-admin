<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_adjustments', function (Blueprint $table): void {
            $table->decimal('cash_amount', 18, 2)->default(0)->after('amount');
            $table->decimal('gift_quota_amount', 18, 2)->default(0)->after('cash_amount');
        });

        Schema::create('cash_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_no', 32)->unique();
            $table->foreignId('ledger_adjustment_id')->nullable()->constrained('ledger_adjustments')->nullOnDelete();
            $table->unsignedBigInteger('sub2api_user_id')->nullable()->index();
            $table->string('sub2api_user_email')->nullable();
            $table->string('direction', 20)->index();
            $table->decimal('cash_amount', 18, 2);
            $table->string('source', 40)->index();
            $table->string('remark', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gift_quota_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_no', 32)->unique();
            $table->foreignId('ledger_adjustment_id')->nullable()->constrained('ledger_adjustments')->nullOnDelete();
            $table->unsignedBigInteger('sub2api_user_id')->nullable()->index();
            $table->string('sub2api_user_email')->nullable();
            $table->decimal('quota_amount', 18, 2);
            $table->string('source', 40)->index();
            $table->string('remark', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('operation_expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_no', 32)->unique();
            $table->string('category', 80);
            $table->decimal('amount', 18, 2);
            $table->string('paid_at', 20)->index();
            $table->string('remark', 500)->nullable();
            $table->text('content_html')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('attachable_type', 80)->index();
            $table->unsignedBigInteger('attachable_id')->index();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliation_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_no', 32)->unique();
            $table->date('biz_date')->unique();
            $table->decimal('cash_total', 18, 2)->default(0);
            $table->decimal('quota_total', 18, 2)->default(0);
            $table->decimal('gift_total', 18, 2)->default(0);
            $table->decimal('sub2api_delta_total', 18, 2)->default(0);
            $table->decimal('diff_amount', 18, 2)->default(0);
            $table->string('status', 20)->index();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliation_diffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_batch_id')->constrained('reconciliation_batches')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 200);
            $table->decimal('amount', 18, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->string('action', 80)->index();
            $table->string('target_type', 80)->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->string('ip', 80)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reconciliation_diffs');
        Schema::dropIfExists('reconciliation_batches');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('operation_expenses');
        Schema::dropIfExists('gift_quota_entries');
        Schema::dropIfExists('cash_entries');

        Schema::table('ledger_adjustments', function (Blueprint $table): void {
            $table->dropColumn(['cash_amount', 'gift_quota_amount']);
        });
    }
};
