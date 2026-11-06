<?php

declare(strict_types=1);

namespace App\Actions\Proxies;

use App\Contracts\Repositories\ProxyRepositoryInterface;
use App\Http\Controllers\Admin\SyncController;
use App\Models\MonitoredProxy;

readonly class ToggleProxyAction
{
    public function __construct(
        private ProxyRepositoryInterface $repository
    ) {}

    public function execute(int $id): MonitoredProxy
    {
        $proxy = $this->repository->toggleActive($id);
        app(SyncController::class)->exportToConfigFiles();
        return $proxy;
    }
}
