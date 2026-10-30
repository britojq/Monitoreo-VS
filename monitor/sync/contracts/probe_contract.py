# monitor/sync/contracts/probe_contract.py
from typing import Protocol, runtime_checkable

@runtime_checkable
class NetworkProbeProtocol(Protocol):
    """Contrato base para sondas de disponibilidad y latencia de red."""
    async def probe(self, target: str, timeout: float) -> tuple[bool, float]:
        ...
