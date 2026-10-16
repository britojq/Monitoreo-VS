#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ QUALITY GATE & CERTIFICADOR INTEGRAL DEL SISTEMA: certify_system_integrity.py
# Compilación estricta Blade/PHP, render en frío, auditoría DOM, Python y MariaDB
# Ubicación: /scripts/telegram-admin-bot/tools/certify_system_integrity.py
# License: GNU AGPLv3
# ==============================================================================
"""

import os
import py_compile
import subprocess
import sys
import time
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
VENV_PYTHON = BASE_DIR / "venv" / "bin" / "python3"
if sys.prefix == sys.base_prefix and VENV_PYTHON.exists():
    os.execv(str(VENV_PYTHON), [str(VENV_PYTHON)] + sys.argv)

if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

WEB_DIR = Path("/var/www/monitoreo")
PORTAL_DIR = BASE_DIR / "web_portal"

GREEN = "\033[0;32m"
RED = "\033[0;31m"
CYAN = "\033[0;36m"
YELLOW = "\033[1;33m"
BOLD = "\033[1m"
NC = "\033[0m"


def log(msg: str):
    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{ts}] {msg}")


def certify_python_modules() -> bool:
    """Verifica compilación sintáctica e importación limpia de todos los módulos Python."""
    log(f"{CYAN}{BOLD}▶ AUDITORÍA PYTHON: Verificando compilación sintáctica y módulos...{NC}")
    py_files = list(BASE_DIR.glob("monitor/*.py")) + list(BASE_DIR.glob("tools/*.py")) + list(BASE_DIR.glob("bot_core/**/*.py"))
    failed = []

    for pf in py_files:
        try:
            py_compile.compile(str(pf), doraise=True)
        except py_compile.PyCompileError as e:
            failed.append(f"Error de sintaxis en {pf}: {e}")

    # Verificar importación limpia de módulos críticos
    critical_imports = [
        "monitor.monitor_db",
        "monitor.ups_service",
        "monitor.telegram_dispatcher",
        "tools.post_update",
    ]

    for ci in critical_imports:
        try:
            __import__(ci)
        except Exception as e:
            failed.append(f"Fallo al importar {ci}: {e}")

    if failed:
        for err in failed:
            log(f"{RED}❌ {err}{NC}")
        return False

    log(f"{GREEN}✓ {len(py_files)} archivos Python compilados y certificados sin errores.{NC}")
    return True


def certify_laravel_system() -> bool:
    """Ejecuta el comando Artisan system:certify en el portal web."""
    log(f"{CYAN}{BOLD}▶ AUDITORÍA LARAVEL & BLADE: Ejecutando system:certify...{NC}")
    artisan_bin = PORTAL_DIR / "artisan"
    if not artisan_bin.exists():
        log(f"{RED}❌ No se encontró artisan en {artisan_bin}{NC}")
        return False

    res = subprocess.run(
        ["php", str(artisan_bin), "system:certify"],
        cwd=str(PORTAL_DIR),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )

    print(res.stdout)
    return res.returncode == 0


def sync_and_certify_web_root() -> bool:
    """Sincroniza a /var/www/monitoreo y certifica que la web raíz también pase la auditoría (Error #12)."""
    if not WEB_DIR.exists():
        log(f"{YELLOW}ℹ️ Directorio {WEB_DIR} no existe en este nodo. Omitiendo sincronización local.{NC}")
        return True

    log(f"{CYAN}{BOLD}▶ SINCRONIZACIÓN WEB ROOT (/var/www/monitoreo): Sincronizando y purgando cachés...{NC}")
    rsync_cmd = [
        "sudo", "rsync", "-a",
        "--exclude=vendor",
        "--exclude=node_modules",
        "--exclude=.env",
        "--exclude=storage",
        "--exclude=database/database.sqlite",
        f"{PORTAL_DIR}/",
        f"{WEB_DIR}/",
    ]

    res_sync = subprocess.run(rsync_cmd, check=False)
    if res_sync.returncode != 0:
        log(f"{RED}❌ Falló rsync hacia {WEB_DIR}{NC}")
        return False

    # Purgar cachés en /var/www/monitoreo
    for action in ["view:clear", "route:clear", "config:clear", "cache:clear"]:
        subprocess.run(["php", f"{WEB_DIR}/artisan", action], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False)

    # Correr system:certify en /var/www/monitoreo
    res_cert = subprocess.run(
        ["php", f"{WEB_DIR}/artisan", "system:certify", "--skip-views"],
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )

    if res_cert.returncode != 0:
        log(f"{RED}❌ Falló la certificación en {WEB_DIR}:{NC}\n{res_cert.stdout}")
        return False

    log(f"{GREEN}✓ /var/www/monitoreo sincronizado, con cachés purgadas y certificado al 100%.{NC}")
    return True


def certify_infrastructure_and_runtime() -> bool:
    """Certifica unidades systemd, ausencia de drift en templates, permisos britojab:www-data y journald limpio."""
    log(f"{CYAN}{BOLD}▶ AUDITORÍA INFRAESTRUCTURA & RUNTIME: Verificando systemd, permisos y journald...{NC}")
    failed = []

    # 1. Comprobar binarios en ExecStart/ExecStartPre de system/templates/systemd/
    tpl_dir = BASE_DIR / "system" / "templates" / "systemd"
    if tpl_dir.exists():
        for tpl in tpl_dir.glob("*.service"):
            content = tpl.read_text(encoding="utf-8", errors="ignore")
            for line in content.splitlines():
                line = line.strip()
                if line.startswith("ExecStart=") or line.startswith("ExecStartPre="):
                    val = line.split("=", 1)[1].strip()
                    if val.startswith("-"):
                        val = val[1:].strip()
                    parts = val.split()
                    if parts:
                        bin_p = Path(parts[0])
                        if not bin_p.exists() or not os.access(bin_p, os.X_OK):
                            failed.append(f"Binario no ejecutable en {tpl.name}: {parts[0]}")

    # 2. Comprobar que ningún archivo dentro de /scripts/telegram-admin-bot pertenezca a root
    try:
        res_find = subprocess.run(
            ["find", str(BASE_DIR), "-user", "root"],
            capture_output=True,
            text=True,
            check=False,
        )
        root_files = [f for f in res_find.stdout.splitlines() if f.strip() and ".git" not in f]
        if root_files:
            failed.append(f"Se detectaron {len(root_files)} archivos pertenecientes a root en {BASE_DIR} (ej: {root_files[:3]})")
    except Exception as e:
        failed.append(f"Error comprobando propiedad de archivos: {e}")

    # 3. Comprobar que tg-admin-bot.service no tenga drift con su plantilla
    installed_unit = Path("/etc/systemd/system/tg-admin-bot.service")
    tpl_unit = tpl_dir / "tg-admin-bot.service"
    if installed_unit.exists() and tpl_unit.exists():
        import filecmp
        if not filecmp.cmp(str(installed_unit), str(tpl_unit), shallow=False):
            failed.append(f"Drift detectado entre {installed_unit} y la plantilla {tpl_unit}. Ejecute tools/system_sync.py.")

    # 4. Comprobar estado de tg-admin-bot en systemd
    try:
        res_status = subprocess.run(["systemctl", "is-active", "tg-admin-bot"], capture_output=True, text=True, check=False)
        status_out = res_status.stdout.strip()
        if status_out != "active":
            failed.append(f"tg-admin-bot.service no está activo (estado: '{status_out}')")
    except Exception as e:
        failed.append(f"Error verificando systemctl is-active: {e}")

    # 5. Comprobar journalctl en busca de 203/EXEC
    try:
        res_journal = subprocess.run(
            ["journalctl", "-u", "tg-admin-bot", "-n", "30", "--no-pager"],
            capture_output=True,
            text=True,
            check=False,
        )
        journal_text = res_journal.stdout
        if "status=203/EXEC" in journal_text:
            failed.append("Se detectó error 'status=203/EXEC' en journalctl de tg-admin-bot")
    except Exception as e:
        failed.append(f"Error verificando journalctl: {e}")

    if failed:
        for err in failed:
            log(f"{RED}❌ {err}{NC}")
        return False

    log(f"{GREEN}✓ Infraestructura y runtime certificados: systemd sin drift, permisos correctos y cero fallos 203/EXEC.{NC}")
    return True


def main():
    print(f"\n{BOLD}{CYAN}========================================================================={NC}")
    print(f"{BOLD}{CYAN} 🛡️ BARRERA DE CALIDAD ANTI-REGRESIONES Y CERTIFICACIÓN COMPLETA        {NC}")
    print(f"{BOLD}{CYAN}========================================================================={NC}\n")

    py_ok = certify_python_modules()
    laravel_ok = certify_laravel_system()
    web_ok = sync_and_certify_web_root() if laravel_ok else False
    infra_ok = certify_infrastructure_and_runtime()

    print(f"\n{BOLD}{CYAN}========================================================================={NC}")
    if py_ok and laravel_ok and web_ok and infra_ok:
        print(f"{BOLD}{GREEN} 🎉 CERTIFICACIÓN INTEGRAL SUPERADA AL 100%: SISTEMA BLINDADO Y SEGURO {NC}")
        print(f"{BOLD}{CYAN}========================================================================={NC}\n")
        sys.exit(0)
    else:
        print(f"{BOLD}{RED} ⛔ LA CERTIFICACIÓN FALLÓ: EXISTEN ERRORES QUE DEBEN RESOLVERSE YA    {NC}")
        print(f"{BOLD}{CYAN}========================================================================={NC}\n")
        sys.exit(1)


if __name__ == "__main__":
    main()
