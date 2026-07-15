<?php

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Rebate\RebateProgress;
use App\Models\Rebate\RebateRecord;
use App\Models\Rebate\RebateReferral;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use App\Services\Rebate\ConfigService;
use App\Services\Rebate\RebateTrendService;
use App\Services\Rebate\WithdrawalService;
use App\Support\ChinaTime;
use App\Support\RebatePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffiliateController extends Controller
{
    public function dashboard(Request $request, RebateTrendService $trends): JsonResponse
    {
        $user = $this->user($request);
        $stats = $this->teamStats($user->id);
        $records = RebateRecord::query()
            ->with(['payer:id,email', 'receiver:id,email'])
            ->where('receiver_user_id', $user->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (RebateRecord $record): array => RebatePresenter::record($record))
            ->all();
        $pending = RebateWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                RebateWithdrawal::STATUS_PENDING,
                RebateWithdrawal::STATUS_PROCESSING,
                RebateWithdrawal::STATUS_EXCEPTION,
            ])
            ->sum('amount');

        return response()->json([
            'user' => RebatePresenter::user($user),
            'balance' => RebatePresenter::balance($user->balance()->first()),
            ...$stats,
            'pending_withdrawal_amount' => $this->amount($pending),
            'rebate_trend' => $trends->lastSevenDays($user->id),
            'recent_rebates' => $records,
        ]);
    }

    public function team(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->page($request);

        return response()->json($this->teamPage($this->user($request)->id, $page, $pageSize));
    }

    public function promotion(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $stats = $this->teamStats($user->id);
        $direct = $stats['direct_count'];
        $rate = $direct > 0
            ? bcmul(bcdiv((string) $stats['converted_count'], (string) $direct, 4), '100', 2)
            : '0.00';
        $template = (string) config('rebate.invite_url_template', '');

        return response()->json([
            'invite_code' => (string) ($user->aff_code ?? ''),
            'invite_url' => str_replace('{code}', rawurlencode((string) ($user->aff_code ?? '')), $template),
            'balance' => RebatePresenter::balance($user->balance()->first()),
            ...$stats,
            'conversion_rate' => $rate,
            'items' => $this->teamPage($user->id, 1, 10)['items'],
        ]);
    }

    public function rebateRecords(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->page($request);
        $data = $request->validate([
            'type' => ['nullable', Rule::in([RebateRecord::TYPE_MILESTONE, RebateRecord::TYPE_STAGE])],
        ]);
        $query = RebateRecord::query()
            ->with(['payer:id,email', 'receiver:id,email'])
            ->where('receiver_user_id', $this->user($request)->id);
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get()
            ->map(fn (RebateRecord $record): array => RebatePresenter::record($record))
            ->all();

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function withdrawals(Request $request, ConfigService $configs, WithdrawalService $withdrawals): JsonResponse
    {
        [$page, $pageSize] = $this->page($request);
        $user = $this->user($request);
        $query = RebateWithdrawal::query()->with('user:id,email')->where('user_id', $user->id);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get()
            ->map(fn (RebateWithdrawal $withdrawal): array => RebatePresenter::withdrawal($withdrawal))
            ->all();
        $config = $configs->get();
        $today = $withdrawals->todayUsage($user->id);

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'balance' => RebatePresenter::balance($user->balance()->first()),
            'config' => [
                'min_amount' => (string) $config->withdraw_min_amount,
                'daily_limit' => $config->withdraw_daily_limit,
                'daily_amount_limit' => (string) $config->withdraw_daily_amount_limit,
                'to_api_quota_rate' => (string) $config->withdraw_to_api_quota_rate,
            ],
            'today_count' => $today['count'],
            'today_amount' => $today['amount'],
        ]);
    }

    public function storeWithdrawal(Request $request, WithdrawalService $withdrawals): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
        ]);
        $withdrawal = $withdrawals->request($this->user($request), $data['amount']);

        return response()->json([
            'withdrawal' => RebatePresenter::withdrawal($withdrawal->load('user:id,email')),
            'message' => '提现申请已提交',
        ], 201);
    }

    private function teamPage(int $userId, int $page, int $pageSize): array
    {
        $query = RebateReferral::query()
            ->with(['user:id,email,username,created_at', 'user.progress'])
            ->where('parent_user_id', $userId);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();
        $ids = $rows->pluck('user_id')->all();
        $rebates = $ids === []
            ? collect()
            : RebateRecord::query()
                ->where('receiver_user_id', $userId)
                ->whereIn('payer_user_id', $ids)
                ->get(['payer_user_id', 'rebate_amount'])
                ->groupBy('payer_user_id')
                ->map(fn ($items): string => $items->reduce(
                    fn (string $sum, RebateRecord $record): string => bcadd($sum, (string) $record->rebate_amount, 2),
                    '0.00',
                ));
        $items = $rows->map(function (RebateReferral $referral) use ($rebates): array {
            $member = $referral->user;
            $progress = $member?->progress;

            return [
                'user_id' => $referral->user_id,
                'email' => (string) ($member?->email ?? ''),
                'username' => $member?->username,
                'total_recharge_amount' => (string) ($progress?->total_recharge_amount ?? '0.00'),
                'total_rebate_amount' => (string) ($rebates->get($referral->user_id) ?? '0.00'),
                'milestone_times' => (int) ($progress?->milestone_times ?? 0),
                'joined_at' => ChinaTime::fmt($member?->created_at),
            ];
        })->all();

        return compact('items', 'total', 'page') + ['page_size' => $pageSize];
    }

    private function teamStats(int $userId): array
    {
        $base = RebateProgress::query()
            ->join('rebate_referrals', 'rebate_referrals.user_id', '=', 'rebate_progress.user_id')
            ->where('rebate_referrals.parent_user_id', $userId);

        return [
            'direct_count' => RebateReferral::query()->where('parent_user_id', $userId)->count(),
            'converted_count' => (clone $base)->where('rebate_progress.total_recharge_amount', '>', 0)->count(),
            'total_direct_recharge_amount' => $this->amount((clone $base)->sum('rebate_progress.total_recharge_amount')),
        ];
    }

    private function page(Request $request): array
    {
        return [
            max((int) $request->query('page', 1), 1),
            min(max((int) $request->query('page_size', 20), 1), 100),
        ];
    }

    private function user(Request $request): RebateUser
    {
        /** @var RebateUser $user */
        $user = $request->user();

        return $user;
    }

    private function amount(mixed $value): string
    {
        return bcadd((string) ($value ?: '0'), '0', 2);
    }
}
