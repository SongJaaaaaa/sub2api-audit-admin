<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ],
        );

        Admin::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理员',
                'username' => 'admin',
                'password' => '1',
                'status' => Admin::STATUS_ACTIVE,
            ],
        );
    }
}
