"""
Pruebas unitarias para el filtro de datos personales (PII) y rate limiting de bot.py
"""

import pytest
from bot import sanitizar_pii, check_ai_rate_limit


class TestPiiFilter:
    def test_sanitize_venezuelan_id(self):
        """Verifica que las cédulas de identidad venezolanas sean redactadas."""
        text = "El operador con cédula V-12345678 reporta falla en el nodo."
        result = sanitizar_pii(text)
        assert "12345678" not in result
        assert "[C.I. OCULTA]" in result

    def test_sanitize_phone_numbers(self):
        """Verifica que números telefónicos móviles y fijos sean redactados."""
        text1 = "Llamar al técnico al 0412-1234567 para autorizar."
        result1 = sanitizar_pii(text1)
        assert "0412-1234567" not in result1
        assert "[TLF. OCULTO]" in result1

        text2 = "Contacto de guardia: 0241-8889900 en la sede."
        result2 = sanitizar_pii(text2)
        assert "0241-8889900" not in result2
        assert "[TLF. OCULTO]" in result2

    def test_no_false_positives_on_technical_data(self):
        """Verifica que métricas técnicas o IPs no sean alteradas erróneamente."""
        text = "El servidor 10.20.23.252 tiene 64GB de RAM y 4 núcleos a 3200MHz."
        result = sanitizar_pii(text)
        assert "10.20.23.252" in result
        assert "64GB" in result
        assert "3200MHz" in result


class TestAiRateLimit:
    def test_rate_limit_allows_under_threshold(self):
        """Verifica que permita solicitudes dentro del límite."""
        user_id = 999901
        for _ in range(5):
            assert check_ai_rate_limit(user_id, max_requests=5, window_seconds=60) is True

    def test_rate_limit_blocks_exceeded_threshold(self):
        """Verifica que bloquee la 6ta solicitud al superar el límite."""
        user_id = 999902
        for _ in range(5):
            check_ai_rate_limit(user_id, max_requests=5, window_seconds=60)
        assert check_ai_rate_limit(user_id, max_requests=5, window_seconds=60) is False
