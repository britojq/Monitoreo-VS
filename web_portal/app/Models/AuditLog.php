<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'event',
        'module',
        'auditable_type',
        'auditable_id',
        'entity_name',
        'entity_label',
        'description',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEventLabelAttribute(): string
    {
        return match($this->event) {
            'login' => 'Inicio de Sesión',
            'logout' => 'Cierre de Sesión',
            'login_failed' => 'Acceso Fallido',
            'created' => 'Creación',
            'updated' => 'Modificación',
            'deleted' => 'Eliminación',
            'toggled' => 'Cambio de Estado',
            'exported' => 'Exportación',
            'terms_accepted' => 'Términos Aceptados',
            'terms_revoked' => 'Términos Revocados',
            'terms_revoked_all' => 'Reinicio Masivo Términos',
            'acknowledged' => 'Reconocimiento Alerta',
            'resolved' => 'Resolución Alerta',
            'silenced' => 'Alerta Silenciada',
            'scanned' => 'Escaneo de Red',
            'remote_activation' => 'Activación Remota',
            'rechecked' => 'Re-inspección SSL',
            'unauthorized_command' => 'Comando Denegado',
            'access_denied' => 'Acceso No Autorizado',
            'unauthorized_access' => 'Intento No Autorizado',
            'ssh_login' => 'Acceso SSH',
            'ssh_logout' => 'Desconexión SSH',
            'privilege_escalation' => 'Elevación Privilegios',
            'sudo_command' => 'Comando Administrativo',
            'ip_banned' => 'IP Bloqueada (Fail2ban)',
            'ip_unbanned' => 'IP Desbloqueada (Fail2ban)',
            default => strtoupper($this->event),
        };
    }

    public function getEventBadgeClassAttribute(): string
    {
        return match($this->event) {
            'login' => 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/40',
            'logout' => 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border',
            'login_failed' => 'bg-red-950/80 text-red-400 border border-red-500/40',
            'created' => 'bg-cyan-950/80 text-cyan-300 border border-cyan-500/40',
            'updated' => 'bg-blue-950/80 text-blue-300 border border-blue-500/40',
            'deleted' => 'bg-rose-950/80 text-rose-400 border border-rose-500/40',
            'toggled' => 'bg-purple-950/80 text-purple-300 border border-purple-500/40',
            'exported' => 'bg-indigo-950/80 text-indigo-300 border border-indigo-500/40',
            'terms_accepted' => 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40',
            'terms_revoked', 'terms_revoked_all' => 'bg-amber-950/80 text-amber-300 border border-amber-500/40',
            'acknowledged' => 'bg-amber-950/80 text-amber-300 border border-amber-500/40',
            'resolved' => 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40',
            'silenced' => 'bg-purple-950/80 text-purple-300 border border-purple-500/40',
            'scanned', 'rechecked', 'remote_activation' => 'bg-cyan-950/80 text-cyan-300 border border-cyan-500/40',
            'unauthorized_command', 'access_denied', 'unauthorized_access' => 'bg-rose-950/80 text-rose-300 border border-rose-500/40',
            'ssh_login' => 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/40',
            'ssh_logout' => 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border',
            'privilege_escalation' => 'bg-amber-950/80 text-amber-400 border border-amber-500/40',
            'sudo_command' => 'bg-blue-950/80 text-blue-300 border border-blue-500/40',
            'ip_banned' => 'bg-red-950/80 text-red-300 border border-red-500/40',
            'ip_unbanned' => 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40',
            default => 'bg-obsidian-panel text-obsidian-muted border border-obsidian-border',
        };
    }

    public function getEventIconAttribute(): string
    {
        return match($this->event) {
            'login' => 'login',
            'logout' => 'logout',
            'login_failed' => 'lock_clock',
            'created' => 'add_circle',
            'updated' => 'edit',
            'deleted' => 'delete',
            'toggled' => 'toggle_on',
            'exported' => 'download',
            'terms_accepted' => 'verified_user',
            'terms_revoked', 'terms_revoked_all' => 'restart_alt',
            'acknowledged' => 'check_circle',
            'resolved' => 'task_alt',
            'silenced' => 'notifications_paused',
            'scanned' => 'radar',
            'remote_activation' => 'settings_remote',
            'rechecked' => 'refresh',
            'unauthorized_command', 'access_denied', 'unauthorized_access' => 'shield_lock',
            'ssh_login' => 'terminal',
            'ssh_logout' => 'logout',
            'privilege_escalation' => 'admin_panel_settings',
            'sudo_command' => 'manage_accounts',
            'ip_banned' => 'block',
            'ip_unbanned' => 'lock_open',
            default => 'info',
        };
    }

    public function getModuleLabelAttribute(): string
    {
        return match($this->module) {
            'auth' => 'Seguridad & Acceso',
            'security' => 'Seguridad Institucional',
            'services' => 'Servicios',
            'sites' => 'Sedes & Enlaces',
            'devices' => 'Equipos de Red',
            'proxies' => 'Proxies',
            'users' => 'Gestión de Usuarios',
            'settings' => 'Configuración',
            'alerts' => 'Centro de Alertas',
            'snmp' => 'Monitoreo SNMP',
            'ssl' => 'Certificados SSL',
            'netradar' => 'NET Radar',
            'bot' => 'Bot Telegram',
            'pam' => 'Servidor & PAM (SSH/Sudo)',
            default => ucfirst($this->module),
        };
    }

    public function getModuleIconAttribute(): string
    {
        return match($this->module) {
            'auth' => 'security',
            'security' => 'gavel',
            'services' => 'dns',
            'sites' => 'domain',
            'devices' => 'router',
            'proxies' => 'shield',
            'users' => 'group',
            'settings' => 'tune',
            'alerts' => 'notifications_active',
            'snmp' => 'analytics',
            'ssl' => 'lock',
            'netradar' => 'radar',
            'bot' => 'smart_toy',
            'pam' => 'terminal',
            default => 'folder',
        };
    }
}
