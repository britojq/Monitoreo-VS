"""
# ==============================================================================
# 🔍 DECODIFICADOR Y VERIFICADOR DE INTEGRIDAD QR / HASH: qr_decoder.py
# Ubicación: monitor/actas/qr_decoder.py
# License: GNU AGPLv3
# ==============================================================================
"""

from __future__ import annotations

import hashlib
import logging
import re
from pathlib import Path
from typing import Optional

logger = logging.getLogger("actas_qr_decoder")


class ActaQrDecoder:
    """Decodifica metadatos QR y verifica la integridad criptográfica de actas escaneadas."""

    @staticmethod
    def calculate_file_hash(file_path: Path, algorithm: str = "sha256") -> str:
        """Calcula el hash criptográfico SHA-256 de un documento PDF."""
        if not file_path.exists():
            raise FileNotFoundError(f"Archivo no encontrado para hash: {file_path}")

        hasher = hashlib.new(algorithm)
        with open(file_path, "rb") as f:
            while chunk := f.read(65536):
                hasher.update(chunk)
        return hasher.hexdigest()

    @staticmethod
    def extract_acta_code_from_filename(filename: str) -> Optional[str]:
        """Extrae el código formal del acta (ej. UINF-4.05-2026-0025) a partir del nombre de archivo."""
        match = re.search(r"(UINF-4\.05-\d{4}-\d{4})", filename)
        if match:
            return match.group(1)
        return None

    @classmethod
    def verify_acta_integrity(cls, pdf_path: Path, expected_hash: Optional[str] = None) -> bool:
        """Verifica que el acta PDF sea legible y coincida con el hash esperado si se suministra."""
        if not pdf_path.exists() or pdf_path.stat().st_size == 0:
            return False

        if expected_hash is not None:
            actual_hash = cls.calculate_file_hash(pdf_path)
            return actual_hash.lower() == expected_hash.lower()

        return True
