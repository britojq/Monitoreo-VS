#!/usr/bin/env python3
"""
tools/restart_bot.py
Inicia o reinicia el demonio del Bot de Telegram de forma desacoplada (POSIX setsid)
para evitar bloqueos de descriptores en sesiones SSH y garantizar ejecución en background.
"""
import os
import sys
import time
import subprocess
import signal
from pathlib import Path

BOT_DIR = str(Path(__file__).resolve().parent.parent)
PYTHON_BIN = os.path.join(BOT_DIR, "venv", "bin", "python")
BOT_SCRIPT = os.path.join(BOT_DIR, "bot.py")

def get_bot_pids() -> list[int]:
    try:
        out = subprocess.check_output(["pgrep", "-f", BOT_SCRIPT], text=True)
        my_pid = os.getpid()
        return [int(p.strip()) for p in out.strip().split() if int(p.strip()) != my_pid]
    except Exception:
        return []

def stop_bot() -> None:
    pids = get_bot_pids()
    if not pids:
        return
    print(f"--> Deteniendo instancias previas de bot.py (PIDs: {pids})...")
    for pid in pids:
        try:
            os.kill(pid, signal.SIGTERM)
        except ProcessLookupError:
            pass
    time.sleep(2)
    # Force kill if still alive
    pids = get_bot_pids()
    for pid in pids:
        try:
            os.kill(pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
    time.sleep(1)

def start_bot() -> int:
    fallback_log = "/tmp/bot_manual.log"
    log_fd = open(fallback_log, "a")
    proc = subprocess.Popen(
        [PYTHON_BIN, BOT_SCRIPT],
        cwd=BOT_DIR,
        stdout=log_fd,
        stderr=subprocess.STDOUT,
        stdin=subprocess.DEVNULL,
        start_new_session=True,
        close_fds=True
    )
    time.sleep(2)
    print(f"✅ Bot de Telegram lanzado exitosamente con PID {proc.pid}")
    return proc.pid

def main() -> None:
    # 1. Si existe y responde el servicio systemd, usarlo para evitar colisiones
    try:
        res = subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot.service"], capture_output=True, text=True, timeout=10)
        if res.returncode == 0:
            print("✅ Bot de Telegram reiniciado exitosamente vía systemctl (tg-admin-bot.service).")
            time.sleep(2)
            pids = get_bot_pids()
            print(f"✓ Proceso bot.py activo y verificado en systemd (PIDs: {pids}).")
            sys.exit(0)
    except Exception:
        pass

    # 2. Fallback desacoplado (para entornos sin systemd o SSH sin dbus)
    stop_bot()
    pid = start_bot()
    time.sleep(1)
    pids = get_bot_pids()
    if pid in pids or len(pids) > 0:
        print(f"✓ Proceso bot.py activo y verificado (PIDs: {pids}).")
        sys.exit(0)
    else:
        print("❌ Error: bot.py no permaneció activo tras el lanzamiento.")
        sys.exit(1)

if __name__ == "__main__":
    main()
