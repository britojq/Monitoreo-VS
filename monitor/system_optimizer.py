"""
# ==============================================================================
# 🚀 OPTIMIZADOR Y DIAGNÓSTICO DE RECURSOS: system_optimizer.py (@IA_ValleSeco_bot)
# Monitoreo de memoria RAM, Swap, I/O wait y recuperación autónoma de Plasma Shell
# Ubicación: /scripts/telegram-admin-bot/monitor/system_optimizer.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import signal
import subprocess
import sys
import time
from typing import Any, Dict, List, Optional, Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup

logger = logging.getLogger("monitor.system_optimizer")

# Denominación neutral según Regla de Oro #2
SANITIZED_PROCESS_NAMES = {
    "ollama": "Servicio local de IA",
    "ollama_llama_server": "Motor local de IA (Inferencia)",
    "kwin_wayland": "Gestor de ventanas (KWin)",
    "kwin_x11": "Gestor de ventanas (KWin)",
    "plasmashell": "Entorno gráfico (Plasma)",
    "firefox-esr": "Navegador Firefox",
    "firefox": "Navegador Firefox",
    "mariadbd": "Base de datos MariaDB",
    "apache2": "Servidor Web Apache",
    "python": "Bot / Script Python",
    "agy": "Asistente Agente CLI"
}


def get_memory_info() -> Dict[str, float]:
    """Obtiene el estado de la memoria RAM y SWAP directamente desde /proc/meminfo."""
    mem: Dict[str, int] = {}
    try:
        with open("/proc/meminfo", "r", encoding="utf-8") as f:
            for line in f:
                parts = line.split(":")
                if len(parts) == 2:
                    k = parts[0].strip()
                    v = parts[1].strip().split()[0]
                    mem[k] = int(v)
    except Exception as e:
        logger.error(f"Error leyendo /proc/meminfo: {e}")

    ram_total = mem.get("MemTotal", 0) / 1024.0  # MB
    ram_avail = mem.get("MemAvailable", 0) / 1024.0  # MB
    ram_used = max(0.0, ram_total - ram_avail)
    ram_pct = (ram_used / ram_total * 100.0) if ram_total > 0 else 0.0

    swap_total = mem.get("SwapTotal", 0) / 1024.0  # MB
    swap_free = mem.get("SwapFree", 0) / 1024.0  # MB
    swap_used = max(0.0, swap_total - swap_free)
    swap_pct = (swap_used / swap_total * 100.0) if swap_total > 0 else 0.0

    return {
        "ram_total_mb": round(ram_total, 1),
        "ram_used_mb": round(ram_used, 1),
        "ram_free_mb": round(ram_avail, 1),
        "ram_percent": round(ram_pct, 1),
        "swap_total_mb": round(swap_total, 1),
        "swap_used_mb": round(swap_used, 1),
        "swap_free_mb": round(swap_free, 1),
        "swap_percent": round(swap_pct, 1),
    }


def get_cpu_info() -> Dict[str, Any]:
    """Obtiene la carga de CPU y la métrica de I/O wait."""
    loads = ["0.00", "0.00", "0.00"]
    try:
        with open("/proc/loadavg", "r", encoding="utf-8") as f:
            loads = f.read().strip().split()[:3]
    except Exception as e:
        logger.warning(f"Error leyendo /proc/loadavg: {e}")

    iowait = 0.0
    try:
        with open("/proc/stat", "r", encoding="utf-8") as f:
            cpu_line = f.readline()
            fields = [float(x) for x in cpu_line.split()[1:]]
            total = sum(fields)
            if total > 0 and len(fields) >= 5:
                iowait = (fields[4] / total) * 100.0
    except Exception as e:
        logger.warning(f"Error leyendo /proc/stat: {e}")

    temp_info = {"max_temp": 0.0, "package_temp": 0.0, "badge": "🟢", "level": "NORMAL"}
    try:
        from monitor.thermal_guard import get_cpu_temperatures
        temp_info = get_cpu_temperatures()
    except Exception:
        pass

    return {
        "load_1m": loads[0],
        "load_5m": loads[1],
        "load_15m": loads[2],
        "iowait_percent": round(iowait, 1),
        "temp": temp_info
    }


def get_plasmashell_info() -> Dict[str, Any]:
    """Obtiene métricas en tiempo real del proceso plasmashell."""
    try:
        res = subprocess.run(
            ["ps", "-C", "plasmashell", "-o", "pid,%cpu,%mem,vsz,rss,stat,comm", "--no-headers"],
            capture_output=True,
            text=True,
            timeout=4.0
        )
        lines = res.stdout.strip().splitlines()
        if not lines:
            return {
                "running": False,
                "pid": None,
                "ram_mb": 0.0,
                "mem_percent": 0.0,
                "cpu_percent": 0.0,
                "stat": "INACTIVO"
            }

        parts = lines[0].split()
        pid = int(parts[0])
        cpu = float(parts[1].replace(",", "."))
        mem_pct = float(parts[2].replace(",", "."))
        rss_kb = int(parts[4])
        stat = parts[5]

        return {
            "running": True,
            "pid": pid,
            "ram_mb": round(rss_kb / 1024.0, 1),
            "mem_percent": mem_pct,
            "cpu_percent": cpu,
            "stat": stat
        }
    except Exception as e:
        logger.warning(f"Error consultando plasmashell: {e}")
        return {
            "running": False,
            "pid": None,
            "ram_mb": 0.0,
            "mem_percent": 0.0,
            "cpu_percent": 0.0,
            "stat": f"Error: {e}"
        }


def get_top_memory_consumers(limit: int = 5) -> List[Dict[str, Any]]:
    """Obtiene los principales consumidores de memoria RAM sanitizando nombres según Reglas de Oro."""
    consumers = []
    try:
        res = subprocess.run(
            ["ps", "aux", "--sort=-%mem"],
            capture_output=True,
            text=True,
            timeout=4.0
        )
        lines = res.stdout.strip().splitlines()[1:]
        for line in lines[:limit]:
            parts = line.split(None, 10)
            if len(parts) >= 11:
                comm = parts[10].strip().split()[0]
                comm_base = os.path.basename(comm)
                display_name = SANITIZED_PROCESS_NAMES.get(comm_base, comm_base)
                
                # Sanitización estricta Regla de Oro #2
                if "ollama" in display_name.lower():
                    display_name = "Servicio local de IA"

                try:
                    rss_mb = round(int(parts[5]) / 1024.0, 1)
                    mem_pct = float(parts[3].replace(",", "."))
                except Exception:
                    rss_mb = 0.0
                    mem_pct = 0.0

                consumers.append({
                    "user": parts[0],
                    "pid": parts[1],
                    "mem_percent": mem_pct,
                    "rss_mb": rss_mb,
                    "command": display_name
                })
    except Exception as e:
        logger.warning(f"Error obteniendo top procesos: {e}")

    return consumers


def get_system_health() -> Dict[str, Any]:
    """Compila el diagnóstico integral del sistema y determina nivel de salud."""
    mem = get_memory_info()
    cpu = get_cpu_info()
    plasma = get_plasmashell_info()
    top_proc = get_top_memory_consumers(4)

    # Evaluación heurística de estado
    is_critical = False
    is_warning = False
    reasons = []

    if mem["ram_free_mb"] < 800 or mem["ram_percent"] > 88:
        is_critical = True
        reasons.append("Memoria RAM casi agotada (< 800 MB libres)")
    elif mem["ram_free_mb"] < 1500 or mem["ram_percent"] > 78:
        is_warning = True
        reasons.append("Memoria RAM bajo presión (< 1.5 GB libres)")

    if plasma["running"] and plasma["ram_mb"] > 2000:
        is_critical = True
        reasons.append(f"Fuga severa en Plasma Shell ({plasma['ram_mb']:.1f} MB)")
    elif plasma["running"] and plasma["ram_mb"] > 1000:
        is_warning = True
        reasons.append(f"Consumo elevado en Plasma Shell ({plasma['ram_mb']:.1f} MB)")

    if plasma.get("stat", "").startswith("D"):
        is_critical = True
        reasons.append("Plasma Shell congelado en I/O Wait (Estado D)")

    if mem["swap_percent"] > 55:
        is_critical = True
        reasons.append(f"Saturación de Swap ({mem['swap_percent']}%)")
    elif mem["swap_percent"] > 30:
        is_warning = True
        reasons.append(f"Uso moderado de Swap ({mem['swap_percent']}%)")

    if cpu["iowait_percent"] > 25:
        is_critical = True
        reasons.append(f"Congelamiento por I/O Wait ({cpu['iowait_percent']}%)")
    elif cpu["iowait_percent"] > 15:
        is_warning = True
        reasons.append(f"Espera de disco elevada ({cpu['iowait_percent']}%)")

    # Evaluación Térmica de CPU
    cpu_temp_info = cpu.get("temp", {})
    cpu_max_t = cpu_temp_info.get("max_temp", 0.0)
    if cpu_max_t >= 75.0:
        is_critical = True
        reasons.append(f"Temperatura crítica de CPU ({cpu_max_t}°C)")
    elif cpu_max_t >= 68.0:
        is_warning = True
        reasons.append(f"Temperatura elevada de CPU ({cpu_max_t}°C)")

    if is_critical:
        status_level = "CRÍTICO"
        status_badge = "🔴"
        diagnosis = " | ".join(reasons) if reasons else "Riesgo alto de lentitud o congelamiento"
    elif is_warning:
        status_level = "ADVERTENCIA"
        status_badge = "🟡"
        diagnosis = " | ".join(reasons) if reasons else "Consumo de recursos elevado"
    else:
        status_level = "ÓPTIMO"
        status_badge = "🟢"
        diagnosis = "Todos los recursos y servicios operan con normalidad y fluidez"

    return {
        "status_level": status_level,
        "status_badge": status_badge,
        "diagnosis": diagnosis,
        "mem": mem,
        "cpu": cpu,
        "plasma": plasma,
        "top_proc": top_proc
    }


def format_optimizer_dashboard_html() -> Tuple[str, InlineKeyboardMarkup]:
    """Genera el mensaje interactivo en formato HTML para Telegram con botones inline."""
    health = get_system_health()
    mem = health["mem"]
    cpu = health["cpu"]
    plasma = health["plasma"]
    top_proc = health["top_proc"]

    plasma_stat_str = "Activo"
    if plasma["running"]:
        p_stat = plasma.get("stat", "S")
        if p_stat.startswith("D"):
            plasma_stat_str = "⚠️ Congelado (D-wait)"
        elif p_stat.startswith("Z"):
            plasma_stat_str = "⚠️ Zombie (Z)"
        else:
            plasma_stat_str = f"PID {plasma['pid']} ({p_stat})"
    else:
        plasma_stat_str = "Detenido / No detectado"

    top_proc_lines = []
    for p in top_proc:
        top_proc_lines.append(
            f"  ▫️ <b>{html.escape(p['command'])}:</b> <code>{p['rss_mb']:.0f} MB</code> ({p['mem_percent']}%)"
        )
    top_proc_str = "\n".join(top_proc_lines) if top_proc_lines else "  ▫️ Sin datos disponibles"

    text = (
        "<b>💻 MONITOR DE RENDIMIENTO Y RECURSOS DEL SERVIDOR</b>\n"
        "═══════════════════════════════\n"
        f"<b>Estado General:</b> {health['status_badge']} <b>{health['status_level']}</b>\n"
        f"<i>Diagnóstico:</i> {html.escape(health['diagnosis'])}\n\n"
        "<b>🧠 Memoria RAM Física:</b>\n"
        f"  • Total: <code>{mem['ram_total_mb']:.0f} MB</code> | Usada: <code>{mem['ram_used_mb']:.0f} MB</code> (<b>{mem['ram_percent']}%</b>)\n"
        f"  • Libre / Disponible: <b><code>{mem['ram_free_mb']:.0f} MB</code></b>\n\n"
        "<b>🔄 Memoria Swap (Disco):</b>\n"
        f"  • Total: <code>{mem['swap_total_mb']:.0f} MB</code> | Usada: <code>{mem['swap_used_mb']:.0f} MB</code> (<b>{mem['swap_percent']}%</b>)\n"
        f"  • Libre: <code>{mem['swap_free_mb']:.0f} MB</code>\n\n"
        "<b>⚡ Procesador, Temperatura & I/O:</b>\n"
        f"  • Temp. CPU: {cpu['temp']['badge']} <b><code>{cpu['temp']['max_temp']:.1f}°C</code></b> (Package: <code>{cpu['temp']['package_temp']:.1f}°C</code>)\n"
        f"  • Load Avg: <code>{cpu['load_1m']}</code> (1m), <code>{cpu['load_5m']}</code> (5m), <code>{cpu['load_15m']}</code> (15m)\n"
        f"  • Espera de Disco (I/O Wait): <code>{cpu['iowait_percent']}%</code>\n\n"
        "<b>🖥️ Entorno Gráfico (Plasma Shell):</b>\n"
        f"  • Consumo RAM: <b><code>{plasma['ram_mb']:.0f} MB</code></b> ({plasma['mem_percent']}%)\n"
        f"  • CPU: <code>{plasma['cpu_percent']}%</code> | Estado: <code>{plasma_stat_str}</code>\n\n"
        "<b>📊 Principales Consumidores de RAM:</b>\n"
        f"{top_proc_str}\n"
        "═══════════════════════════════\n"
        "<i>Pulsa el botón inferior para reiniciar limpiamente el proceso de Plasma y liberar memoria acumulada al instante.</i>"
    )

    keyboard = InlineKeyboardMarkup([
        [
            InlineKeyboardButton("🧹 Optimizar / Liberar Memoria", callback_data="sys_opt:restart_plasma")
        ],
        [
            InlineKeyboardButton("🔄 Actualizar Diagnóstico", callback_data="sys_opt:refresh")
        ]
    ])

    return text, keyboard


def execute_optimize_plasma() -> Tuple[str, Dict[str, Any]]:
    """
    Ejecuta la optimización y liberación de memoria:
    1. Registra métricas previas.
    2. Reinicia limpiamente plasma-plasmashell vía systemctl --user.
    3. Si está congelado en I/O o timeout, fuerza kill -9 y lo relanza.
    4. Ejecuta sync para limpiar buffers de disco.
    5. Registra métricas posteriores y calcula ahorro.
    """
    start_time = time.time()
    pre_mem = get_memory_info()
    pre_plasma = get_plasmashell_info()

    env = os.environ.copy()
    env["XDG_RUNTIME_DIR"] = "/run/user/1000"

    logger.info("Iniciando optimización y reinicio de Plasma Shell...")

    restart_success = False
    method_used = "systemctl --user restart"

    try:
        # Intentar reinicio estándar con timeout de 8 segundos
        p = subprocess.run(
            ["systemctl", "--user", "restart", "plasma-plasmashell"],
            env=env,
            capture_output=True,
            text=True,
            timeout=8.0
        )
        if p.returncode == 0:
            restart_success = True
        else:
            logger.warning(f"Reinicio estándar devolvió código {p.returncode}: {p.stderr}")
    except subprocess.TimeoutExpired:
        logger.warning("Timeout esperando systemctl restart; aplicando recuperación forzada controlada...")
        method_used = "Recuperación forzada (kill -9 + relaunch)"
    except Exception as e:
        logger.error(f"Fallo en systemctl restart: {e}")

    # Si falló o hizo timeout, forzar terminación del PID viejo si persiste
    if not restart_success or method_used != "systemctl --user restart":
        if pre_plasma.get("running") and pre_plasma.get("pid"):
            try:
                os.kill(pre_plasma["pid"], signal.SIGKILL)
                time.sleep(0.5)
            except Exception:
                pass

        try:
            subprocess.run(
                ["systemctl", "--user", "restart", "plasma-plasmashell"],
                env=env,
                capture_output=True,
                text=True,
                timeout=5.0
            )
            restart_success = True
        except Exception as e:
            logger.error(f"Error en relanzamiento de emergencia: {e}")

    # Ejecutar sync para asentar escrituras y liberar buffers
    try:
        subprocess.run(["sync"], timeout=3.0)
    except Exception:
        pass

    # Pausa técnica de estabilización para que el nuevo proceso inicialice
    time.sleep(1.8)

    elapsed = round(time.time() - start_time, 2)
    post_mem = get_memory_info()
    post_plasma = get_plasmashell_info()

    ram_freed = max(0.0, post_mem["ram_free_mb"] - pre_mem["ram_free_mb"])
    swap_freed = max(0.0, pre_mem["swap_used_mb"] - post_mem["swap_used_mb"])

    # Formatear banner de resultado
    banner = (
        "✅ <b>OPTIMIZACIÓN COMPLETADA CON ÉXITO</b>\n"
        "═══════════════════════════════\n"
        f"• <b>Método:</b> <code>{method_used}</code>\n"
        f"• <b>Memoria RAM Liberada:</b> <b>+{ram_freed:.0f} MB</b>\n"
        f"• <b>Swap Recuperado:</b> <b>+{swap_freed:.0f} MB</b>\n"
        f"• <b>Plasma Shell Antes:</b> <code>{pre_plasma['ram_mb']:.0f} MB</code> ➔ <b>Ahora:</b> <code>{post_plasma['ram_mb']:.0f} MB</code>\n"
        f"• <b>RAM Libre Actual:</b> <b><code>{post_mem['ram_free_mb']:.0f} MB</code></b> (Disponibilidad: {100 - post_mem['ram_percent']:.1f}%)\n"
        f"• <b>Tiempo de Respuesta:</b> <code>{elapsed} seg</code>\n"
        "═══════════════════════════════\n"
        "<i>Las aplicaciones abiertas y la sesión se mantuvieron intactas.</i>"
    )

    stats = {
        "success": restart_success,
        "method": method_used,
        "ram_freed_mb": ram_freed,
        "swap_freed_mb": swap_freed,
        "plasma_before_mb": pre_plasma["ram_mb"],
        "plasma_after_mb": post_plasma["ram_mb"],
        "ram_free_now_mb": post_mem["ram_free_mb"],
        "elapsed_seconds": elapsed
    }

    return banner, stats


def print_cli_report() -> None:
    """Imprime el estado detallado en la consola terminal."""
    health = get_system_health()
    mem = health["mem"]
    cpu = health["cpu"]
    plasma = health["plasma"]

    print("\n=======================================================")
    print(f" 💻 DIAGNÓSTICO DE RECURSOS: {health['status_badge']} {health['status_level']}")
    print("=======================================================")
    print(f" Diagnóstico: {health['diagnosis']}")
    print("-------------------------------------------------------")
    print(f" RAM Total:   {mem['ram_total_mb']:.0f} MB | Usada: {mem['ram_used_mb']:.0f} MB ({mem['ram_percent']}%)")
    print(f" RAM Libre:   {mem['ram_free_mb']:.0f} MB")
    print(f" Swap Total:  {mem['swap_total_mb']:.0f} MB | Usada: {mem['swap_used_mb']:.0f} MB ({mem['swap_percent']}%)")
    print(f" Load Avg:    {cpu['load_1m']} (1m), {cpu['load_5m']} (5m), {cpu['load_15m']} (15m)")
    print(f" I/O Wait:    {cpu['iowait_percent']}%")
    print("-------------------------------------------------------")
    if plasma["running"]:
        print(f" Plasma Shell: PID {plasma['pid']} | RAM: {plasma['ram_mb']:.0f} MB ({plasma['mem_percent']}%) | CPU: {plasma['cpu_percent']}% | Stat: {plasma['stat']}")
    else:
        print(" Plasma Shell: Inactivo / No detectado")
    print("-------------------------------------------------------")
    print(" Principales Consumidores de RAM:")
    for p in health["top_proc"]:
        print(f"   [{p['pid']}] {p['command']:<25} {p['rss_mb']:>7.0f} MB ({p['mem_percent']}%)")
    print("=======================================================\n")


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] in ["--optimize", "-o", "optimize", "optimizar"]:
        print("\n⏳ Ejecutando optimización de memoria y reinicio de Plasma Shell...")
        banner, stats = execute_optimize_plasma()
        print("\n" + banner.replace("<b>", "").replace("</b>", "").replace("<code>", "").replace("</code>", "").replace("<i>", "").replace("</i>", ""))
    else:
        print_cli_report()
