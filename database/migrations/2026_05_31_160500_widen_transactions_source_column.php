<?php

use App\Enums\TransactionSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'source')) {
            return;
        }

        $length = TransactionSource::DB_COLUMN_LENGTH;
        $default = TransactionSource::WebManual->value;
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql' => DB::statement(
                "ALTER TABLE `transactions` MODIFY `source` VARCHAR({$length}) NOT NULL DEFAULT '{$default}'"
            ),
            'pgsql' => DB::statement(
                "ALTER TABLE transactions ALTER COLUMN source TYPE VARCHAR({$length}), ALTER COLUMN source SET DEFAULT '{$default}'"
            ),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'source')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql' => DB::statement(
                "ALTER TABLE `transactions` MODIFY `source` VARCHAR(20) NOT NULL DEFAULT 'web'"
            ),
            'pgsql' => DB::statement(
                "ALTER TABLE transactions ALTER COLUMN source TYPE VARCHAR(20), ALTER COLUMN source SET DEFAULT 'web'"
            ),
            default => null,
        };
    }
};
