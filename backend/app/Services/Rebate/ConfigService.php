<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateConfig;
use InvalidArgumentException;
use RuntimeException;

class ConfigService
{
    public const DEFAULTS = [
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
        'rebate_cutover_at' => null,
    ];

    private const AMOUNT_FIELDS = [
        'milestone_amount',
        'milestone_reward_amount',
        'stage_amount',
        'stage_reward_amount',
        'withdraw_min_amount',
        'withdraw_daily_amount_limit',
    ];

    private const BOOL_FIELDS = [
        'native_recharge_enabled',
        'redeem_enabled',
        'admin_adjust_enabled',
    ];

    public function get(): RebateConfig
    {
        return RebateConfig::query()->firstOrCreate(['id' => 1], self::DEFAULTS);
    }

    public function update(array $data): RebateConfig
    {
        $config = $this->get();
        $values = array_intersect_key($data, self::DEFAULTS);

        foreach (self::AMOUNT_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = RebateMoney::normalize((string) $values[$field]);
            }
        }

        if (array_key_exists('withdraw_to_api_quota_rate', $values)) {
            $values['withdraw_to_api_quota_rate'] = RebateMoney::normalize(
                (string) $values['withdraw_to_api_quota_rate'],
                4,
            );
        }

        foreach (self::BOOL_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = (bool) $values[$field];
            }
        }

        foreach (['milestone_max_times', 'withdraw_daily_limit'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = (int) $values[$field];
                if ($values[$field] < 0) {
                    throw new InvalidArgumentException("{$field} 不能小于 0");
                }
            }
        }

        if ($config->rebate_cutover_at && array_key_exists('rebate_cutover_at', $values)) {
            $next = $values['rebate_cutover_at'];
            if ($next === null || $config->rebate_cutover_at->toDateTimeString() !== date('Y-m-d H:i:s', strtotime((string) $next))) {
                throw new RuntimeException('返利切换时间已锁定，不能修改');
            }
        }

        $config->fill($values)->save();

        return $config->refresh();
    }

    public function sourceEnabled(string $sourceType): bool
    {
        $field = match ($sourceType) {
            EventIngestService::SOURCE_NATIVE_RECHARGE => 'native_recharge_enabled',
            EventIngestService::SOURCE_REDEEM => 'redeem_enabled',
            EventIngestService::SOURCE_ADMIN_ADJUSTMENT => 'admin_adjust_enabled',
            default => throw new InvalidArgumentException('未知返利事件来源'),
        };

        return (bool) $this->get()->{$field};
    }

    public function snapshot(): array
    {
        return $this->get()->only(array_keys(self::DEFAULTS));
    }
}
