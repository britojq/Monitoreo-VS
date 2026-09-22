<?php

namespace App\Traits;

use App\Services\AuditService;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            try {
                AuditService::logModelCreated($model);
            } catch (\Throwable $e) {
                \Log::warning("Error registrando auditoría de creación en " . get_class($model) . ": " . $e->getMessage());
            }
        });

        static::updating(function ($model) {
            try {
                AuditService::logModelUpdated($model);
            } catch (\Throwable $e) {
                \Log::warning("Error registrando auditoría de actualización en " . get_class($model) . ": " . $e->getMessage());
            }
        });

        static::deleted(function ($model) {
            try {
                AuditService::logModelDeleted($model);
            } catch (\Throwable $e) {
                \Log::warning("Error registrando auditoría de eliminación en " . get_class($model) . ": " . $e->getMessage());
            }
        });
    }
}
