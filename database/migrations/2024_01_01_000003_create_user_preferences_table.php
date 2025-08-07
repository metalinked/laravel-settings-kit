<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $preferencesTable = config('settings-kit.tables.preferences', 'preferences');
        $tableName = config('settings-kit.tables.user_preferences', 'user_preferences');
        
        Schema::create($tableName, function (Blueprint $table) use ($preferencesTable) {
            $table->id();
            $table->foreignId('preference_id')->constrained($preferencesTable)->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable()->index(); // Allow null for global overrides
            $table->text('value');
            $table->timestamps();

            $table->unique(['preference_id', 'user_id']);
            $table->index(['user_id', 'preference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('settings-kit.tables.user_preferences', 'user_preferences');
        Schema::dropIfExists($tableName);
    }
};
