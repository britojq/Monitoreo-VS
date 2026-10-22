<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ServiceData
{
    public function __construct(
        public ?int $id,
        public string $letter,
        public string $name,
        public string $type,
        public string $scope,
        public ?string $hostIp = null,
        public ?string $webUrl = null,
        public ?int $port = null,
        public ?string $checkInterface = null,
        public ?string $dnsTestDomain = null,
        public ?string $normalStateMsg = null,
        public ?string $errorStateMsg = null,
        public bool $isActive = true,
        public int $sortOrder = 0,
        public bool $telegramAlertEnabled = true,
        public ?string $telegramAlertTarget = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            letter: (string) ($data['letter'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? 'ping'),
            scope: (string) ($data['scope'] ?? 'lan'),
            hostIp: $data['host_ip'] ?? null,
            webUrl: $data['web_url'] ?? null,
            port: isset($data['port']) ? (int) $data['port'] : null,
            checkInterface: $data['check_interface'] ?? null,
            dnsTestDomain: $data['dns_test_domain'] ?? null,
            normalStateMsg: $data['normal_state_msg'] ?? null,
            errorStateMsg: $data['error_state_msg'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            telegramAlertEnabled: (bool) ($data['telegram_alert_enabled'] ?? false),
            telegramAlertTarget: $data['telegram_alert_target'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'letter' => $this->letter,
            'name' => $this->name,
            'type' => $this->type,
            'scope' => $this->scope,
            'host_ip' => $this->hostIp,
            'web_url' => $this->webUrl,
            'port' => $this->port,
            'check_interface' => $this->checkInterface,
            'dns_test_domain' => $this->dnsTestDomain,
            'normal_state_msg' => $this->normalStateMsg,
            'error_state_msg' => $this->errorStateMsg,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'telegram_alert_enabled' => $this->telegramAlertEnabled,
            'telegram_alert_target' => $this->telegramAlertTarget,
        ];
    }
}
