<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_proxies', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 5)->unique();
            $table->string('name');
            $table->string('ip_port');
            $table->string('auth_userpass')->nullable();
            $table->string('test_url')->default('https://core.telegram.org/bots');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_proxies');
    }
};
