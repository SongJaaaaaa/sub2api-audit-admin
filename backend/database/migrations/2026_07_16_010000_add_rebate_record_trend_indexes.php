<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rebate_records', function (Blueprint $table): void {
            $table->index('created_at', 'rebate_records_created_at_index');
            $table->index(['receiver_user_id', 'created_at'], 'rebate_records_receiver_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('rebate_records', function (Blueprint $table): void {
            $table->dropIndex('rebate_records_receiver_created_at_index');
            $table->dropIndex('rebate_records_created_at_index');
        });
    }
};
