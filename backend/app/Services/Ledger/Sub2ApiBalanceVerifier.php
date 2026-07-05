<?php

namespace App\Services\Ledger;

use App\Services\Sub2Api\Sub2ApiAdminClient;

class Sub2ApiBalanceVerifier
{
    public function __construct(private readonly Sub2ApiAdminClient $client)
    {
    }

    public function currentBalance(int $userId): array
    {
        $res = $this->client->user($userId);
        $data = $this->data($res);

        return [
            'balance' => $this->money($data['balance'] ?? null),
            'email' => $data['email'] ?? null,
            'response' => $res,
        ];
    }

    public function verify(int $userId, string $expected): array
    {
        $current = $this->currentBalance($userId);

        return [
            'ok' => $current['balance'] === $expected,
            'balance' => $current['balance'],
            'response' => $current['response'],
        ];
    }

    private function data(array $res): array
    {
        return is_array($res['data'] ?? null) ? $res['data'] : $res;
    }

    private function money(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        return number_format((float) $val, 2, '.', '');
    }
}
