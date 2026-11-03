<?php

namespace Database\Seeders;

use App\Models\AlertCorrelationGroup;
use App\Models\AlertCorrelationMember;
use App\Models\AlertEscalationLevel;
use App\Models\AlertRule;
use App\Models\MonitoredSite;
use App\Models\SnmpDevice;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlertRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('role', 'admin')->first();
        $adminId = $adminUser ? $adminUser->id : 1;

        $rules = [
            [
                'name' => 'Servicio Caído (HTTP/TCP)',
                'description' => 'Dispara cuando un servicio crítico responde con estado caído (DOWN).',
                'entity_type' => 'service',
                'entity_id' => null,
                'condition_type' => 'is_down',
                'threshold_value' => null,
                'comparison' => null,
                'duration_seconds' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 30,
                'max_alerts_per_hour' => 5,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Latencia Alta de Servicio (>500ms)',
                'description' => 'Dispara cuando la latencia de un servicio supera los 500ms durante 60s continuos.',
                'entity_type' => 'service',
                'entity_id' => null,
                'condition_type' => 'latency_high',
                'threshold_value' => 500,
                'comparison' => 'gt',
                'duration_seconds' => 60,
                'severity' => 'warning',
                'cooldown_minutes' => 15,
                'max_alerts_per_hour' => 4,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Pérdida de Paquetes WAN (>10%)',
                'description' => 'Dispara cuando el packet loss hacia una sede WAN excede el 10%.',
                'entity_type' => 'site',
                'entity_id' => null,
                'condition_type' => 'packet_loss_high',
                'threshold_value' => 10,
                'comparison' => 'gt',
                'duration_seconds' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 15,
                'max_alerts_per_hour' => 4,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Latencia Alta en Sede WAN (>250ms)',
                'description' => 'Dispara cuando la latencia promedio hacia una sede supera los 250ms durante 60s.',
                'entity_type' => 'site',
                'entity_id' => null,
                'condition_type' => 'latency_high',
                'threshold_value' => 250,
                'comparison' => 'gt',
                'duration_seconds' => 60,
                'severity' => 'warning',
                'cooldown_minutes' => 15,
                'max_alerts_per_hour' => 4,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Uso de CPU Elevado (>80%)',
                'description' => 'Dispara cuando la carga de CPU de un dispositivo SNMP supera el 80% sostenido 5 minutos.',
                'entity_type' => 'snmp_device',
                'entity_id' => null,
                'condition_type' => 'cpu_high',
                'threshold_value' => 80,
                'comparison' => 'gt',
                'duration_seconds' => 300,
                'severity' => 'warning',
                'cooldown_minutes' => 30,
                'max_alerts_per_hour' => 3,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Uso de Memoria RAM Alto (>85%)',
                'description' => 'Dispara cuando el porcentaje de uso de memoria RAM supera el 85%.',
                'entity_type' => 'snmp_device',
                'entity_id' => null,
                'condition_type' => 'memory_high',
                'threshold_value' => 85,
                'comparison' => 'gt',
                'duration_seconds' => 0,
                'severity' => 'warning',
                'cooldown_minutes' => 30,
                'max_alerts_per_hour' => 3,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Certificado SSL por Expirar (<= 7 días)',
                'description' => 'Dispara cuando restan 7 días o menos para el vencimiento de un certificado SSL/TLS.',
                'entity_type' => 'ssl_certificate',
                'entity_id' => null,
                'condition_type' => 'cert_expiring',
                'threshold_value' => 7,
                'comparison' => 'lte',
                'duration_seconds' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 1440,
                'max_alerts_per_hour' => 1,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Certificado SSL Expirado',
                'description' => 'Dispara de inmediato cuando un certificado SSL/TLS ha expirado.',
                'entity_type' => 'ssl_certificate',
                'entity_id' => null,
                'condition_type' => 'cert_expired',
                'threshold_value' => null,
                'comparison' => null,
                'duration_seconds' => 0,
                'severity' => 'emergency',
                'cooldown_minutes' => 720,
                'max_alerts_per_hour' => 2,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Nuevo Dispositivo Detectado en Red',
                'description' => 'Notifica cuando el motor de Auto-Discovery descubre una IP o MAC no autorizada.',
                'entity_type' => 'discovered_device',
                'entity_id' => null,
                'condition_type' => 'new_device',
                'threshold_value' => null,
                'comparison' => null,
                'duration_seconds' => 0,
                'severity' => 'info',
                'cooldown_minutes' => 60,
                'max_alerts_per_hour' => 5,
                'auto_resolve' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Dispositivo Autorizado Desaparecido (>1h)',
                'description' => 'Dispara cuando un equipo previamente catalogado como conocido no responde por más de 1 hora.',
                'entity_type' => 'discovered_device',
                'entity_id' => null,
                'condition_type' => 'device_disappeared',
                'threshold_value' => 3600,
                'comparison' => 'gt',
                'duration_seconds' => 0,
                'severity' => 'warning',
                'cooldown_minutes' => 60,
                'max_alerts_per_hour' => 2,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Interfaz de Red Caída',
                'description' => 'Dispara cuando una interfaz monitoreada por SNMP pasa a estado operState DOWN.',
                'entity_type' => 'interface',
                'entity_id' => null,
                'condition_type' => 'interface_down',
                'threshold_value' => null,
                'comparison' => null,
                'duration_seconds' => 0,
                'severity' => 'warning',
                'cooldown_minutes' => 15,
                'max_alerts_per_hour' => 4,
                'auto_resolve' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Temperatura Elevada en Equipo (>65°C)',
                'description' => 'Dispara cuando los sensores térmicos SNMP reportan más de 65°C en chasis.',
                'entity_type' => 'snmp_device',
                'entity_id' => null,
                'condition_type' => 'temperature_high',
                'threshold_value' => 65,
                'comparison' => 'gt',
                'duration_seconds' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 30,
                'max_alerts_per_hour' => 3,
                'auto_resolve' => true,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $ruleData) {
            $rule = AlertRule::updateOrCreate(
                [
                    'name' => $ruleData['name'],
                    'entity_type' => $ruleData['entity_type'],
                    'condition_type' => $ruleData['condition_type'],
                ],
                $ruleData
            );

            // Seed escalation levels (Levels 1, 2, 3)
            // Level 1: Immediate notification to Admin (0 min)
            AlertEscalationLevel::updateOrCreate(
                [
                    'alert_rule_id' => $rule->id,
                    'level' => 1,
                ],
                [
                    'delay_minutes' => 0,
                    'channel' => 'telegram',
                    'target_type' => 'user',
                    'target_id' => $adminId,
                    'target_external' => '38914901',
                    'is_active' => true,
                ]
            );

            // Level 2: Reminder after 15 min if still firing
            AlertEscalationLevel::updateOrCreate(
                [
                    'alert_rule_id' => $rule->id,
                    'level' => 2,
                ],
                [
                    'delay_minutes' => 15,
                    'channel' => 'telegram',
                    'target_type' => 'user',
                    'target_id' => $adminId,
                    'target_external' => '38914901',
                    'is_active' => true,
                ]
            );

            // Level 3: Final escalation after 30 min
            AlertEscalationLevel::updateOrCreate(
                [
                    'alert_rule_id' => $rule->id,
                    'level' => 3,
                ],
                [
                    'delay_minutes' => 30,
                    'channel' => 'telegram',
                    'target_type' => 'user',
                    'target_id' => $adminId,
                    'target_external' => '38914901',
                    'is_active' => true,
                ]
            );
        }

        // Seed an initial correlation group if a site or network device exists
        $firstSite = MonitoredSite::first();
        if ($firstSite) {
            $group = AlertCorrelationGroup::updateOrCreate(
                [
                    'name' => 'Sede ' . $firstSite->name . ' - Enlace Principal',
                    'parent_entity_type' => 'site',
                    'parent_entity_id' => $firstSite->id,
                ],
                [
                    'description' => 'Suprime alertas de servicios si el enlace principal hacia ' . $firstSite->name . ' está caído.',
                    'suppression_strategy' => 'suppress_if_parent_down',
                    'is_active' => true,
                ]
            );
        }
    }
}
