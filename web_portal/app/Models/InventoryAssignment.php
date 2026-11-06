<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAssignment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'assignment_code',
        'inventory_asset_id',
        'user_id',
        'custodian_name',
        'custodian_id_number',
        'custodian_department',
        'custodian_role',
        'monitored_site_id',
        'movement_type',
        'provenance',
        'provenance_other',
        'phone_extension',
        'checkpoint_name',
        'gate_operator_name',
        'departure_time',
        'entry_time',
        'control_number',
        'hardware_specs',
        'peripherals_json',
        'assigned_at',
        'returned_at',
        'document_pdf_path',
        'status',
        'delivered_by_user_id',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'inventory_asset_id' => 'integer',
            'user_id' => 'integer',
            'monitored_site_id' => 'integer',
            'delivered_by_user_id' => 'integer',
            'peripherals_json' => 'array',
            'hardware_specs' => 'array',
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MonitoredSite::class, 'monitored_site_id');
    }

    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function getMovementTypeLabelAttribute(): string
    {
        return match ($this->movement_type) {
            'assignment' => 'Asignación Inicial',
            'transfer' => 'Movilización / Traslado',
            'return' => 'Devolución / Desincorporación',
            'loan' => 'Préstamo Temporal',
            default => ucfirst($this->movement_type),
        };
    }

    /**
     * Indica si el acta cuenta con archivo digitalizado / escaneado subido
     */
    public function getHasSignedScanAttribute(): bool
    {
        return !empty($this->document_pdf_path);
    }

    /**
     * Días continuos transcurridos desde la entrega del activo
     */
    public function getDaysSinceAssignedAttribute(): int
    {
        $date = $this->assigned_at ?? $this->created_at;
        if (!$date) {
            return 0;
        }

        return (int) \Carbon\Carbon::parse($date)->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Días de holgura estimados para subir el acta escaneada
     */
    public function getDaysRemainingForScanAttribute(): int
    {
        return max(0, 15 - $this->days_since_assigned);
    }

    /**
     * Estado de cumplimiento del soporte físico escaneado:
     * - 'validated': Acta con documento escaneado firmado y sellado
     * - 'overdue': Sin acta escaneada registrada
     * - 'warning': Próxima a requerir carga
     * - 'pending': En proceso
     */
    public function getScanComplianceStatusAttribute(): string
    {
        if ($this->has_signed_scan) {
            return 'validated';
        }

        if ($this->days_since_assigned > 15) {
            return 'overdue';
        }

        if ($this->days_since_assigned >= 11) {
            return 'warning';
        }

        return 'pending';
    }

    /**
     * Etiqueta descriptiva del estado de cumplimiento SLA
     */
    public function getScanComplianceLabelAttribute(): string
    {
        return match ($this->scan_compliance_status) {
            'validated' => 'Validada con Soporte Digital',
            'overdue' => 'Digitalización Pendiente',
            'warning' => 'Pendiente de Digitalización',
            'pending' => 'Pendiente de Digitalización',
            default => 'Pendiente',
        };
    }

    /**
     * Clases CSS Tailwind para insignias de auditoría SLA
     */
    public function getScanComplianceBadgeClassAttribute(): string
    {
        return match ($this->scan_compliance_status) {
            'validated' => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
            'overdue' => 'bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse',
            'warning' => 'bg-amber-500/20 text-amber-300 border border-amber-500/40',
            'pending' => 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30',
            default => 'bg-slate-800 text-slate-300 border border-slate-700',
        };
    }

    /**
     * Scope para actas con SLA vencido (> 15 días sin documento escaneado)
     */
    public function scopeOverdueScan($query)
    {
        return $query->where('status', 'active')
            ->where('movement_type', 'assignment')
            ->where(function ($q) {
                $q->whereNull('document_pdf_path')->orWhere('document_pdf_path', '');
            })
            ->where('assigned_at', '<', now()->subDays(15));
    }

    /**
     * Scope para actas pendientes de digitalizar
     */
    public function scopePendingScan($query)
    {
        return $query->where('status', 'active')
            ->where('movement_type', 'assignment')
            ->where(function ($q) {
                $q->whereNull('document_pdf_path')->orWhere('document_pdf_path', '');
            });
    }

    /**
     * Scope para actas validadas con soporte físico escaneado
     */
    public function scopeValidatedScan($query)
    {
        return $query->whereNotNull('document_pdf_path')
            ->where('document_pdf_path', '!=', '');
    }
}
