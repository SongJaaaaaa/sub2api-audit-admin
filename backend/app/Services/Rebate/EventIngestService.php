<?php

namespace App\Services\Rebate;

use App\Jobs\Rebate\ProcessRebateEvent;
use App\Models\Rebate\RebateEvent;
use App\Models\Rebate\RebateScanCursor;
use App\Models\Rebate\RebateUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class EventIngestService
{
    public const SOURCE_NATIVE_RECHARGE = 'native_recharge';

    public const SOURCE_REDEEM = 'redeem';

    public const SOURCE_ADMIN_ADJUSTMENT = 'admin_adjustment';

    public function __construct(private readonly ConfigService $configs) {}

    public function ingest(string $sourceType, array $events, int|string|null $cursorValue): array
    {
        $enabled = $this->configs->sourceEnabled($sourceType);
        $config = $this->configs->get();
        if ($config->rebate_cutover_at === null) {
            throw new RuntimeException('返利切换时间尚未锁定');
        }

        return DB::transaction(function () use ($sourceType, $events, $cursorValue, $enabled, $config): array {
            RebateScanCursor::query()->createOrFirst(
                ['source_type' => $sourceType],
                ['cursor_value' => null],
            );
            $cursor = RebateScanCursor::query()
                ->where('source_type', $sourceType)
                ->lockForUpdate()
                ->firstOrFail();
            $created = [];
            $duplicates = 0;
            $skipped = 0;

            foreach ($events as $row) {
                if (! $enabled || ! $this->eligible($sourceType, $row, $config->rebate_cutover_at)) {
                    $skipped++;

                    continue;
                }

                $userId = (int) ($row['user_id'] ?? 0);
                if (! RebateUser::query()->whereKey($userId)->exists()) {
                    throw new RuntimeException("返利用户 {$userId} 尚未同步");
                }

                $event = RebateEvent::query()->firstOrCreate(
                    [
                        'source_type' => $sourceType,
                        'source_id' => (string) ($row['source_id'] ?? ''),
                    ],
                    [
                        'user_id' => $userId,
                        'amount' => RebateMoney::positive((string) ($row['amount'] ?? '0')),
                        'happened_at' => $row['happened_at'] ?? now(),
                        'payload' => $row['payload'] ?? null,
                        'status' => RebateEvent::STATUS_PENDING,
                    ],
                );

                if ($event->source_id === '') {
                    throw new InvalidArgumentException('返利事件来源 ID 不能为空');
                }

                if ($event->wasRecentlyCreated) {
                    $created[] = $event;
                } else {
                    if ((int) $event->user_id !== $userId
                        || RebateMoney::compare($event->amount, (string) ($row['amount'] ?? '0')) !== 0) {
                        throw new LogicException('返利事件幂等键与原事件不一致');
                    }

                    $duplicates++;
                }
            }

            $cursor->update([
                'cursor_value' => $this->laterCursor($cursor->cursor_value, $cursorValue),
                'cursor_at' => now(),
            ]);

            foreach ($created as $event) {
                ProcessRebateEvent::dispatch($event->id)->afterCommit();
            }

            return [
                'events' => $created,
                'created_count' => count($created),
                'duplicate_count' => $duplicates,
                'skipped_count' => $skipped,
                'enabled' => $enabled,
                'cursor' => $cursor->refresh(),
            ];
        });
    }

    private function eligible(string $sourceType, array $row, mixed $cutoverAt): bool
    {
        $happenedAt = CarbonImmutable::parse($row['happened_at'] ?? now(), 'UTC');
        if ($cutoverAt && $happenedAt->lessThan($cutoverAt)) {
            return false;
        }

        if ($sourceType !== self::SOURCE_ADMIN_ADJUSTMENT) {
            return true;
        }

        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
        $businessSource = $row['business_source'] ?? $payload['business_source'] ?? null;
        if ($businessSource === 'rebate_withdrawal') {
            return false;
        }

        if (($row['is_rebate_withdrawal'] ?? $payload['is_rebate_withdrawal'] ?? false)
            || ($row['is_gift_only'] ?? $payload['is_gift_only'] ?? false)) {
            return false;
        }

        $cashAmount = $row['cash_amount'] ?? $payload['cash_amount'] ?? null;

        return $cashAmount === null || RebateMoney::compare((string) $cashAmount, '0.00') > 0;
    }

    private function laterCursor(?string $current, int|string|null $candidate): ?string
    {
        if ($candidate === null || $candidate === '') {
            return $current;
        }

        $next = (string) $candidate;
        if ($current === null || $current === '') {
            return $next;
        }

        $left = json_decode($current, true);
        $right = json_decode($next, true);
        if (is_array($left) && is_array($right) && isset($left['at'], $left['id'], $right['at'], $right['id'])) {
            $time = strcmp((string) $right['at'], (string) $left['at']);

            return $time > 0 || ($time === 0 && (int) $right['id'] > (int) $left['id']) ? $next : $current;
        }

        if (ctype_digit($current) && ctype_digit($next)) {
            return bccomp($next, $current, 0) > 0 ? $next : $current;
        }

        return $next;
    }
}
