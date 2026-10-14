<?php

namespace App\Services;

use App\Contracts\NotifierInterface;
use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\Notification\Formatters\AuthNotificationFormatter;
use App\Services\Notification\Formatters\GlpiNotificationFormatter;
use App\Services\Notification\Formatters\ItamNotificationFormatter;
use App\Services\Notification\TelegramTransport;

/**
 * Fachada coordinadora de Notificaciones Telegram del Sistema.
 * Mantiene compatibilidad hacia atrás total con todos los controladores y servicios existentes.
 * Aplica Principios SOLID:
 * - S: Delega el transporte HTTP a TelegramTransport y el formateo a Formatters especializados.
 * - D: Permite inyección de NotifierInterface o TelegramTransport.
 */
class TelegramNotificationService
{
    protected TelegramTransport $transport;
    protected AuthNotificationFormatter $authFormatter;
    protected GlpiNotificationFormatter $glpiFormatter;
    protected ItamNotificationFormatter $itamFormatter;

    public function __construct(
        ?TelegramTransport $transport = null,
        ?AuthNotificationFormatter $authFormatter = null,
        ?GlpiNotificationFormatter $glpiFormatter = null,
        ?ItamNotificationFormatter $itamFormatter = null
    ) {
        $this->transport = $transport ?? app(TelegramTransport::class);
        $this->authFormatter = $authFormatter ?? app(AuthNotificationFormatter::class);
        $this->glpiFormatter = $glpiFormatter ?? app(GlpiNotificationFormatter::class);
        $this->itamFormatter = $itamFormatter ?? app(ItamNotificationFormatter::class);
    }

    /**
     * Enviar mensaje HTML formateado a los chats privados de administración.
     */
    public function sendMessage(string $text, string $parseMode = 'HTML'): bool
    {
        return $this->transport->sendToDefaultChats($text, $parseMode);
    }

    /**
     * Notificación cuando un usuario se registra automáticamente vía LDAP (JIT).
     */
    public function notifyLdapAutoRegistered(User $user, string $ip): void
    {
        rescue(function () use ($user, $ip) {
            $msg = $this->authFormatter->formatLdapAutoRegistered($user, $ip);
            $this->logAudit("AUTO-REGISTRO LDAP", $user, $ip, "Auto-registro exitoso como {$user->role}");
            $this->sendMessage($msg);
        }, report: true);
    }

    /**
     * Notificación cuando un usuario accede al sistema con credenciales asignadas.
     */
    public function notifyUserLogin(User $user, string $ip, string $authType = 'local'): void
    {
        rescue(function () use ($user, $ip, $authType) {
            $msg = $this->authFormatter->formatUserLogin($user, $ip, $authType);
            $this->logAudit("INICIO DE SESIÓN", $user, $ip, "Login exitoso ({$authType})");
            $this->sendMessage($msg);
        }, report: true);
    }

    /**
     * Notificación cuando un administrador otorga o autoriza acceso a un nuevo usuario.
     */
    public function notifyUserCreatedOrAuthorized(User $user, string $adminName, string $origin = 'manual'): void
    {
        rescue(function () use ($user, $adminName, $origin) {
            $msg = $this->authFormatter->formatUserCreatedOrAuthorized($user, $adminName, $origin);
            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Notificación cuando el agente GLPI descubre un nuevo equipo.
     */
    public function notifyGlpiNewAsset(InventoryAsset $asset): void
    {
        rescue(function () use ($asset) {
            $msg = $this->glpiFormatter->formatNewAsset($asset);
            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Notificación cuando el agente GLPI detecta cambio físico de hardware en un activo.
     */
    public function notifyGlpiHardwareChanged(InventoryAsset $asset, array $changes): void
    {
        rescue(function () use ($asset, $changes) {
            $msg = $this->glpiFormatter->formatHardwareChanged($asset, $changes);
            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Notificación cuando se abre una orden de servicio técnico.
     */
    public function notifyMaintenanceTicketOpened(MaintenanceTicket $ticket): void
    {
        rescue(function () use ($ticket) {
            $msg = $this->itamFormatter->formatMaintenanceTicket($ticket);
            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Notificación cuando se registra un acta de asignación o devolución (UINF-4.05).
     */
    public function notifyAssetAssignment(InventoryAssignment $assignment): void
    {
        rescue(function () use ($assignment) {
            $msg = $this->itamFormatter->formatAssetAssignment($assignment);
            $this->sendMessage($msg);
        }, report: false);
    }

    /**
     * Escribir línea en el archivo de auditoría local (preservado para compatibilidad histórica).
     */
    protected function logAudit(string $action, User $user, string $ip, string $detail): void
    {
        $logPath = '/scripts/telegram-admin-bot/audit/intentos_acceso.log';
        $now = now()->format('Y-m-d H:i:s');
        $idStr = $user->username ? "UID: {$user->username}" : "ID: {$user->id}";
        $line = sprintf(
            "[%s] 🌐 %s | %s (%s, %s, Rol: %s) | IP: %s | %s\n",
            $now,
            $action,
            $user->name,
            $idStr,
            $user->email,
            $user->role,
            $ip,
            $detail
        );
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }

    public function getTransport(): TelegramTransport
    {
        return $this->transport;
    }
}
