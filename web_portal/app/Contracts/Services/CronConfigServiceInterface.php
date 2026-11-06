<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface CronConfigServiceInterface
{
    /**
     * Obtiene la configuración estructurada de cron e informes automáticos desde MariaDB SSOT.
     *
     * @return array<string, mixed>
     */
    public function getCronConfig(): array;
}
