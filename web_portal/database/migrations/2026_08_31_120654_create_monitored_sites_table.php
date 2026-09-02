<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_sites', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 5)->unique();
            $table->string('name');
            $table->string('ip')->nullable();
            $table->string('phone_1')->nullable();
            $table->string('phone_2')->nullable();
            $table->string('phone_3')->nullable();
            $table->string('phone_4')->nullable();
            $table->string('phone_5')->nullable();
            $table->string('phone_6')->nullable();
            $table->string('phone_7')->nullable();
            $table->string('phone_8')->nullable();
            $table->text('address')->nullable();
            $table->string('normal_state_msg')->nullable();
            $table->string('error_state_msg')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_sites');
    }
};
