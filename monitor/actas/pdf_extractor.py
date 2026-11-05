"""
# ==============================================================================
# 📄 SERVICIO DE EXTRACCIÓN Y SEGMENTACIÓN PDF: pdf_extractor.py
# Ubicación: monitor/actas/pdf_extractor.py
# License: GNU AGPLv3
# ==============================================================================
"""

from __future__ import annotations

import logging
import subprocess
from pathlib import Path
from typing import List

from monitor.actas.models import PageActaConfig

logger = logging.getLogger("actas_pdf_extractor")


class ActaPdfExtractor:
    """Extrae y segmenta páginas de actas PDF mediante Ghostscript."""

    @staticmethod
    def extract_page(source_pdf: Path, page_num: int, output_pdf: Path) -> None:
        """Extrae una página específica de un archivo PDF usando Ghostscript de forma segura."""
        if not source_pdf.exists():
            raise FileNotFoundError(f"PDF fuente no encontrado: {source_pdf}")

        output_pdf.parent.mkdir(parents=True, exist_ok=True)

        cmd: List[str] = [
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
        res = subprocess.run(cmd, capture_output=True, text=True, check=False)
        if res.returncode != 0:
            raise RuntimeError(f"Fallo en Ghostscript al extraer página {page_num}: {res.stderr}")

    @classmethod
    def segment_batch(
        cls,
        source_pdf: Path,
        output_dir: Path,
        configs: List[PageActaConfig],
    ) -> List[Path]:
        """Segmenta un conjunto de páginas individuales a partir de un lote de actas."""
        generated_paths: List[Path] = []
        for cfg in configs:
            target_file = output_dir / cfg.pdf_name
            cls.extract_page(source_pdf, cfg.page, target_file)
            generated_paths.append(target_file)
            logger.info(f"  ✓ Segmentada página {cfg.page} -> {cfg.pdf_name}")
        return generated_paths
