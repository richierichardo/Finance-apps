<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')->where('source', 'web')->update(['source' => 'web_manual']);

        DB::table('transactions')
            ->where('source', 'system')
            ->where('description', 'Initial balance')
            ->update(['source' => 'system_initial_balance']);

        DB::table('transactions')
            ->where('source', 'system')
            ->whereNotNull('recurring_transaction_id')
            ->update(['source' => 'system_recurring']);

        DB::table('transactions')
            ->where('source', 'system')
            ->update(['source' => 'system_recurring']);

        DB::table('transactions')->where('source', 'telegram')->update(['source' => 'telegram_manual']);
    }

    public function down(): void
    {
        DB::table('transactions')->where('source', 'web_manual')->update(['source' => 'web']);
        DB::table('transactions')->where('source', 'web_ai')->update(['source' => 'web']);
        DB::table('transactions')->where('source', 'telegram_manual')->update(['source' => 'telegram']);
        DB::table('transactions')->where('source', 'telegram_ai')->update(['source' => 'telegram']);
        DB::table('transactions')->where('source', 'system_initial_balance')->update(['source' => 'system']);
        DB::table('transactions')->where('source', 'system_recurring')->update(['source' => 'system']);
        DB::table('transactions')->where('source', 'sync_recalculation')->update(['source' => 'system']);
    }
};
