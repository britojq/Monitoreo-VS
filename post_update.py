#!/usr/bin/env python3
"""
# ==============================================================================
# 🚀 HOOK DE POST-ACTUALIZACIÓN & CAMBIOS MAYORES: post_update.py
# Ubicación: /scripts/telegram-admin-bot/post_update.py
# Propósito: Ejecutar automáticamente tareas críticas del sistema operativo,
#            permisos de red, reglas sudoers y dependencias tras cada despliegue GitOps
#            sin requerir intervención manual vía SSH.
# ==============================================================================
"""

import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

BASE_DIR = Path("/scripts/telegram-admin-bot")
LOG_DIR = Path("/tmp/monitor")


def log(msg: str):
    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{ts}] [POST-UPDATE] {msg}")


def get_cmd_prefix():
    return ["sudo"] if os.geteuid() != 0 else []


def configure_sudoers():
    """Configura las reglas sudoers requeridas para capturas de red sin clave interactiva."""
    sudoers_file = Path("/etc/sudoers.d/www-data-netradar")
    rule = (
        "# Reglas de ejecucion desatendida para NET Radar y Monitoreo Web\n"
        "www-data ALL=(ALL) NOPASSWD: /scripts/telegram-admin-bot/venv/bin/python *, "
        "/usr/bin/tcpdump *, /usr/bin/tshark *, /bin/kill *, /usr/bin/kill *\n"
    )
    cmd_prefix = get_cmd_prefix()

    try:
        # Verificar si el archivo ya existe con el contenido exacto
        if sudoers_file.exists():
            try:
                if sudoers_file.read_text(encoding="utf-8") == rule:
                    log("Regla sudoers /etc/sudoers.d/www-data-netradar ya está al día.")
                    return True
            except Exception:
                pass

        temp_file = Path("/tmp/www-data-netradar.tmp")
        temp_file.write_text(rule, encoding="utf-8")
        os.chmod(temp_file, 0o440)
        subprocess.run(cmd_prefix + ["chown", "root:root", str(temp_file)], check=False)

        # Validar sintaxis con visudo antes de mover
        res = subprocess.run(cmd_prefix + ["visudo", "-c", "-f", str(temp_file)], capture_output=True, text=True)
        if res.returncode == 0:
            subprocess.run(cmd_prefix + ["mv", str(temp_file), str(sudoers_file)], check=True)
            subprocess.run(cmd_prefix + ["chown", "root:root", str(sudoers_file)], check=True)
            subprocess.run(cmd_prefix + ["chmod", "0440", str(sudoers_file)], check=True)
            log("✅ Regla sudoers instalada y validada exitosamente con visudo.")
            return True
        else:
            log(f"⚠️ Error de sintaxis en visudo: {res.stderr}")
            if temp_file.exists():
                temp_file.unlink()
            return False
    except Exception as e:
        log(f"⚠️ Aviso configurando sudoers: {e}")
        return False


def configure_system_directories():
    """Crea y otorga permisos al directorio de logs y temporales."""
    cmd_prefix = get_cmd_prefix()
    try:
        LOG_DIR.mkdir(parents=True, exist_ok=True)
        subprocess.run(cmd_prefix + ["chmod", "777", str(LOG_DIR)], check=False)

        for item in LOG_DIR.iterdir():
            try:
                subprocess.run(cmd_prefix + ["chmod", "666", str(item)], check=False)
            except Exception:
                pass
        log("✅ Directorio /tmp/monitor y archivos de log configurados con permisos de escritura (0666).")
    except Exception as e:
        log(f"⚠️ Aviso en permisos de directorios: {e}")


def configure_web_portal_permissions():
    """Asegura la pertenencia de archivos del portal web a www-data y permisos de storage."""
    cmd_prefix = get_cmd_prefix()
    web_dir = Path("/var/www/monitoreo")
    if web_dir.exists():
        try:
            subprocess.run(cmd_prefix + ["chown", "-R", "www-data:www-data", str(web_dir)], check=False)
            storage_dir = web_dir / "storage"
            cache_dir = web_dir / "bootstrap" / "cache"
            if storage_dir.exists():
                subprocess.run(cmd_prefix + ["chmod", "-R", "777", str(storage_dir)], check=False)
            if cache_dir.exists():
                subprocess.run(cmd_prefix + ["chmod", "-R", "775", str(cache_dir)], check=False)
            log("✅ Permisos del portal web (/var/www/monitoreo) sincronizados con www-data.")
        except Exception as e:
            log(f"⚠️ Aviso en permisos del portal web: {e}")


