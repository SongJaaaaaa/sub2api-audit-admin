<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_no', 32)->unique();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('income_total', 18, 2)->default(0);
            $table->decimal('expense_total', 18, 2)->default(0);
            $table->decimal('profit_total', 18, 2)->default(0);
            $table->unsignedInteger('income_count')->default(0);
            $table->unsignedInteger('expense_count')->default(0);
            $table->string('status', 20)->index();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('profit_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profit_settlement_id')->constrained('profit_settlements')->cascadeOnDelete();
            $table->string('item_type', 40);
            $table->unsignedBigInteger('item_id');
            $table->date('biz_date')->index();
            $table->foreignId('owner_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('owner_name', 100)->nullable();
            $table->string('reference_no', 64);
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['profit_settlement_id', 'item_type', 'item_id'], 'profit_items_batch_source_unique');
            $table->index(['item_type', 'item_id'], 'profit_items_source_index');
        });

        Schema::table('cash_entries', function (Blueprint $table): void {
            $table->boolean('profit_eligible')->default(false)->index();
            $table->unsignedBigInteger('profit_settlement_id')->nullable()->index();
        });

        Schema::table('operation_expenses', function (Blueprint $table): void {
            $table->boolean('profit_eligible')->default(false)->index();
            $table->unsignedBigInteger('profit_settlement_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('operation_expenses', function (Blueprint $table): void {
            $table->dropIndex(['profit_eligible']);
            $table->dropIndex(['profit_settlement_id']);
            $table->dropColumn(['profit_eligible', 'profit_settlement_id']);
        });

        Schema::table('cash_entries', function (Blueprint $table): void {
            $table->dropIndex(['profit_eligible']);
            $table->dropIndex(['profit_settlement_id']);
            $table->dropColumn(['profit_eligible', 'profit_settlement_id']);
        });

        Schema::dropIfExists('profit_settlement_items');
        Schema::dropIfExists('profit_settlements');
    }
};
