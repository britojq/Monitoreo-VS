"""
Pruebas unitarias para utilidades criptográficas y filtros de seguridad en monitor
"""

import pytest
from monitor.alert_engine import compute_fingerprint
from monitor.terminal_shield import is_protected_process


class TestSecurityShield:
    def test_compute_fingerprint_deterministic(self):
        """Verifica que el hash SHA-256 de huella sea determinista y único."""
        fp1 = compute_fingerprint("service", 10, "cpu_high")
        fp2 = compute_fingerprint("service", 10, "cpu_high")
        fp3 = compute_fingerprint("service", 11, "cpu_high")

        assert fp1 == fp2
        assert fp1 != fp3
        assert len(fp1) == 64

    def test_is_protected_process_invalid_pids(self):
        """Verifica que PIDs no válidos retornen False de forma segura."""
        assert is_protected_process(0) is False
        assert is_protected_process(-1) is False
        assert is_protected_process(1) is False