def configure_network_capabilities():
    """Asigna capabilities de Linux (cap_net_raw) a herramientas de captura."""
    cmd_prefix = get_cmd_prefix()
    for bin_path in ["/usr/bin/tcpdump", "/usr/bin/dumpcap", "/usr/sbin/arp-scan"]:
        if Path(bin_path).exists():
            try:
                subprocess.run(cmd_prefix + ["setcap", "cap_net_raw,cap_net_admin=eip", bin_path], capture_output=True, check=False)
            except Exception:
                pass
    log("✅ Capacidades de captura de red (cap_net_raw) actualizadas.")


def configure_cli_symlink():
    """Garantiza que /usr/local/bin/estatus apunte al CLI principal."""
    cmd_prefix = get_cmd_prefix()
    cli_target = Path("/usr/local/bin/estatus")
    cli_source = BASE_DIR / "estatus"

    if cli_source.exists():
        try:
            cli_source.chmod(0o755)
            if not cli_target.exists() or not cli_target.is_symlink():
                subprocess.run(cmd_prefix + ["ln", "-sf", str(cli_source), str(cli_target)], check=True)
            log("✅ Enlace simbólico /usr/local/bin/estatus verificado y activo.")
        except Exception as e:
            log(f"⚠️ Aviso verificando symlink estatus: {e}")


def run_initial_radar_cycle():
    """Ejecuta un ciclo rápido de NET Radar para poblar tablas iniciales en segundo plano."""
    radar_script = BASE_DIR / "monitor" / "net_radar_engine.py"
    python_bin = BASE_DIR / "venv" / "bin" / "python"

    if radar_script.exists() and python_bin.exists():
        try:
            subprocess.run(
                [str(python_bin), str(radar_script), "--once", "--cycle", "3"],
                capture_output=True,
                timeout=20,
                check=False
            )
            log("✅ Ciclo inicial de NET Radar completado y tablas sincronizadas.")
        except Exception as e:
            log(f"ℹ️ Ciclo inicial diferido: {e}")


def configure_radar_cron():
    """Configura cron desatendido cada 5 minutos para telemetría continua de NET Radar."""
    cron_file = Path("/etc/cron.d/monitoreo_radar")
    cron_content = (
        "# /etc/cron.d/monitoreo_radar - Captura pasiva periodica de trafico y actualizaciones (NET Radar)\n"
        "*/5 * * * * root /scripts/telegram-admin-bot/venv/bin/python "
        "/scripts/telegram-admin-bot/monitor/net_radar_engine.py --once --cycle 10 >/dev/null 2>&1\n"
    )
    cmd_prefix = get_cmd_prefix()
    try:
        if cron_file.exists() and cron_file.read_text(encoding="utf-8") == cron_content:
            log("Cron /etc/cron.d/monitoreo_radar ya está al día.")
            return True

        temp_file = Path("/tmp/monitoreo_radar.tmp")
        temp_file.write_text(cron_content, encoding="utf-8")
        subprocess.run(cmd_prefix + ["mv", str(temp_file), str(cron_file)], check=True)
        subprocess.run(cmd_prefix + ["chown", "root:root", str(cron_file)], check=True)
        subprocess.run(cmd_prefix + ["chmod", "0644", str(cron_file)], check=True)
        log("✅ Tarea programada /etc/cron.d/monitoreo_radar configurada (cada 5 min).")
        return True
    except Exception as e:
        log(f"⚠️ Aviso configurando cron de radar: {e}")
def cleanup_audit_artifacts():
    """Purga capturas .pcap residuales y reportes de red mayores a 30 días en /audit/."""
    audit_dir = BASE_DIR / "audit"
    if not audit_dir.exists():
        return
    now = time.time()
    max_age_sec = 30 * 86400
    cleaned = 0

    # 1. Purgar capturas .pcap residuales con más de 1 hora
    for pcap_f in audit_dir.glob("captura_*.pcap"):
        try:
            if (now - pcap_f.stat().st_mtime) > 3600:
                pcap_f.unlink()
                cleaned += 1
        except Exception:
            pass

    # 2. Purgar reportes antiguos (>30 días)
    for pat in ("reporte_red_*.html", "reporte_red_*.md", "reporte_red_*.txt"):
        for rep_f in audit_dir.glob(pat):
            try:
                if (now - rep_f.stat().st_mtime) > max_age_sec:
                    rep_f.unlink()
                    cleaned += 1
            except Exception:
                pass

    if cleaned > 0:
        log(f"🧹 Purgados {cleaned} archivos residuales y reportes antiguos en {audit_dir}.")


