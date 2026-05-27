<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fix environments that already ran the earlier owner/bootstrap migration.
     */
    public function up(): void
    {
        User::query()
            ->where('is_superadmin', true)
            ->update(['role' => UserRole::Admin->value]);
    }

    public function down(): void
    {
        // no-op
    }
};
