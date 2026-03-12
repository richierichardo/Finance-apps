<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
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
                'role' => \App\Enums\UserRole::Admin->value,
                'status' => \App\Enums\UserStatus::Active->value,
                'is_superadmin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
