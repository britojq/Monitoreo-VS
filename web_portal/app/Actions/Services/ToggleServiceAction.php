<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Contracts\Repositories\ServiceRepositoryInterface;
use App\Http\Controllers\Admin\SyncController;
use App\Models\MonitoredService;

readonly class ToggleServiceAction
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(int $id): MonitoredService
    {
        $service = $this->repository->toggleActive($id);
        app(SyncController::class)->exportToConfigFiles();
        return $service;
    }
}
