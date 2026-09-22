<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertNotification extends Model
{
    use HasFactory;

    protected $table = 'alert_notifications';
    public $timestamps = false;

    protected $fillable = [
        'alert_id',
        'escalation_level_id',
        'channel',
        'target',
        'message_sent',
        'status',
        'error_message',
        'external_message_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'alert_id');
    }

    public function escalationLevel(): BelongsTo
    {
        return $this->belongsTo(AlertEscalationLevel::class, 'escalation_level_id');
    }
}
