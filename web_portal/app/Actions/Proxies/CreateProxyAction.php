<?php

declare(strict_types=1);

namespace App\Actions\Proxies;

use App\Contracts\Repositories\ProxyRepositoryInterface;
use App\DTOs\ProxyData;
use App\Http\Controllers\Admin\SyncController;
use App\Models\MonitoredProxy;

readonly class CreateProxyAction
{
    public function __construct(
        private ProxyRepositoryInterface $repository
    ) {}

    public function execute(ProxyData $data): MonitoredProxy
    {
        $proxy = $this->repository->create($data);
        app(SyncController::class)->exportToConfigFiles();
        return $proxy;
    }
}
