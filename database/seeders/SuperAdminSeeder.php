<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Bootstrap the primary admin account (panel access via role=admin + is_superadmin).
     * AI/Telegram and other user roles are managed from /admin/users, not here.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'username' => 'superadmin',
                'name' => 'Super Admin',
                'phone' => '1234567890',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'is_superadmin' => true,
                'ai_enabled' => false,
                'telegram_enabled' => false,
                'email_verified_at' => now(),
            ]
        );
    }
}
