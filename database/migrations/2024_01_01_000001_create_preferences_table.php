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
        $tableName = config('settings-kit.tables.preferences', 'preferences');
        
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('role')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->enum('type', ['string', 'boolean', 'integer', 'json', 'select'])->default('string');
            $table->boolean('required')->default(false);
            $table->string('key')->unique();
            $table->text('default_value')->nullable();
            $table->json('options')->nullable(); // For select type
            $table->boolean('is_user_customizable')->default(false); // Whether users can customize this setting
            $table->timestamps();

            $table->index(['role', 'category']);
            $table->index(['category', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('settings-kit.tables.preferences', 'preferences');
        Schema::dropIfExists($tableName);
    }
};
