<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $table = config('settings-kit.tables.user_preferences', 'user_preferences');
        $driver = Schema::getConnection()->getDriverName();

        // SQLite does not support ALTER COLUMN; new installs already get NOT NULL
        // from migration 000003 (column was changed there). No-op here.
        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::getConnection()->statement("ALTER TABLE `{$table}` MODIFY `user_id` BIGINT UNSIGNED NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            Schema::getConnection()->statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"user_id\" SET NOT NULL");
            return;
        }
    }

    public function down(): void {
        $table = config('settings-kit.tables.user_preferences', 'user_preferences');
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::getConnection()->statement("ALTER TABLE `{$table}` MODIFY `user_id` BIGINT UNSIGNED NULL");
            return;
        }

        if ($driver === 'pgsql') {
            Schema::getConnection()->statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"user_id\" DROP NOT NULL");
            return;
        }
    }
};
