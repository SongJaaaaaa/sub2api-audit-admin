<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('available_amount', 18, 2)->default(0);
            $table->decimal('frozen_amount', 18, 2)->default(0);
            $table->decimal('withdrawn_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
        });

        Schema::create('rebate_balance_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('balance_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('action', 30);
            $table->decimal('amount', 18, 2);
            $table->decimal('available_before', 18, 2);
            $table->decimal('available_after', 18, 2);
            $table->decimal('frozen_before', 18, 2);
            $table->decimal('frozen_after', 18, 2);
            $table->decimal('withdrawn_before', 18, 2);
            $table->decimal('withdrawn_after', 18, 2);
            $table->string('business_type', 50);
            $table->string('business_key', 191);
            $table->string('note', 500)->nullable();
            $table->json('meta')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_source_id', 191)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->boolean('read_only')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['business_type', 'business_key', 'action'], 'rebate_balance_entries_business_unique');
            $table->unique(['legacy_source', 'legacy_source_id'], 'rebate_balance_entries_legacy_unique');
            $table->foreign('balance_id')->references('id')->on('rebate_balances')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
        });

        Schema::create('rebate_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->string('withdrawal_no', 64)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('quota_amount', 18, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('remark', 500)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->string('reject_reason', 500)->nullable();
            $table->text('exception_reason')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('payout_reference', 191)->nullable();
            $table->json('payout_response')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_source_id', 191)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->boolean('read_only')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['legacy_source', 'legacy_source_id'], 'rebate_withdrawals_legacy_unique');
            $table->foreign('user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_withdrawals');
        Schema::dropIfExists('rebate_balance_entries');
        Schema::dropIfExists('rebate_balances');
    }
};
