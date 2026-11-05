"""
Test unitario para verificación de mitigación SEC-15 en snmp_activator.py.
Verifica que las credenciales pueden ser entregadas vía stdin y que la falta
de contraseñas tanto en CLI como en stdin es detectada y abortada de forma segura.
"""

import json
import subprocess
import sys
from pathlib import Path

PYTHON_BIN = sys.executable
SCRIPT_PATH = Path("/opt/sonar/monitor/cli/snmp_activator.py")


def test_snmp_activator_stdin_auth_decodes_json_correctly():
    """Verifica que --stdin-auth procese las credenciales desde sys.stdin."""
    payload = json.dumps({
        "password": "TestMockPassword123!",
        "secret": "TestSecretEnable456!",
        "community": "TestCommRO"
    })

    # Ejecutamos con una IP inalcanzable local o sintética para probar el parsing temprano
    cmd = [
        PYTHON_BIN,
        str(SCRIPT_PATH),
        "--ip", "127.0.0.1",
        "--method", "ssh",
        "--user", "mockuser",
        "--stdin-auth"
    ]

    proc = subprocess.run(
        cmd,
        input=payload,
        text=True,
        capture_output=True,
        timeout=15
    )

    # El comando debe haber parseado stdin; no debe fallar con "Contraseña requerida"
    # Puede fallar con auth_error o timeout porque 127.0.0.1 no tiene esas credenciales
    output_str = proc.stdout.strip()
    assert "Contraseña requerida pero no suministrada" not in proc.stderr
    assert "Fallo en decodificación stdin" not in output_str

    try:
        data = json.loads(output_str)
        assert data.get("status") in ("auth_error", "failed", "unreachable")
    except json.JSONDecodeError:
        pass


def test_snmp_activator_rejects_when_no_password_provided():
    """Verifica que sin password ni en CLI ni en stdin, la ejecución aborte con error controlado."""
    cmd = [
        PYTHON_BIN,
        str(SCRIPT_PATH),
        "--ip", "127.0.0.1",
        "--method", "ssh",
        "--user", "mockuser",
        "--stdin-auth"
    ]

    proc = subprocess.run(
        cmd,
        input="{}",  # JSON sin password
        text=True,
        capture_output=True,
        timeout=10
    )

    assert proc.returncode != 0
    data = json.loads(proc.stdout.strip())
    assert data.get("status") == "failed"
    assert "Contraseña de activación requerida" in data.get("error", "")
