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
        // 1. Tabla: alert_rules (Reglas y umbrales de alerta)
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('entity_type', [
                'service',
                'site',
                'site_device',
                'network_device',
                'proxy',
                'ssl_certificate',
                'snmp_device',
                'discovered_device',
                'interface'
            ]);
            $table->unsignedBigInteger('entity_id')->nullable()->comment('NULL = aplica a todos los de este tipo');
            $table->enum('condition_type', [
                'is_down',
                'latency_high',
                'packet_loss_high',
                'jitter_high',
                'cpu_high',
                'memory_high',
                'interface_down',
                'interface_utilization_high',
                'cert_expiring',
                'cert_expired',
                'new_device',
                'device_disappeared',
                'config_changed',
                'temperature_high',
                'battery_low',
                'snmp_unreachable'
            ]);
            $table->decimal('threshold_value', 15, 4)->nullable();
            $table->enum('comparison', ['gt', 'lt', 'eq', 'gte', 'lte'])->nullable();
            $table->unsignedInteger('duration_seconds')->default(0)->comment('Tiempo sostenido para disparar');
            $table->enum('severity', ['info', 'warning', 'critical', 'emergency']);
            $table->unsignedInteger('cooldown_minutes')->default(30);
            $table->unsignedInteger('max_alerts_per_hour')->default(5);
            $table->boolean('auto_resolve')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'idx_alert_rule_entity');
            $table->index('is_active', 'idx_alert_rule_active');
            $table->index('condition_type', 'idx_alert_rule_condition');
        });

        // 2. Tabla: alert_escalation_levels (Niveles y canales de escalación)
        Schema::create('alert_escalation_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alert_rule_id');
            $table->unsignedInteger('level');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->enum('channel', ['telegram', 'email', 'sms', 'phone_call', 'webhook']);
            $table->enum('target_type', ['user', 'group', 'external']);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_group', 255)->nullable();
            $table->string('target_external', 255)->nullable();
            $table->unsignedBigInteger('message_template_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('alert_rule_id')->references('id')->on('alert_rules')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('message_template_id')->references('id')->on('bot_message_templates')->onDelete('set null');
            $table->unique(['alert_rule_id', 'level'], 'uniq_rule_level');
        });

        // 3. Tabla: alert_correlation_groups (Grupos de correlación padre-hijo)
        Schema::create('alert_correlation_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('parent_entity_type', ['site', 'network_device', 'proxy', 'snmp_device']);
            $table->unsignedBigInteger('parent_entity_id');
            $table->enum('suppression_strategy', ['suppress_all', 'suppress_if_parent_down', 'reduce_severity']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_entity_type', 'parent_entity_id'], 'idx_corr_parent');
            $table->index('is_active', 'idx_corr_active');
        });

        // 4. Tabla: alert_correlation_members (Miembros hijos del grupo de correlación)
        Schema::create('alert_correlation_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('correlation_group_id');
            $table->enum('child_entity_type', ['service', 'site_device', 'network_device', 'interface', 'snmp_device']);
            $table->unsignedBigInteger('child_entity_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('correlation_group_id')->references('id')->on('alert_correlation_groups')->onDelete('cascade');
            $table->unique(['correlation_group_id', 'child_entity_type', 'child_entity_id'], 'uniq_group_child');
            $table->index(['child_entity_type', 'child_entity_id'], 'idx_corr_child');
        });

        // 5. Tabla: alerts (Registro activo e histórico de incidentes y alarmas)
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alert_rule_id')->nullable();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_name', 255)->nullable();
            $table->string('condition_type', 50);
            $table->enum('severity', ['info', 'warning', 'critical', 'emergency']);
            $table->enum('status', ['firing', 'acknowledged', 'resolved', 'suppressed', 'auto_resolved'])->default('firing');
            $table->unsignedInteger('current_escalation_level')->default(1);
            $table->decimal('value_at_trigger', 15, 4)->nullable();
            $table->decimal('threshold_value', 15, 4)->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('correlation_group_id')->nullable();
            $table->boolean('is_correlated_suppressed')->default(false);
            $table->unsignedBigInteger('parent_alert_id')->nullable();
            $table->timestamp('fired_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->enum('resolved_by', ['auto', 'manual'])->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedInteger('notification_count')->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('alert_rule_id')->references('id')->on('alert_rules')->onDelete('set null');
            $table->foreign('correlation_group_id')->references('id')->on('alert_correlation_groups')->onDelete('set null');
            $table->foreign('parent_alert_id')->references('id')->on('alerts')->onDelete('set null');
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_alert_status');
            $table->index(['entity_type', 'entity_id'], 'idx_alert_entity');
            $table->index('severity', 'idx_alert_severity');
            $table->index('fired_at', 'idx_alert_fired');
        });

        // 6. Tabla: alert_notifications (Despachos de avisos por canal)
        Schema::create('alert_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alert_id');
            $table->unsignedBigInteger('escalation_level_id')->nullable();
            $table->string('channel', 50);
            $table->string('target', 255)->nullable();
            $table->text('message_sent')->nullable();
            $table->enum('status', ['sent', 'failed', 'pending', 'suppressed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->string('external_message_id', 255)->nullable();
            $table->timestamp('sent_at')->useCurrent();

            $table->foreign('alert_id')->references('id')->on('alerts')->onDelete('cascade');
            $table->foreign('escalation_level_id')->references('id')->on('alert_escalation_levels')->onDelete('set null');
            $table->index('alert_id', 'idx_notif_alert');
            $table->index('status', 'idx_notif_status');
        });

        // 7. Tabla: maintenance_windows (Ventanas de mantenimiento planificadas)
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('entity_type', ['service', 'site', 'site_device', 'network_device', 'proxy', 'snmp_device', 'all']);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('suppress_severities')->nullable()->comment('Array: ["warning","critical"]');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['entity_type', 'entity_id'], 'idx_maint_entity');
            $table->index(['starts_at', 'ends_at'], 'idx_maint_dates');
        });

        // 8. Tabla: alert_storm_suppression (Supresión de tormentas y flapping)
        Schema::create('alert_storm_suppression', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique()->comment('hash de entity_type+entity_id+condition_type');
            $table->unsignedInteger('alert_count')->default(1);
            $table->timestamp('first_alert_at')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->unsignedInteger('suppressed_count')->default(0);
            $table->timestamp('next_allowed_at')->nullable();

            $table->index('last_alert_at', 'idx_storm_last');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_storm_suppression');
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('alert_notifications');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_correlation_members');
        Schema::dropIfExists('alert_correlation_groups');
        Schema::dropIfExists('alert_escalation_levels');
        Schema::dropIfExists('alert_rules');
    }
};
