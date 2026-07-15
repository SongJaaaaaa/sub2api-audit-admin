<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateScanCursor;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RebateScanService
{
    public const SOURCES = [
        EventIngestService::SOURCE_NATIVE_RECHARGE,
        EventIngestService::SOURCE_REDEEM,
        EventIngestService::SOURCE_ADMIN_ADJUSTMENT,
    ];

    public function __construct(
        private readonly Sub2ApiReadRepository $read,
        private readonly UserSyncService $users,
        private readonly ConfigService $configs,
        private readonly EventIngestService $events,
    ) {}

    public function scan(?string $source = null, int $limit = 200): array
    {
        $sources = $source === null ? self::SOURCES : [$source];
        $result = [];

        foreach ($sources as $name) {
            if (! in_array($name, self::SOURCES, true)) {
                throw new RuntimeException('未知返利扫描来源：'.$name);
            }

            $result[$name] = $this->withSourceLock($name, fn (): array => $this->scanSource($name, $limit));
        }

        return $result;
    }

    private function scanSource(string $source, int $limit): array
    {
        $cursor = RebateScanCursor::query()->where('source_type', $source)->value('cursor_value');
        $page = $this->read->rebateEvents($source, $cursor, $limit);
        if ($this->configs->sourceEnabled($source)) {
            $this->syncUsers($page['items']);
        }

        $ingested = $this->events->ingest($source, $page['items'], $page['next_cursor']);

        return [
            'read_count' => count($page['items']),
            'created_count' => $ingested['created_count'],
            'duplicate_count' => $ingested['duplicate_count'],
            'skipped_count' => $ingested['skipped_count'],
            'enabled' => $ingested['enabled'],
            'cursor' => $ingested['cursor']->cursor_value,
            'has_more' => $page['has_more'],
        ];
    }

    private function withSourceLock(string $source, callable $callback): array
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        if ($pgsql) {
            DB::select("SELECT pg_advisory_lock(hashtext('rebate-scan'), hashtext(?))", [$source]);
        }

        try {
            return $callback();
        } finally {
            if ($pgsql) {
                DB::select("SELECT pg_advisory_unlock(hashtext('rebate-scan'), hashtext(?))", [$source]);
            }
        }
    }

    private function syncUsers(array $events): void
    {
        $ids = collect($events)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->unique();
        foreach ($ids as $id) {
            $user = $this->read->affiliateUser($id);
            if ($user === null) {
                throw new RuntimeException("Sub2API 返利事件用户 {$id} 不存在");
            }

            $parent = isset($user['parent_user_id'])
                ? $this->read->affiliateUser((int) $user['parent_user_id'])
                : null;
            $this->users->sync($user, $parent);
        }
    }
}
