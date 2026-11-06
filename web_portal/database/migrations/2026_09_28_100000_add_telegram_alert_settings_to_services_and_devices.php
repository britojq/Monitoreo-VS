<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. monitored_services
        Schema::table('monitored_services', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_services', 'telegram_alert_enabled')) {
                $table->boolean('telegram_alert_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('monitored_services', 'telegram_alert_target')) {
                $table->string('telegram_alert_target', 20)->default('owner')->after('telegram_alert_enabled');
            }
            if (!Schema::hasColumn('monitored_services', 'last_alert_state')) {
                $table->string('last_alert_state', 10)->nullable()->after('telegram_alert_target');
            }
            if (!Schema::hasColumn('monitored_services', 'down_since')) {
                $table->timestamp('down_since')->nullable()->after('last_alert_state');
            }
        });

        // 2. monitored_network_devices
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_network_devices', 'telegram_alert_enabled')) {
                $table->boolean('telegram_alert_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'telegram_alert_target')) {
                $table->string('telegram_alert_target', 20)->default('owner')->after('telegram_alert_enabled');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'last_alert_state')) {
                $table->string('last_alert_state', 10)->nullable()->after('telegram_alert_target');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'down_since')) {
                $table->timestamp('down_since')->nullable()->after('last_alert_state');
            }
        });

        // 3. monitored_site_devices
        Schema::table('monitored_site_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_site_devices', 'telegram_alert_enabled')) {
                $table->boolean('telegram_alert_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'telegram_alert_target')) {
                $table->string('telegram_alert_target', 20)->default('owner')->after('telegram_alert_enabled');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'last_alert_state')) {
                $table->string('last_alert_state', 10)->nullable()->after('telegram_alert_target');
            }
            if (!Schema::hasColumn('monitored_site_devices', 'down_since')) {
                $table->timestamp('down_since')->nullable()->after('last_alert_state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monitored_services', function (Blueprint $table) {
            $table->dropColumn(['telegram_alert_enabled', 'telegram_alert_target', 'last_alert_state', 'down_since']);
        });

        Schema::table('monitored_network_devices', function (Blueprint $table) {
            $table->dropColumn(['telegram_alert_enabled', 'telegram_alert_target', 'last_alert_state', 'down_since']);
        });

        Schema::table('monitored_site_devices', function (Blueprint $table) {
            $table->dropColumn(['telegram_alert_enabled', 'telegram_alert_target', 'last_alert_state', 'down_since']);
        });
    }
};
