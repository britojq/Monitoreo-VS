"""
# ==============================================================================
# 📦 PAQUETE DE PROCESAMIENTO MODULAR DE ACTAS ITAM: __init__.py
# Ubicación: monitor/actas/__init__.py
# License: GNU AGPLv3
# ==============================================================================
"""

from __future__ import annotations

from monitor.actas.itam_persistence import ActaItamPersistence
from monitor.actas.models import (
    AssetData,
    CustodianData,
    PageActaConfig,
    PeripheralData,
)
from monitor.actas.pdf_extractor import ActaPdfExtractor
from monitor.actas.qr_decoder import ActaQrDecoder
from monitor.actas.registry import PAGES_REGISTRY

__all__ = [
    "ActaPdfExtractor",
    "ActaQrDecoder",
    "ActaItamPersistence",
    "CustodianData",
    "PeripheralData",
    "AssetData",
    "PageActaConfig",
    "PAGES_REGISTRY",
]
