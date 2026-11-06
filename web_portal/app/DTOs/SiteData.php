<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SiteData
{
    public function __construct(
        public ?int $id,
        public string $letter,
        public string $name,
        public ?string $ip = null,
        public ?string $phone1 = null,
        public ?string $phone2 = null,
        public ?string $phone3 = null,
        public ?string $phone4 = null,
        public ?string $phone5 = null,
        public ?string $phone6 = null,
        public ?string $phone7 = null,
        public ?string $phone8 = null,
        public ?string $address = null,
        public ?string $normalStateMsg = null,
        public ?string $errorStateMsg = null,
        public bool $isActive = true,
        public int $sortOrder = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            letter: (string) ($data['letter'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            ip: $data['ip'] ?? null,
            phone1: $data['phone_1'] ?? null,
            phone2: $data['phone_2'] ?? null,
            phone3: $data['phone_3'] ?? null,
            phone4: $data['phone_4'] ?? null,
            phone5: $data['phone_5'] ?? null,
            phone6: $data['phone_6'] ?? null,
            phone7: $data['phone_7'] ?? null,
            phone8: $data['phone_8'] ?? null,
            address: $data['address'] ?? null,
            normalStateMsg: $data['normal_state_msg'] ?? null,
            errorStateMsg: $data['error_state_msg'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'letter' => $this->letter,
            'name' => $this->name,
            'ip' => $this->ip,
            'phone_1' => $this->phone1,
            'phone_2' => $this->phone2,
            'phone_3' => $this->phone3,
            'phone_4' => $this->phone4,
            'phone_5' => $this->phone5,
            'phone_6' => $this->phone6,
            'phone_7' => $this->phone7,
            'phone_8' => $this->phone8,
            'address' => $this->address,
            'normal_state_msg' => $this->normalStateMsg,
            'error_state_msg' => $this->errorStateMsg,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
        ];
    }
}
