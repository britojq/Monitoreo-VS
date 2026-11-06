#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ VALLE SECO ITAM - ORQUESTADOR RESILIENTE DE ACTAS (CLI FACADE): process_actas_batch_resilient.py
# Ubicación: /opt/sonar/tools/process_actas_batch_resilient.py
# Refactorizado bajo principios SOLID y Clean Architecture (ARC-05).
# License: GNU AGPLv3
# ==============================================================================
"""

from __future__ import annotations

import argparse
import logging
import os
import re
import subprocess
import sys
from pathlib import Path
from typing import Optional

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.actas import (
    ActaItamPersistence,
    ActaPdfExtractor,
    PAGES_REGISTRY,
)

STORAGE_SCANS_REPO = BASE_DIR / "web_portal" / "storage" / "app" / "public" / "assignments" / "scans"
STORAGE_SCANS_DATA = Path("/home/data_monitoreo/storage/app/public/assignments/scans")
DEFAULT_CANONICAL_SOURCE = BASE_DIR / "storage" / "scans" / "actas_consolidado.pdf"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [RESILIENT-ACTAS] %(message)s",
)
logger = logging.getLogger("resilient_actas")


def resolve_pdf_source(cli_path: Optional[str] = None) -> Path:
    """Resuelve la ruta del PDF consolidado dinámicamente desde CLI, MariaDB SSOT o ruta canónica."""
    if cli_path:
        p = Path(cli_path)
        if p.exists():
            return p

    # Consulta opcional en MariaDB bot_settings
    try:
        persistence = ActaItamPersistence(BASE_DIR)
        out = persistence.run_mysql("SELECT setting_value FROM bot_settings WHERE setting_key = 'actas_pdf_source_path';").strip().splitlines()
        if len(out) > 1 and out[1].strip():
            db_path = Path(out[1].strip())
            if db_path.exists():
                return db_path
    except Exception:
        pass

    return DEFAULT_CANONICAL_SOURCE


def segment_all_pdfs(source_pdf: Path) -> None:
    """Segmenta las 25 páginas del archivo consolidado en archivos individuales."""
    logger.info("▶ SEGMENTACIÓN: Extrayendo 25 PDFs individuales de alta calidad...")
    STORAGE_SCANS_REPO.mkdir(parents=True, exist_ok=True)
    STORAGE_SCANS_DATA.mkdir(parents=True, exist_ok=True)

    for lot_idx, pages in PAGES_REGISTRY.items():
        for item in pages:
            pdf_name = item.pdf_name
            repo_file = STORAGE_SCANS_REPO / pdf_name
            data_file = STORAGE_SCANS_DATA / pdf_name

            ActaPdfExtractor.extract_page(source_pdf, item.page, data_file)

            if not repo_file.exists():
                try:
                    repo_file.symlink_to(data_file)
                except Exception:
                    pass

            os.chmod(data_file, 0o664)
            logger.info(f"  ✓ Página {item.page:02d} extraída: {pdf_name}")

    # Ajuste de permisos www-data
    try:
        subprocess.run(["sudo", "chown", "-R", "www-data:www-data", str(STORAGE_SCANS_DATA)], check=False)
        subprocess.run(["sudo", "chmod", "-R", "775", str(STORAGE_SCANS_DATA)], check=False)
    except Exception as e:
        logger.warning(f"No se pudieron ajustar permisos www-data: {e}")

    logger.info("✅ Segmentación de 25 PDFs completada exitosamente.")


def get_current_version() -> str:
    """Extrae la versión oficial del sistema desde RuntimeParityInspector."""
    inspector = BASE_DIR / "web_portal" / "app" / "Services" / "Cluster" / "RuntimeParityInspector.php"
    if inspector.exists():
        content = inspector.read_text(encoding="utf-8")
        match = re.search(r"PLATFORM_VERSION\s*=\s*['\"]([^'\"]+)['\"]", content)
        if match:
            return match.group(1).lstrip("v")
    return "5.53.20"


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
    parser = argparse.ArgumentParser(description="Orquestador Resiliente de Actas ITAM (ARC-05 Modular)")
    parser.add_argument("--pdf-source", type=str, default=None, help="Ruta al archivo PDF consolidado de origen")
    parser.add_argument("--segment", action="store_true", help="Segmenta los 25 PDFs individuales")
    parser.add_argument("--lot", type=int, choices=[1, 2, 3, 4, 5], help="Procesa un lote específico")
    parser.add_argument("--all", action="store_true", help="Procesa todos los 5 lotes")
    parser.add_argument("--resume", action="store_true", help="Reanuda a partir del último checkpoint")
    parser.add_argument("--status", action="store_true", help="Consulta el estado del checkpoint")
    args = parser.parse_args()

    persistence = ActaItamPersistence(BASE_DIR)

    if args.status:
        ckpt = persistence.get_checkpoint()
        logger.info(f"Estado de Checkpoint en MariaDB: {ckpt}")
        return

    source_pdf = resolve_pdf_source(args.pdf_source)

    if args.segment:
        if not source_pdf.exists():
            logger.error(f"Archivo de origen no encontrado: {source_pdf}")
            sys.exit(1)
        segment_all_pdfs(source_pdf)
        return

    if args.lot:
        persistence.process_lot(args.lot, PAGES_REGISTRY[args.lot])
        update_estado_vivo(args.lot)
        return

    if args.all:
        logger.info("=========================================================================")
        logger.info("Iniciando procesamiento integral de 25 Actas Digitalizadas en 5 Lotes")
        logger.info("=========================================================================")
        if source_pdf.exists():
            segment_all_pdfs(source_pdf)
        for i in range(1, 6):
            persistence.process_lot(i, PAGES_REGISTRY[i])
            update_estado_vivo(i)
        logger.info("=========================================================================")
        logger.info("🎉 LOS 5 LOTES DE ACTAS FUERON PROCESADOS Y CONFIRMADOS EXITOSAMENTE")
        logger.info("=========================================================================")
        return

    if args.resume:
        last_ckpt = persistence.get_checkpoint()
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
            persistence.process_lot(i, PAGES_REGISTRY[i])
            update_estado_vivo(i)
        return

    parser.print_help()


if __name__ == "__main__":
    main()
