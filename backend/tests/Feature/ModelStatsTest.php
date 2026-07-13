<?php

namespace Tests\Feature;

use App\Models\Admin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModelStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 12:00:00', 'Asia/Shanghai'));
        config()->set('sub2api.admin_api.base_url', 'https://sub2api.test');
        config()->set('sub2api.admin_api.key', 'test-key');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_model_stats_uses_requested_models_and_sorts_by_tokens(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/models*' => Http::response($this->official('models', [
                $this->modelRow('model-a', 100),
                $this->modelRow('model-b', 300),
                $this->modelRow('model-c', 200),
            ])),
        ]);

        $res = $this->withToken($this->token())
            ->getJson('/api/v1/sub2api/model-stats?start_date=2026-07-01&end_date=2026-07-09&limit=2')
            ->assertOk()
            ->assertJsonPath('range.start_date', '2026-07-01')
            ->assertJsonPath('range.end_date', '2026-07-09')
            ->assertJsonPath('range.timezone', 'Asia/Shanghai')
            ->assertJsonPath('model_source', 'requested')
            ->assertJsonPath('selected_model', null)
            ->assertJsonPath('models.0.model', 'model-b')
            ->assertJsonPath('models.0.total_tokens', 300)
            ->assertJsonPath('models.1.model', 'model-c')
            ->assertJsonPath('models.1.total_tokens', 200)
            ->assertJsonPath('summary.model_count', 3)
            ->assertJsonPath('summary.request_count', 6)
            ->assertJsonPath('summary.total_tokens', 600)
            ->assertJsonPath('summary.cache_tokens', 210)
            ->assertJsonPath('summary.cache_rate', 35)
            ->assertJsonPath('summary.standard_cost', '10.5')
            ->assertJsonPath('summary.actual_cost', '9.3')
            ->assertJsonPath('summary.top3_token_rate', 100)
            ->assertJsonCount(0, 'users');

        $this->assertCount(2, $res->json('models'));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $req): bool => str_starts_with($req->url(), 'https://sub2api.test/api/v1/admin/dashboard/models?')
            && $req['start_date'] === '2026-07-01'
            && $req['end_date'] === '2026-07-09'
            && $req['timezone'] === 'Asia/Shanghai'
            && $req['model_source'] === 'requested');
    }

    public function test_selected_model_uses_requested_user_breakdown_sorted_by_total_tokens(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/user-breakdown*' => Http::response($this->official('users', [
                $this->userRow(1001, 800),
                $this->userRow(1002, 1200),
            ])),
        ]);

        $res = $this->withToken($this->token())
            ->getJson('/api/v1/sub2api/model-stats?start_date=2026-07-01&end_date=2026-07-09&model=claude-sonnet-4-6&limit=10')
            ->assertOk()
            ->assertJsonPath('model_source', 'requested')
            ->assertJsonPath('selected_model', 'claude-sonnet-4-6')
            ->assertJsonCount(0, 'models')
            ->assertJsonPath('users.0.user_id', 1002)
            ->assertJsonPath('users.0.total_tokens', 1200)
            ->assertJsonPath('users.0.cache_tokens', 70)
            ->assertJsonPath('users.1.user_id', 1001)
            ->assertJsonPath('summary.model_count', 1)
            ->assertJsonPath('summary.request_count', 4)
            ->assertJsonPath('summary.total_tokens', 2000)
            ->assertJsonPath('summary.cache_tokens', 140)
            ->assertJsonPath('summary.cache_rate', 7)
            ->assertJsonPath('summary.standard_cost', '7')
            ->assertJsonPath('summary.actual_cost', '6.2')
            ->assertJsonPath('summary.top3_token_rate', 100);

        $this->assertCount(2, $res->json('users'));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $req): bool => str_starts_with($req->url(), 'https://sub2api.test/api/v1/admin/dashboard/user-breakdown?')
            && $req['model'] === 'claude-sonnet-4-6'
            && $req['model_source'] === 'requested'
            && $req['sort_by'] === 'total_tokens'
            && (int) $req['limit'] === 10);
    }

    public function test_model_stats_defaults_to_today_and_validates_dates(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/models*' => Http::response($this->official('models', [])),
        ]);
        $token = $this->token();

        $this->withToken($token)->getJson('/api/v1/sub2api/model-stats')
            ->assertOk()
            ->assertJsonPath('range.start_date', '2026-07-10')
            ->assertJsonPath('range.end_date', '2026-07-10');
        $this->withToken($token)->getJson('/api/v1/sub2api/model-stats?start_date=2026-07-01')
            ->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/sub2api/model-stats?start_date=2026-07-02&end_date=2026-07-01')
            ->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/sub2api/model-stats?limit=101')
            ->assertStatus(422);
    }

    public function test_invalid_official_model_shape_returns_stable_502(): void
    {
        Http::fake([
            'https://sub2api.test/api/v1/admin/dashboard/models*' => Http::response([
                'code' => 0,
                'data' => [
                    'models' => [[
                        'model' => 'model-a',
                        'requests' => 1,
                    ]],
                ],
            ]),
        ]);

        $this->withToken($this->token())
            ->getJson('/api/v1/sub2api/model-stats?start_date=2026-07-01&end_date=2026-07-09')
            ->assertStatus(502)
            ->assertExactJson([
                'code' => 'SUB2API_STATS_UNAVAILABLE',
                'message' => 'Sub2API 官方统计暂不可用',
            ]);
    }

    private function token(): string
    {
        $admin = Admin::query()->firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => '管理员',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);

        return $admin->createToken('model-stats')->plainTextToken;
    }

    private function official(string $field, array $rows): array
    {
        return [
            'code' => 0,
            'message' => 'success',
            'data' => [$field => $rows],
        ];
    }

    private function modelRow(string $model, int $tokens): array
    {
        return [
            'model' => $model,
            'requests' => 2,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_creation_tokens' => 30,
            'cache_read_tokens' => 40,
            'total_tokens' => $tokens,
            'cost' => '3.5',
            'actual_cost' => '3.1',
        ];
    }

    private function userRow(int $id, int $tokens): array
    {
        return [
            'user_id' => $id,
            'email' => 'user'.$id.'@example.com',
            'requests' => 2,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_tokens' => 70,
            'total_tokens' => $tokens,
            'cost' => '3.5',
            'actual_cost' => '3.1',
        ];
    }
}
