<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class InventoryAsset extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'asset_tag',
        'name',
        'type',
        'status',
        'brand',
        'model',
        'serial_number',
        'mac_address',
        'ip_address',
        'monitored_site_id',
        'device_number',
        'access_type',
        'access_port',
        'ssh_port',
        'vnc_port',
        'web_port',
        'telnet_port',
        'ssh_username',
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
        'vnc_password_encrypted',
        'ports',
        'normal_state_msg',
        'error_state_msg',
        'is_active',
        'sort_order',
        'telegram_alert_enabled',
        'telegram_alert_target',
        'last_alert_state',
        'down_since',
        'location_detail',
        'monitored_network_device_id',
        'current_user_id',
        'custodian_name',
        'custodian_id_number',
        'custodian_department',
        'operating_system',
        'os_kernel',
        'purchase_date',
        'warranty_expiration',
        'last_glpi_sync_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monitored_site_id' => 'integer',
            'device_number' => 'integer',
            'access_port' => 'integer',
            'ssh_port' => 'integer',
            'vnc_port' => 'integer',
            'web_port' => 'integer',
            'telnet_port' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'telegram_alert_enabled' => 'boolean',
            'down_since' => 'datetime',
            'ssh_password_encrypted' => 'encrypted',
            'ssh_enable_secret_encrypted' => 'encrypted',
            'vnc_password_encrypted' => 'encrypted',
            'monitored_network_device_id' => 'integer',
            'current_user_id' => 'integer',
            'purchase_date' => 'date',
            'warranty_expiration' => 'date',
            'last_glpi_sync_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'ssh_password_encrypted',
        'ssh_enable_secret_encrypted',
        'vnc_password_encrypted',
    ];

    protected $appends = [
        'has_ssh_credentials',
        'has_vnc_credentials',
        'ssh_port_resolved',
        'vnc_port_resolved',
        'web_port_resolved',
        'telnet_port_resolved',
    ];

    protected static function booted(): void
    {
        static::creating(function ($asset) {
            if (empty($asset->asset_tag)) {
                $maxId = (static::max('id') ?? 0) + 1;
                $asset->asset_tag = 'AST-' . str_pad($maxId, 5, '0', STR_PAD_LEFT);
            }
            if (empty($asset->monitored_network_device_id) && !empty($asset->id)) {
                $asset->monitored_network_device_id = $asset->id;
            }
        });

        static::saved(function ($asset) {
            $ip = $asset->ip_address;
            $mac = $asset->mac_address;

            // Auto-conciliar con discovered_devices si la tabla existe
            if (Schema::hasTable('discovered_devices')) {
                if (!empty($ip) || (!empty($mac) && !str_starts_with($mac, 'IP:'))) {
                    DiscoveredDevice::where(function ($q) use ($ip, $mac) {
                        if (!empty($ip)) {
                            $q->where('ip_address', $ip);
                        }
                        if (!empty($mac) && !str_starts_with($mac, 'IP:')) {
                            $q->orWhere('mac_address', $mac);
                        }
                    })->update([
                        'classification_status' => 'clasificado',
                        'is_authorized' => 1,
                        'linked_network_device_id' => $asset->id,
                        'site_id' => $asset->monitored_site_id,
                        'vendor' => $asset->brand ?: 'Desconocido',
                    ]);
                }
            }

            // Sincronizar interfaz de red primaria en la tabla Multi-NIC si la tabla existe
            if (Schema::hasTable('inventory_asset_network_interfaces')) {
                if (!empty($ip) || (!empty($mac) && !str_starts_with($mac, 'IP:'))) {
                    $primaryNic = $asset->networkInterfaces()->where('is_primary', true)->first();
                    if ($primaryNic) {
                        $primaryNic->update([
                            'ip_address' => ($ip && $ip !== '127.0.0.1') ? $ip : $primaryNic->ip_address,
                            'mac_address' => ($mac && !str_starts_with($mac, 'IP:')) ? $mac : $primaryNic->mac_address,
                        ]);
                    } else {
                        $asset->networkInterfaces()->create([
                            'interface_name' => 'eth0',
                            'ip_address' => ($ip && $ip !== '127.0.0.1') ? $ip : null,
                            'mac_address' => ($mac && !str_starts_with($mac, 'IP:')) ? $mac : null,
                            'is_primary' => true,
                            'status' => 'up',
                            'interface_type' => 'ethernet',
                        ]);
                    }
                }
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }

    public function monitoredSite(): BelongsTo
    {
        return $this->site();
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(MonitoredNetworkDevice::class, 'id');
    }

    public function discoveredDevice(): HasOne
    {
        return $this->hasOne(DiscoveredDevice::class, 'linked_network_device_id', 'id');
    }

    public function snmpDevice(): HasOne
    {
        return $this->hasOne(SnmpDevice::class, 'network_device_id', 'id');
    }

    public function wolDevice(): HasOne
    {
        return $this->hasOne(WolDevice::class, 'mac_address', 'mac_address');
    }

    public function netRadarHost(): HasOne
    {
        return $this->hasOne(NetRadarHost::class, 'ip', 'ip_address');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(NetworkDeviceCheckHistory::class, 'monitored_network_device_id');
    }

    public function networkInterfaces(): HasMany
    {
        return $this->hasMany(InventoryAssetNetworkInterface::class, 'inventory_asset_id');
    }

    public function primaryNetworkInterface(): HasOne
    {
        return $this->hasOne(InventoryAssetNetworkInterface::class, 'inventory_asset_id')->where('is_primary', true);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(DeviceConfiguration::class, 'network_device_id');
    }

    public function latestConfiguration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeviceConfiguration::class, 'network_device_id')->latestOfMany('captured_at');
    }

    public function hasSshCredentials(): bool
    {
        return !empty($this->ssh_username) && !empty($this->ssh_password_encrypted);
    }

    public function getHasSshCredentialsAttribute(): bool
    {
        return $this->hasSshCredentials();
    }

    public function getHasVncCredentialsAttribute(): bool
    {
        return !empty($this->vnc_password_encrypted);
    }

    public function getSshPortResolvedAttribute(): int
    {
        return $this->ssh_port ?: (($this->access_type === 'SSH' || $this->access_type === 'SSH+VNC') ? ($this->access_port ?: 22) : 22);
    }

    public function getVncPortResolvedAttribute(): int
    {
        return $this->vnc_port ?: (($this->access_type === 'VNC' || $this->access_type === 'SSH+VNC') ? ($this->access_port ?: 5900) : 5900);
    }

    public function getWebPortResolvedAttribute(): int
    {
        return $this->web_port ?: ($this->access_type === 'WEB' ? ($this->access_port ?: 80) : 80);
    }

    public function getTelnetPortResolvedAttribute(): int
    {
        return $this->telnet_port ?: ($this->access_type === 'TELNET' ? ($this->access_port ?: 23) : 23);
    }

    public function hasSsh(): bool
    {
        return in_array($this->access_type, ['SSH', 'SSH+VNC']) || !empty($this->ssh_port);
    }

    public function hasVnc(): bool
    {
        return in_array($this->access_type, ['VNC', 'SSH+VNC']) || !empty($this->vnc_port);
    }

    public function hasWeb(): bool
    {
        return $this->access_type === 'WEB' || !empty($this->web_port);
    }

    public function hasTelnet(): bool
    {
        return $this->access_type === 'TELNET' || !empty($this->telnet_port);
    }

    public function getIpAttribute(): ?string
    {
        return $this->ip_address;
    }

    public function setIpAttribute(?string $value): void
    {
        $this->attributes['ip_address'] = $value;
    }

    public function getMacAttribute(): ?string
    {
        return $this->mac_address;
    }

    public function setMacAttribute(?string $value): void
    {
        $this->attributes['mac_address'] = $value;
    }

    public function getSerialAttribute(): ?string
    {
        return $this->serial_number;
    }

    public function setSerialAttribute(?string $value): void
    {
        $this->attributes['serial_number'] = $value;
    }

    public function getVendorDataAttribute(): ?string
    {
        return $this->brand;
    }

    public function setVendorDataAttribute(?string $value): void
    {
        $this->attributes['brand'] = $value;
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(InventoryComponent::class, 'inventory_asset_id');
    }

    public function componentHistories(): HasMany
    {
        return $this->hasMany(InventoryComponentHistory::class, 'inventory_asset_id')->orderBy('recorded_at', 'desc');
    }

    public function peripherals(): HasMany
    {
        return $this->hasMany(InventoryPeripheral::class, 'current_asset_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(InventoryAssignment::class, 'inventory_asset_id')->orderBy('assigned_at', 'desc');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(InventoryAssignment::class, 'inventory_asset_id')
            ->where('status', 'active')
            ->latestOfMany('assigned_at');
    }

    public function getActiveAssignmentAttribute(): ?InventoryAssignment
    {
        if ($this->relationLoaded('activeAssignment')) {
            return $this->getRelation('activeAssignment');
        }
        if ($this->relationLoaded('assignments')) {
            return $this->assignments->firstWhere('status', 'active');
        }
        return $this->getRelationValue('activeAssignment');
    }

    /**
     * Determina si el equipo asignado tiene modificaciones de hardware o periféricos
     * posteriores a la fecha del acta de asignación activa, exigiendo renovar la planilla UINF-4.05.
     */
    public function hasOutdatedAssignment(): bool
    {
        if ($this->status !== 'assigned') {
            return false;
        }

        $assignment = $this->active_assignment;
        if (!$assignment) {
            return false;
        }

        $assignedAt = $assignment->assigned_at ? \Carbon\Carbon::parse($assignment->assigned_at) : null;
        $createdAt = $assignment->created_at ? \Carbon\Carbon::parse($assignment->created_at) : null;
        $benchmarkDate = $assignedAt ?: $createdAt;

        if (!$benchmarkDate) {
            return false;
        }

        // 1. Revisar si hay eventos en el histórico inmutable de componentes posteriores a la fecha del acta
        $histories = $this->relationLoaded('componentHistories')
            ? $this->componentHistories
            : $this->componentHistories()->get();

        $hasPostAssignmentHistory = $histories->contains(function ($h) use ($benchmarkDate) {
            return $h->recorded_at && \Carbon\Carbon::parse($h->recorded_at)->gt($benchmarkDate);
        });

        if ($hasPostAssignmentHistory) {
            return true;
        }

        // 2. Revisar si los periféricos actuales difieren de lo plasmado en peripherals_json del acta
        $currentPeripherals = $this->relationLoaded('peripherals')
            ? $this->peripherals
            : $this->peripherals()->get();

        $savedPeripherals = collect($assignment->peripherals_json ?? []);

        // Si el acta previa no registró snapshot de periféricos (acta legada o inicial),
        // no generar falsas alertas salvo que existan periféricos agregados/modificados posterior al acta
        if ($savedPeripherals->isEmpty()) {
            if ($currentPeripherals->isEmpty()) {
                return false;
            }
            return $currentPeripherals->contains(function ($p) use ($benchmarkDate) {
                $created = $p->created_at ? \Carbon\Carbon::parse($p->created_at) : null;
                $updated = $p->updated_at ? \Carbon\Carbon::parse($p->updated_at) : null;
                return ($created && $created->gt($benchmarkDate)) || ($updated && $updated->gt($benchmarkDate));
            });
        }

        if ($currentPeripherals->count() !== $savedPeripherals->count()) {
            return true;
        }

        $normalize = fn($val) => strtoupper(trim((string)$val));

        $currentSerials = $currentPeripherals->map(fn($p) => $normalize($p->serial_number ?: $p->type))->sort()->values();
        $savedSerials = $savedPeripherals->map(fn($p) => $normalize($p['serial_number'] ?? $p['type'] ?? ''))->sort()->values();

        if ($currentSerials->toArray() !== $savedSerials->toArray()) {
            return true;
        }

        return false;
    }

    public function getHasOutdatedAssignmentAttribute(): bool
    {
        return $this->hasOutdatedAssignment();
    }

    /**
     * Estado del SLA del acta activa ('validated', 'overdue', 'warning', 'pending' o null)
     */
    public function getAssignmentScanStatusAttribute(): ?string
    {
        return $this->active_assignment?->scan_compliance_status;
    }

    public function getIsScanOverdueAttribute(): bool
    {
        return $this->active_assignment?->scan_compliance_status === 'overdue';
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class, 'inventory_asset_id')->orderBy('created_at', 'desc');
    }

    /**
     * Scope para búsqueda rápida en catálogo
     */
    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('normal_state_msg', 'like', "%{$term}%")
              ->orWhere('asset_tag', 'like', "%{$term}%")
              ->orWhere('serial_number', 'like', "%{$term}%")
              ->orWhere('mac_address', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%")
              ->orWhere('custodian_name', 'like', "%{$term}%")
              ->orWhere('brand', 'like', "%{$term}%")
              ->orWhere('model', 'like', "%{$term}%")
              ->orWhere('notes', 'like', "%{$term}%");
        });
    }

    /**
     * Retorna el icono material symbol asociado al tipo de equipo
     */
    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'desktop' => 'desktop_windows',
            'laptop' => 'laptop_mac',
            'server' => 'dns',
            'switch' => 'hub',
            'router' => 'router',
            'firewall' => 'security',
            'access_point' => 'wifi',
            'printer' => 'print',
            'ip_phone' => 'deskphone',
            'ups' => 'battery_charging_full',
            default => 'devices',
        };
    }

    /**
     * Retorna la etiqueta legible en español del tipo de dispositivo
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'desktop' => 'Desktop / Computadora',
            'laptop' => 'Laptop / Portátil',
            'server' => 'Servidor',
            'switch' => 'Switch de Red',
            'router' => 'Router Gateway',
            'firewall' => 'Firewall / Seguridad',
            'access_point' => 'Access Point Wi-Fi',
            'printer' => 'Impresora',
            'ip_phone' => 'Teléfono IP',
            'ups' => 'Sistema UPS',
            default => ucfirst(str_replace('_', ' ', $this->type ?? 'Dispositivo')),
        };
    }

    /**
     * Retorna la etiqueta legible del estado
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'in_stock' => 'En Bóveda (Disponible)',
            'assigned' => 'Asignado (En Uso)',
            'in_maintenance' => 'En Mantenimiento',
            'in_transit' => 'En Movilización / Tránsito',
            'decommissioned' => 'Desincorporado / Baja',
            'damaged' => 'Dañado / Para Revisión',
            'unauthorized' => 'Descubierto (No Autorizado)',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Identidad operativa principal (Nombre en monitoreo si los PC están siendo monitoreados, sino nombre del equipo)
     */
    public function getDisplayIdentityAttribute(): string
    {
        if (!empty($this->normal_state_msg)) {
            $clean = trim(preg_replace('/^[✅❌⚠️\s\-]+/u', '', $this->normal_state_msg));
            if (!empty($clean)) {
                return $clean;
            }
        }
        return $this->name;
    }

    /**
     * Subtítulo técnico (Hostname cuando el display_identity es el nombre de monitoreo)
     */
    public function getTechnicalSubtitleAttribute(): ?string
    {
        $display = $this->display_identity;
        if (!empty($this->name) && $this->name !== $display) {
            return $this->name;
        }
        if (!empty($this->notes) && preg_match('/(?:Hostname|NetBIOS):\s*([^\s\|]+)/i', $this->notes, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Indica si el activo es una computadora o servidor con componentes/periféricos
     */
    public function getIsPcAttribute(): bool
    {
        return in_array($this->type, ['desktop', 'laptop', 'server'], true);
    }
}
