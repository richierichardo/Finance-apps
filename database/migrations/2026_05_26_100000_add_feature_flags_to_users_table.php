<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false)->after('is_superadmin');
            $table->boolean('telegram_enabled')->default(false)->after('ai_enabled');
            $table->timestamp('last_login_at')->nullable()->after('telegram_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_enabled', 'telegram_enabled', 'last_login_at']);
        });
    }
};
