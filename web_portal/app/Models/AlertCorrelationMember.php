<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertCorrelationMember extends Model
{
    use HasFactory;

    protected $table = 'alert_correlation_members';
    public $timestamps = false;

    protected $fillable = [
        'correlation_group_id',
        'child_entity_type',
        'child_entity_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AlertCorrelationGroup::class, 'correlation_group_id');
    }
}
