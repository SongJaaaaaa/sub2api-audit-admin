<?php

namespace App\Services\Ledger;

use App\Models\LedgerAdjustment;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class LedgerSourceLinkService
{
    public function __construct(private readonly Sub2ApiReadRepository $repo) {}

    public function link(LedgerAdjustment $adj): ?int
    {
        if ($adj->status !== LedgerAdjustment::STATUS_SUCCEEDED || $adj->sub2api_source_id) {
            return $adj->sub2api_source_id ? (int) $adj->sub2api_source_id : null;
        }

        try {
            $rows = $this->repo->findAdminAdjustmentSources(
                (int) $adj->sub2api_user_id,
                (string) $adj->idempotency_key,
            );
        } catch (Throwable $e) {
            Log::warning('ledger.source_link.query_failed', [
                'local_adjustment_id' => $adj->id,
                'sub2api_user_id' => $adj->sub2api_user_id,
                'error_type' => $e::class,
            ]);

            return null;
        }

        if (count($rows) !== 1) {
            Log::warning('ledger.source_link.not_unique', [
                'local_adjustment_id' => $adj->id,
                'sub2api_user_id' => $adj->sub2api_user_id,
                'match_count' => count($rows),
            ]);

            return null;
        }

        try {
            $sourceId = (int) $rows[0]['id'];
            $adj->update(['sub2api_source_id' => $sourceId]);

            return $sourceId;
        } catch (Throwable $e) {
            Log::warning('ledger.source_link.save_failed', [
                'local_adjustment_id' => $adj->id,
                'sub2api_user_id' => $adj->sub2api_user_id,
                'remote_event_id' => (int) $rows[0]['id'],
                'error_type' => $e::class,
            ]);

            return null;
        }
    }
}
