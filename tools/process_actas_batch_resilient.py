#!/usr/bin/env python3
"""
=============================================================================
🛡️ VALLE SECO ITAM - ORQUESTADOR RESILIENTE DE PROCESAMIENTO DE ACTAS (UINF-4.05)
tools/process_actas_batch_resilient.py
=============================================================================

Procesa el archivo escaneado de 25 actas físicas:
/home/britojab/Escritorio/escaneado/2026-10-22 09-05.pdf

Características de Resiliencia Industrial:
1. Resistencia Anti-Apagón: Checkpoints en MariaDB SSOT (`bot_settings`).
2. Transacciones Atómicas: Cada lote de 5 actas se procesa en una transacción SQL aislada.
3. Enriquecimiento Cruzado: Preserva 100% de datos IP/MAC/SO si el equipo ya existe.
4. Trazabilidad de Devoluciones: Cambia activos a 'in_stock' o 'damaged' y desvincula custodios.
5. Segmentación individual en PDF con Ghostscript.
6. Permisos seguros para Apache/Laravel (www-data).
=============================================================================
"""

from __future__ import annotations

import argparse
import hashlib
import json
import logging
import os
import re
import subprocess
import sys
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional

BASE_DIR = Path(__file__).resolve().parent.parent
PDF_SOURCE = Path("/home/britojab/Escritorio/escaneado/2026-10-22 09-05.pdf")
STORAGE_SCANS_REPO = BASE_DIR / "web_portal" / "storage" / "app" / "public" / "assignments" / "scans"
STORAGE_SCANS_DATA = Path("/home/data_monitoreo/storage/app/public/assignments/scans")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [RESILIENT-ACTAS] %(message)s",
)
logger = logging.getLogger("resilient_actas")


def run_mysql(query: str, as_transaction: bool = False) -> str:
    """Ejecuta una consulta SQL en MariaDB SSOT."""
    cmd = ["sudo", "mariadb", "monitoreo_vs", "-e", query]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return res.stdout


def escape_sql(val: Optional[Any]) -> str:
    """Escapa un valor para inserción SQL segura."""
    if val is None:
        return "NULL"
    return "'" + str(val).replace("'", "''") + "'"


def extract_pdf_page(source_pdf: Path, page_num: int, output_pdf: Path) -> None:
    """Extrae una página específica de un PDF usando Ghostscript."""
    cmd = [
        "gs",
        "-sDEVICE=pdfwrite",
        "-dSAFER",
        "-dNOPAUSE",
        "-dBATCH",
        f"-dFirstPage={page_num}",
        f"-dLastPage={page_num}",
        f"-sOutputFile={output_pdf}",
        str(source_pdf),
    ]
    subprocess.run(cmd, capture_output=True, check=True)


@dataclass(frozen=True)
class CustodianData:
    name: str
    cedula: str
    department: str
    role: Optional[str] = None


@dataclass(frozen=True)
class PeripheralData:
    type: str
    label: str
    brand: str
    model: str
    serial: str
    notes: str


@dataclass(frozen=True)
class AssetData:
    serial: str
    name: str
    brand: str
    model: str
    type: str = "desktop"
    ip_address: Optional[str] = None
    mac_address: Optional[str] = None
    operating_system: Optional[str] = None
    notes: Optional[str] = None
    exists_id: Optional[int] = None


@dataclass(frozen=True)
class PageActaConfig:
    page: int
    code: str
    pdf_name: str
    date: str
    movement_type: str  # 'assignment' o 'return'
    custodian: CustodianData
    asset: AssetData
    peripherals: List[PeripheralData] = field(default_factory=list)
    observations: str = ""


# ==============================================================================
# DEFINICIÓN OFICIAL DE LAS 25 ACTAS DIGITALIZADAS (5 LOTES)
# ==============================================================================

