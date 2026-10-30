<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertStormSuppression extends Model
{
    use HasFactory;

    protected $table = 'alert_storm_suppression';
    public $timestamps = false;

    protected $fillable = [
        'fingerprint',
        'alert_count',
        'first_alert_at',
        'last_alert_at',
        'suppressed_count',
        'next_allowed_at',
    ];

    protected $casts = [
        'alert_count' => 'integer',
        'suppressed_count' => 'integer',
        'first_alert_at' => 'datetime',
        'last_alert_at' => 'datetime',
        'next_allowed_at' => 'datetime',
    ];

    /**
     * Compute a deterministic fingerprint for deduplication
     */
    public static function makeFingerprint(string $entityType, int $entityId, string $conditionType): string
    {
        return hash('sha256', "{$entityType}:{$entityId}:{$conditionType}");
    }

    /**
     * Check if an alert matching fingerprint is currently suppressed by storm control
     */
    public static function checkSuppressed(string $fingerprint, int $maxPerHour = 5, int $cooldownMinutes = 30): bool
    {
        $record = static::where('fingerprint', $fingerprint)->first();
        if (!$record) {
            return false;
        }

        $now = Carbon::now();

        // If next_allowed_at is in the future, it's suppressed
        if ($record->next_allowed_at && $now->lt($record->next_allowed_at)) {
            $record->increment('suppressed_count');
            return true;
        }

        return false;
    }
}
