<?php

namespace App\Services\Notification\Formatters;

use App\Models\InventoryAssignment;
use App\Models\MaintenanceTicket;

/**
 * Formateador de mensajes para eventos operativos de ITAM (Tickets y Actas de Asignación).
 * Responsabilidad Única (S): Formateo de texto HTML para tickets de mantenimiento y actas UINF-4.05.
 */
class ItamNotificationFormatter
{
    /**
     * Formatea el mensaje para la apertura de un ticket u orden de servicio de mantenimiento.
     */
    public function formatMaintenanceTicket(MaintenanceTicket $ticket): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        $typeLabel = strtoupper($ticket->type_label ?? 'Mantenimiento');
        $priorityLabel = strtoupper($ticket->priority ?? 'Normal');

        return "🔧 <b>ITAM: ORDEN DE SERVICIO TÉCNICO REGISTRADA</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "📋 <b>Nº Orden:</b> <code>" . htmlspecialchars($ticket->ticket_code) . "</code>\n"
             . "💻 <b>Activo:</b> " . htmlspecialchars($ticket->asset->name ?? 'Equipo') . " (<code>" . htmlspecialchars($ticket->asset->asset_tag ?? 'S/T') . "</code>)\n"
             . "🔢 <b>Serial:</b> <code>" . htmlspecialchars($ticket->asset->serial_number ?? 'S/N') . "</code>\n"
             . "⚙️ <b>Tipo:</b> {$typeLabel} | <b>Prioridad:</b> {$priorityLabel}\n"
             . "📝 <b>Motivo:</b> " . htmlspecialchars($ticket->title) . "\n"
             . "👨‍🔧 <b>Técnico:</b> " . htmlspecialchars($ticket->technician->name ?? 'Por asignar') . "\n"
             . "📅 <b>Fecha:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "ℹ️ <i>Equipo registrado en el módulo de mantenimiento.</i>";
    }

    /**
     * Formatea el mensaje para la asignación o devolución de equipos bajo acta corporativa.
     */
    public function formatAssetAssignment(InventoryAssignment $assignment): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        $action = ($assignment->movement_type === 'return') ? "DEVOLUCIÓN DE EQUIPO" : "ASIGNACIÓN DE EQUIPO (UINF-4.05)";

        return "📑 <b>ITAM: {$action}</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "📄 <b>Código Acta:</b> <code>" . htmlspecialchars($assignment->assignment_code) . "</code>\n"
             . "👤 <b>Funcionario Receptor:</b> " . htmlspecialchars($assignment->custodian_name) . "\n"
             . "🆔 <b>C.I.:</b> " . htmlspecialchars($assignment->custodian_id_number ?? 'N/A') . "\n"
             . "🏢 <b>Unidad:</b> " . htmlspecialchars($assignment->custodian_department ?? 'N/A') . "\n"
             . "💻 <b>Activo:</b> " . htmlspecialchars($assignment->asset->name ?? 'Equipo') . "\n"
             . "🔢 <b>Serial:</b> <code>" . htmlspecialchars($assignment->asset->serial_number ?? 'S/N') . "</code>\n"
             . "📅 <b>Fecha:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "✅ <i>Acta registrada bajo formato corporativo.</i>";
    }
}
