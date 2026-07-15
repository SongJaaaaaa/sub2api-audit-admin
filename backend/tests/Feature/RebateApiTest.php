<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Rebate\RebateEvent;
use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use App\Services\Rebate\BalanceService;
use App\Services\Rebate\DirectRebateService;
use App\Services\Rebate\EventIngestService;
use App\Services\Rebate\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Sub2ApiTestDatabase;
use Tests\TestCase;

class RebateApiTest extends TestCase
{
    use RefreshDatabase;
    use Sub2ApiTestDatabase;

    private RebateUser $parent;

    private RebateUser $child;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->setUpSub2ApiDatabase();
        $this->insertSub2ApiUser(['id' => 1001, 'email' => 'parent@example.com', 'username' => 'parent']);
        $this->insertSub2ApiUser(['id' => 1002, 'email' => 'child@example.com', 'username' => 'child']);
        DB::connection('sub2api')->table('user_affiliates')->insert([
            ['user_id' => 1001, 'aff_code' => 'PARENT', 'inviter_id' => null],
            ['user_id' => 1002, 'aff_code' => 'CHILD', 'inviter_id' => 1001],
        ]);
        $this->parent = $this->user(1001, 'parent@example.com', 'PARENT');
        $this->child = $this->user(1002, 'child@example.com', 'CHILD');
        RebateReferral::query()->create(['user_id' => 1001, 'parent_user_id' => null]);
        RebateReferral::query()->create(['user_id' => 1002, 'parent_user_id' => 1001]);
        app(DirectRebateService::class)->process(RebateEvent::query()->create([
            'source_type' => EventIngestService::SOURCE_NATIVE_RECHARGE,
            'source_id' => 'api-test-100',
            'user_id' => 1002,
            'amount' => '100.00',
            'happened_at' => now(),
            'status' => RebateEvent::STATUS_PENDING,
        ]));
        config()->set('rebate.invite_url_template', 'https://sub2api.test/register?aff={code}');
    }

    protected function tearDown(): void
    {
        $this->tearDownSub2ApiDatabase();

        parent::tearDown();
    }

    public function test_affiliate_business_pages_and_withdrawal_application_return_the_frontend_contract(): void
    {
        $token = $this->parent->createToken('affiliate-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/affiliate/dashboard')
            ->assertOk()
            ->assertJsonPath('user.invite_code', 'PARENT')
            ->assertJsonPath('balance.available_amount', '15.00')
            ->assertJsonPath('direct_count', 1)
            ->assertJsonPath('converted_count', 1)
            ->assertJsonPath('total_direct_recharge_amount', '100.00')
            ->assertJsonPath('recent_rebates.0.payer_email', 'child@example.com');
        $this->withToken($token)->getJson('/api/v1/affiliate/team')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.user_id', 1002)
            ->assertJsonPath('items.0.total_rebate_amount', '15.00');
        $this->withToken($token)->getJson('/api/v1/affiliate/promotion')
            ->assertOk()
            ->assertJsonPath('invite_url', 'https://sub2api.test/register?aff=PARENT')
            ->assertJsonPath('conversion_rate', '100.00');
        $this->withToken($token)->getJson('/api/v1/affiliate/rebate-records')
            ->assertOk()
            ->assertJsonPath('items.0.level', 1)
            ->assertJsonPath('items.0.rebate_amount', '15.00');
        $this->withToken($token)->getJson('/api/v1/affiliate/withdrawals')
            ->assertOk()
            ->assertJsonPath('config.min_amount', '2.00')
            ->assertJsonPath('balance.available_amount', '15.00');

        $this->withToken($token)->postJson('/api/v1/affiliate/withdrawals', ['amount' => '10.00'])
            ->assertCreated()
            ->assertJsonPath('withdrawal.amount', '10.00')
            ->assertJsonPath('withdrawal.status', RebateWithdrawal::STATUS_PENDING);
        $this->assertDatabaseHas('rebate_balances', [
            'user_id' => 1001,
            'available_amount' => '5.00',
            'frozen_amount' => '10.00',
        ]);
    }

    public function test_admin_relationship_config_and_withdrawal_actions_return_expected_statuses(): void
    {
        $admin = Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/rebate/admin/relationships')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/v1/rebate/admin/relationships?user_id=1001')
            ->assertOk()
            ->assertJsonPath('user.user_id', 1001)
            ->assertJsonPath('user.direct_count', 1)
            ->assertJsonPath('items.0.user_id', 1002)
            ->assertJsonPath('items.0.total_recharge_amount', '100.00');
        $this->withToken($token)->getJson('/api/v1/rebate/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('total_users', 2)
            ->assertJsonPath('total_rebate_amount', '15.00');

        $this->withToken($token)->putJson('/api/v1/rebate/admin/config', [
            'milestone_amount' => '100.00',
            'milestone_reward_amount' => '15.00',
            'milestone_max_times' => 2,
            'stage_amount' => '100.00',
            'stage_reward_amount' => '15.00',
            'withdraw_min_amount' => '2.00',
            'withdraw_daily_limit' => 10,
            'withdraw_daily_amount_limit' => '0.00',
            'withdraw_to_api_quota_rate' => '1.0000',
            'native_recharge_enabled' => true,
            'redeem_enabled' => true,
            'admin_adjust_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('message', '返利配置已保存')
            ->assertJsonPath('admin_adjust_enabled', false);

        $withdrawals = app(WithdrawalService::class);
        $approved = $withdrawals->request($this->parent, '5.00');
        $this->withToken($token)->postJson("/api/v1/rebate/admin/withdrawals/{$approved->id}/approve")
            ->assertStatus(202)
            ->assertJsonPath('withdrawal.status', RebateWithdrawal::STATUS_PROCESSING);

        app(BalanceService::class)->credit($this->parent->id, '10.00', 'test', 'api-extra');
        $rejected = $withdrawals->request($this->parent, '5.00');
        $this->withToken($token)->postJson("/api/v1/rebate/admin/withdrawals/{$rejected->id}/reject", ['reason' => '资料不符'])
            ->assertOk()
            ->assertJsonPath('withdrawal.status', RebateWithdrawal::STATUS_REJECTED);

        $exception = $withdrawals->request($this->parent, '5.00');
        $exception->update(['status' => RebateWithdrawal::STATUS_EXCEPTION]);
        $this->withToken($token)->postJson("/api/v1/rebate/admin/withdrawals/{$exception->id}/retry")
            ->assertStatus(202)
            ->assertJsonPath('withdrawal.status', RebateWithdrawal::STATUS_PROCESSING);
        $this->withToken($token)->getJson('/api/v1/rebate/admin/withdrawals')
            ->assertOk()->assertJsonPath('total', 3);
    }

    public function test_affiliate_today_usage_excludes_rejected_and_read_only_withdrawals(): void
    {
        $service = app(WithdrawalService::class);
        $rejected = $service->request($this->parent, '5.00');
        $service->reject($rejected, null, '测试拒绝');
        RebateWithdrawal::query()->create([
            'withdrawal_no' => 'LEGACY-TODAY',
            'user_id' => $this->parent->id,
            'amount' => '7.00',
            'quota_amount' => '7.00',
            'status' => RebateWithdrawal::STATUS_SUCCEEDED,
            'requested_at' => now(),
            'read_only' => true,
        ]);
        $service->request($this->parent, '4.00');

        $token = $this->parent->createToken('affiliate-today')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/affiliate/withdrawals')
            ->assertOk()
            ->assertJsonPath('today_count', 1)
            ->assertJsonPath('today_amount', '4.00');
    }

    private function user(int $id, string $email, string $affCode): RebateUser
    {
        return RebateUser::query()->create([
            'id' => $id,
            'email' => $email,
            'username' => 'user'.$id,
            'status' => RebateUser::STATUS_ACTIVE,
            'aff_code' => $affCode,
        ]);
    }
}
