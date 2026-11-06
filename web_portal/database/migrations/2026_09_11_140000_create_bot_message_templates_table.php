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
        Schema::create('bot_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 50)->unique();
            $table->string('title', 100);
            $table->text('header_text');
            $table->string('sub_header', 150)->nullable();
            $table->text('legend_text')->nullable();
            $table->text('impact_statement')->nullable();
            $table->text('default_signature')->nullable();
            $table->string('slogan', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_message_templates');
    }
};
