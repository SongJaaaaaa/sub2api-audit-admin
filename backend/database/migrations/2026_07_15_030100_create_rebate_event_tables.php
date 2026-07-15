<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_scan_cursors', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 40)->unique();
            $table->string('cursor_value', 191)->nullable();
            $table->timestamp('cursor_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('rebate_events', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 40);
            $table->string('source_id', 191);
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 18, 2);
            $table->timestamp('happened_at')->index();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id'], 'rebate_events_source_unique');
            $table->foreign('user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
        });

        Schema::create('rebate_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('total_recharge_amount', 18, 2)->default(0);
            $table->unsignedInteger('milestone_times')->default(0);
            $table->unsignedInteger('stage_times')->default(0);
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
            $table->foreign('last_event_id')->references('id')->on('rebate_events')->nullOnDelete();
        });

        Schema::create('rebate_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('receiver_user_id')->index();
            $table->unsignedBigInteger('payer_user_id')->index();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('type', 20);
            $table->decimal('source_amount', 18, 2);
            $table->decimal('rebate_amount', 18, 2);
            $table->unsignedInteger('trigger_count')->default(1);
            $table->string('status', 20)->default('confirmed');
            $table->json('config_snapshot');
            $table->string('remark', 255)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'receiver_user_id', 'level', 'type'], 'rebate_records_event_receiver_unique');
            $table->foreign('event_id')->references('id')->on('rebate_events')->cascadeOnDelete();
            $table->foreign('receiver_user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
            $table->foreign('payer_user_id')->references('id')->on('rebate_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_records');
        Schema::dropIfExists('rebate_progress');
        Schema::dropIfExists('rebate_events');
        Schema::dropIfExists('rebate_scan_cursors');
    }
};
