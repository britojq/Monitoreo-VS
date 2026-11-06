# monitor/sync/dtos/scan_dtos.py
from dataclasses import dataclass, field
from typing import Optional, List, Dict, Any

@dataclass(frozen=True)
class WanQualityResult:
    """Resultado de diagnóstico de calidad de enlace WAN."""
    is_up: bool
    latency_ms: float
    packet_loss_pct: float
    jitter_ms: float
    min_rtt_ms: float
    max_rtt_ms: float
    mdev_ms: float

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "is_up": self.is_up,
            "latency_ms": self.latency_ms,
            "packet_loss_pct": self.packet_loss_pct,
            "jitter_ms": self.jitter_ms,
            "min_rtt_ms": self.min_rtt_ms,
            "max_rtt_ms": self.max_rtt_ms,
            "mdev_ms": self.mdev_ms,
        }

@dataclass(frozen=True)
class ServiceEvaluationResult:
    """Resultado de evaluación de un servicio monitoreado."""
    id: int
    letter: str
    name: str
    type: str
    scope: str
    host_ip: str
    web_url: str
    status: str
    is_up: bool
    latency_ms: float
    http_code: Optional[int]

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "id": self.id,
            "letter": self.letter,
            "name": self.name,
            "type": self.type,
            "scope": self.scope,
            "host_ip": self.host_ip,
            "web_url": self.web_url,
            "status": self.status,
            "is_up": self.is_up,
            "latency_ms": self.latency_ms,
            "http_code": self.http_code,
        }

@dataclass(frozen=True)
class SiteDeviceEvaluationResult:
    """Resultado de evaluación de un dispositivo dentro de una sede."""
    id: int
    device_number: Optional[int]
    name: str
    ip: str
    access_type: str
    access_port: Optional[int]
    status: str
    is_up: bool
    latency_ms: float

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "id": self.id,
            "device_number": self.device_number,
            "name": self.name,
            "ip": self.ip,
            "access_type": self.access_type,
            "access_port": self.access_port,
            "status": self.status,
            "is_up": self.is_up,
            "latency_ms": self.latency_ms,
        }

@dataclass(frozen=True)
class SiteEvaluationResult:
    """Resultado de evaluación de una sede y sus enlaces."""
    id: int
    letter: str
    name: str
    ip: str
    address: str
    status: str
    is_up: bool
    latency_ms: float
    packet_loss_pct: float
    jitter_ms: float
    min_rtt_ms: float
    max_rtt_ms: float
    mdev_ms: float
    devices: List[Dict[str, Any]] = field(default_factory=list)

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "id": self.id,
            "letter": self.letter,
            "name": self.name,
            "ip": self.ip,
            "address": self.address,
            "status": self.status,
            "is_up": self.is_up,
            "latency_ms": self.latency_ms,
            "packet_loss_pct": self.packet_loss_pct,
            "jitter_ms": self.jitter_ms,
            "min_rtt_ms": self.min_rtt_ms,
            "max_rtt_ms": self.max_rtt_ms,
            "mdev_ms": self.mdev_ms,
            "devices": self.devices,
        }

@dataclass(frozen=True)
class ProxyEvaluationResult:
    """Resultado de evaluación de un proxy corporativo."""
    id: int
    letter: str
    name: str
    ip_port: str
    status: str
    is_up: bool
    latency_ms: float

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "id": self.id,
            "letter": self.letter,
            "name": self.name,
            "ip_port": self.ip_port,
            "status": self.status,
            "is_up": self.is_up,
            "latency_ms": self.latency_ms,
        }

@dataclass(frozen=True)
class NetworkDeviceEvaluationResult:
    """Resultado de evaluación de un equipo de red troncal."""
    id: int
    device_number: Optional[int]
    name: str
    ip: str
    mac: str
    vendor_data: str
    access_type: str
    access_port: Optional[int]
    normal_state_msg: str
    error_state_msg: str
    status: str
    is_up: bool
    latency_ms: float

    def to_dict(self) -> Dict[str, Any]:
        """Serializa los atributos del objeto a un diccionario de datos estructurado."""
        return {
            "id": self.id,
            "device_number": self.device_number,
            "name": self.name,
            "ip": self.ip,
            "mac": self.mac,
            "vendor_data": self.vendor_data,
            "access_type": self.access_type,
            "access_port": self.access_port,
            "normal_state_msg": self.normal_state_msg,
            "error_state_msg": self.error_state_msg,
            "status": self.status,
            "is_up": self.is_up,
            "latency_ms": self.latency_ms,
        }
