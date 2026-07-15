<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('username', 100)->nullable();
            $table->string('email', 191)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->string('aff_code', 100)->nullable()->unique();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->string('legacy_source_id', 191)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->boolean('read_only')->default(false);
            $table->timestamps();
            $table->unique(['legacy_source', 'legacy_source_id'], 'rebate_users_legacy_unique');
        });

        Schema::create('rebate_referrals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('parent_user_id')->nullable()->index();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
            $table->foreign('parent_user_id')->references('id')->on('rebate_users')->nullOnDelete();
        });

        Schema::create('rebate_configs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->decimal('milestone_amount', 18, 2)->default(100);
            $table->decimal('milestone_reward_amount', 18, 2)->default(15);
            $table->unsignedInteger('milestone_max_times')->default(2);
            $table->decimal('stage_amount', 18, 2)->default(100);
            $table->decimal('stage_reward_amount', 18, 2)->default(15);
            $table->decimal('withdraw_min_amount', 18, 2)->default(2);
            $table->unsignedInteger('withdraw_daily_limit')->default(10);
            $table->decimal('withdraw_daily_amount_limit', 18, 2)->default(0);
            $table->decimal('withdraw_to_api_quota_rate', 18, 4)->default(1);
            $table->boolean('native_recharge_enabled')->default(true);
            $table->boolean('redeem_enabled')->default(true);
            $table->boolean('admin_adjust_enabled')->default(false);
            $table->timestamp('rebate_cutover_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_referrals');
        Schema::dropIfExists('rebate_configs');
        Schema::dropIfExists('rebate_users');
    }
};
