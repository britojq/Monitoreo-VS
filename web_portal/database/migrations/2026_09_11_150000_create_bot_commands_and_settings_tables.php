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
        Schema::create('bot_commands', function (Blueprint $table) {
            $table->id();
            $table->string('command', 50)->unique();
            $table->string('title', 100)->nullable();
            $table->string('description', 500);
            $table->text('help_text')->nullable();
            $table->string('category', 50)->default('General');
            $table->enum('access_level', ['all', 'admin', 'owner'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });

        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->longText('setting_value')->nullable();
            $table->string('setting_group', 50)->default('general');
            $table->string('title', 150)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('type', 20)->default('text'); // text, textarea, boolean, integer, json
            $table->timestamps();

            $table->index('setting_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
        Schema::dropIfExists('bot_commands');
    }
};