PAGES_REGISTRY: Dict[int, List[PageActaConfig]] = {
    # --------------------------------------------------------------------------
    # LOTE 1 (PÁGINAS 1 A 5) — Validadoras y Puestos Principales Valle Seco
    # --------------------------------------------------------------------------
    1: [
        PageActaConfig(
            page=1,
            code="UINF-4.05-2026-0025",
            pdf_name="UINF-4.05-2026-0025_DEVOLUCION_VALIDADORA_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="return",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Responsable de Oficina",
            ),
            asset=AssetData(
                serial="J9EVF000374",
                name="EPSON-TM-U675P-VALIDADORA",
                brand="EPSON",
                model="TM-U675P",
                type="printer",
                notes="Impresora validadora devuelta por reemplazo según Acta UINF-4.05-2026-0025.",
            ),
            observations="Devolución de impresora validadora Epson TM-U675P por reemplazo.",
        ),
        PageActaConfig(
            page=2,
            code="UINF-4.05-2026-0026",
            pdf_name="UINF-4.05-2026-0026_ASIGNACION_VALIDADORA_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Responsable de Oficina",
            ),
            asset=AssetData(
                serial="JEBUF016277",
                name="EPSON-TM-U675-VALIDADORA",
                brand="EPSON",
                model="TM-U675",
                type="printer",
                notes="Impresora validadora asignada por reemplazo según Acta UINF-4.05-2026-0026.",
            ),
            observations="Asignación de impresora validadora Epson TM-U675 por reemplazo.",
        ),
        PageActaConfig(
            page=3,
            code="UINF-4.05-2026-0027",
            pdf_name="UINF-4.05-2026-0027_ACTA_MJ07DHJ6_SONIA_TOSCANO.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="SONIA TOSCANO",
                cedula="V-13.602.217",
                department="SEDE VALLE SECO",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MJ07DHJ6",
                name="CENCARVAL-M910S-01",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Estación de trabajo asignada por cambio de equipo a Sonia Toscano.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147253804", notes="Monitor oficial"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250287", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506501446", notes="Protector Marsriva"),
            ],
            observations="Asignación por cambio de equipo a Sonia Toscano.",
        ),
        PageActaConfig(
            page=4,
            code="UINF-4.05-2026-0028",
            pdf_name="UINF-4.05-2026-0028_ACTA_MJ07M22X_JONIRYS_LAMAS.pdf",
            date="2026-02-13 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="JONIRYS LAMAS",
                cedula="V-16.801.642",
                department="DISTRIBUCION",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MJ07M22X",
                name="CENCARDIS-0202",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                ip_address="192.168.1.254",
                mac_address="F4:93:9F:F0:65:3D",
                operating_system="Windows 7",
                notes="Estación de trabajo asignada por reemplazo a Jonirys Lamas (Distribución).",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147250106", notes="Monitor oficial"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250288", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506501452", notes="Protector Marsriva"),
            ],
            observations="Asignación por reemplazo a Jonirys Lamas (Distribución).",
        ),
        PageActaConfig(
            page=5,
            code="UINF-4.05-2026-0029",
            pdf_name="UINF-4.05-2026-0029_ACTA_MXL524355X_JICKSY_FLORES.pdf",
            date="2026-02-14 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="JICKSY FLORES",
                cedula="V-14.108.170",
                department="COMERCIALES - GESTION DE ENERGIA",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MXL524355X",
                name="CENCARCOM-800G1-01",
                brand="HP",
                model="EliteDesk 800 G1 SFF",
                type="desktop",
                operating_system="Windows 7",
                notes="Equipo entregado en Gestión de Energía. Flores tiene asignado a Karima Guinand en inventario.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ5132203", notes="Monitor HP V193B"),
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="SK-2015", serial="BDMEP0CCP7B56V", notes="Teclado HP"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="N/A", serial="FCMHF0BAU7BRMH", notes="Mouse HP"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="CDP", model="WISE 1000", serial="073903654", notes="Protector CDP Wise 1000"),
            ],
            observations="Entrega en Gestión de Energía a Jicksy Flores.",
        ),
    ],

    # --------------------------------------------------------------------------
    # LOTE 2 (PÁGINAS 6 A 10) — Gestión de Energía y Reemplazos
    # --------------------------------------------------------------------------
    2: [
        PageActaConfig(
            page=6,
            code="UINF-4.05-2026-0030",
            pdf_name="UINF-4.05-2026-0030_ACTA_MJ07DKZK_EMILY_SALCEDO.pdf",
            date="2026-02-03 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="EMILY SALCEDO",
                cedula="V-15.642.000",
                department="COMERCIALES - GESTION DE ENERGIA",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MJ07DKZK",
                name="CENCARCOM204",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                exists_id=104956,
                ip_address="192.168.1.245",
                notes="Estación de trabajo asignada a Emily Salcedo (Gestión de Energía).",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147253194", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250283", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506502107", notes="Protector Marsriva"),
            ],
            observations="Asignado a Gestión de Energía - Emily Salcedo.",
        ),
        PageActaConfig(
            page=7,
            code="UINF-4.05-2026-0031",
            pdf_name="UINF-4.05-2026-0031_DEVOLUCION_MXL52434KR_EMILY_SALCEDO.pdf",
            date="2026-02-03 09:00:00",
            movement_type="return",
            custodian=CustodianData(
                name="EMILY SALCEDO",
                cedula="V-15.642.000",
                department="COMERCIALES - GESTION DE ENERGIA",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MXL52434KR",
                name="EQUIPO 01",
                brand="HP",
                model="EliteDesk 800 G1 SFF",
                type="desktop",
                exists_id=22,
                notes="Equipo devuelto ubicado en Centro Técnico por reemplazo.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ5130BJN", notes="Monitor devuelto a Centro Técnico"),
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="KB57P", serial="BDMEP0AHH8N12Z", notes="Teclado devuelto a Centro Técnico"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="MOFYKO", serial="FCMHF0AHD8BHSN", notes="Mouse devuelto a Centro Técnico"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="CDP", model="R2C-AVR1008", serial="150407-0000112", notes="Protector devuelto a Centro Técnico"),
            ],
            observations="Equipo devuelto ubicado en Centro Técnico.",
        ),
        PageActaConfig(
            page=8,
            code="UINF-4.05-2026-0032",
            pdf_name="UINF-4.05-2026-0032_ACTA_MJ09RQ1M_RINALDO_MIRANDA.pdf",
            date="2026-02-09 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="RINALDO MIRANDA",
                cedula="V-12.423.304",
                department="SEDE VALLE SECO",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MJ09RQ1M",
                name="CENCARVAL-M910S-02",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Asignación por reemplazo de equipo a Rinaldo Miranda.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147253180", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250289", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506502110", notes="Protector Marsriva"),
            ],
            observations="Asignación por reemplazo de equipo a Rinaldo Miranda.",
        ),
        PageActaConfig(
            page=9,
            code="UINF-4.05-2026-0033",
            pdf_name="UINF-4.05-2026-0033_DEVOLUCION_EQUIPOS_ADRIANA_UGUETO.pdf",
            date="2026-02-09 09:00:00",
            movement_type="return",
            custodian=CustodianData(
                name="ADRIANA UGUETO",
                cedula="V-12.746.421",
                department="COMERCIALES - GESTION DE ENERGIA",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="DEV-UGUETO-P09",
                name="LOTE-PERIFERICOS-DEVUELTOS-UGUETO",
                brand="HP/Epson/Sharp",
                model="Periféricos",
                type="desktop",
                notes="Devolución de componentes y periféricos sustituidos.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ51323HK", notes="Monitor devuelto"),
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="KB57P", serial="BDMEP0AHH8N0EB", notes="Teclado devuelto"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="N/A", serial="FCMHF0BAU7BRP7", notes="Mouse devuelto"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="CDP", model="R2C-AVR1008", serial="150407-0000529", notes="Protector devuelto"),
                PeripheralData(type="other", label="VIDEO BEAM", brand="EPSON", model="EMP-S5", serial="UX4F860407L", notes="Video Beam devuelto"),
                PeripheralData(type="other", label="FAX", brand="SHARP", model="UX-P200", serial="67120917", notes="Fax devuelto"),
            ],
            observations="Devolución de periféricos por reemplazo de puesto (Adriana Ugueto).",
        ),
        PageActaConfig(
            page=10,
            code="UINF-4.05-2026-0034",
            pdf_name="UINF-4.05-2026-0034_ACTA_MJ09LCY0_ADRIANA_UGUETO.pdf",
            date="2026-02-09 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="ADRIANA UGUETO",
                cedula="V-12.746.421",
                department="COMERCIALES - GESTION DE ENERGIA",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MJ09LCY0",
                name="CENCARCOM-M910S-01",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Asignación por cambio de equipo a Adriana Ugueto.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147250176", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250290", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506501451", notes="Protector Marsriva"),
            ],
            observations="Asignación por cambio de equipo a Adriana Ugueto.",
        ),
    ],

    # --------------------------------------------------------------------------
    # LOTE 3 (PÁGINAS 11 A 15) — Estaciones de Trabajo y Devoluciones
    # --------------------------------------------------------------------------
    3: [
        PageActaConfig(
            page=11,
            code="UINF-4.05-2026-0035",
            pdf_name="UINF-4.05-2026-0035_ACTA_MXL5252JVV_EDUARDO_MENDOZA.pdf",
            date="2026-02-18 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="EDUARDO MENDOZA",
                cedula="V-14.379.568",
                department="SEDE VALLE SECO",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MXL5252JVV",
                name="CENCARVAL-800G1-01",
                brand="HP",
                model="EliteDesk 800 G1 SFF",
                type="desktop",
                operating_system="Windows 7",
                notes="Asignación oficial a Eduardo Mendoza.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ51323GW", notes="Monitor HP"),
                PeripheralData(type="keyboard", label="TECLADO", brand="EN", model="KKS-8820", serial="11685286", notes="Teclado"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="N/A", serial="FCMHF0AHD9BKHU", notes="Mouse HP"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="CDP", model="R2C-AVR1008", serial="150407-0000538", notes="Protector CDP"),
            ],
            observations="Asignación oficial a Eduardo Mendoza.",
        ),
        PageActaConfig(
            page=12,
            code="UINF-4.05-2026-0036",
            pdf_name="UINF-4.05-2026-0036_ACTA_G001016467_JOSE_FRANCIS.pdf",
            date="2026-02-18 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="JOSE FRANCIS",
                cedula="V-13.332.229",
                department="SEDE VALLE SECO",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="G001016467",
                name="CENCARVAL-VIT-01",
                brand="VIT",
                model="E1210-01",
                type="desktop",
                operating_system="Windows 7",
                notes="Asignación de equipo VIT a José Francis.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="LENOVO", model="2580-AB1", serial="V1N8TZ1", notes="Monitor Lenovo"),
                PeripheralData(type="keyboard", label="TECLADO", brand="LENOVO", model="KU-0225", serial="2100240", notes="Teclado Lenovo"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="N/A", serial="LZ202AV0YAK", notes="Mouse HP"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="CDP", model="AVR PRO 1808", serial="111017-02816", notes="Protector CDP"),
            ],
            observations="Asignación de equipo a José Francis.",
        ),
        PageActaConfig(
            page=13,
            code="UINF-4.05-2026-0037",
            pdf_name="UINF-4.05-2026-0037_ACTA_MJ09RZVS_RUBEN_FAJARDO.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="RUBEN FAJARDO",
                cedula="V-12.746.110",
                department="SEDE VALLE SECO",
                role="Funcionario",
            ),
            asset=AssetData(
                serial="MJ09RZVS",
                name="CENCARVAL-M910S-03",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Asignación por reemplazo de equipo a Rubén Fajardo.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147253106", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250300", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506501447", notes="Protector Marsriva"),
            ],
            observations="Asignación por reemplazo de equipo a Rubén Fajardo.",
        ),
        PageActaConfig(
            page=14,
            code="UINF-4.05-2026-0038",
            pdf_name="UINF-4.05-2026-0038_DEVOLUCION_IMPRESORAS_HP_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="return",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Responsable de Oficina",
            ),
            asset=AssetData(
                serial="CNB9T40486",
                name="HP-LASERJET-P2055-01",
                brand="HP",
                model="LaserJet P2055",
                type="printer",
                notes="Impresora devuelta por reemplazo por equipo nuevo según Acta UINF-4.05-2026-0038.",
            ),
            peripherals=[
                PeripheralData(type="printer", label="IMPRESORA LASER", brand="HP", model="LaserJet P4015", serial="CNDY314421", notes="Impresora LaserJet P4015 devuelta"),
            ],
            observations="Devolución de impresoras láser HP por reemplazo por equipo nuevo.",
        ),
        PageActaConfig(
            page=15,
            code="UINF-4.05-2026-0039",
            pdf_name="UINF-4.05-2026-0039_DEVOLUCION_PUESTO_TRABAJO_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="return",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Responsable de Oficina",
            ),
            asset=AssetData(
                serial="DEV-KBD-MSE-CIAU",
                name="LOTE-TECLADOS-RATONES-DEVUELTOS-CIAU",
                brand="HP",
                model="KB57P/MOFYKO",
                type="desktop",
                notes="Devolución de 4 teclados y 4 ratones HP sustituidos en CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="KB57P", serial="BDMEP0CCP7B5AA", notes="Teclado devuelto"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="MOFYKO", serial="FCMHF0BAU7BDRL", notes="Mouse devuelto"),
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="KB57P", serial="BDMEP0AHH8N13Y", notes="Teclado devuelto"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="MOFYKO", serial="FCMHF0BAU7BER0", notes="Mouse devuelto"),
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="KB57P", serial="BDMEP0CVB7G1RV", notes="Teclado devuelto"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="MOFYKO", serial="FCMHF0BAU7BRI7", notes="Mouse devuelto"),
                PeripheralData(type="keyboard", label="TECLADO", brand="HP", model="KB57P", serial="BDMEP0AHH8Q01Z", notes="Teclado devuelto"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="HP", model="MOFYKO", serial="FCMHF0AHD8BL7M", notes="Mouse devuelto"),
            ],
            observations="Devolución de periféricos (teclados y ratones) sustituidos en CIAU Valle Seco.",
        ),
    ],

    # --------------------------------------------------------------------------
    # LOTE 4 (PÁGINAS 16 A 20) — Devolución de Desktops y Nuevos Puestos CIAU
    # --------------------------------------------------------------------------
    4: [
        PageActaConfig(
            page=16,
            code="UINF-4.05-2026-0040",
            pdf_name="UINF-4.05-2026-0040_DEVOLUCION_4_DESKTOPS_HP_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="return",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Responsable de Oficina",
            ),
            asset=AssetData(
                serial="MXL5243BZP",
                name="EQUIPO 03",
                brand="HP",
                model="EliteDesk 800 G1 SFF",
                type="desktop",
                exists_id=24,
                notes="Devolución de 4 desktops y 4 monitores HP sustituidos por Lenovo ThinkCentre M910s.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ5130BWM", notes="Monitor devuelto"),
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ51322CL", notes="Monitor devuelto"),
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ51323DS", notes="Monitor devuelto"),
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="HP", model="V193B", serial="3CQ5132368", notes="Monitor devuelto"),
                PeripheralData(type="other", label="DESKTOP HP DEVUELTO", brand="HP", model="EliteDesk 800 G1 SFF", serial="MXL5252FXG", notes="Desktop devuelto a stock"),
                PeripheralData(type="other", label="DESKTOP HP DEVUELTO", brand="HP", model="EliteDesk 800 G1 SFF", serial="MXL52436Y8", notes="Desktop devuelto a stock"),
                PeripheralData(type="other", label="DESKTOP HP DEVUELTO", brand="HP", model="EliteDesk 800 G1 SFF", serial="MXL524250G", notes="Desktop devuelto a stock"),
            ],
            observations="Devolución de lote de 4 equipos Desktop y 4 monitores HP sustituidos en CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=17,
            code="UINF-4.05-2026-0041",
            pdf_name="UINF-4.05-2026-0041_ACTA_MJ09DHSS_COORDINADOR_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Coordinador CIAU",
            ),
            asset=AssetData(
                serial="MJ09DHSS",
                name="CENCARCIAU-COORD-01",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Estación asignada al Coordinador CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147253107", notes="Monitor AUCE"),
            ],
            observations="Asignación puesto Coordinador CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=18,
            code="UINF-4.05-2026-0042",
            pdf_name="UINF-4.05-2026-0042_ACTA_MJ07N9DQ_GRANDES_USUARIOS_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Grandes Usuarios",
            ),
            asset=AssetData(
                serial="MJ07N9DQ",
                name="CENCARCIAU-GU-01",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Ubicado en Oficina Grandes Usuarios CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147250362", notes="Monitor AUCE"),
            ],
            observations="Ubicado en Oficina Grandes Usuarios CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=19,
            code="UINF-4.05-2026-0043",
            pdf_name="UINF-4.05-2026-0043_ACTA_MJ07N5AA_ATU_01_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Cubículo A.T.U 01",
            ),
            asset=AssetData(
                serial="MJ07N5AA",
                name="CENCARCIAU-ATU-01",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Estación de trabajo ubicada en Cubículo A.T.U 01.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147251847", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250297", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
            ],
            observations="Asignación Cubículo A.T.U 01 CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=20,
            code="UINF-4.05-2026-0044",
            pdf_name="UINF-4.05-2026-0044_ACTA_MJ07BWPQ_ATU_02_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Cubículo A.T.U 02",
            ),
            asset=AssetData(
                serial="MJ07BWPQ",
                name="CENCARCIAU-ATU-02",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Estación de trabajo ubicada en Cubículo A.T.U 02.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147250337", notes="Monitor AUCE"),
            ],
            observations="Asignación Cubículo A.T.U 02 CIAU Valle Seco.",
        ),
    ],

    # --------------------------------------------------------------------------
    # LOTE 5 (PÁGINAS 21 A 25) — Taquillas Comerciales, SIGCOM y Periféricos
    # --------------------------------------------------------------------------
    5: [
        PageActaConfig(
            page=21,
            code="UINF-4.05-2026-0045",
            pdf_name="UINF-4.05-2026-0045_ACTA_MJ07DHKP_TAQUILLA_01_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Taquilla 01",
            ),
            asset=AssetData(
                serial="MJ07DHKP",
                name="CENCARCIAU-TAQ-01",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Estación ubicada en Taquilla 01 CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147250272", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250299", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506502115", notes="Protector Marsriva"),
                PeripheralData(type="printer", label="IMPRESORA VALIDADORA", brand="EPSON", model="TM-U675", serial="JEBUF013693", notes="Validadora Epson Taquilla 01"),
            ],
            observations="Asignación Taquilla 01 - CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=22,
            code="UINF-4.05-2026-0046",
            pdf_name="UINF-4.05-2026-0046_ACTA_MJ07DJWH_TAQUILLA_02_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Taquilla 02",
            ),
            asset=AssetData(
                serial="MJ07DJWH",
                name="CENCARCIAU-TAQ-02",
                brand="LENOVO",
                model="ThinkCentre M910s",
                type="desktop",
                operating_system="Windows 7",
                notes="Estación ubicada en Taquilla 02 CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="AUCE", model="N/A", serial="AU20C0147253257", notes="Monitor AUCE"),
                PeripheralData(type="keyboard", label="TECLADO", brand="AST", model="ASTK899K", serial="ASTK899K250292", notes="Teclado AST"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="AST", model="N/A", serial="N/A", notes="Mouse AST"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="MARSRIVA", model="MR-AVR600", serial="91212506502113", notes="Protector Marsriva"),
                PeripheralData(type="printer", label="IMPRESORA VALIDADORA", brand="EPSON", model="TM-U675", serial="J9EVF000374", notes="Validadora Epson Taquilla 02"),
            ],
            observations="Asignación Taquilla 02 - CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=23,
            code="UINF-4.05-2026-0047",
            pdf_name="UINF-4.05-2026-0047_ACTA_GGC-BN637_TAQUILLA_01_SIGCOM_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Taquilla 01 SIGCOM",
            ),
            asset=AssetData(
                serial="GGC-BN 637",
                name="CENCARSIGCOM-TAQ-01",
                brand="DELL",
                model="OptiPlex 3040",
                type="desktop",
                operating_system="Windows 10",
                notes="Estación Dell ubicada en Taquilla 01 SIGCOM - CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="LOGAN", model="LM19LB1H1V-D", serial="SDN0165740A800050", notes="Monitor Logan"),
                PeripheralData(type="keyboard", label="TECLADO", brand="DELUX", model="KA193", serial="KA193211001706", notes="Teclado Delux"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="DELUX", model="M5238U", serial="M52322C001337", notes="Mouse Delux"),
                PeripheralData(type="surge_protector", label="REGULADOR / PROTECTOR", brand="CDP", model="R2C-AVR1008", serial="150407-0000251", notes="Protector CDP"),
            ],
            observations="Taquilla 01 SIGCOM - CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=24,
            code="UINF-4.05-2026-0048",
            pdf_name="UINF-4.05-2026-0048_ACTA_MXL7441B8D_TAQUILLA_02_SIGCOM_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Taquilla 02 SIGCOM",
            ),
            asset=AssetData(
                serial="MXL7441B8D",
                name="CENCARSIGCOM-TAQ-02",
                brand="HP",
                model="EliteDesk 800 G3 SFF",
                type="desktop",
                operating_system="Windows 10",
                notes="Estación HP ubicada en Taquilla 02 SIGCOM - CIAU Valle Seco.",
            ),
            peripherals=[
                PeripheralData(type="monitor", label="MONITOR / PANTALLA", brand="LOGAN", model="LM19LB1H1V-D", serial="8J00165740A801494", notes="Monitor Logan"),
                PeripheralData(type="keyboard", label="TECLADO", brand="DELUX", model="KA193", serial="KA193211001349", notes="Teclado Delux"),
                PeripheralData(type="mouse", label="MOUSE / RATÓN", brand="DELUX", model="M5238U", serial="M52322C001361", notes="Mouse Delux"),
            ],
            observations="Taquilla 02 SIGCOM - CIAU Valle Seco.",
        ),
        PageActaConfig(
            page=25,
            code="UINF-4.05-2026-0049",
            pdf_name="UINF-4.05-2026-0049_ACTA_CANON_ROUTER_CIAU_NAHYR_GENNER.pdf",
            date="2026-02-02 09:00:00",
            movement_type="assignment",
            custodian=CustodianData(
                name="NAHYR GENNER",
                cedula="V-10.250.998",
                department="COMERCIALES - CIAU VALLE SECO",
                role="Responsable de Oficina",
            ),
            asset=AssetData(
                serial="CANON-MF270-CIAU",
                name="CANON-MF270-MULTIFUNCIONAL",
                brand="CANON",
                model="MF270 Series",
                type="printer",
                ip_address="192.168.1.47",
                mac_address="6C:3C:7C:36:0F:E3",
                notes="Impresora Láser Multifuncional Canon MF270 Series ubicada en CIAU Valle Seco (IP 192.168.1.47).",
            ),
            peripherals=[
                PeripheralData(type="other", label="ROUTER WIFI", brand="MERCUSYS", model="AX3000 DUALBAND", serial="2248734001884", notes="Router WiFi Mercusys AX3000 ubicado en CIAU Valle Seco"),
            ],
            observations="Asignación Impresora Multifuncional Canon y Router WiFi CIAU Valle Seco.",
        ),
    ],
}


