<?php

declare(strict_types=1);

namespace App\Actions\Proxies;

use App\Contracts\Repositories\ProxyRepositoryInterface;
use App\Http\Controllers\Admin\SyncController;

readonly class DeleteProxyAction
{
    public function __construct(
        private ProxyRepositoryInterface $repository
    ) {}

    public function execute(int $id): bool
    {
        $result = $this->repository->delete($id);
        app(SyncController::class)->exportToConfigFiles();
        return $result;
    }
}
