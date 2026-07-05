<?php

namespace App\Support;

class Sub2ApiNoteTag
{
    public static function make(string $ledgerNo, string $idempotencyKey): string
    {
        return "[sub2api-audit ledger_no={$ledgerNo} idempotency_key={$idempotencyKey}]";
    }

    public static function append(string $notes, string $ledgerNo, string $idempotencyKey): string
    {
        $tag = self::make($ledgerNo, $idempotencyKey);
        $notes = trim($notes);

        return $notes === '' ? $tag : $tag."\n".$notes;
    }
}