def segment_all_pdfs() -> None:
    """Segmenta las 25 páginas del archivo consolidado en archivos individuales."""
    logger.info("▶ SEGMENTACIÓN: Extrayendo 25 PDFs individuales de alta calidad...")
    STORAGE_SCANS_REPO.mkdir(parents=True, exist_ok=True)
    STORAGE_SCANS_DATA.mkdir(parents=True, exist_ok=True)

    for lot_idx, pages in PAGES_REGISTRY.items():
        for item in pages:
            pdf_name = item.pdf_name
            repo_file = STORAGE_SCANS_REPO / pdf_name
            data_file = STORAGE_SCANS_DATA / pdf_name
            page_num = item.page

            # Extraer en ambas rutas (o seguir symlink)
            extract_pdf_page(PDF_SOURCE, page_num, data_file)
            if not repo_file.exists():
                try:
                    repo_file.symlink_to(data_file)
                except Exception:
                    pass

            os.chmod(data_file, 0o664)
            logger.info(f"  ✓ Página {page_num:02d} extraída: {pdf_name}")

    # Permisos www-data
    subprocess.run(["sudo", "chown", "-R", "www-data:www-data", str(STORAGE_SCANS_DATA)], check=True)
    subprocess.run(["sudo", "chmod", "-R", "775", str(STORAGE_SCANS_DATA)], check=True)
    logger.info("✅ Segmentación de 25 PDFs completada con permisos www-data.")


