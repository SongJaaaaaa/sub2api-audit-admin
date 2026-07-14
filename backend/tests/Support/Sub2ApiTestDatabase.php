<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait Sub2ApiTestDatabase
{
    protected function setUpSub2ApiDatabase(): void
    {
        config()->set('sub2api.db_connection', 'sub2api');
        config()->set('database.connections.sub2api', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sub2api');

        Schema::connection('sub2api')->create('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('role')->nullable();
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('total_recharged', 20, 8)->default(0);
            $table->string('status')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::connection('sub2api')->create('redeem_codes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('type')->nullable();
            $table->decimal('value', 20, 8)->default(0);
            $table->string('status')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('sub2api')->create('usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('created_at');
        });

        Schema::connection('sub2api')->create('payment_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('out_trade_no')->nullable();
            $table->string('order_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDownSub2ApiDatabase(): void
    {
        DB::disconnect('sub2api');
    }

    protected function insertSub2ApiUser(array $values = []): void
    {
        DB::connection('sub2api')->table('users')->insert(array_merge([
            'id' => 1001,
            'email' => 'alpha@example.com',
            'username' => 'alpha',
            'role' => 'user',
            'balance' => '0',
            'total_recharged' => '0',
            'status' => 'active',
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-01 00:00:00',
            'deleted_at' => null,
        ], $values));
    }
}
