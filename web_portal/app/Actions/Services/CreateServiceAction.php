<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Contracts\Repositories\ServiceRepositoryInterface;
use App\DTOs\ServiceData;
use App\Http\Controllers\Admin\SyncController;
use App\Models\MonitoredService;

readonly class CreateServiceAction
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(ServiceData $data): MonitoredService
    {
        $service = $this->repository->create($data);
        app(SyncController::class)->exportToConfigFiles();
        return $service;
    }
}
