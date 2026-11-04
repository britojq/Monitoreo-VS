"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS FASE 3: test_phase3_dependencies_and_sanitization.py
# Validación de Dependencias Acotadas (SEC-005) y Sanitización de IPs en Docs (SEC-007).
# ==============================================================================
"""

import re
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestPhase3HardeningAndSanitization:
    """Pruebas de endurecimiento de dependencias y sanitización de documentación."""

    def test_requirements_txt_bounded_ranges(self) -> None:
        """Verifica que requirements.txt no contenga rangos excesivamente amplios (SEC-005)."""
        req_file = BASE_DIR / "requirements.txt"
        assert req_file.exists(), "El archivo requirements.txt no existe"

        lines = [line.strip() for line in req_file.read_text(encoding="utf-8").splitlines() if line.strip() and not line.startswith("#")]
        
        # Verificar que cryptography no tenga un rango mayor a 2 versiones mayores
        crypto_lines = [l for l in lines if l.startswith("cryptography")]
        assert len(crypto_lines) == 1, "Falta línea de cryptography en requirements.txt"
        
        crypto_rule = crypto_lines[0]
        # Prohibir el rango amplio previo >=42.0.0,<51.0.0
        assert ">=42.0.0,<51.0.0" not in crypto_rule, "Se detectó rango excesivamente amplio en cryptography (CWE-1104)"
        assert ">=50.0.0" in crypto_rule or "==" in crypto_rule, f"cryptography debe estar acotada a su versión auditada: {crypto_rule}"

    def test_requirements_lock_exists_and_pinned(self) -> None:
        """Verifica que requirements.lock exista y tenga versiones fijadas exactamente con == (SEC-005)."""
        lock_file = BASE_DIR / "requirements.lock"
        assert lock_file.exists(), "El archivo requirements.lock debe existir para garantizar reproducibilidad"

        lines = [l.strip() for l in lock_file.read_text(encoding="utf-8").splitlines() if l.strip() and not l.startswith("#")]
        assert len(lines) >= 20, f"requirements.lock tiene muy pocas dependencias ({len(lines)})"

        for line in lines:
            assert "==" in line, f"Dependencia en requirements.lock no tiene fijación exacta (==): {line}"

    def test_readme_has_no_internal_ip_addresses(self) -> None:
        """Verifica que README.md público no exponga direcciones IP privadas corporativas (SEC-007)."""
        readme_file = BASE_DIR / "README.md"
        assert readme_file.exists(), "El archivo README.md no existe"

        content = readme_file.read_text(encoding="utf-8")
        # Patrón para detectar subredes/IPs privadas corporativas expuestas
        private_ip_pattern = re.compile(r"\b(?:192\.168\.\d{1,3}\.\d{1,3}|10\.20\.\d{1,3}\.\d{1,3})\b")
        matches = private_ip_pattern.findall(content)

        assert len(matches) == 0, f"Se detectaron IPs privadas corporativas en README.md público: {matches}"
