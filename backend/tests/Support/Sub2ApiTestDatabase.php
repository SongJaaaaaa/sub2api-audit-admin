<?php

namespace Tests\Support;

use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Support\ChinaDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;

trait Sub2ApiTestDatabase
{
    protected function setUpSub2ApiDatabase(): void
    {
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
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_creation_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->decimal('actual_cost', 20, 10)->default(0);
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

        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'test-key');
        $client = Mockery::mock(Sub2ApiAdminClient::class)->makePartial();
        $client->shouldReceive('users')->andReturnUsing(
            fn (int $page = 1, int $pageSize = 100): array => $this->sub2ApiUsers($page, $pageSize),
        );
        $client->shouldReceive('redeemCodes')->andReturnUsing(
            fn (int $page = 1, int $pageSize = 100): array => $this->sub2ApiRedeemCodes($page, $pageSize),
        );
        $client->shouldReceive('paymentOrders')->andReturnUsing(
            fn (int $page = 1, int $pageSize = 100): array => $this->sub2ApiPaymentOrders($page, $pageSize),
        );
        $client->shouldReceive('dashboardUsersRanking')->andReturnUsing(
            fn (ChinaDateRange $range, int $limit): array => $this->sub2ApiRanking($range, $limit),
        );
        app()->instance(Sub2ApiAdminClient::class, $client);
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

    private function sub2ApiUsers(int $page, int $pageSize): array
    {
        $rows = DB::connection('sub2api')->table('users')->whereNull('deleted_at')->orderByDesc('id')->get()
            ->map(function ($row): array {
                $item = (array) $row;
                $item['last_used_at'] = DB::connection('sub2api')->table('usage_logs')
                    ->where('user_id', $row->id)->max('created_at');

                return $item;
            })->all();

        return $this->sub2ApiPage($rows, $page, $pageSize);
    }

    private function sub2ApiRedeemCodes(int $page, int $pageSize): array
    {
        $users = DB::connection('sub2api')->table('users')->get()->keyBy('id');
        $rows = DB::connection('sub2api')->table('redeem_codes')->orderBy('id')->get()
            ->map(function ($row) use ($users): array {
                $item = (array) $row;
                $user = $users->get($row->used_by);
                $item['user'] = $user ? [
                    'email' => $user->email,
                    'username' => $user->username,
                ] : null;

                return $item;
            })->all();

        return $this->sub2ApiPage($rows, $page, $pageSize);
    }

    private function sub2ApiPaymentOrders(int $page, int $pageSize): array
    {
        $users = DB::connection('sub2api')->table('users')->get()->keyBy('id');
        $rows = DB::connection('sub2api')->table('payment_orders')->orderBy('id')->get()
            ->map(function ($row) use ($users): array {
                $item = (array) $row;
                $user = $users->get($row->user_id);
                $item['user'] = $user ? [
                    'email' => $user->email,
                    'username' => $user->username,
                ] : null;

                return $item;
            })->all();

        return $this->sub2ApiPage($rows, $page, $pageSize);
    }

    private function sub2ApiRanking(ChinaDateRange $range, int $limit): array
    {
        $users = DB::connection('sub2api')->table('users')->get()->keyBy('id');
        $rows = DB::connection('sub2api')->table('usage_logs')->get()
            ->filter(function ($row) use ($range): bool {
                $time = CarbonImmutable::parse($row->created_at, 'UTC');

                return $time->greaterThanOrEqualTo($range->utcStart) && $time->lessThan($range->utcEndExclusive);
            })
            ->groupBy('user_id')
            ->map(function ($logs, $userId) use ($users): array {
                $user = $users->get($userId);

                return [
                    'user_id' => (int) $userId,
                    'email' => $user?->email,
                    'actual_cost' => (string) $logs->sum('actual_cost'),
                    'requests' => $logs->count(),
                    'tokens' => (int) $logs->sum(fn ($row): int => (int) $row->input_tokens
                        + (int) $row->output_tokens
                        + (int) $row->cache_creation_tokens
                        + (int) $row->cache_read_tokens),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['actual_cost'])
            ->take($limit)
            ->values()
            ->all();

        return ['ranking' => $rows];
    }

    private function sub2ApiPage(array $rows, int $page, int $pageSize): array
    {
        $total = count($rows);

        return [
            'code' => 0,
            'data' => [
                'items' => array_slice($rows, ($page - 1) * $pageSize, $pageSize),
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => max(1, (int) ceil($total / $pageSize)),
                'total' => $total,
            ],
        ];
    }
}
