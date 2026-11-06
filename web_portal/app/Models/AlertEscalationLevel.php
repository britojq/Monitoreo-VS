<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertEscalationLevel extends Model
{
    use HasFactory;

    protected $table = 'alert_escalation_levels';

    protected $fillable = [
        'alert_rule_id',
        'level',
        'delay_minutes',
        'channel',
        'target_type',
        'target_id',
        'target_group',
        'target_external',
        'message_template_id',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'delay_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(BotMessageTemplate::class, 'message_template_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class, 'escalation_level_id');
    }
}