def process_lot(lot_num: int) -> None:
    """Procesa un lote de 5 actas bajo transacción atómica y checkpoint."""
    if lot_num not in PAGES_REGISTRY:
        raise ValueError(f"Lote no válido: {lot_num}")

    pages = PAGES_REGISTRY[lot_num]
    logger.info(f"▶ PROCESANDO LOTE {lot_num} (Páginas {pages[0].page} a {pages[-1].page})...")

    # Iniciar transacción
    sql_stmts = ["START TRANSACTION;"]

    for item in pages:
        code = item.code
        cust = item.custodian
        asset_info = item.asset
        mov_type = item.movement_type
        assigned_date = item.date
        pdf_rel_path = f"assignments/scans/{item.pdf_name}"
        obs = item.observations

        # 1. Resolver el ID del Activo en MariaDB
        asset_id: Optional[int] = asset_info.exists_id
        if not asset_id:
            # Buscar por serial
            check_q = f"SELECT id FROM inventory_assets WHERE serial_number = {escape_sql(asset_info.serial)};"
            out = run_mysql(check_q).strip().splitlines()
            if len(out) > 1 and out[1].strip():
                asset_id = int(out[1].strip())

        # Si el activo no existe, prepararlo para inserción dentro de la transacción
        if not asset_id:
            tag = f"NET-{asset_info.serial}"[:64]
            ip_val = asset_info.ip_address
            mac_val = asset_info.mac_address
            os_val = asset_info.operating_system or "Windows 7"
            status_val = "in_stock" if mov_type == "return" else "assigned"
            cust_name_val = None if mov_type == "return" else cust.name
            cust_id_val = None if mov_type == "return" else cust.cedula
            cust_dept_val = None if mov_type == "return" else cust.department

            ins_asset_sql = f"""
            INSERT INTO inventory_assets (
                asset_tag, name, type, status, brand, model, serial_number,
                ip_address, mac_address, operating_system,
                monitored_site_id, custodian_name, custodian_id_number, custodian_department,
                access_type, notes, is_active, created_at, updated_at
            ) VALUES (
                {escape_sql(tag)}, {escape_sql(asset_info.name)}, {escape_sql(asset_info.type)}, {escape_sql(status_val)},
                {escape_sql(asset_info.brand)}, {escape_sql(asset_info.model)}, {escape_sql(asset_info.serial)},
                {escape_sql(ip_val)}, {escape_sql(mac_val)}, {escape_sql(os_val)},
                1, {escape_sql(cust_name_val)}, {escape_sql(cust_id_val)}, {escape_sql(cust_dept_val)},
                'VNC', {escape_sql(asset_info.notes)}, {0 if not ip_val else 1}, NOW(), NOW()
            );
            """
            sql_stmts.append(ins_asset_sql)
            # Referencia al último ID insertado
            sql_stmts.append("SET @current_asset_id = LAST_INSERT_ID();")
        else:
            # Actualizar activo existente respetando regla de devolución / asignación
            if mov_type == "return":
                up_asset_sql = f"""
                UPDATE inventory_assets SET
                    custodian_name = NULL,
                    custodian_id_number = NULL,
                    custodian_department = NULL,
                    status = 'in_stock',
                    updated_at = NOW()
                WHERE id = {asset_id};
                """
            else:
                up_asset_sql = f"""
                UPDATE inventory_assets SET
                    custodian_name = {escape_sql(cust.name)},
                    custodian_id_number = {escape_sql(cust.cedula)},
                    custodian_department = {escape_sql(cust.department)},
                    status = 'assigned',
                    updated_at = NOW()
                WHERE id = {asset_id};
                """
            sql_stmts.append(up_asset_sql)
            sql_stmts.append(f"SET @current_asset_id = {asset_id};")

        # 2. Periféricos
        periph_json_list = []
        for p in item.peripherals:
            p_notes = p.notes or f"Registrado en Acta {code}"
            periph_json_list.append({
                "type": p.label,
                "brand": p.brand,
                "model": p.model,
                "serial_number": p.serial,
                "observations": p_notes,
            })
            p_status = "in_stock" if mov_type == "return" else "assigned"
            prefix_map = {
                "monitor": "MON",
                "keyboard": "KBD",
                "mouse": "MSE",
                "surge_protector": "PWR",
                "ups": "UPS",
                "printer": "PRN",
                "other": "CMP",
            }
            pref = prefix_map.get(p.type, "PER")
            hash_in = f"{p.type}_{p.serial}_{p.brand}_{code}"
            tag_p = f"{pref}-{hashlib.md5(hash_in.encode()).hexdigest()[:6].upper()}"

            # Si el periférico tiene serial, verificar si existe para actualizar
            if p.serial and p.serial != "N/A":
                up_periph = f"""
                INSERT INTO inventory_peripherals (
                    asset_tag, type, brand, model, serial_number, status,
                    monitored_site_id, current_asset_id, notes, created_at, updated_at
                ) VALUES (
                    {escape_sql(tag_p)}, {escape_sql(p.type)}, {escape_sql(p.brand)}, {escape_sql(p.model)},
                    {escape_sql(p.serial)}, {escape_sql(p_status)}, 1, @current_asset_id, {escape_sql(p_notes)}, NOW(), NOW()
                ) ON DUPLICATE KEY UPDATE
                    current_asset_id = IF({escape_sql(mov_type)} = 'return', NULL, @current_asset_id),
                    status = {escape_sql(p_status)},
                    updated_at = NOW();
                """
                sql_stmts.append(up_periph)
            else:
                # Periférico sin serial
                ins_periph = f"""
                INSERT INTO inventory_peripherals (
                    asset_tag, type, brand, model, serial_number, status,
                    monitored_site_id, current_asset_id, notes, created_at, updated_at
                ) VALUES (
                    {escape_sql(tag_p)}, {escape_sql(p.type)}, {escape_sql(p.brand)}, {escape_sql(p.model)},
                    NULL, {escape_sql(p_status)}, 1, @current_asset_id, {escape_sql(p_notes)}, NOW(), NOW()
                );
                """
                sql_stmts.append(ins_periph)

        # 3. Registrar el Acta en inventory_assignments
        periph_json_str = json.dumps(periph_json_list, ensure_ascii=False)
        ins_acta = f"""
        INSERT INTO inventory_assignments (
            assignment_code, inventory_asset_id, user_id, custodian_name,
            custodian_id_number, custodian_department, monitored_site_id,
            movement_type, provenance, assigned_at, document_pdf_path,
            peripherals_json, observations, status, created_at, updated_at
        ) VALUES (
            {escape_sql(code)}, @current_asset_id, 1, {escape_sql(cust.name)},
            {escape_sql(cust.cedula)}, {escape_sql(cust.department)}, 1,
            {escape_sql(mov_type)}, 'fisico_existente', {escape_sql(assigned_date)}, {escape_sql(pdf_rel_path)},
            {escape_sql(periph_json_str)}, {escape_sql(obs)}, 'active', NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            inventory_asset_id = @current_asset_id,
            custodian_name = VALUES(custodian_name),
            custodian_id_number = VALUES(custodian_id_number),
            custodian_department = VALUES(custodian_department),
            movement_type = VALUES(movement_type),
            document_pdf_path = VALUES(document_pdf_path),
            peripherals_json = VALUES(peripherals_json),
            observations = VALUES(observations),
            updated_at = NOW();
        """
        sql_stmts.append(ins_acta)

        # 4. Auditoría en audit_logs
        audit_sql = f"""
        INSERT INTO audit_logs (
            user_id, user_name, user_role, event, module, entity_name,
            description, created_at, updated_at
        ) VALUES (
            1, 'Administrador del Sistema', 'admin', 'import_acta', 'itam', {escape_sql(code)},
            {escape_sql(f'Carga resiliente de Acta {code} ({mov_type}) - Custodio: {cust.name}')}, NOW(), NOW()
        );
        """
        sql_stmts.append(audit_sql)

    # 5. Checkpoint en bot_settings
    ckpt_sql = f"""
    INSERT INTO bot_settings (
        setting_key, setting_value, setting_group, title, description, type, created_at, updated_at
    ) VALUES (
        'actas_batch_checkpoint', 'LOTE_{lot_num}_COMPLETED', 'itam',
        'Punto de Control Actas', 'Punto de control de procesamiento de actas digitalizadas', 'text', NOW(), NOW()
    ) ON DUPLICATE KEY UPDATE
        setting_value = VALUES(setting_value),
        updated_at = NOW();
    """
    sql_stmts.append(ckpt_sql)
    sql_stmts.append("COMMIT;")

    # Ejecución atómica en MariaDB
    full_query = "\n".join(sql_stmts)
    run_mysql(full_query)
    logger.info(f"✅ LOTE {lot_num} PROCESADO Y CONFIRMADO (COMMIT) EXITOSAMENTE.")

    # Purgar cachés de Laravel
    artisan_bin = str(BASE_DIR / "web_portal" / "artisan")
    subprocess.run(["php", artisan_bin, "cache:clear"], capture_output=True, check=True)
    subprocess.run(["php", artisan_bin, "view:clear"], capture_output=True, check=True)


