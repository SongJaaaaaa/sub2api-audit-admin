<?php

namespace App\Services\Stats;

use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Support\ChinaDateRange;
use Illuminate\Support\Collection;

class ModelStatsService
{
    public function __construct(private readonly Sub2ApiAdminClient $client) {}

    public function data(ChinaDateRange $range, ?string $model, int $limit): array
    {
        $model = trim((string) $model);
        $models = [];
        $users = [];

        if ($model === '') {
            $rows = collect($this->client->dashboardModels($range)['models'])
                ->sortByDesc(fn (array $row): int => (int) $row['total_tokens'])
                ->values();
            $models = $rows->take($limit)->map(fn (array $row): array => $this->modelRow($row))->all();
            $summary = $this->modelSummary($rows);
        } else {
            $rows = collect($this->client->dashboardUserBreakdown($range, $limit, $model)['users'])
                ->sortByDesc(fn (array $row): int => (int) $row['total_tokens'])
                ->take($limit)
                ->values();
            $users = $rows->map(fn (array $row): array => $this->userRow($row))->all();
            $summary = $this->userSummary($rows);
        }

        return [
            'range' => [
                'start_date' => $range->startDate,
                'end_date' => $range->endDate,
                'timezone' => $range->timezone,
            ],
            'model_source' => 'requested',
            'selected_model' => $model !== '' ? $model : null,
            'summary' => $summary,
            'models' => $models,
            'users' => $users,
        ];
    }

    private function modelSummary(Collection $rows): array
    {
        $totalTokens = (int) $rows->sum('total_tokens');
        $cacheTokens = (int) $rows->sum(fn (array $row): int => (int) $row['cache_creation_tokens'] + (int) $row['cache_read_tokens']);

        return $this->summary(
            $rows->count(),
            (int) $rows->sum('requests'),
            $totalTokens,
            $cacheTokens,
            $rows->sum('cost'),
            $rows->sum('actual_cost'),
            (int) $rows->take(3)->sum('total_tokens'),
        );
    }

    private function userSummary(Collection $rows): array
    {
        $totalTokens = (int) $rows->sum('total_tokens');
        $cacheTokens = (int) $rows->sum('cache_tokens');

        return $this->summary(
            $rows->isEmpty() ? 0 : 1,
            (int) $rows->sum('requests'),
            $totalTokens,
            $cacheTokens,
            $rows->sum('cost'),
            $rows->sum('actual_cost'),
            $totalTokens,
        );
    }

    private function summary(int $models, int $requests, int $tokens, int $cache, mixed $standard, mixed $actual, int $top3): array
    {
        return [
            'model_count' => $models,
            'request_count' => $requests,
            'total_tokens' => $tokens,
            'cache_tokens' => $cache,
            'cache_rate' => $tokens > 0 ? round($cache / $tokens * 100, 2) : 0,
            'standard_cost' => $this->decimal($standard),
            'actual_cost' => $this->decimal($actual),
            'top3_token_rate' => $tokens > 0 ? round($top3 / $tokens * 100, 2) : 0,
        ];
    }

    private function modelRow(array $row): array
    {
        return [
            'model' => (string) $row['model'],
            'request_count' => (int) $row['requests'],
            'input_tokens' => (int) $row['input_tokens'],
            'output_tokens' => (int) $row['output_tokens'],
            'cache_creation_tokens' => (int) $row['cache_creation_tokens'],
            'cache_read_tokens' => (int) $row['cache_read_tokens'],
            'total_tokens' => (int) $row['total_tokens'],
            'standard_cost' => $this->decimal($row['cost']),
            'actual_cost' => $this->decimal($row['actual_cost']),
        ];
    }

    private function userRow(array $row): array
    {
        return [
            'user_id' => (int) $row['user_id'],
            'email' => $row['email'] ?? null,
            'request_count' => (int) $row['requests'],
            'input_tokens' => (int) $row['input_tokens'],
            'output_tokens' => (int) $row['output_tokens'],
            'cache_tokens' => (int) $row['cache_tokens'],
            'total_tokens' => (int) $row['total_tokens'],
            'standard_cost' => $this->decimal($row['cost']),
            'actual_cost' => $this->decimal($row['actual_cost']),
        ];
    }

    private function decimal(mixed $value): string
    {
        $text = number_format((float) $value, 10, '.', '');

        return rtrim(rtrim($text, '0'), '.') ?: '0';
    }
}
