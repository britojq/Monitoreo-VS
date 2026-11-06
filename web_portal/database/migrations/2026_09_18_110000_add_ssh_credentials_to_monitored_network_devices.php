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
        // 1. Agregar credenciales SSH a monitored_network_devices
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('monitored_network_devices', 'ssh_username')) {
                $table->string('ssh_username', 100)->nullable()->after('access_port');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'ssh_password_encrypted')) {
                $table->text('ssh_password_encrypted')->nullable()->after('ssh_username');
            }
            if (!Schema::hasColumn('monitored_network_devices', 'ssh_enable_secret_encrypted')) {
                $table->text('ssh_enable_secret_encrypted')->nullable()->after('ssh_password_encrypted');
            }
        });

        // 2. Agregar ssh_enable_secret_encrypted a snmp_devices
        Schema::table('snmp_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('snmp_devices', 'ssh_enable_secret_encrypted')) {
                $table->text('ssh_enable_secret_encrypted')->nullable()->after('ssh_password_encrypted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitored_network_devices', function (Blueprint $table) {
            if (Schema::hasColumn('monitored_network_devices', 'ssh_enable_secret_encrypted')) {
                $table->dropColumn('ssh_enable_secret_encrypted');
            }
            if (Schema::hasColumn('monitored_network_devices', 'ssh_password_encrypted')) {
                $table->dropColumn('ssh_password_encrypted');
            }
            if (Schema::hasColumn('monitored_network_devices', 'ssh_username')) {
                $table->dropColumn('ssh_username');
            }
        });

        Schema::table('snmp_devices', function (Blueprint $table) {
            if (Schema::hasColumn('snmp_devices', 'ssh_enable_secret_encrypted')) {
                $table->dropColumn('ssh_enable_secret_encrypted');
            }
        });
    }
};
