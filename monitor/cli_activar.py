#!/usr/bin/env python3
# ==============================================================================
# 🛡️ CLI DE VALIDACIÓN Y ACTIVACIÓN DE HARDWARE: cli_activar.py
# Ubicación: /scripts/telegram-admin-bot/monitor/cli_activar.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================

import os
import sys
import re
import json
import time
import platform
import subprocess
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

from monitor.core_shield import (
    SecureCore,
    activate_hardware_first_boot,
    force_core_hardware_anchor,
    notify_core_console_activation,
    get_core_status,
    is_core_operational,
    get_node_telemetry,
    AUDIT_DIR,
    CHALLENGE_FILE,
    ANCHOR_FILE
)

# Colores ANSI para terminal
C_RESET = "\033[0m"
C_BOLD = "\033[1m"
C_GREEN = "\033[32m"
C_YELLOW = "\033[33m"
C_RED = "\033[31m"
C_CYAN = "\033[36m"
C_BLUE = "\033[34m"


def print_banner() -> None:
    print(f"{C_BOLD}{C_CYAN}=============================================================={C_RESET}")
    print(f"{C_BOLD}{C_GREEN} 🛡️  VALIDACIÓN DE INSTALACIÓN Y ANCLAJE DE HARDWARE (SHELL) {C_RESET}")
    print(f"{C_BOLD}{C_CYAN}=============================================================={C_RESET}")


def show_help() -> None:
    print_banner()
    print(f"""
{C_BOLD}DESCRIPCIÓN:{C_RESET}
  Herramienta administrativa de consola para validar la instalación del bot,
  anclar criptográficamente el software a la placa/hardware local y desbloquear
  el servicio en equipos clonados o nuevas instalaciones.

{C_BOLD}USO:{C_RESET}
  activar [SERIAL]
  activar [OPCIONES]

{C_BOLD}OPCIONES:{C_RESET}
  [SERIAL]             Código alfanumérico (ej: AUTH-A1B2-C3D4-E5F6-7890).
  -s, --estado         Muestra la telemetría y estado actual de hardware del nodo.
  -f, --forzar         Fuerza el anclaje directo de hardware sin requerir serial previo.
  -h, --help           Muestra esta ayuda y termina.

{C_BOLD}EJEMPLOS:{C_RESET}
  activar AUTH-A1B2-C3D4-E5F6-7890
  activar --estado
  activar --forzar
""")


def restart_bot_service() -> bool:
    """Reinicia limpiamente el servicio tg-admin-bot en systemd."""
    cmd_sudo = ["sudo", "systemctl", "restart", "tg-admin-bot.service"]
    cmd_normal = ["systemctl", "restart", "tg-admin-bot.service"]

    # Probar primero con sudo si no somos root
    target_cmd = cmd_sudo if os.geteuid() != 0 else cmd_normal
    try:
        res = subprocess.run(target_cmd, capture_output=True, text=True, timeout=15)
        if res.returncode == 0:
            return True
    except Exception:
        pass

    # Fallback inverso
    fallback_cmd = cmd_normal if target_cmd == cmd_sudo else cmd_sudo
    try:
        res = subprocess.run(fallback_cmd, capture_output=True, text=True, timeout=15)
        return res.returncode == 0
    except Exception:
        return False


def show_status() -> None:
    print_banner()
    telem = get_node_telemetry()
    status = get_core_status()
    is_op = is_core_operational()

    state_color = C_GREEN if is_op else C_YELLOW
    print(f"🖥️  {C_BOLD}Servidor (Hostname):{C_RESET}  {telem['hostname']}")
    print(f"🌐 {C_BOLD}Direcciones IP:{C_RESET}       {', '.join(telem['ips'])}")
    print(f"👤 {C_BOLD}Usuario del Sistema:{C_RESET}  {telem['user']}")
    print(f"📁 {C_BOLD}Directorio Base:{C_RESET}      {telem['path']}")
    print(f"🔒 {C_BOLD}Estado de Validación:{C_RESET} {state_color}{C_BOLD}{status}{C_RESET}")
    print(f"⚓ {C_BOLD}Anclaje Hardware:{C_RESET}     {'Presente (.sys_anchor)' if ANCHOR_FILE.exists() else 'No existe'}")

    if CHALLENGE_FILE.exists():
        try:
            c_data = json.loads(CHALLENGE_FILE.read_text(encoding="utf-8"))
            ts = c_data.get("ts", 0)
            now = int(time.time())
            remaining = max(0, 600 - (now - ts))
            print(f"\n⚠️  {C_YELLOW}{C_BOLD}Desafío Pendiente:{C_RESET}       {c_data.get('serial', 'N/A')}")
            print(f"⏳ {C_BOLD}Tiempo Restante:{C_RESET}         {remaining // 60}m {remaining % 60}s")
        except Exception:
            pass
    print(f"{C_BOLD}{C_CYAN}=============================================================={C_RESET}")


def normalize_serial(raw_str: str) -> str:
    """Limpia y valida el formato del Serial de activación."""
    clean = raw_str.strip().upper()
    # Si viene sin el prefijo AUTH- pero con los 4 bloques
    if not clean.startswith("AUTH-") and len(clean) == 19 and clean.count("-") == 3:
        clean = f"AUTH-{clean}"
    return clean


