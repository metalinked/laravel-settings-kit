<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void {
        Schema::create(config('settings-kit.table', 'settings_kit_values'), function (Blueprint $table) {
            $table->id();
            $table->string('key');
            // Global values use empty strings (not NULL) so the unique index applies to them too.
            // settable_id is a string so integer, UUID and ULID keys all work.
            $table->string('settable_type')->default('');
            $table->string('settable_id', 64)->default('');
            $table->text('value');
            $table->timestamps();

            $table->unique(['settable_type', 'settable_id', 'key']);
            $table->index('key');
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('settings-kit.table', 'settings_kit_values'));
    }
};
