#!/usr/bin/env python3
"""
# ==============================================================================
# 🛠️ MOTOR DE INFRAESTRUCTURA COMO CÓDIGO (IaC): system_sync.py
# Sincronización atómica de systemd, cron, sudoers, profile, PAM y fail2ban
# Ubicación: /scripts/telegram-admin-bot/tools/system_sync.py
# License: GNU AGPLv3
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# ==============================================================================
"""

from __future__ import annotations

import argparse
import filecmp
import logging
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path
from typing import List, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
TEMPLATES_DIR = BASE_DIR / "system" / "templates"

logging.basicConfig(
    level=logging.INFO,
    format="[%(asctime)s] [SYSTEM-SYNC] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("system_sync")


def get_cmd_prefix() -> List[str]:
    return ["sudo"] if os.geteuid() != 0 else []


def check_executable(bin_path_str: str) -> bool:
    """Verifica si un binario existe físicamente y es ejecutable."""
    p = Path(bin_path_str.strip())
    return p.exists() and os.access(p, os.X_OK)


def extract_executables_from_service(service_path: Path) -> List[str]:
    """Extrae las rutas ejecutables de ExecStart y ExecStartPre de una unidad systemd."""
    executables = []
    if not service_path.exists():
        return executables

    content = service_path.read_text(encoding="utf-8", errors="ignore")
    for line in content.splitlines():
        line = line.strip()
        if line.startswith("ExecStart=") or line.startswith("ExecStartPre="):
            val = line.split("=", 1)[1].strip()
            # Si comienza con guion (ignorar error), removerlo
            if val.startswith("-"):
                val = val[1:].strip()
            # Tomar el primer token (ruta al binario)
            parts = val.split()
            if parts:
                executables.append(parts[0])
    return executables


def sync_systemd_units(dry_run: bool = False) -> Tuple[bool, int]:
    """Sincroniza plantillas de systemd hacia /etc/systemd/system/."""
    src_dir = TEMPLATES_DIR / "systemd"
    dest_dir = Path("/etc/systemd/system")
    cmd_prefix = get_cmd_prefix()

    if not src_dir.exists():
        logger.warning(f"No existe directorio de plantillas: {src_dir}")
        return True, 0

    changed = 0
    errors = 0

    for unit_file in src_dir.glob("*.service"):
        # 1. Validar que los binarios en ExecStart/ExecStartPre existan
        bins = extract_executables_from_service(unit_file)
        missing_bins = [b for b in bins if not check_executable(b)]
        if missing_bins:
            logger.error(
                f"❌ Plantilla {unit_file.name} contiene binarios inexistentes o no ejecutables: {missing_bins}"
            )
            errors += 1
            continue

        target = dest_dir / unit_file.name
        needs_update = False

        if not target.exists():
            needs_update = True
        else:
            try:
                if not filecmp.cmp(str(unit_file), str(target), shallow=False):
                    needs_update = True
            except Exception:
                needs_update = True

        if needs_update:
            logger.info(f"Actualizando unidad systemd: {unit_file.name} -> {target}")
            if not dry_run:
                try:
                    subprocess.run(cmd_prefix + ["cp", str(unit_file), str(target)], check=True)
                    subprocess.run(cmd_prefix + ["chmod", "0644", str(target)], check=True)
                    subprocess.run(cmd_prefix + ["chown", "root:root", str(target)], check=True)
                    changed += 1
                except Exception as e:
                    logger.error(f"Error copiando {unit_file.name}: {e}")
                    errors += 1
        else:
            logger.debug(f"Unidad {unit_file.name} ya está sincronizada.")

    if changed > 0 and not dry_run:
        logger.info("Ejecutando systemctl daemon-reload...")
        subprocess.run(cmd_prefix + ["systemctl", "daemon-reload"], check=False)

    return (errors == 0), changed


def sync_cron_jobs(dry_run: bool = False) -> Tuple[bool, int]:
    """Sincroniza tareas cron hacia /etc/cron.d/."""
    src_dir = TEMPLATES_DIR / "cron.d"
    dest_dir = Path("/etc/cron.d")
    cmd_prefix = get_cmd_prefix()

    if not src_dir.exists():
        return True, 0

    changed = 0
    errors = 0

    for cron_file in src_dir.iterdir():
        if cron_file.is_file() and not cron_file.name.startswith("."):
            target = dest_dir / cron_file.name
            needs_update = not target.exists() or not filecmp.cmp(str(cron_file), str(target), shallow=False)
            if needs_update:
                logger.info(f"Actualizando cron.d: {cron_file.name} -> {target}")
                if not dry_run:
                    try:
                        subprocess.run(cmd_prefix + ["cp", str(cron_file), str(target)], check=True)
                        subprocess.run(cmd_prefix + ["chmod", "0644", str(target)], check=True)
                        subprocess.run(cmd_prefix + ["chown", "root:root", str(target)], check=True)
                        changed += 1
                    except Exception as e:
                        logger.error(f"Error copiando {cron_file.name}: {e}")
                        errors += 1

    return (errors == 0), changed


def sync_sudoers(dry_run: bool = False) -> Tuple[bool, int]:
    """Sincroniza reglas sudoers validando con visudo -cf."""
    src_dir = TEMPLATES_DIR / "sudoers.d"
    dest_dir = Path("/etc/sudoers.d")
    cmd_prefix = get_cmd_prefix()

    if not src_dir.exists():
        return True, 0

    changed = 0
    errors = 0

    for sudo_file in src_dir.iterdir():
        if sudo_file.is_file() and not sudo_file.name.startswith("."):
            target = dest_dir / sudo_file.name
            needs_update = not target.exists()
            if not needs_update:
                try:
                    res_diff = subprocess.run(
                        cmd_prefix + ["cmp", "-s", str(sudo_file), str(target)],
                        check=False,
                    )
                    if res_diff.returncode != 0:
                        needs_update = True
                except Exception:
                    needs_update = True

            if needs_update:
                logger.info(f"Validando y sincronizando sudoers: {sudo_file.name}")
                if not dry_run:
                    # Validar sintaxis con visudo en temporal
                    tmp_file = Path(f"/tmp/{sudo_file.name}.sudo.tmp")
                    try:
                        shutil.copy2(sudo_file, tmp_file)
                        tmp_file.chmod(0o440)
                        val_res = subprocess.run(
                            cmd_prefix + ["visudo", "-c", "-f", str(tmp_file)],
                            capture_output=True,
                            text=True,
                        )
                        if val_res.returncode == 0:
                            subprocess.run(cmd_prefix + ["mv", str(tmp_file), str(target)], check=True)
                            subprocess.run(cmd_prefix + ["chown", "root:root", str(target)], check=True)
                            subprocess.run(cmd_prefix + ["chmod", "0440", str(target)], check=True)
                            changed += 1
                        else:
                            logger.error(f"❌ Error sintáctico en {sudo_file.name}: {val_res.stderr}")
                            errors += 1
                    except Exception as e:
                        logger.error(f"Error instalando sudoers {sudo_file.name}: {e}")
                        errors += 1
                    finally:
                        if tmp_file.exists():
                            try:
                                tmp_file.unlink()
                            except Exception:
                                pass

    return (errors == 0), changed


def sync_profile_hooks(dry_run: bool = False) -> Tuple[bool, int]:
    """Sincroniza scripts de profile.d (como terminal_shield.sh)."""
    src_dir = TEMPLATES_DIR / "profile.d"
    dest_dir = Path("/etc/profile.d")
    cmd_prefix = get_cmd_prefix()

    if not src_dir.exists():
        return True, 0

    changed = 0
    errors = 0

    for prof_file in src_dir.glob("*.sh"):
        target = dest_dir / prof_file.name
        needs_update = not target.exists() or not filecmp.cmp(str(prof_file), str(target), shallow=False)
        if needs_update:
            logger.info(f"Actualizando profile.d: {prof_file.name} -> {target}")
            if not dry_run:
                try:
                    subprocess.run(cmd_prefix + ["cp", str(prof_file), str(target)], check=True)
                    subprocess.run(cmd_prefix + ["chmod", "0644", str(target)], check=True)
                    subprocess.run(cmd_prefix + ["chown", "root:root", str(target)], check=True)
                    changed += 1
                except Exception as e:
                    logger.error(f"Error copiando {prof_file.name}: {e}")
                    errors += 1

    return (errors == 0), changed


def sync_fail2ban(dry_run: bool = False) -> Tuple[bool, int]:
    """Sincroniza configuraciones de Fail2ban (jails, actions, filters)."""
    base_f2b = TEMPLATES_DIR / "fail2ban"
    dest_f2b = Path("/etc/fail2ban")
    cmd_prefix = get_cmd_prefix()

    if not base_f2b.exists() or not dest_f2b.exists():
        return True, 0

    changed = 0
    errors = 0

    mappings = [
        (base_f2b / "jail.d", dest_f2b / "jail.d"),
        (base_f2b / "action.d", dest_f2b / "action.d"),
        (base_f2b / "filter.d", dest_f2b / "filter.d"),
    ]

    for src_sub, dest_sub in mappings:
        if not src_sub.exists():
            continue
        dest_sub.mkdir(parents=True, exist_ok=True)
        for conf_file in src_sub.iterdir():
            if conf_file.is_file() and not conf_file.name.startswith("."):
                target = dest_sub / conf_file.name
                needs_update = not target.exists() or not filecmp.cmp(str(conf_file), str(target), shallow=False)
                if needs_update:
                    logger.info(f"Actualizando fail2ban: {conf_file.name} -> {target}")
                    if not dry_run:
                        try:
                            subprocess.run(cmd_prefix + ["cp", str(conf_file), str(target)], check=True)
                            subprocess.run(cmd_prefix + ["chmod", "0644", str(target)], check=True)
                            subprocess.run(cmd_prefix + ["chown", "root:root", str(target)], check=True)
                            changed += 1
                        except Exception as e:
                            logger.error(f"Error copiando {conf_file.name}: {e}")
                            errors += 1

    if changed > 0 and not dry_run:
        # Recargar fail2ban si el servicio está activo
        try:
            res_f2b = subprocess.run(
                cmd_prefix + ["fail2ban-client", "ping"],
                capture_output=True,
                text=True,
                check=False,
            )
            if "Server replied: pong" in res_f2b.stdout:
                logger.info("Recargando reglas de fail2ban...")
                subprocess.run(cmd_prefix + ["fail2ban-client", "reload"], check=False)
        except Exception:
            pass

    return (errors == 0), changed


def sync_pam_directives(dry_run: bool = False) -> Tuple[bool, int]:
    """Asegura que las directivas obligatorias de PAM estén presentes."""
    cmd_prefix = get_cmd_prefix()
    changed = 0
    errors = 0

    sshd_pam = Path("/etc/pam.d/sshd")
    sudo_pam = Path("/etc/pam.d/sudo")
    su_pam = Path("/etc/pam.d/su")

    hook_script = "/scripts/telegram-admin-bot/monitor/ssh_alert.sh"
    if not Path(hook_script).exists():
        logger.warning(f"Hook PAM no encontrado en {hook_script}")
        return True, 0

    # 1. /etc/pam.d/sshd
    if sshd_pam.exists():
        try:
            content = sshd_pam.read_text(encoding="utf-8", errors="ignore")
            directive = f"session optional pam_exec.so seteuid /bin/bash {hook_script}\n"
            if hook_script not in content:
                logger.info(f"Insertando directiva PAM en {sshd_pam}")
                if not dry_run:
                    new_content = content.rstrip() + "\n" + directive
                    tmp = Path("/tmp/sshd_pam.tmp")
                    tmp.write_text(new_content, encoding="utf-8")
                    subprocess.run(cmd_prefix + ["mv", str(tmp), str(sshd_pam)], check=True)
                    subprocess.run(cmd_prefix + ["chmod", "0644", str(sshd_pam)], check=True)
                    subprocess.run(cmd_prefix + ["chown", "root:root", str(sshd_pam)], check=True)
                    changed += 1
        except Exception as e:
            logger.error(f"Error actualizando PAM sshd: {e}")
            errors += 1

    # 2. /etc/pam.d/sudo y /etc/pam.d/su
    for target_pam in [sudo_pam, su_pam]:
        if target_pam.exists():
            try:
                content = target_pam.read_text(encoding="utf-8", errors="ignore")
                directive = f"session requisite pam_exec.so seteuid stdout /bin/bash {hook_script}\n"
                if hook_script not in content:
                    logger.info(f"Insertando directiva PAM en {target_pam}")
                    if not dry_run:
                        new_content = content.rstrip() + "\n" + directive
                        tmp = Path(f"/tmp/{target_pam.name}_pam.tmp")
                        tmp.write_text(new_content, encoding="utf-8")
                        subprocess.run(cmd_prefix + ["mv", str(tmp), str(target_pam)], check=True)
                        subprocess.run(cmd_prefix + ["chmod", "0644", str(target_pam)], check=True)
                        subprocess.run(cmd_prefix + ["chown", "root:root", str(target_pam)], check=True)
                        changed += 1
            except Exception as e:
                logger.error(f"Error actualizando PAM {target_pam.name}: {e}")
                errors += 1

    return (errors == 0), changed


def sync_symlinks_and_resilience(dry_run: bool = False) -> bool:
    """Garantiza symlinks globales y de resiliencia ante rutas históricas."""
    cmd_prefix = get_cmd_prefix()

    # 1. /usr/local/bin/estatus -> /scripts/telegram-admin-bot/tools/estatus
    estatus_src = BASE_DIR / "tools" / "estatus"
    estatus_dest = Path("/usr/local/bin/estatus")
    if estatus_src.exists() and not dry_run:
        estatus_src.chmod(0o755)
        if not estatus_dest.exists() or not estatus_dest.is_symlink():
            subprocess.run(cmd_prefix + ["ln", "-sf", str(estatus_src), str(estatus_dest)], check=False)

    # 2. Symlink de resiliencia: /scripts/telegram-admin-bot/rollback -> tools/rollback
    # Previene el error 203/EXEC si alguna unidad systemd histórica busca rollback en la raíz
    root_rollback = BASE_DIR / "rollback"
    tools_rollback = BASE_DIR / "tools" / "rollback"
    if tools_rollback.exists() and not dry_run:
        tools_rollback.chmod(0o755)
        if not root_rollback.exists() or not root_rollback.is_symlink():
            subprocess.run(["ln", "-sf", "tools/rollback", str(root_rollback)], check=False)

    return True


def enforce_project_permissions(dry_run: bool = False) -> bool:
    """
    Garantiza que todos los archivos en /scripts/telegram-admin-bot pertenezcan a britojab:www-data con 775.
    Prohíbe terminantemente la presencia de archivos pertenecientes a root dentro del repositorio.
    """
    if dry_run:
        return True

    cmd_prefix = get_cmd_prefix()
    logger.info(f"Normalizando permisos y propiedad (britojab:www-data 775) en {BASE_DIR}...")
    try:
        # Pertenencia
        subprocess.run(cmd_prefix + ["chown", "-R", "britojab:www-data", str(BASE_DIR)], check=True)
        # Directorios en 775
        subprocess.run(["find", str(BASE_DIR), "-type", "d", "-exec", "chmod", "775", "{}", "+"], check=False)
        # Archivos ejecutables y scripts en 775
        for pattern in ["*.py", "*.sh", "tools/estatus", "tools/rollback", "tools/checkpoint", "tools/activar"]:
            subprocess.run(["find", str(BASE_DIR), "-path", f"*/{pattern}", "-exec", "chmod", "775", "{}", "+"], check=False)
        logger.info("✓ Permisos y propiedad normalizados sin archivos huérfanos de root.")
        return True
    except Exception as e:
        logger.error(f"Error normalizando permisos: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(description="Motor de Infraestructura como Código (system_sync.py)")
    parser.add_argument("--check", action="store_true", help="Solo verifica si existen discrepancias (drift) sin aplicar cambios")
    args = parser.parse_args()

    dry_run = args.check

    logger.info(f"=== INICIANDO SINCRONIZACIÓN DE INFRAESTRUCTURA (Modo: {'CHECK' if dry_run else 'APPLY'}) ===")

    all_ok = True
    total_changed = 0

    ok, c = sync_systemd_units(dry_run)
    all_ok = all_ok and ok
    total_changed += c

    ok, c = sync_cron_jobs(dry_run)
    all_ok = all_ok and ok
    total_changed += c

    ok, c = sync_sudoers(dry_run)
    all_ok = all_ok and ok
    total_changed += c

    ok, c = sync_profile_hooks(dry_run)
    all_ok = all_ok and ok
    total_changed += c

    ok, c = sync_fail2ban(dry_run)
    all_ok = all_ok and ok
    total_changed += c

    ok, c = sync_pam_directives(dry_run)
    all_ok = all_ok and ok
    total_changed += c

    sync_symlinks_and_resilience(dry_run)
    enforce_project_permissions(dry_run)

    if all_ok:
        logger.info(f"=== SINCRONIZACIÓN DE INFRAESTRUCTURA COMPLETADA ({total_changed} elementos actualizados) ===")
        sys.exit(0)
    else:
        logger.error("=== LA SINCRONIZACIÓN DE INFRAESTRUCTURA REPORTÓ ERRORES ===")
        sys.exit(1)


if __name__ == "__main__":
    main()
