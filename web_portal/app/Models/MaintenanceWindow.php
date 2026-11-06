<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWindow extends Model
{
    use HasFactory;

    protected $table = 'maintenance_windows';

    protected $fillable = [
        'title',
        'description',
        'entity_type',
        'entity_id',
        'suppress_severities',
        'starts_at',
        'ends_at',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'suppress_severities' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveNow($query)
    {
        $now = Carbon::now();
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    public function scopeForEntity($query, string $type, ?int $id = null)
    {
        return $query->where(function ($q) use ($type, $id) {
            $q->where('entity_type', 'all')
                ->orWhere(function ($sub) use ($type, $id) {
                    $sub->where('entity_type', $type)
                        ->where(function ($idQ) use ($id) {
                            $idQ->whereNull('entity_id');
                            if ($id !== null) {
                                $idQ->orWhere('entity_id', $id);
                            }
                        });
                });
        });
    }

    public function isCurrentlyRunning(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        $now = Carbon::now();
        return $this->starts_at && $this->ends_at && $now->between($this->starts_at, $this->ends_at);
    }
}