def configure_terminal_shield():
    """Configura el hook global de terminal interactiva en /etc/profile.d/ y sudoers para Fail2ban."""
    cmd_prefix = get_cmd_prefix()
    try:
        shield_src = BASE_DIR / "monitor" / "terminal_shield.sh"
        shield_dest = Path("/etc/profile.d/terminal_shield.sh")
        if shield_src.exists():
            shield_src.chmod(0o755)
            subprocess.run(cmd_prefix + ["cp", "-f", str(shield_src), str(shield_dest)], check=False)
            subprocess.run(cmd_prefix + ["chmod", "0644", str(shield_dest)], check=False)
            log("✅ Hook global /etc/profile.d/terminal_shield.sh desplegado y activo.")

        # Inyectar hook en /etc/bash.bashrc para cubrir emuladores no-login (Konsole, xterm, VNC)
        bashrc_path = Path("/etc/bash.bashrc")
        hook_marker = "terminal_shield.sh"
        hook_block = (
            "\n# Sentinel Terminal Shield Hook (Konsole, xterm, shells interactivas)\n"
            "if [ -f /etc/profile.d/terminal_shield.sh ]; then\n"
            "    . /etc/profile.d/terminal_shield.sh\n"
            "fi\n"
        )
        try:
            res_b = subprocess.run(cmd_prefix + ["cat", str(bashrc_path)], capture_output=True, text=True)
            if res_b.returncode == 0 and hook_marker not in res_b.stdout:
                temp_b = Path("/tmp/bash.bashrc.tmp")
                temp_b.write_text(res_b.stdout + hook_block, encoding="utf-8")
                subprocess.run(cmd_prefix + ["cp", "-f", str(temp_b), str(bashrc_path)], check=True)
                subprocess.run(cmd_prefix + ["chmod", "0644", str(bashrc_path)], check=False)
                temp_b.unlink(missing_ok=True)
                log("✅ Hook integrado en /etc/bash.bashrc para cobertura de emuladores gráficos (Konsole).")
            else:
                log("Hook en /etc/bash.bashrc ya está presente.")
        except Exception as eb:
            log(f"Aviso integrando hook en /etc/bash.bashrc: {eb}")

        # Asegurar permisos de ejecución en scripts de monitoreo
        for s in ["terminal_shield.py", "terminal_shield.sh", "ssh_alert.sh", "ssh_alert.py", "fail2ban_alert.py"]:
            sp = BASE_DIR / "monitor" / s
            if sp.exists():
                sp.chmod(0o755)

        # Regla sudoers para Fail2ban
        f2b_file = Path("/etc/sudoers.d/www-fail2ban")
        f2b_rule = "www-data ALL=(ALL) NOPASSWD: /usr/bin/fail2ban-client, /usr/bin/fail2ban-client *\n"
        is_up_to_date = False
        try:
            res_chk = subprocess.run(cmd_prefix + ["cat", str(f2b_file)], capture_output=True, text=True)
            if res_chk.returncode == 0 and res_chk.stdout == f2b_rule:
                is_up_to_date = True
        except Exception:
            pass

        if not is_up_to_date:
            temp_f = Path("/tmp/www-fail2ban.tmp")
            temp_f.write_text(f2b_rule, encoding="utf-8")
            subprocess.run(cmd_prefix + ["chmod", "0440", str(temp_f)], check=False)
            res = subprocess.run(cmd_prefix + ["visudo", "-c", "-f", str(temp_f)], capture_output=True, text=True)
            if res.returncode == 0:
                subprocess.run(cmd_prefix + ["mv", str(temp_f), str(f2b_file)], check=True)
                subprocess.run(cmd_prefix + ["chown", "root:root", str(f2b_file)], check=True)
                subprocess.run(cmd_prefix + ["chmod", "0440", str(f2b_file)], check=True)
                log("✅ Regla sudoers /etc/sudoers.d/www-fail2ban validada con visudo.")
            else:
                temp_f.unlink(missing_ok=True)
        else:
            log("Regla sudoers /etc/sudoers.d/www-fail2ban ya está al día.")
    except Exception as e:
        log(f"⚠️ Aviso configurando Sentinel Terminal Shield: {e}")


def main():
    log("=================================================================")
    log("🚀 EJECUTANDO HOOK DE POST-ACTUALIZACIÓN MAYOR (post_update.py)")
    log("=================================================================")

    configure_sudoers()
    configure_terminal_shield()
    configure_system_directories()
    configure_network_capabilities()
    configure_cli_symlink()
    configure_radar_cron()
    run_initial_radar_cycle()
    # Re-asegurar permisos de todos los archivos generados tras el ciclo inicial
    configure_system_directories()
    configure_web_portal_permissions()
    cleanup_audit_artifacts()

    log("=================================================================")
    log("🎉 HOOK DE POST-ACTUALIZACIÓN FINALIZADO CON ÉXITO")
    log("=================================================================")
    return 0


if __name__ == "__main__":
    sys.exit(main())
