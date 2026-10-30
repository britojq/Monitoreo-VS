<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertCorrelationGroup extends Model
{
    use HasFactory;

    protected $table = 'alert_correlation_groups';

    protected $fillable = [
        'name',
        'description',
        'parent_entity_type',
        'parent_entity_id',
        'suppression_strategy',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(AlertCorrelationMember::class, 'correlation_group_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'correlation_group_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
