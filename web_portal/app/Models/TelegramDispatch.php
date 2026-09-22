<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_type',
        'operator_name',
        'operator_ci',
        'operator_personal_number',
        'operator_phone',
        'status',
        'response_message',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getLatestDispatch(?string $type = null): ?self
    {
        $q = self::query()->where('status', 'success');
        if ($type) {
            $q->where('report_type', $type);
        }
        return $q->latest()->first();
    }
}
