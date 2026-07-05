<?php

use App\Models\LedgerAdjustment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('ledger_no', 32)->unique();
            $table->string('idempotency_key', 80)->unique();
            $table->unsignedBigInteger('sub2api_user_id')->index();
            $table->string('sub2api_user_email')->nullable();
            $table->string('operation', 20);
            $table->decimal('amount', 18, 2);
            $table->decimal('before_balance', 18, 2)->nullable();
            $table->decimal('after_balance', 18, 2)->nullable();
            $table->string('status', 20)->default(LedgerAdjustment::STATUS_PENDING)->index();
            $table->string('adjust_reason', 500);
            $table->text('admin_notes')->nullable();
            $table->text('sub2api_notes')->nullable();
            $table->text('exception_reason')->nullable();
            $table->json('sub2api_request')->nullable();
            $table->json('sub2api_response')->nullable();
            $table->json('confirm_response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_adjustments');
    }
};
