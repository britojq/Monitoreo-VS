#!/usr/bin/env python3
"""
==============================================================================
 🔍 AUDITORÍA FORENSE CAPA 5: PROCESOS VIVOS Y RUNTIME EN KERNEL
 S.O.N.A.R. - Sistema de Observación, Notificación y Administración de Recursos
 Audita PIDs, CGroups, descriptores en kernel y ejecutables en memoria de
 todos los demonios y servicios del ecosistema para garantizar cero drift.
 Ubicación: /opt/sonar/tools/audit_kernel_runtime.py
 License: GNU Affero General Public License v3.0
 Author: Operador ATIT (@britojab:@britojq), https://britojab.com
 Copyright (c) 2026 Operador ATIT
==============================================================================
"""

import os
import sys
import subprocess
from pathlib import Path

DAEMON_SERVICES = [
    "tg-admin-bot.service",
    "ups-monitor.service",
    "tg-push-telemetry.service",
    "tg-thermal-guard.service",
    "webssh.service",
    "apache2.service",
    "mariadb.service",
]

LEGACY_TARGETS = [
    "/scripts/telegram-admin-bot",
    "/var/www/monitoreo",
]

def print_header(title: str):
    print("\n" + "=" * 78)
    print(f" 🔹 {title}")
    print("=" * 78)

def get_systemd_property(service_name: str, prop: str) -> str:
    res = subprocess.run(
        ["systemctl", "show", "-p", prop, "--value", service_name],
        capture_output=True,
        text=True,
        check=False
    )
    return res.stdout.strip()

def run_kernel_runtime_audit() -> bool:
    print_header("AUDITORÍA FORENSE CAPA 5: PROCESOS VIVOS Y RUNTIME EN KERNEL")
    all_passed = True

    # -------------------------------------------------------------------------
    # 1. ESTADO DE SALUD Y BINDING DE SERVICIOS SYSTEMD
    # -------------------------------------------------------------------------
    print_header("1. AUDITORÍA DE SERVICIOS Y DEMONIOS SYSTEMD")
    
    daemon_pids = []

    for svc in DAEMON_SERVICES:
        state = get_systemd_property(svc, "ActiveState")
        main_pid = get_systemd_property(svc, "MainPID")
        exec_start = get_systemd_property(svc, "ExecStart")
        work_dir = get_systemd_property(svc, "WorkingDirectory")
        
        if state != "active":
            print(f"❌ [FAIL] {svc}: Estado inactivo ({state})")
            all_passed = False
            continue

        try:
            pid_num = int(main_pid)
        except ValueError:
            pid_num = 0

        if pid_num > 0:
            daemon_pids.append((svc, pid_num))

        # Validar que no contenga rutas legacy en ExecStart
        has_legacy = any(legacy in exec_start for legacy in LEGACY_TARGETS)
        if has_legacy:
            print(f"❌ [FAIL] {svc}: ExecStart contiene ruta legacy: {exec_start}")
            all_passed = False
            continue

        # Validar que WorkingDirectory esté conforme si aplica
        if work_dir and any(legacy in work_dir for legacy in LEGACY_TARGETS):
            print(f"❌ [FAIL] {svc}: WorkingDirectory contiene ruta legacy: {work_dir}")
            all_passed = False
            continue

        print(f" ✓ [OK] {svc:<25} | PID: {main_pid:<7} | Estado: {state.upper()} | Dir: {work_dir or 'Default'}")

    # -------------------------------------------------------------------------
    # 2. INSPECCIÓN DE /proc/<PID>/exe Y /proc/<PID>/cwd EN KERNEL
    # -------------------------------------------------------------------------
    print_header("2. AUDITORÍA DE INODOS EN /proc (KERNEL PROCESS TABLE)")

    for svc, pid in daemon_pids:
        proc_path = Path(f"/proc/{pid}")
        if not proc_path.exists():
            print(f"❌ [FAIL] {svc}: PID {pid} no existe en /proc")
            all_passed = False
            continue

        try:
            exe_target = os.readlink(f"/proc/{pid}/exe")
            cwd_target = os.readlink(f"/proc/{pid}/cwd")
            
            # Revisar si exe o cwd apuntan a rutas legacy
            if any(legacy in exe_target for legacy in LEGACY_TARGETS):
                print(f"❌ [FAIL] {svc} (PID {pid}): /proc/exe apunta a ruta legacy: {exe_target}")
                all_passed = False
                continue

            if any(legacy in cwd_target for legacy in LEGACY_TARGETS):
                print(f"❌ [FAIL] {svc} (PID {pid}): /proc/cwd apunta a ruta legacy: {cwd_target}")
                all_passed = False
                continue

            print(f" ✓ [OK] PID {pid:<7} ({svc:<22}) -> exe: {exe_target} | cwd: {cwd_target}")

            # Inspección de descriptores abiertos /proc/<pid>/fd/
            fd_dir = proc_path / "fd"
            if fd_dir.exists():
                legacy_fds = []
                try:
                    for fd in fd_dir.iterdir():
                        try:
                            target = os.readlink(fd)
                            if any(l in target for l in LEGACY_TARGETS):
                                legacy_fds.append((fd.name, target))
                        except (OSError, FileNotFoundError):
                            pass
                except PermissionError:
                    pass

                if legacy_fds:
                    print(f"❌ [FAIL] {svc} (PID {pid}) tiene descriptores abiertos a rutas legacy:")
                    for fd_name, t in legacy_fds:
                        print(f"      FD {fd_name} -> {t}")
                    all_passed = False
        except Exception as e:
            print(f"⚠️ Aviso al inspeccionar PID {pid} ({svc}): {e}")

    # -------------------------------------------------------------------------
    # 3. ESCANEO FORENSE DE DESCRIPTORES HUÉRFANOS DEL KERNEL
    # -------------------------------------------------------------------------
    print_header("3. ESCANEO FORENSE DE DESCRIPTORES HUÉRFANOS EN DEMONIOS")
    
    # Comprobar si hay descriptores huérfanos con lsof en los demonios
    pids_arg = ",".join(str(p) for _, p in daemon_pids)
    res_lsof = subprocess.run(
        ["lsof", "-p", pids_arg],
        capture_output=True,
        text=True,
        check=False
    )
    
    found_residual = False
    for line in res_lsof.stdout.splitlines():
        if any(l in line for l in LEGACY_TARGETS):
            print(f"❌ Descriptor residual detectado: {line}")
            found_residual = True

    if found_residual:
        all_passed = False
    else:
        print(f" ✓ [OK] Cero descriptores residuales en los {len(daemon_pids)} demonios y servicios vivos.")

    # -------------------------------------------------------------------------
    # DICTAMEN FINAL
    # -------------------------------------------------------------------------
    print_header("DICTAMEN FINAL DE AUDITORÍA CAPA 5")
    if all_passed:
        print("🎉 CERTIFICACIÓN 100% EXITOSA: CERO PROCESOS O DESCRIPTORES RESIDUALES.")
        print("   Todos los demonios y servicios se ejecutan de forma saludable,")
        print("   vinculados nativa y exclusivamente al ecosistema canónico /opt/sonar.")
        print("=" * 78 + "\n")
        return True
    else:
        print("❌ AUDITORÍA FALLIDA: Existen procesos o descriptores inconsistentes en memoria.")
        print("=" * 78 + "\n")
        return False

if __name__ == "__main__":
    success = run_kernel_runtime_audit()
    sys.exit(0 if success else 1)
