<?php

namespace App\Services\Notification\Formatters;

use App\Models\InventoryAsset;

/**
 * Formateador de mensajes para eventos de Telemetría e Inventario GLPI / ITAM.
 * Responsabilidad Única (S): Formateo de texto HTML para hallazgos y cambios de hardware.
 */
class GlpiNotificationFormatter
{
    /**
     * Formatea el mensaje de alerta para un nuevo activo descubierto por el agente GLPI.
     */
    public function formatNewAsset(InventoryAsset $asset): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        return "🆕 <b>ITAM: NUEVO ACTIVO DETECTADO POR AGENTE GLPI</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "💻 <b>Equipo:</b> <code>" . htmlspecialchars($asset->name) . "</code>\n"
             . "🏷️ <b>Marca / Modelo:</b> " . htmlspecialchars($asset->brand ?? 'Genérico') . " " . htmlspecialchars($asset->model ?? '') . "\n"
             . "🔢 <b>Serial:</b> <code>" . htmlspecialchars($asset->serial_number ?? 'No reportado') . "</code>\n"
             . "🌐 <b>IP / MAC:</b> <code>" . htmlspecialchars($asset->ip_address ?? 'N/D') . "</code> (" . htmlspecialchars($asset->mac_address ?? 'N/D') . ")\n"
             . "🖥️ <b>S.O.:</b> " . htmlspecialchars($asset->operating_system ?? 'Desconocido') . "\n"
             . "📅 <b>Detectado:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "ℹ️ <i>El equipo fue registrado en estado 'Descubierto' en el inventario.</i>";
    }

    /**
     * Formatea el mensaje de alerta para cambios físicos en los componentes de un activo.
     */
    public function formatHardwareChanged(InventoryAsset $asset, array $changes): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        $changesStr = "";
        foreach ($changes as $c) {
            $changesStr .= "• " . htmlspecialchars($c) . "\n";
        }

        return "⚠️ <b>ITAM AUDITORÍA: CAMBIO DE HARDWARE DETECTADO</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "💻 <b>Equipo:</b> <code>" . htmlspecialchars($asset->name) . "</code>\n"
             . "🔢 <b>Serial:</b> <code>" . htmlspecialchars($asset->serial_number ?? 'N/D') . "</code>\n"
             . "👤 <b>Funcionario Responsable:</b> " . htmlspecialchars($asset->custodian_name ?? 'Sin asignar') . "\n"
             . "🛠️ <b>Modificaciones Registradas:</b>\n"
             . $changesStr
             . "📅 <b>Fecha:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "🛡️ <i>Evento registrado en el historial inmutable de componentes.</i>";
    }
}
