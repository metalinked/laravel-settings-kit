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
        $tableName = config('settings-kit.tables.preference_contents', 'preference_contents');
        
        Schema::create($tableName, function (Blueprint $table) use ($preferencesTable) {
            $table->id();
            $table->foreignId('preference_id')->constrained($preferencesTable)->onDelete('cascade');
            $table->string('lang', 5)->index();
            $table->string('title');
            $table->text('text')->nullable();
            $table->timestamps();

            $table->unique(['preference_id', 'lang']);
            $table->index(['lang', 'preference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('settings-kit.tables.preference_contents', 'preference_contents');
        Schema::dropIfExists($tableName);
    }
};