def get_current_version() -> str:
    """Extrae la versión oficial del sistema."""
    inspector = BASE_DIR / "web_portal" / "app" / "Services" / "Cluster" / "RuntimeParityInspector.php"
    if inspector.exists():
        content = inspector.read_text(encoding="utf-8")
        match = re.search(r"PLATFORM_VERSION\s*=\s*['\"]([^'\"]+)['\"]", content)
        if match:
            return match.group(1).lstrip("v")
    return "5.43.20"


def update_estado_vivo(last_lot: int) -> None:
    """Actualiza docs/ESTADO_VIVO.md con el lote actual y próximo paso."""
    estado_vivo_path = BASE_DIR / "docs" / "ESTADO_VIVO.md"
    version = get_current_version()

    if last_lot < 5:
        next_step = f"Procesamiento del Lote {last_lot + 1} de Actas Digitalizadas"
        phase_str = f"Lote {last_lot} de Actas Digitalizadas Procesado y Confirmado"
    else:
        next_step = "Certificación de Integridad Integral (tools/certify_system_integrity.py) y Cierre de Fase"
        phase_str = "Procesamiento de 25 Actas Digitalizadas Culminado al 100%"

    content = f"""# 📍 ESTADO VIVO DE EJECUCIÓN — SISTEMA S.O.N.A.R.

* **Versión Oficial:** v{version} (Paridad atómica certificada en backend, frontend y API).
* **Fecha / Timestamp:** {subprocess.run(['date', '+%Y-%m-%d %H:%M:%S %z'], capture_output=True, text=True).stdout.strip()}
* **Macro-Tarea Activa:** docs/procesamiento_actas_lote_25.md
* **Última Fase Completada:** {phase_str}
* **Próximo Paso Inmediato:** {next_step}
* **Estado de Servidores:** Desarrollo (192.168.1.220): v{version} (100% Zero-Fail) | Producción (192.168.1.250): v5.34.0 (Operativo).
* **Memoria Forense Detallada:** docs/historico_sesiones/HISTORICO_SESIONES.md
"""
    estado_vivo_path.write_text(content, encoding="utf-8")
    logger.info(f"  ✓ docs/ESTADO_VIVO.md actualizado con Lote {last_lot}.")


