<?php

namespace App\Http\Controllers\Api\V1\RebateAdmin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Rebate\RebateBalance;
use App\Models\Rebate\RebateProgress;
use App\Models\Rebate\RebateRecord;
use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use App\Services\Rebate\ConfigService;
use App\Services\Rebate\RebateTrendService;
use App\Services\Rebate\UserSyncService;
use App\Services\Rebate\WithdrawalService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaTime;
use App\Support\RebatePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RebateAdminController extends Controller
{
    public function dashboard(RebateTrendService $trends): JsonResponse
    {
        $today = now(config('ledger.timezone', 'Asia/Shanghai'))->startOfDay();
        $month = $today->copy()->startOfMonth();
        $pending = RebateWithdrawal::query()->where('status', RebateWithdrawal::STATUS_PENDING);
        $records = RebateRecord::query();

        return response()->json([
            'total_users' => RebateUser::query()->count(),
            'direct_referral_count' => RebateReferral::query()->whereNotNull('parent_user_id')->count(),
            'total_rebate_amount' => $this->amount((clone $records)->sum('rebate_amount')),
            'available_rebate_amount' => $this->amount(RebateBalance::query()->sum('available_amount')),
            'frozen_rebate_amount' => $this->amount(RebateBalance::query()->sum('frozen_amount')),
            'withdrawn_amount' => $this->amount(RebateBalance::query()->sum('withdrawn_amount')),
            'today_rebate_amount' => $this->amount((clone $records)->where('created_at', '>=', $today)->sum('rebate_amount')),
            'month_rebate_amount' => $this->amount((clone $records)->where('created_at', '>=', $month)->sum('rebate_amount')),
            'pending_withdrawal_count' => (clone $pending)->count(),
            'pending_withdrawal_amount' => $this->amount((clone $pending)->sum('amount')),
            'rebate_trend' => $trends->lastSevenDays(),
            'recent_rebates' => RebateRecord::query()
                ->with(['payer:id,email', 'receiver:id,email'])
                ->orderByDesc('id')->limit(8)->get()
                ->map(fn (RebateRecord $record): array => RebatePresenter::record($record))->all(),
            'recent_withdrawals' => RebateWithdrawal::query()
                ->with('user:id,email')->orderByDesc('id')->limit(8)->get()
                ->map(fn (RebateWithdrawal $withdrawal): array => RebatePresenter::withdrawal($withdrawal))->all(),
        ]);
    }

    public function relationships(
        Request $request,
        Sub2ApiReadRepository $read,
        UserSyncService $sync,
    ): JsonResponse {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);
        [$page, $pageSize] = $this->page($request);
        $remote = $read->affiliateUser((int) $data['user_id']);
        if ($remote === null) {
            abort(404, 'Sub2API 用户不存在');
        }

        $parent = isset($remote['parent_user_id'])
            ? $read->affiliateUser((int) $remote['parent_user_id'])
            : null;
        $user = $sync->sync($remote, $parent);
        $children = $read->affiliateChildren($user->id, $page, $pageSize);
        foreach ($children['items'] as $child) {
            $sync->sync($child, $remote);
        }

        $ids = collect($children['items'])->pluck('id')->all();
        $users = RebateUser::query()->with('progress')->whereIn('id', $ids)->get()->keyBy('id');
        $rebates = $this->rebateByPayer($user->id, $ids);
        $directCounts = $ids === []
            ? collect()
            : RebateReferral::query()
                ->whereIn('parent_user_id', $ids)
                ->selectRaw('parent_user_id, COUNT(*) as total')
                ->groupBy('parent_user_id')
                ->pluck('total', 'parent_user_id');
        $items = collect($children['items'])->map(function (array $remoteChild) use ($users, $rebates, $directCounts, $user): array {
            /** @var RebateUser|null $child */
            $child = $users->get($remoteChild['id']);
            $progress = $child?->progress;

            return [
                'user_id' => (int) $remoteChild['id'],
                'email' => (string) $remoteChild['email'],
                'username' => $remoteChild['username'],
                'invite_code' => $remoteChild['aff_code'],
                'parent_user_id' => $user->id,
                'parent_email' => $user->email,
                'direct_count' => (int) ($directCounts->get($remoteChild['id']) ?? 0),
                'total_recharge_amount' => (string) ($progress?->total_recharge_amount ?? '0.00'),
                'total_rebate_amount' => (string) ($rebates->get($remoteChild['id']) ?? '0.00'),
                'created_at' => ChinaTime::fmt($child?->created_at),
            ];
        })->all();

        return response()->json([
            'user' => $this->relationshipUser($user, (int) $children['total']),
            'items' => $items,
            'total' => (int) $children['total'],
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->page($request);
        $data = $request->validate([
            'status' => ['nullable', Rule::in([
                RebateWithdrawal::STATUS_PENDING,
                RebateWithdrawal::STATUS_PROCESSING,
                RebateWithdrawal::STATUS_SUCCEEDED,
                RebateWithdrawal::STATUS_REJECTED,
                RebateWithdrawal::STATUS_EXCEPTION,
            ])],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);
        $query = RebateWithdrawal::query()->with('user:id,email');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        $keyword = trim((string) ($data['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('withdrawal_no', 'like', '%'.$keyword.'%')
                    ->orWhereHas('user', fn (Builder $user): Builder => $user->where('email', 'like', '%'.$keyword.'%'));
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get()
            ->map(fn (RebateWithdrawal $withdrawal): array => RebatePresenter::withdrawal($withdrawal))
            ->all();

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function approve(Request $request, RebateWithdrawal $withdrawal, WithdrawalService $service): JsonResponse
    {
        $withdrawal = $service->approve($withdrawal, $this->admin($request)->id);

        return response()->json([
            'withdrawal' => RebatePresenter::withdrawal($withdrawal->load('user:id,email')),
            'message' => '提现已进入处理队列',
        ], 202);
    }

    public function reject(Request $request, RebateWithdrawal $withdrawal, WithdrawalService $service): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $withdrawal = $service->reject($withdrawal, $this->admin($request)->id, trim($data['reason']));

        return response()->json([
            'withdrawal' => RebatePresenter::withdrawal($withdrawal->load('user:id,email')),
            'message' => '提现已拒绝并退回可用余额',
        ]);
    }

    public function retry(Request $request, RebateWithdrawal $withdrawal, WithdrawalService $service): JsonResponse
    {
        $withdrawal = $service->retry($withdrawal, $this->admin($request)->id);

        return response()->json([
            'withdrawal' => RebatePresenter::withdrawal($withdrawal->load('user:id,email')),
            'message' => '提现已重新进入处理队列',
        ], 202);
    }

    public function config(ConfigService $configs): JsonResponse
    {
        return response()->json(RebatePresenter::config($configs->get()));
    }

    public function updateConfig(Request $request, ConfigService $configs): JsonResponse
    {
        $data = $request->validate([
            'milestone_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'milestone_reward_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'milestone_max_times' => ['required', 'integer', 'min:1'],
            'stage_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'stage_reward_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'withdraw_min_amount' => ['required', 'decimal:0,2', 'gt:0'],
            'withdraw_daily_limit' => ['required', 'integer', 'min:1'],
            'withdraw_daily_amount_limit' => ['required', 'decimal:0,2', 'min:0'],
            'withdraw_to_api_quota_rate' => ['required', 'numeric', 'gt:0'],
            'native_recharge_enabled' => ['required', 'boolean'],
            'redeem_enabled' => ['required', 'boolean'],
            'admin_adjust_enabled' => ['required', 'boolean'],
        ]);
        $payload = RebatePresenter::config($configs->update($data));

        return response()->json([...$payload, 'message' => '返利配置已保存']);
    }

    private function relationshipUser(RebateUser $user, int $directCount): array
    {
        $childIds = RebateReferral::query()->where('parent_user_id', $user->id)->pluck('user_id');
        $referral = $user->referral()->with('parent:id,email')->first();

        return [
            'user_id' => $user->id,
            'email' => (string) $user->email,
            'username' => $user->username,
            'invite_code' => $user->aff_code,
            'parent_user_id' => $referral?->parent_user_id,
            'parent_email' => $referral?->parent?->email,
            'direct_count' => $directCount,
            'total_recharge_amount' => $this->amount(RebateProgress::query()->whereIn('user_id', $childIds)->sum('total_recharge_amount')),
            'total_rebate_amount' => $this->amount(RebateRecord::query()->where('receiver_user_id', $user->id)->sum('rebate_amount')),
            'created_at' => ChinaTime::fmt($user->created_at),
        ];
    }

    private function rebateByPayer(int $receiverId, array $payerIds): mixed
    {
        if ($payerIds === []) {
            return collect();
        }

        return RebateRecord::query()
            ->where('receiver_user_id', $receiverId)
            ->whereIn('payer_user_id', $payerIds)
            ->get(['payer_user_id', 'rebate_amount'])
            ->groupBy('payer_user_id')
            ->map(fn ($records): string => $records->reduce(
                fn (string $sum, RebateRecord $record): string => bcadd($sum, (string) $record->rebate_amount, 2),
                '0.00',
            ));
    }

    private function page(Request $request): array
    {
        return [
            max((int) $request->query('page', 1), 1),
            min(max((int) $request->query('page_size', 20), 1), 100),
        ];
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user();

        return $admin;
    }

    private function amount(mixed $value): string
    {
        return bcadd((string) ($value ?: '0'), '0', 2);
    }
}
