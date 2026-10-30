# monitor/alerts/dtos/alert_dtos.py
from dataclasses import dataclass
from typing import Optional, Dict, Any

@dataclass(frozen=True)
class CorrelationResultDto:
    """Resultado inmutable del análisis de correlación topológica."""
    is_suppressed: bool
    group_id: Optional[int]
    parent_alert_id: Optional[int]
    final_severity: str

    def to_tuple(self) -> tuple[bool, Optional[int], Optional[int], str]:
        return (self.is_suppressed, self.group_id, self.parent_alert_id, self.final_severity)

@dataclass(frozen=True)
class AlertRuleDto:
    """Representación inmutable de una regla de alerta corporativa."""
    id: int
    name: str
    entity_type: str
    condition_type: str
    severity: str
    threshold_value: Optional[float]
    comparison: Optional[str]
    duration_seconds: int = 0
    cooldown_minutes: int = 30
    max_alerts_per_hour: int = 5
    auto_resolve: bool = True

@dataclass(frozen=True)
class ActiveAlertDto:
    """Representación inmutable de una alerta activa en el sistema."""
    id: int
    severity: str
    status: str
    entity_name: str
    condition_type: str
    current_escalation_level: int
    fired_at: Any
