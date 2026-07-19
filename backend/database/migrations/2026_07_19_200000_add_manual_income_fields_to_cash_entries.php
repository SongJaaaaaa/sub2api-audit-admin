<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_entries', function (Blueprint $table): void {
            $table->date('received_at')->nullable()->index()->after('cash_amount');
            $table->text('content_html')->nullable()->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('cash_entries', function (Blueprint $table): void {
            $table->dropColumn(['received_at', 'content_html']);
        });
    }
};
