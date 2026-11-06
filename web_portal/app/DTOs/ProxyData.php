<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ProxyData
{
    public function __construct(
        public ?int $id,
        public string $letter,
        public string $name,
        public string $ipPort,
        public ?string $authUserpass = null,
        public ?string $testUrl = 'https://core.telegram.org/bots',
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            letter: strtoupper(trim((string) ($data['letter'] ?? ''))),
            name: trim((string) ($data['name'] ?? '')),
            ipPort: trim((string) ($data['ip_port'] ?? '')),
            authUserpass: isset($data['auth_userpass']) && $data['auth_userpass'] !== null ? trim((string) $data['auth_userpass']) : null,
            testUrl: !empty($data['test_url']) ? trim((string) $data['test_url']) : 'https://core.telegram.org/bots',
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'letter' => $this->letter,
            'name' => $this->name,
            'ip_port' => $this->ipPort,
            'auth_userpass' => $this->authUserpass,
            'test_url' => $this->testUrl,
            'is_active' => $this->isActive,
        ];
    }
}