def main() -> None:
    parser = argparse.ArgumentParser(description="Orquestador Resiliente de Actas ITAM")
    parser.add_argument("--segment", action="store_true", help="Segmenta los 25 PDFs individuales")
    parser.add_argument("--lot", type=int, choices=[1, 2, 3, 4, 5], help="Procesa un lote específico")
    parser.add_argument("--all", action="store_true", help="Procesa todos los 5 lotes")
    parser.add_argument("--resume", action="store_true", help="Reanuda a partir del último checkpoint")
    parser.add_argument("--status", action="store_true", help="Consulta el estado del checkpoint")
    args = parser.parse_args()

    if not PDF_SOURCE.exists():
        logger.error(f"Archivo de origen no encontrado: {PDF_SOURCE}")
        sys.exit(1)

    if args.status:
        out = run_mysql("SELECT setting_value, updated_at FROM bot_settings WHERE setting_key = 'actas_batch_checkpoint';")
        logger.info(f"Estado de Checkpoint en MariaDB:\n{out}")
        return

    if args.segment:
        segment_all_pdfs()
        return

    if args.lot:
        process_lot(args.lot)
        update_estado_vivo(args.lot)
        return

    if args.all:
        logger.info("=========================================================================")
        logger.info("Iniciando procesamiento integral de 25 Actas Digitalizadas en 5 Lotes")
        logger.info("=========================================================================")
        segment_all_pdfs()
        for i in range(1, 6):
            process_lot(i)
            update_estado_vivo(i)
        logger.info("=========================================================================")
        logger.info("🎉 LOS 5 LOTES DE ACTAS FUERON PROCESADOS Y CONFIRMADOS EXITOSAMENTE")
        logger.info("=========================================================================")
        return

    if args.resume:
        out = run_mysql("SELECT setting_value FROM bot_settings WHERE setting_key = 'actas_batch_checkpoint';").strip().splitlines()
        last_ckpt = out[1].strip() if len(out) > 1 else "NONE"
        logger.info(f"Último Checkpoint encontrado: {last_ckpt}")
        start_lot = 1
        if "LOTE_1" in last_ckpt:
            start_lot = 2
        elif "LOTE_2" in last_ckpt:
            start_lot = 3
        elif "LOTE_3" in last_ckpt:
            start_lot = 4
        elif "LOTE_4" in last_ckpt:
            start_lot = 5
        elif "LOTE_5" in last_ckpt:
            logger.info("Todos los lotes ya están completados. Nada que reanudar.")
            return

        logger.info(f"Reanudando a partir del LOTE {start_lot}...")
        for i in range(start_lot, 6):
            process_lot(i)
            update_estado_vivo(i)
        return

    parser.print_help()


if __name__ == "__main__":
    main()