def main() -> int:
    args = sys.argv[1:]

    if not args:
        # Modo interactivo si hay TTY
        if sys.stdin.isatty():
            print_banner()
            if is_core_operational():
                print(f"{C_GREEN}ℹ️  El sistema ya se encuentra en estado OPERATIONAL (Hardware anclado).{C_RESET}")
                print(f"   Si desea forzar una nueva vinculación, ejecute: {C_BOLD}activar --forzar{C_RESET}\n")

            if CHALLENGE_FILE.exists():
                try:
                    c_data = json.loads(CHALLENGE_FILE.read_text(encoding="utf-8"))
                    c_serial = c_data.get("serial")
                    if c_serial:
                        print(f"💡 {C_CYAN}Serial de desafío activo detectado:{C_RESET} {C_BOLD}{c_serial}{C_RESET}\n")
                except Exception:
                    pass

            try:
                entered = input(f"{C_BOLD}🔑 Ingrese el Serial de Validación (AUTH-XXXX-XXXX-XXXX-XXXX): {C_RESET}").strip()
            except (KeyboardInterrupt, EOFError):
                print("\nOperación cancelada por el usuario.")
                return 130
            if not entered:
                print(f"{C_RED}[ERROR] Debe ingresar un serial válido.{C_RESET}")
                return 1
            serial_input = entered
        else:
            show_help()
            return 1
    else:
        first = args[0].strip()
        if first in ("-h", "--help", "ayuda"):
            show_help()
            return 0
        elif first in ("-s", "--estado", "status"):
            show_status()
            return 0
        elif first in ("-f", "--forzar", "--force"):
            print_banner()
            print(f"⚙️  {C_YELLOW}Iniciando anclaje forzado de hardware local...{C_RESET}")
            ok, msg = force_core_hardware_anchor()
            if ok:
                print(f"{C_GREEN}[+] {msg}{C_RESET}")
                print("🔄 Reiniciando servicio de monitoreo (tg-admin-bot)...")
                if restart_bot_service():
                    print(f"{C_GREEN}[+] Servicio tg-admin-bot reiniciado exitosamente.{C_RESET}")
                else:
                    print(f"{C_YELLOW}[!] Advertencia: No se pudo reiniciar el servicio automáticamente. Ejecute: sudo systemctl restart tg-admin-bot{C_RESET}")
                notify_core_console_activation("FORZADO_LOCAL_SHELL")
                print(f"\n{C_BOLD}{C_GREEN}🎉 ¡Sistema anclado y 100% OPERATIVO!{C_RESET}\n")
                return 0
            else:
                print(f"{C_RED}[ERROR] No se pudo anclar el hardware: {msg}{C_RESET}")
                return 1
        else:
            serial_input = first

    # Validar y procesar Serial
    serial = normalize_serial(serial_input)
    if not re.match(r"^AUTH-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$", serial):
        print(f"\n{C_RED}[ERROR] El formato del Serial '{serial}' no es válido.{C_RESET}")
        print("El formato esperado es: AUTH-XXXX-XXXX-XXXX-XXXX (ej: AUTH-FB86-3841-ADC9-A28E)\n")
        return 1

    print_banner()
    print(f"🔍 Validando Serial: {C_BOLD}{serial}{C_RESET} ...")

    ok, msg = activate_hardware_first_boot(serial)
    if ok:
        print(f"\n{C_GREEN}{C_BOLD}[+] {msg}{C_RESET}")
        print(f"{C_GREEN}[+] Huella física de hardware derivada y sincronizada a audit/.sys_anchor.{C_RESET}")
        print(f"{C_GREEN}[+] Estado del sistema: OPERATIONAL.{C_RESET}")

        print("\n🔄 Reiniciando servicio de monitoreo (tg-admin-bot)...")
        if restart_bot_service():
            print(f"{C_GREEN}[+] Servicio tg-admin-bot reiniciado exitosamente.{C_RESET}")
        else:
            print(f"{C_YELLOW}[!] Advertencia: No se pudo reiniciar automáticamente el servicio.{C_RESET}")
            print(f"    Por favor ejecute manualmente: {C_BOLD}sudo systemctl restart tg-admin-bot{C_RESET}")

        print("📡 Despachando notificación de confirmación a Telegram al Administrador...")
        notify_core_console_activation(serial)
        print(f"{C_GREEN}[+] Alerta entregada exitosamente al Owner.{C_RESET}")

        print(f"\n{C_BOLD}{C_CYAN}=============================================================={C_RESET}")
        print(f"{C_BOLD}{C_GREEN} 🎉  ¡INSTALACIÓN VALIDADA CON ÉXITO! EQUIPO 100% OPERACIONAL {C_RESET}")
        print(f"{C_BOLD}{C_CYAN}=============================================================={C_RESET}\n")
        return 0
    else:
        print(f"\n{C_RED}{C_BOLD}❌ Error de Validación:{C_RESET}")
        print(f"{C_RED}   {msg}{C_RESET}\n")
        if "expirado" in msg.lower() or "no hay ningún desafío" in msg.lower():
            print(f"💡 {C_YELLOW}Sugerencia:{C_RESET}")
            print("   1. Reinicie el servicio para generar un nuevo Serial de desafío:")
            print(f"      {C_BOLD}sudo systemctl restart tg-admin-bot{C_RESET}")
            print("   2. Revise el nuevo Serial recibido en Telegram y vuelva a ejecutar:")
            print(f"      {C_BOLD}activar AUTH-XXXX-XXXX-XXXX-XXXX{C_RESET}")
            print("   3. O si está en una sesión local autorizada, fuerce el anclaje con:")
            print(f"      {C_BOLD}activar --forzar{C_RESET}\n")
        return 1


if __name__ == "__main__":
    sys.exit(main())
