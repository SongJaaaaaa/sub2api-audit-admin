<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', function () {
        $tz = config('ledger.timezone');

        return response()->json([
            'status' => 'ok',
            'timezone' => $tz,
            'time' => now($tz)->format('Y-m-d H:i:s'),
        ]);
    });
});
