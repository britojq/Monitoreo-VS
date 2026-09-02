<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_services', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 5)->unique();
            $table->string('name');
            $table->string('type')->default('WEB');
            $table->string('host_ip')->nullable();
            $table->string('web_url')->nullable();
            $table->integer('port')->nullable();
            $table->string('credentials')->nullable();
            $table->string('check_interface')->nullable();
            $table->string('dns_test_domain')->nullable();
            $table->string('normal_state_msg')->nullable();
            $table->string('error_state_msg')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_services');
    }
};
