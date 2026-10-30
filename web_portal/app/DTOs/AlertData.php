<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class AlertData
{
    public function __construct(
        public ?int $id,
        public ?int $alertRuleId,
        public string $entityType,
        public int $entityId,
        public string $entityName,
        public string $conditionType,
        public string $severity,
        public string $status,
        public int $currentEscalationLevel = 1,
        public ?float $valueAtTrigger = null,
        public ?float $thresholdValue = null,
        public ?string $message = null,
        public ?int $correlationGroupId = null,
        public bool $isCorrelatedSuppressed = false,
        public ?int $parentAlertId = null,
        public ?string $firedAt = null,
        public ?string $acknowledgedAt = null,
        public ?string $resolvedAt = null,
        public ?string $acknowledgedBy = null,
        public ?string $resolvedBy = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            alertRuleId: isset($data['alert_rule_id']) ? (int) $data['alert_rule_id'] : null,
            entityType: (string) ($data['entity_type'] ?? 'service'),
            entityId: (int) ($data['entity_id'] ?? 0),
            entityName: (string) ($data['entity_name'] ?? ''),
            conditionType: (string) ($data['condition_type'] ?? 'status'),
            severity: (string) ($data['severity'] ?? 'warning'),
            status: (string) ($data['status'] ?? 'active'),
            currentEscalationLevel: (int) ($data['current_escalation_level'] ?? 1),
            valueAtTrigger: isset($data['value_at_trigger']) ? (float) $data['value_at_trigger'] : null,
            thresholdValue: isset($data['threshold_value']) ? (float) $data['threshold_value'] : null,
            message: $data['message'] ?? null,
            correlationGroupId: isset($data['correlation_group_id']) ? (int) $data['correlation_group_id'] : null,
            isCorrelatedSuppressed: (bool) ($data['is_correlated_suppressed'] ?? false),
            parentAlertId: isset($data['parent_alert_id']) ? (int) $data['parent_alert_id'] : null,
            firedAt: $data['fired_at'] ?? null,
            acknowledgedAt: $data['acknowledged_at'] ?? null,
            resolvedAt: $data['resolved_at'] ?? null,
            acknowledgedBy: $data['acknowledged_by'] ?? null,
            resolvedBy: $data['resolved_by'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'alert_rule_id' => $this->alertRuleId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'entity_name' => $this->entityName,
            'condition_type' => $this->conditionType,
            'severity' => $this->severity,
            'status' => $this->status,
            'current_escalation_level' => $this->currentEscalationLevel,
            'value_at_trigger' => $this->valueAtTrigger,
            'threshold_value' => $this->thresholdValue,
            'message' => $this->message,
            'correlation_group_id' => $this->correlationGroupId,
            'is_correlated_suppressed' => $this->isCorrelatedSuppressed,
            'parent_alert_id' => $this->parentAlertId,
            'fired_at' => $this->firedAt,
            'acknowledged_at' => $this->acknowledgedAt,
            'resolved_at' => $this->resolvedAt,
            'acknowledged_by' => $this->acknowledgedBy,
            'resolved_by' => $this->resolvedBy,
            'notes' => $this->notes,
        ];
    }
}
