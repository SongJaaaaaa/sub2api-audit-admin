<?php

namespace App\Services\Migration;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

class SqliteSourceConnectionFactory
{
    public function __construct(private readonly DatabaseManager $db) {}

    public function open(string $path, string $name): Connection
    {
        $this->hashImmutableBackup($path);
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new MigrationException("SQLite 源文件不存在或不可读：{$path}");
        }

        config()->set("database.connections.{$name}", [
            'driver' => 'sqlite',
            'database' => $real,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'transaction_mode' => 'DEFERRED',
        ]);

        $this->db->purge($name);
        $conn = $this->db->connection($name);
        $conn->statement('PRAGMA query_only = ON');

        return $conn;
    }

    public function hashImmutableBackup(string $path): string
    {
        clearstatcache(true, $path);
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new MigrationException("SQLite 源文件不存在或不可读：{$path}");
        }

        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            clearstatcache(true, $real.$suffix);
            if (file_exists($real.$suffix)) {
                throw new MigrationException("SQLite 备份存在旁路文件 {$suffix}，请停机 checkpoint 后重新制作不可变备份。");
            }
        }

        $hash = hash_file('sha256', $real);
        if (! is_string($hash)) {
            throw new MigrationException('无法计算 SQLite 源文件 SHA-256。');
        }

        return strtolower($hash);
    }

    public function assertUnchanged(string $path, string $expectedHash): void
    {
        if (! hash_equals(strtolower($expectedHash), $this->hashImmutableBackup($path))) {
            throw new MigrationException('SQLite 源文件在迁移期间发生变化，请重新备份后再执行。');
        }
    }

    public function close(string $name): void
    {
        $this->db->purge($name);
        config()->offsetUnset("database.connections.{$name}");
    }
}
