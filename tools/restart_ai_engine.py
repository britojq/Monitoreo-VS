#!/usr/bin/env python3
"""
# ==============================================================================
# 🤖 GESTOR RESILIENTE DEL MOTOR LOCAL DE IA
# Archivo: tools/restart_ai_engine.py
# Propósito: Iniciar o reiniciar el motor local de IA con 2 hilos paralelos
#            de inferencia (OLLAMA_NUM_PARALLEL=2) y persistencia en RAM 24h
#            en una sesión POSIX completamente desacoplada (start_new_session=True).
# ==============================================================================
"""

import os
import subprocess
import sys
import time
import urllib.request


def restart_ai_engine() -> bool:
    print("--> Deteniendo instancias previas del motor de IA...")
    subprocess.run(["pkill", "-9", "-f", "llama-server"], check=False)
    subprocess.run(["pkill", "-9", "-f", "ollama serve"], check=False)
    time.sleep(1)

    os.makedirs("/tmp/monitor", exist_ok=True)
    try:
        os.chmod("/tmp/monitor", 0o750)
    except Exception:
        pass

    env = os.environ.copy()
    env["OLLAMA_NUM_PARALLEL"] = "2"
    env["OLLAMA_MAX_LOADED_MODELS"] = "1"
    env["OLLAMA_KEEP_ALIVE"] = "24h"
    env["OLLAMA_MODELS"] = "/home/data_monitoreo/ai_models"
    env["HOME"] = "/usr/share/ollama"

    log_path = "/tmp/monitor/ollama.log"
    log_file = open(log_path, "a")

    kwargs = {
        "env": env,
        "stdin": subprocess.DEVNULL,
        "stdout": log_file,
        "stderr": subprocess.STDOUT,
        "start_new_session": True,
    }

    if os.geteuid() == 0:
        try:
            import pwd
            pwd.getpwnam("ollama")
            kwargs["user"] = "ollama"
        except (KeyError, ImportError):
            pass

    print("--> Lanzando demonio del motor de IA (concurrencia=2, keep_alive=24h)...")
    subprocess.Popen(["/usr/local/bin/ollama", "serve"], **kwargs)

    # Esperar hasta 10 segundos a que responda en 11434
    print("--> Verificando respuesta HTTP del motor en 127.0.0.1:11434...")
    for _ in range(10):
        time.sleep(1)
        try:
            req = urllib.request.Request("http://127.0.0.1:11434/api/tags")
            with urllib.request.urlopen(req, timeout=2) as resp:
                if resp.status == 200:
                    print("✅ Motor local de IA operativo y respondiendo HTTP 200 OK.")
                    return True
        except Exception:
            pass

    print("⚠️ El motor no respondió en 10s. Consulta los logs en /tmp/monitor/ollama.log.")
    return False


if __name__ == "__main__":
    success = restart_ai_engine()
    sys.exit(0 if success else 1)
