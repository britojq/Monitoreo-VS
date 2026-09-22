<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('last_login_ip');
            }
            if (!Schema::hasColumn('users', 'terms_accepted_ip')) {
                $table->string('terms_accepted_ip', 45)->nullable()->after('terms_accepted_at');
            }
            if (!Schema::hasColumn('users', 'terms_version')) {
                $table->string('terms_version', 20)->nullable()->default('1.0')->after('terms_accepted_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_accepted_ip', 'terms_version']);
        });
    }
};
