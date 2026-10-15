<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Contracts\Repositories\ServiceRepositoryInterface;
use App\Http\Controllers\Admin\SyncController;

readonly class DeleteServiceAction
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(int $id): bool
    {
        $result = $this->repository->delete($id);
        app(SyncController::class)->exportToConfigFiles();
        return $result;
    }
}
