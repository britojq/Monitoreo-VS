<?php

declare(strict_types=1);

namespace App\DTOs\Deploy;

readonly class PromoteAssetDto
{
    public function __construct(
        public string $ip,
        public ?string $mac,
        public string $name,
        public string $type,
        public int $monitoredSiteId,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $serial = null,
        public ?string $ports = null,
        public ?string $notes = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ip: trim((string) $data['ip']),
            mac: !empty($data['mac']) ? trim((string) $data['mac']) : null,
            name: trim((string) $data['name']),
            type: (string) $data['type'],
            monitoredSiteId: (int) $data['monitored_site_id'],
            brand: !empty($data['brand']) ? trim((string) $data['brand']) : null,
            model: !empty($data['model']) ? trim((string) $data['model']) : null,
            serial: !empty($data['serial']) ? trim((string) $data['serial']) : null,
            ports: !empty($data['ports']) ? trim((string) $data['ports']) : null,
            notes: !empty($data['notes']) ? trim((string) $data['notes']) : null
        );
    }
}
