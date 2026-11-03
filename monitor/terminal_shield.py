#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ ESCUDO DE TERMINAL INTERACTIVA & LOCKDOWN: terminal_shield.py (@IA_ValleSeco_bot)
# Fachada delegadora SOLID ultra-delgada con preservación de CLI, PAM y API
# Ubicación: /opt/sonar/monitor/terminal_shield.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import os
import sys
from datetime import datetime
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

# 1. Banderas en memoria compartida (/dev/shm)
LOCKDOWN_FLAG = Path("/dev/shm/system_lockdown_active")
GRACE_PERIOD_FLAG = Path("/dev/shm/terminal_shield_grace_period")
RECENT_ALERTS_FLAG = Path("/dev/shm/terminal_shield_recent.json")
INFRACTIONS_FLAG = Path("/dev/shm/terminal_shield_infractions.json")
MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")

# 2. Infraestructura
from monitor.shield.infrastructure.audit_logger import log_event_to_database, safe_unlink
from monitor.shield.infrastructure.master_override import (
    execute_master_override,
    is_master_override_active,
)
from monitor.shield.infrastructure.notifier import (
    load_configured_proxies,
    send_telegram_alert,
)

# 3. Dominio e Inspección
from monitor.shield.domain.proc_inspector import (
    get_process_name,
    is_protected_process,
    is_recent_alert,
)
from monitor.shield.domain.tty_resolver import (
    clean_tty_name,
    is_grace_period_active,
    is_lockdown_active,
    resolve_tty,
)

# 4. UI y Contadores
from monitor.shield.ui.infraction_counter import (
    record_infraction,
    reset_infractions,
)
from monitor.shield.ui.kdialog_adapter import launch_kdialog_fallback
from monitor.shield.ui.pyqt_dialog_adapter import launch_shield_dialog

# 5. Acciones y Workers
from monitor.shield.actions.auth_tty_action import execute_auth
from monitor.shield.actions.enforce_sudo_action import execute_enforce_sudo
from monitor.shield.actions.kill_process_action import execute_kill
from monitor.shield.actions.lockdown_action import (
    execute_lockdown,
    execute_unlock,
)
from monitor.shield.actions.terminal_open_worker import process_terminal_open

logger = logging.getLogger("monitor.terminal_shield")


def main() -> None:
    """Punto de entrada principal para ejecución por línea de comandos."""
    parser = argparse.ArgumentParser(description="Sentinel Terminal Shield Dispatcher")
    parser.add_argument("--action", required=True, choices=["alert_terminal", "kill", "lockdown", "unlock", "rearm", "auth", "override", "status", "enforce_sudo"])
    parser.add_argument("--pid", type=int, default=0)
    parser.add_argument("--ppid", type=int, default=0)
    parser.add_argument("--user", default="")
    parser.add_argument("--sudo-user", default="root")
    parser.add_argument("--sudo-cmd", default="")
    parser.add_argument("--tty", default="")
    parser.add_argument("--display", default="")
    parser.add_argument("--minutes", type=int, default=30)
    parser.add_argument("--dry-run", action="store_true", help="Simular sin reiniciar el equipo físicamente")
    parser.add_argument("--key", default="", help="Token de autorización administrativa")
    args = parser.parse_args()

    if args.action == "alert_terminal":
        asyncio.run(
            process_terminal_open(
                pid=args.pid,
                ppid=args.ppid,
                user=args.user or os.getenv("USER") or "desconocido",
                tty=args.tty or "desconocida",
                display=args.display or os.getenv("DISPLAY", ""),
            )
        )
    elif args.action == "kill":
        res = execute_kill(args.pid, args.ppid, dry_run=args.dry_run)
        print(json.dumps(res))
    elif args.action == "lockdown":
        res = execute_lockdown(args.pid, args.ppid, args.user, dry_run=args.dry_run)
        print(json.dumps(res))
    elif args.action in ("unlock", "rearm"):
        res = execute_unlock()
        print(json.dumps(res))
    elif args.action == "status":
        override_active = is_master_override_active()
        lockdown_active = is_lockdown_active()
        grace_active, grace_exp = is_grace_period_active()
        infrac_count = 0
        if INFRACTIONS_FLAG.exists():
            try:
                infrac_count = json.loads(INFRACTIONS_FLAG.read_text(encoding="utf-8")).get("count", 0)
            except Exception:
                pass
        status_info = {
            "master_override_active": override_active,
            "lockdown_active": lockdown_active,
            "grace_period_active": grace_active,
            "grace_expiry": datetime.fromtimestamp(grace_exp).strftime("%H:%M:%S") if grace_active else None,
            "infractions_count": infrac_count,
        }
        print(json.dumps(status_info, indent=2))
    elif args.action == "auth":
        res = execute_auth(minutes=args.minutes, tty=args.tty, pid=args.pid)
        print(json.dumps(res))
    elif args.action == "override":
        key = args.key
        if not key:
            import getpass
            print("\n🛡️ [SENTINEL SHIELD] Validación de Token de Autorización Administrativa")
            try:
                key = getpass.getpass("🔑 Ingrese Token de Acceso: ")
            except Exception:
                key = input("🔑 Ingrese Token de Acceso: ")
        res = execute_master_override(key, source="CLI / Terminal")
        print(json.dumps(res))
        if res.get("success"):
            print("\n✅ Token validado exitosamente. Escudo de seguridad desactivado hasta el próximo reinicio.\n")
        else:
            print("\n❌ Token de acceso inválido. Intento registrado en auditoría.\n")
            sys.exit(1)
    elif args.action == "enforce_sudo":
        res = execute_enforce_sudo(
            user=args.user or os.getenv("USER") or "desconocido",
            sudo_user=args.sudo_user or "root",
            sudo_cmd=args.sudo_cmd or "sudo",
            tty=args.tty or "consola",
            ppid=args.ppid,
            dry_run=args.dry_run,
        )
        print(json.dumps(res))
        if not res.get("allowed", True):
            sys.exit(1)
        sys.exit(0)


if __name__ == "__main__":
    main()
