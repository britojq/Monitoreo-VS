<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkAnalysisReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'interface',
        'duration_seconds',
        'total_packets',
        'local_hosts_count',
        'external_hosts_count',
        'suspicious_packets',
        'summary_text',
        'report_markdown',
        'report_data_json',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'total_packets' => 'integer',
        'local_hosts_count' => 'integer',
        'external_hosts_count' => 'integer',
        'suspicious_packets' => 'integer',
        'report_data_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getReportTypeLabelAttribute(): string
    {
        return match($this->report_type) {
            'net_radar' => 'NET Radar & Flujos',
            'network_analyzer' => 'Análisis Profundo LAN',
            default => strtoupper($this->report_type),
        };
    }
}
