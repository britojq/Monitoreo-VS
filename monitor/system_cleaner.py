"""
Módulo de diagnóstico de almacenamiento, detección de anomalías y limpieza interactiva del sistema.
Refactorización 1:1, asíncrona y modular de `limpiador.sh`.
Exclusivo para el Administrador / Creador (Owner).
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import re
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup

logger = logging.getLogger("monitor.system_cleaner")

STANDARD_DIRS = {
    "bin", "boot", "dev", "etc", "home", "lib", "lib32", "lib64", "libx32",
    "media", "mnt", "opt", "proc", "root", "run", "sbin", "srv", "sys", "tmp", "usr", "var"
}


async def run_cmd_async(cmd: List[str], timeout: float = 6.0) -> Tuple[int, str]:
    """Ejecuta un comando de sistema de forma asíncrona."""
    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        return proc.returncode or 0, stdout.decode("utf-8", errors="ignore").strip()
    except Exception as e:
        logger.warning(f"Comando {' '.join(cmd[:3])}... finalizado o timeout: {e}")
        return 1, ""


async def get_disk_status() -> Dict[str, str]:
    """Obtiene el estado de almacenamiento e inodos de la partición raíz /."""
    info = {
        "total": "N/A",
        "used": "N/A",
        "avail": "N/A",
        "percent": "0%",
        "inodes_percent": "0%"
    }
    _, df_out = await run_cmd_async(["df", "-h", "/"])
    for line in df_out.splitlines()[1:]:
        parts = line.split()
        if len(parts) >= 6:
            info["total"] = parts[1]
            info["used"] = parts[2]
            info["avail"] = parts[3]
            info["percent"] = parts[4]
            break

    _, inode_out = await run_cmd_async(["df", "-i", "/"])
    for line in inode_out.splitlines()[1:]:
        parts = line.split()
        if len(parts) >= 5:
            info["inodes_percent"] = parts[4]
            break

    return info


async def scan_root_directories() -> Tuple[List[Tuple[str, str]], List[Tuple[str, str]]]:
    """Escanea directorios no estándar en /."""
    non_standard_list = []
    try:
        for entry in os.scandir("/"):
            if entry.is_dir(follow_symlinks=False) and entry.name not in STANDARD_DIRS:
                if not entry.name.startswith("."):
                    p = f"/{entry.name}"
                    _, du_out = await run_cmd_async(["sudo", "du", "-sh", p], timeout=3.0)
                    if du_out:
                        sz = du_out.split()[0]
                        non_standard_list.append((p, sz))
    except Exception as e:
        logger.warning(f"Error escaneando directorios raíz: {e}")

    return [], non_standard_list


async def scan_suspicious_large_files() -> List[Tuple[str, str]]:
    """Busca archivos grandes (>100 MiB) en zonas temporales o de boot."""
    suspicious = []
    find_cmd = [
        "sudo", "find", "/tmp", "/var/tmp", "/boot", "/root",
        "-maxdepth", "4", "-type", "f", "-size", "+100M", "-exec", "du", "-h", "{}", "+"
    ]
    _, find_out = await run_cmd_async(find_cmd, timeout=4.0)
    for line in find_out.splitlines():
        parts = line.split(maxsplit=1)
        if len(parts) == 2:
            suspicious.append((parts[0], parts[1]))

    return suspicious[:6]


async def get_cleanable_metrics() -> Dict[str, str]:
    """Calcula el tamaño de los elementos limpiables del sistema."""
    metrics = {
        "apt_cache": "0B",
        "journal_size": "0B",
        "rotated_logs_count": "0",
        "user_cache": "0B"
    }

    # 1. Caché APT
    apt_dir = Path("/var/cache/apt/archives")
    if apt_dir.exists():
        _, apt_out = await run_cmd_async(["sudo", "du", "-sh", "/var/cache/apt/archives"], timeout=3.0)
        if apt_out:
            metrics["apt_cache"] = apt_out.split()[0]

    # 2. Journal logs
    _, j_out = await run_cmd_async(["journalctl", "--disk-usage"], timeout=3.0)
    match = re.search(r"take up ([0-9.]+[A-Za-z]+)", j_out)
    if match:
        metrics["journal_size"] = match.group(1)

    # 3. Logs rotados en /var/log
    find_logs_cmd = [
        "sudo", "find", "/var/log",
        "(", "-name", "*.gz", "-o", "-name", "*.[0-9]", "-o", "-name", "*.old", ")",
        "-type", "f"
    ]
    _, logs_out = await run_cmd_async(find_logs_cmd, timeout=3.0)
    log_files = [l for l in logs_out.splitlines() if l.strip()]
    metrics["rotated_logs_count"] = str(len(log_files))

    # 4. User caches
    user_home = Path.home()
    cache_path = user_home / ".cache"
    if cache_path.exists():
        _, cache_out = await run_cmd_async(["du", "-sh", str(cache_path)], timeout=3.0)
        if cache_out:
            metrics["user_cache"] = cache_out.split()[0]

    return metrics


async def scan_top_large_files() -> List[Tuple[str, str]]:
    """Obtiene archivos grandes (>200M) en rutas comunes (/var, /opt, /home, /root, /scripts)."""
    top_files = []
    find_cmd = [
        "sudo", "find", "/var", "/opt", "/home", "/root", "/scripts",
        "-maxdepth", "5", "-type", "f", "-size", "+200M",
        "-exec", "du", "-h", "{}", "+"
    ]
    _, find_out = await run_cmd_async(find_cmd, timeout=5.0)
    for line in sorted(find_out.splitlines(), reverse=True):
        parts = line.split(maxsplit=1)
        if len(parts) == 2:
            top_files.append((parts[0], parts[1]))
    return top_files[:6]


async def execute_clean_task(action: str) -> str:
    """Ejecuta una acción de limpieza específica y devuelve el resultado con feedback."""
    if action == "apt_clean":
        rc, _ = await run_cmd_async(["sudo", "apt-get", "clean"], timeout=10.0)
        return "✅ <b>Caché de APT limpiada con éxito</b> (<code>apt-get clean</code>)." if rc == 0 else "❌ Error limpiando caché de APT."

    elif action == "autoremove":
        rc, out = await run_cmd_async(["sudo", "DEBIAN_FRONTEND=noninteractive", "apt-get", "autoremove", "--purge", "-y"], timeout=60.0)
        return "✅ <b>Paquetes innecesarios y dependencias huérfanas eliminadas</b> (<code>autoremove --purge</code>)." if rc == 0 else f"❌ Error ejecutando autoremove: {out}"

    elif action == "vacuum_journal":
        rc, out = await run_cmd_async(["sudo", "journalctl", "--vacuum-size=50M"], timeout=10.0)
        freed = "Logs reducidos a un máximo de 50 MiB."
        match = re.search(r"freed ([0-9.]+[A-Za-z]+)", out)
        if match:
            freed = f"Espacio liberado en journal: <b>{match.group(1)}</b>."
        return f"✅ <b>Logs de Systemd Journal optimizados:</b> {freed}" if rc == 0 else "❌ Error optimizando journalctl."

    elif action == "clean_rotated_logs":
        del_cmd = [
            "sudo", "find", "/var/log",
            "(", "-name", "*.gz", "-o", "-name", "*.[0-9]", "-o", "-name", "*.old", ")",
            "-type", "f", "-delete"
        ]
        rc, _ = await run_cmd_async(del_cmd, timeout=10.0)
        return "✅ <b>Logs rotados antiguos eliminados</b> (<code>.gz, .1, .old</code> en <code>/var/log</code>)." if rc == 0 else "❌ Error eliminando logs rotados."

    elif action == "clean_all_safe":
        res1 = await execute_clean_task("apt_clean")
        res2 = await execute_clean_task("autoremove")
        res3 = await execute_clean_task("vacuum_journal")
        res4 = await execute_clean_task("clean_rotated_logs")
        return (
            "⚡ <b>Limpieza Integral del Sistema Completada:</b>\n\n"
            f"• {res1}\n"
            f"• {res2}\n"
            f"• {res3}\n"
            f"• {res4}"
        )

    return "⚠️ Acción de limpieza no reconocida."


async def build_cleaner_dashboard() -> Tuple[str, InlineKeyboardMarkup]:
    """Construye el panel interactivo completo de diagnóstico y limpieza."""
    disk = await get_disk_status()
    cleanable = await get_cleanable_metrics()
    _, non_std_dirs = await scan_root_directories()
    susp_files = await scan_suspicious_large_files()
    top_large = await scan_top_large_files()

    lines = [
        "🧹 <b>Panel Interactivo de Limpieza y Diagnóstico (Debian)</b>",
        "<i>Herramienta de mantenimiento del sistema para optimizar espacio en disco.</i>",
        "",
        "💾 <b>Estado del Almacenamiento (/):</b>",
        f"• <b>Espacio Usado:</b> <code>{disk['used']} / {disk['total']} ({disk['percent']})</code>",
        f"• <b>Espacio Disponible:</b> <code>{disk['avail']}</code>",
        f"• <b>Uso de Inodos:</b> <code>{disk['inodes_percent']}</code>",
        "",
        "📦 <b>Recuperación de Espacio Sugerida:</b>",
        f"• <b>Caché APT:</b> <code>~{cleanable['apt_cache']}</code>",
        f"• <b>Logs Journal:</b> <code>~{cleanable['journal_size']}</code>",
        f"• <b>Logs Rotados Antiguos:</b> <code>{cleanable['rotated_logs_count']} archivos</code>",
        f"• <b>Caché de Usuario:</b> <code>~{cleanable['user_cache']}</code>",
    ]

    if non_std_dirs:
        lines.append("")
        lines.append("📁 <b>Directorios Adicionales en Raíz (/):</b>")
        for path, sz in non_std_dirs[:4]:
            lines.append(f"• <code>{html.escape(path)}</code> ({sz})")

    if susp_files:
        lines.append("")
        lines.append("⚠️ <b>Archivos Grandes en Zonas Sensibles (>100M):</b>")
        for sz, path in susp_files[:3]:
            lines.append(f"• <code>{sz}</code> → <code>{html.escape(path)}</code>")

    if top_large:
        lines.append("")
        lines.append("📊 <b>Mayores Archivos Detectados (>200M):</b>")
        for sz, path in top_large[:4]:
            lines.append(f"• <code>{sz}</code> → <code>{html.escape(path)}</code>")

    lines.append("")
    lines.append("👇 <i>Selecciona una acción interactiva para ejecutar la limpieza:</i>")

    keyboard = [
        [
            InlineKeyboardButton("🧹 Limpiar Caché APT", callback_data="cleaner_act:apt_clean"),
            InlineKeyboardButton("🗑️ Autoremove Purge", callback_data="cleaner_act:autoremove")
        ],
        [
            InlineKeyboardButton("📜 Reducir Journal (50M)", callback_data="cleaner_act:vacuum_journal"),
            InlineKeyboardButton("🗄️ Borrar Logs Rotados", callback_data="cleaner_act:clean_rotated_logs")
        ],
        [
            InlineKeyboardButton("⚡ Ejecutar Limpieza Total Segura", callback_data="cleaner_act:clean_all_safe")
        ],
        [
            InlineKeyboardButton("🔄 Actualizar Diagnóstico", callback_data="cleaner_act:refresh")
        ]
    ]

    return "\n".join(lines), InlineKeyboardMarkup(keyboard)
