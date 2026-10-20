<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class NetworkDeviceData
{
    public function __construct(
        public ?int $id,
        public int $monitoredSiteId,
        public ?int $deviceNumber = null,
        public string $name, // Nombre Etiqueta (Identidad Operativa Principal)
        public string $ip,
        public ?string $mac = null,
        public ?string $vendorData = null,
        public string $accessType = 'SIN SOPORTE',
        public ?int $accessPort = null,
        public ?int $sshPort = null,
        public ?int $vncPort = null,
        public ?int $webPort = null,
        public ?int $telnetPort = null,
        public ?string $sshUsername = null,
        public ?string $sshPassword = null,
        public ?string $sshEnableSecret = null,
        public ?string $vncPassword = null,
        public ?string $model = null,
        public ?string $serial = null,
        public ?string $ports = null,
        public ?string $notes = null,
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
            monitoredSiteId: (int) ($data['monitored_site_id'] ?? 0),
            deviceNumber: isset($data['device_number']) && $data['device_number'] !== '' ? (int) $data['device_number'] : null,
            name: (string) ($data['name'] ?? ''),
            ip: (string) ($data['ip'] ?? ''),
            mac: $data['mac'] ?? null,
            vendorData: $data['vendor_data'] ?? null,
            accessType: $data['access_type'] ?? 'SIN SOPORTE',
            accessPort: isset($data['access_port']) ? (int) $data['access_port'] : null,
            sshPort: isset($data['ssh_port']) ? (int) $data['ssh_port'] : null,
            vncPort: isset($data['vnc_port']) ? (int) $data['vnc_port'] : null,
            webPort: isset($data['web_port']) ? (int) $data['web_port'] : null,
            telnetPort: isset($data['telnet_port']) ? (int) $data['telnet_port'] : null,
            sshUsername: $data['ssh_username'] ?? null,
            sshPassword: $data['ssh_password'] ?? $data['ssh_password_encrypted'] ?? null,
            sshEnableSecret: $data['ssh_enable_secret'] ?? $data['ssh_enable_secret_encrypted'] ?? null,
            vncPassword: $data['vnc_password'] ?? $data['vnc_password_encrypted'] ?? null,
            model: $data['model'] ?? null,
            serial: $data['serial'] ?? null,
            ports: $data['ports'] ?? null,
            notes: $data['notes'] ?? null,
            normalStateMsg: $data['normal_state_msg'] ?? null,
            errorStateMsg: $data['error_state_msg'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            telegramAlertEnabled: (bool) ($data['telegram_alert_enabled'] ?? true),
            telegramAlertTarget: $data['telegram_alert_target'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'monitored_site_id' => $this->monitoredSiteId,
            'device_number' => $this->deviceNumber,
            'name' => $this->name,
            'ip' => $this->ip,
            'mac' => $this->mac,
            'vendor_data' => $this->vendorData,
            'access_type' => $this->accessType,
            'access_port' => $this->accessPort,
            'ssh_port' => $this->sshPort,
            'vnc_port' => $this->vncPort,
            'web_port' => $this->webPort,
            'telnet_port' => $this->telnetPort,
            'ssh_username' => $this->sshUsername,
            'model' => $this->model,
            'serial' => $this->serial,
            'ports' => $this->ports,
            'notes' => $this->notes,
            'normal_state_msg' => $this->normalStateMsg,
            'error_state_msg' => $this->errorStateMsg,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'telegram_alert_enabled' => $this->telegramAlertEnabled,
            'telegram_alert_target' => $this->telegramAlertTarget,
        ];
    }
}
