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
            'terms_accepted' => 'Términos Aceptados',
            'terms_revoked' => 'Términos Revocados',
            'terms_revoked_all' => 'Reinicio Masivo Términos',
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
            'terms_accepted' => 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40',
            'terms_revoked', 'terms_revoked_all' => 'bg-amber-950/80 text-amber-300 border border-amber-500/40',
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
            'terms_accepted' => 'verified_user',
            'terms_revoked', 'terms_revoked_all' => 'restart_alt',
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
            default => 'folder',
        };
    }
}
