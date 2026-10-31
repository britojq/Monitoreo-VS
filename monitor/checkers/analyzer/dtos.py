"""
DTOs para el análisis y diagnóstico avanzado de red.
"""
from dataclasses import dataclass, field
from typing import List


@dataclass
class NetworkHost:
    ip: str
    hostname: str = "desconocido"
    mac: str = "desconocida"
    vendor: str = ""
    rx: int = 0
    tx: int = 0
    total_traffic: int = 0
    latency_ms: float = 0.0
    is_local: bool = True
    has_mac: bool = False
    is_authorized: bool = True
    issues: List[str] = field(default_factory=list)
