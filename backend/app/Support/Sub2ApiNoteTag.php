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

    public static function parse(mixed $notes): array
    {
        $text = (string) $notes;
        preg_match('/\[sub2api-audit\s+ledger_no=([^\s\]]+)\s+idempotency_key=([^\s\]]+)\]/', $text, $match);

        return [
            'is_audit' => isset($match[0]),
            'ledger_no' => $match[1] ?? null,
            'idempotency_key' => $match[2] ?? null,
        ];
    }

    public static function idempotencyKey(mixed $notes): ?string
    {
        return self::parse($notes)['idempotency_key'];
    }
}
