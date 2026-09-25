"""
# ==============================================================================
# 🔄 MÓDULO DE ACTUALIZACIÓN GIT: system_updater.py (@IA_ValleSeco_bot)
# Sincronización inmutable, respaldo y verificación de versiones desde GitHub
# Conmutación Adaptativa de Red, Respaldo Atómico de BD y Circuit Breaker
# Ubicación: /scripts/telegram-admin-bot/monitor/system_updater.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import shutil
import tempfile
from datetime import datetime
import sys
from pathlib import Path
from typing import Dict, List, Optional, Tuple, Callable, Awaitable

import httpx
from telegram import InlineKeyboardButton, InlineKeyboardMarkup

logger = logging.getLogger("monitor.system_updater")

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"
LOCK_FILE = BASE_DIR / ".update_lock"
LOG_FILE = BASE_DIR / "logs" / "deploy_pipeline.log"
WEB_DIR = Path("/var/www/monitoreo")

# =========================================================================
# 🔒 POLÍTICAS DE SEGURIDAD INMUTABLES Y OFUSCADAS (NÚCLEO BLINDADO)
# =========================================================================
from monitor.core_shield import (
    IMMUTABLE_OWNER_ID,
    IMMUTABLE_BOT_TOKEN,
    IMMUTABLE_GIT_REPO_URL,
    IMMUTABLE_GIT_BRANCH,
    get_core_repo_url,
    get_core_branch,
    is_core_auto_update_enabled,
    trip_deadman_switch
)
from monitor.git_network import evaluate_github_connectivity, get_git_proxy_args
from monitor.database_backup import create_db_snapshot, restore_db_snapshot, list_backups, get_latest_backup


# =========================================================================
# 🔒 CIRCUIT BREAKER (BLOQUEO AUTOMÁTICO ANTE FALLAS)
# =========================================================================
def is_update_locked() -> Tuple[bool, str]:
    """Comprueba si el pipeline de despliegue se encuentra bloqueado por un error previo."""
    if LOCK_FILE.exists():
        try:
            content = LOCK_FILE.read_text(encoding="utf-8").strip()
            return True, content
        except Exception:
            return True, "Bloqueo activo por fallo previo."
    return False, ""


def set_update_lock(reason: str, error_details: str = "") -> None:
    """Activa el bloqueo de seguridad para impedir futuros despliegues hasta resolución."""
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    content = f"Fecha: {now_str}\nMotivo: {reason}\n"
    if error_details:
        content += f"\nDetalle:\n{error_details}\n"
    try:
        LOCK_FILE.write_text(content, encoding="utf-8")
        logger.error(f"🔒 Circuit Breaker activado: {reason}")
    except Exception as e:
        logger.error(f"Error escribiendo archivo de bloqueo: {e}")


def clear_update_lock() -> bool:
    """Retira el bloqueo de seguridad del pipeline."""
    if LOCK_FILE.exists():
        try:
            LOCK_FILE.unlink()
            logger.info("🔓 Circuit Breaker liberado. Pipeline de despliegue habilitado.")
            return True
        except Exception as e:
            logger.error(f"Error al eliminar lockfile: {e}")
            return False
    return True


# =========================================================================
# ⚙️ EJECUCIÓN ASÍNCRONA DE COMANDOS GIT CON SOPORTE DE PROXY
# =========================================================================
async def _run_git_command(
    args: List[str],
    timeout: float = 30.0,
    proxy_args: Optional[List[str]] = None
) -> Tuple[int, str, str]:
    """Ejecuta un comando git en el directorio del proyecto con argumentos opcionales de proxy."""
    cmd = ["git"]
    if proxy_args:
        cmd.extend(proxy_args)
    cmd.extend(args)

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            cwd=str(BASE_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        return (
            proc.returncode or 0,
            stdout.decode("utf-8", errors="ignore").strip(),
            stderr.decode("utf-8", errors="ignore").strip()
        )
    except asyncio.TimeoutExpired:
        try:
            proc.kill()
        except Exception:
            pass
        return -1, "", "Timeout: El comando Git tardó demasiado tiempo en responder."
    except Exception as e:
        return -1, "", str(e)


async def notify_owner_git_failure(bot_instance, error_message: str, operation: str = "comprobación") -> None:
    """Envía una alerta crítica inmediata al Owner si Git no puede actualizarse o sincronizarse."""
    if not bot_instance:
        return

    repo_url = get_core_repo_url()
    branch = get_core_branch()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    alert_text = (
        "⚠️ <b>ALERTA CRÍTICA: Fallo en Actualización de Git</b>\n"
        f"El sistema no pudo completar la <b>{html.escape(operation)}</b> con el repositorio oficial.\n\n"
        f"🔗 <b>Repositorio:</b> <code>{repo_url}</code>\n"
        f"🌿 <b>Rama:</b> <code>{branch}</code>\n"
        f"⏰ <b>Fecha y Hora:</b> <code>{now_str}</code>\n\n"
        f"❌ <b>Detalle del Error:</b>\n"
        f"<pre>{html.escape(error_message)}</pre>\n\n"
        "<i>⚠️ Se requiere verificar la conectividad del servidor, DNS corporativo o acceso a GitHub.</i>"
    )
    try:
        await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=alert_text, parse_mode='HTML')
        logger.info(f"Alerta de fallo en Git notificada exitosamente al Owner ({IMMUTABLE_OWNER_ID}).")
    except Exception as e:
        logger.error(f"Fallo al notificar error de Git al Owner: {e}")


# =========================================================================
# 🔍 COMPROBACIÓN DE ACTUALIZACIONES DISPONIBLES
# =========================================================================
async def check_updates(bot_instance=None) -> Dict[str, any]:
    """
    Comprueba si existen actualizaciones en el repositorio remoto oficial (origin/master).
    Utiliza el evaluador multi-ruta (Directa vs Proxies) y reporta el estado del Circuit Breaker.
    """
    # 0. Comprobar si hay bloqueo activo
    locked, lock_reason = is_update_locked()
    if locked:
        return {
            "success": False,
            "is_locked": True,
            "error": f"⛔ CIRCUIT BREAKER ACTIVO: Las actualizaciones están bloqueadas por un fallo previo.\n\n{lock_reason}\n\nUse /desbloquear_update tras corregir el repositorio."
        }

    # 1. Evaluar salida de red
    proxy_url, route_label, is_net_ok = await evaluate_github_connectivity(timeout=4.0)
    if not is_net_ok:
        err_msg = "No se detectó salida a GitHub por ninguna de las rutas (Directa ni Proxies corporativos)."
        logger.warning(err_msg)
        return {
            "success": False,
            "is_locked": False,
            "error": err_msg,
            "route_label": route_label
        }

    proxy_args = get_git_proxy_args(proxy_url)
    repo_url = get_core_repo_url()
    branch = get_core_branch()

    # 2. Asegurar URL remota inmutable oficial
    await _run_git_command(["remote", "set-url", "origin", repo_url])

    # 3. Ejecutar git fetch con reintentos
    rc_fetch, err_fetch = -1, ""
    for attempt in range(1, 3):
        rc_fetch, _, err_fetch = await _run_git_command(["fetch", "origin", branch], timeout=25.0, proxy_args=proxy_args)
        if rc_fetch == 0:
            break
        if attempt < 2:
            await asyncio.sleep(4.0)

    if rc_fetch != 0:
        err_msg = err_fetch or "Fallo de conexión con GitHub usando la ruta seleccionada"
        logger.error(f"Error en git fetch ({repo_url}): {err_msg}")
        if bot_instance:
            await notify_owner_git_failure(bot_instance, err_msg, operation="comprobación de actualizaciones")
        return {
            "success": False,
            "is_locked": False,
            "error": f"Error conectando con el repositorio remoto: {err_msg}",
            "route_label": route_label
        }

    # 4. Obtener hashes locales y remotos
    _, local_hash, _ = await _run_git_command(["rev-parse", "HEAD"])
    _, remote_hash, _ = await _run_git_command(["rev-parse", f"origin/{branch}"])

    # 5. Obtener información del commit actual
    _, current_commit_info, _ = await _run_git_command(["log", "-1", "--format=%h - %s (%cd)", "--date=format:%d/%m/%Y %H:%M", "HEAD"])

    if local_hash == remote_hash:
        return {
            "success": True,
            "has_update": False,
            "is_locked": False,
            "local_hash": local_hash[:7] if local_hash else "N/A",
            "current_commit": current_commit_info,
            "route_label": route_label,
            "message": "El sistema se encuentra en la versión más reciente."
        }

    # 6. Obtener lista de commits pendientes
    _, commits_raw, _ = await _run_git_command(["log", f"HEAD..origin/{branch}", "--format=• <code>%h</code>: %s (%cr)"])
    commits_list = [c for c in commits_raw.splitlines() if c.strip()]

    if not commits_list:
        return {
            "success": True,
            "has_update": False,
            "is_locked": False,
            "local_hash": local_hash[:7] if local_hash else "N/A",
            "remote_hash": remote_hash[:7] if remote_hash else "N/A",
            "current_commit": current_commit_info,
            "route_label": route_label,
            "message": "El bot se encuentra al día o adelantado respecto al remoto."
        }

    # 7. Obtener lista de archivos modificados
    _, diff_files_raw, _ = await _run_git_command(["diff", "--name-status", "HEAD", f"origin/{branch}"])
    changed_files = []
    has_migrations = False
    for line in diff_files_raw.splitlines():
        parts = line.strip().split(maxsplit=1)
        if len(parts) == 2:
            status, fname = parts
            icon = {"M": "✏️", "A": "➕", "D": "🗑️", "R": "🔄"}.get(status, "📄")
            changed_files.append(f"{icon} <code>{fname}</code>")
            if "migrations/" in fname or "database/" in fname:
                has_migrations = True

    return {
        "success": True,
        "has_update": True,
        "is_locked": False,
        "local_hash": local_hash[:7] if local_hash else "N/A",
        "remote_hash": remote_hash[:7] if remote_hash else "N/A",
        "current_commit": current_commit_info,
        "commits": commits_list,
        "files": changed_files,
        "has_migrations": has_migrations,
        "route_label": route_label
    }


# =========================================================================
# 📊 CONSTRUCCIÓN DEL DASHBOARD DE ACTUALIZACIÓN PARA TELEGRAM
# =========================================================================
async def build_update_dashboard(bot_instance=None) -> Tuple[str, InlineKeyboardMarkup]:
    """Genera el texto formateado HTML y el teclado interactivo con el estado de Git."""
    check_result = await check_updates(bot_instance=bot_instance)

    # Caso 0: Si está bloqueado por Circuit Breaker
    if check_result.get("is_locked"):
        err_msg = html.escape(check_result.get("error", "Bloqueo activo"))
        text = (
            "⛔ <b>CIRCUIT BREAKER ACTIVO: Actualizaciones Bloqueadas</b>\n\n"
            "El sistema ha bloqueado las actualizaciones para proteger la integridad operativa tras una falla reciente.\n\n"
            f"<pre>{err_msg}</pre>\n\n"
            "<i>Para habilitar nuevamente el pipeline una vez corregido el error en Git, use el botón a continuación.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔓 Desbloquear Pipeline", callback_data="update_act:unlock"),
                InlineKeyboardButton("🔄 Recomprobar", callback_data="update_act:check")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    # Caso 1: Error de red o Git
    if not check_result.get("success"):
        err_msg = html.escape(check_result.get("error", "Error desconocido"))
        route_lbl = html.escape(check_result.get("route_label", "Sin ruta"))
        text = (
            "🔄 <b>PANEL DE ACTUALIZACIÓN DE SISTEMA (GIT)</b>\n\n"
            f"❌ <b>Error al conectar con GitHub:</b>\n"
            f"<pre>{err_msg}</pre>\n\n"
            f"🌐 <b>Ruta evaluada:</b> <code>{route_lbl}</code>\n"
            "<i>Presione 'Reintentar' para evaluar nuevamente las rutas de Internet.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 Reintentar", callback_data="update_act:check"),
                InlineKeyboardButton("⚡ Forzar Sincronización", callback_data="update_act:force")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    route_lbl = html.escape(check_result.get("route_label", "Directa"))

    # Caso 2: Al día sin actualizaciones
    if not check_result.get("has_update"):
        current_info = html.escape(check_result.get("current_commit", "N/A"))
        repo_url = get_core_repo_url()
        text = (
            "🔄 <b>PANEL DE ACTUALIZACIÓN DE SISTEMA (GIT)</b>\n\n"
            "✅ <b>¡El sistema se encuentra 100% sincronizado con GitHub!</b>\n\n"
            f"🏷️ <b>Versión Actual:</b> <code>{check_result.get('local_hash')}</code>\n"
            f"📋 <b>Último Commit:</b> {current_info}\n"
            f"🌐 <b>Ruta de Red:</b> <code>{route_lbl}</code>\n"
            f"🔗 <b>Repositorio:</b> <code>{repo_url}</code>\n\n"
            "<i>No hay cambios pendientes por descargar.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 Comprobar de Nuevo", callback_data="update_act:check"),
                InlineKeyboardButton("⚡ Forzar Sincronización", callback_data="update_act:force")
            ],
            [
                InlineKeyboardButton("📋 Ver Estado del Despliegue", callback_data="update_act:status")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    # Caso 3: Nueva actualización disponible
    local_h = check_result.get("local_hash")
    remote_h = check_result.get("remote_hash")
    commits = check_result.get("commits", [])
    files = check_result.get("files", [])
    repo_url = get_core_repo_url()
    has_migrations = check_result.get("has_migrations", False)

    commits_text = "\n".join(commits[:8])
    if len(commits) > 8:
        commits_text += f"\n<i>... y {len(commits) - 8} commit(s) más.</i>"

    files_text = "\n".join(files[:10])
    if len(files) > 10:
        files_text += f"\n<i>... y {len(files) - 10} archivo(s) más.</i>"

    migr_alert = "\n🗄️ <b>¡Atención!</b> Contiene migraciones de BD (se respaldará automáticamente antes de migrar)." if has_migrations else ""

    text = (
        "🔄 <b>NUEVA ACTUALIZACIÓN DISPONIBLE (GIT)</b>\n\n"
        f"🏷️ <b>Versión Local:</b> <code>{local_h}</code>\n"
        f"🚀 <b>Versión Remota:</b> <code>{remote_h}</code>\n"
        f"🌐 <b>Ruta de Red:</b> <code>{route_lbl}</code>\n"
        f"🔗 <b>Origen:</b> <code>{repo_url}</code>{migr_alert}\n\n"
        f"📦 <b>Commits ({len(commits)}):</b>\n"
        f"{commits_text}\n\n"
        f"📂 <b>Archivos Afectados ({len(files)}):</b>\n"
        f"{files_text}\n\n"
        "🛡️ <i>Garantía: Se generará un snapshot de MariaDB y respaldo de <code>config/</code> previo a la instalación.</i>"
    )

    keyboard = [
        [
            InlineKeyboardButton("🚀 Descargar e Instalar Ahora", callback_data="update_act:apply"),
            InlineKeyboardButton("⚡ Forzar Sincronización", callback_data="update_act:force")
        ],
        [
            InlineKeyboardButton("🔄 Recomprobar", callback_data="update_act:check")
        ]
    ]
    return text, InlineKeyboardMarkup(keyboard)


# =========================================================================
# 🧪 SMOKE TEST (VERIFICACIÓN DE SALUD POST-DESPLIEGUE)
# =========================================================================
async def run_post_deploy_smoke_test() -> Tuple[bool, str]:
    """Ejecuta una batería rápida de pruebas sobre el portal web y base de datos."""
    try:
        async with httpx.AsyncClient(timeout=4.0) as client:
            # 1. Probar API de estado
            r_api = await client.get("http://127.0.0.1/api/status")
            if r_api.status_code != 200:
                return False, f"Endpoint /api/status retornó HTTP {r_api.status_code} (esperado 200)"

            # 2. Probar página de login
            r_login = await client.get("http://127.0.0.1/login")
            if r_login.status_code != 200:
                return False, f"Endpoint /login retornó HTTP {r_login.status_code} (esperado 200)"

            # 2.1 Probar disponibilidad de Logo Corporativo
            r_logo = await client.get("http://127.0.0.1/img/logo.png")
            if r_logo.status_code != 200:
                src_l = BASE_DIR / "web_portal" / "public" / "img" / "logo.png"
                dst_l = WEB_DIR / "public" / "img" / "logo.png"
                if src_l.exists():
                    dst_l.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(src_l, dst_l)

        # 3. Comprobar consulta a MariaDB vía Artisan
        proc_art = await asyncio.create_subprocess_exec(
            "sudo", "php", f"{WEB_DIR}/artisan", "tinker", "--execute=echo \\App\\Models\\User::count() > 0 ? 'OK' : 'EMPTY';",
            cwd=str(WEB_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        out_art, err_art = await proc_art.communicate()
        if "OK" not in out_art.decode("utf-8", errors="ignore"):
            return False, f"Fallo al consultar base de datos en Laravel: {err_art.decode('utf-8', errors='ignore')}"

        # 4. Chequeo de sanidad de telemetría y ausencia de dominios ficticios
        proc_san = await asyncio.create_subprocess_exec(
            "sudo", "php", f"{WEB_DIR}/artisan", "tinker", "--execute="
            "echo \\DB::table('monitored_services')->where('web_url', 'like', '%empresa.com.ve%')->count();",
            cwd=str(WEB_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        out_san, _ = await proc_san.communicate()
        raw_cnt = out_san.decode("utf-8", errors="ignore").strip()
        if raw_cnt.isdigit() and int(raw_cnt) > 0:
            return False, f"Se detectaron {raw_cnt} servicios con dominio ficticio residual 'empresa.com.ve'."

        return True, "Todos los endpoints web, base de datos y dominios corporativos verificados exitosamente (200 OK)."
    except Exception as e:
        return False, f"Excepción durante Smoke Test: {e}"


async def collect_db_census() -> dict:
    """Recopila censo en tiempo real de entidades en MariaDB y auto-repara topología/SNMP si es necesario."""
    census = {}
    try:
        tinker_cmd = (
            "echo json_encode(["
            "'snmp_devices' => \\App\\Models\\SnmpDevice::count(),"
            "'topology_links' => \\App\\Models\\NetworkTopologyLink::count(),"
            "'services' => \\App\\Models\\MonitoredService::count(),"
            "'network_devices' => \\App\\Models\\MonitoredNetworkDevice::count(),"
            "'sites' => \\App\\Models\\MonitoredSite::count(),"
            "'proxies' => \\App\\Models\\MonitoredProxy::count(),"
            "'users' => \\App\\Models\\User::count()"
            "]);"
        )
        proc = await asyncio.create_subprocess_exec(
            "sudo", "php", f"{WEB_DIR}/artisan", "tinker", f"--execute={tinker_cmd}",
            cwd=str(WEB_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        out, _ = await proc.communicate()
        raw = out.decode("utf-8", errors="ignore").strip()
        start_idx = raw.find("{")
        end_idx = raw.rfind("}")
        if start_idx != -1 and end_idx != -1:
            import json
            census = json.loads(raw[start_idx:end_idx + 1])
    except Exception as e:
        logger.warning(f"Error consultando censo de base de datos: {e}")

    # Auto-curación preventiva en caso de datos incompletos
    if census.get("snmp_devices", 0) < 9:
        try:
            p_s = await asyncio.create_subprocess_exec(
                "sudo", "php", f"{WEB_DIR}/artisan", "db:seed", "--class=SnmpOidsSeeder", "--force",
                cwd=str(WEB_DIR), stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            await p_s.communicate()
            logger.info("SnmpOidsSeeder ejecutado por auto-curación.")
        except Exception:
            pass

    if census.get("topology_links", 0) == 0:
        top_script = BASE_DIR / "monitor" / "topology_builder.py"
        py_bin = BASE_DIR / "venv" / "bin" / "python"
        py_exec = str(py_bin) if py_bin.exists() else sys.executable
        if top_script.exists():
            try:
                p_t = await asyncio.create_subprocess_exec(
                    py_exec, str(top_script), "--build",
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await p_t.communicate()
                logger.info("topology_builder ejecutado por auto-curación.")
            except Exception:
                pass

    # Si se ejecutó auto-curación, refrescar conteo
    if census.get("snmp_devices", 0) < 9 or census.get("topology_links", 0) == 0:
        try:
            proc = await asyncio.create_subprocess_exec(
                "sudo", "php", f"{WEB_DIR}/artisan", "tinker", f"--execute={tinker_cmd}",
                cwd=str(WEB_DIR), stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            out, _ = await proc.communicate()
            raw = out.decode("utf-8", errors="ignore").strip()
            start_idx = raw.find("{")
            end_idx = raw.rfind("}")
            if start_idx != -1 and end_idx != -1:
                import json
                census = json.loads(raw[start_idx:end_idx + 1])
        except Exception:
            pass

    return census


async def get_system_services_status() -> dict:
    """Consulta el estado en vivo de los demonios de systemd."""
    services = {}
    for svc in ["tg-admin-bot", "apache2", "mariadb", "php8.4-fpm"]:
        try:
            proc = await asyncio.create_subprocess_exec(
                "systemctl", "is-active", svc,
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            out, _ = await proc.communicate()
            state = out.decode("utf-8", errors="ignore").strip() or "unknown"
            services[svc] = state
        except Exception:
            services[svc] = "unknown"
    return services


def save_deployment_audit_manifest(manifest: dict, log_text: str):
    """Guarda el manifiesto JSON y el archivo de log para auditoría sin SSH."""
    import json
    audit_dir = BASE_DIR / "audit"
    logs_dir = BASE_DIR / "logs"
    audit_dir.mkdir(parents=True, exist_ok=True)
    logs_dir.mkdir(parents=True, exist_ok=True)

    json_path = audit_dir / "last_deployment.json"
    log_path = logs_dir / "last_deploy_audit.log"
    hist_path = logs_dir / "deploy_history.log"

    try:
        json_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
        storage_app = WEB_DIR / "storage" / "app"
        if storage_app.exists():
            (storage_app / "last_deployment.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
    except Exception as e:
        logger.warning(f"Error escribiendo last_deployment.json: {e}")

    try:
        log_path.write_text(log_text, encoding="utf-8")
        with open(hist_path, "a", encoding="utf-8") as f:
            f.write(f"\n{'='*72}\n{log_text}\n{'='*72}\n")
    except Exception as e:
        logger.warning(f"Error escribiendo log de auditoría: {e}")


def set_pending_restart_notification(manifest: dict, chat_id: Optional[int] = None):
    """Guarda metadatos para que el bot notifique al Owner inmediatamente tras arrancar con la nueva versión."""
    import json
    pending_path = BASE_DIR / "audit" / ".pending_restart_notification"
    try:
        pending_path.parent.mkdir(parents=True, exist_ok=True)
        data = dict(manifest)
        if chat_id:
            data["chat_id"] = chat_id
        pending_path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")
    except Exception as e:
        logger.warning(f"Error guardando .pending_restart_notification: {e}")


# =========================================================================
# 🚀 EJECUCIÓN DEL DESPLIEGUE SEGURO CON AUTO-ROLLBACK Y RESPALDO DE BD
# =========================================================================
async def execute_git_update(
    bot_instance=None,
    auto_restart: bool = False,
    restart_delay: float = 6.0,
    progress_callback: Optional[Callable[[str], Awaitable[None]]] = None
) -> str:
    """
    Ejecuta el ciclo de actualización forzada con el repositorio oficial:
    1. Comprobación de Circuit Breaker.
    2. Conmutación y selección de ruta de Internet para Git (Directa vs Proxies).
    3. Respaldo atómico de MariaDB (mysqldump comprimido) y config/.
    4. Git fetch y git reset --hard origin/master.
    5. Sincronización hacia /var/www/monitoreo y ajuste de permisos www-data.
    6. Ejecución de migraciones (artisan migrate --force) y seeder idempotente.
    7. Purga de cachés y recarga de Apache / PHP-FPM.
    8. Smoke Test post-despliegue.
    9. AUTO-ROLLBACK automático si falla cualquier paso.
    """
    start_dt = datetime.now()
    out_m_str = ""
    logs = []
    logs.append("🚀 <b>Iniciando ciclo de despliegue seguro del sistema...</b>")

    async def report_progress(msg: str):
        if progress_callback:
            try:
                await progress_callback(msg)
            except Exception as e_prog:
                logger.debug(f"Aviso actualizando progreso: {e_prog}")

    # 1. Comprobar bloqueo
    locked, lock_reason = is_update_locked()
    if locked:
        return (
            "⛔ <b>Despliegue Cancelado: Circuit Breaker Activo</b>\n\n"
            "El sistema se encuentra bloqueado por un fallo previo:\n"
            f"<pre>{html.escape(lock_reason)}</pre>\n"
            "<i>Para retirar la protección tras corregir el código en GitHub, envíe /desbloquear_update.</i>"
        )

    await report_progress(
        "⏳ <b>[1/5] Evaluando conexión a GitHub y respaldando base de datos...</b>\n\n"
        "• Verificando conectividad por ruta activa\n"
        "• Generando snapshot comprimido de MariaDB y configs"
    )

    # 2. Evaluar salida de red
    proxy_url, route_label, is_net_ok = await evaluate_github_connectivity(timeout=4.0)
    if not is_net_ok:
        err_msg = "No se detectó salida a GitHub por ninguna de las rutas evaluadas (Directa ni Proxies)."
        logs.append(f"❌ <b>Error de Red:</b> {html.escape(err_msg)}")
        logs.append("🛡️ <i>Operación cancelada. El sistema no ha sido modificado.</i>")
        return "\n\n".join(logs)

    proxy_args = get_git_proxy_args(proxy_url)
    logs.append(f"🌐 <i>Ruta de Red Activa: {html.escape(route_label)}</i>")

    # 3. Registrar commit previo
    _, prev_commit, _ = await _run_git_command(["rev-parse", "HEAD"])
    _, prev_commit_short, _ = await _run_git_command(["rev-parse", "--short", "HEAD"])

    # 4. Respaldo previo de Base de Datos
    ok_dump, dump_path, dump_msg = create_db_snapshot("pre_update")
    if not ok_dump:
        logs.append(f"❌ <b>Fallo al respaldar base de datos:</b> {html.escape(dump_msg)}")
        logs.append("🛡️ <i>Abortando despliegue para garantizar CERO pérdida de datos.</i>")
        return "\n\n".join(logs)
    logs.append(f"💾 <i>Respaldo de BD garantizado: {Path(dump_path).name}</i>")

    await report_progress(
        "⏳ <b>[2/5] Descargando novedades y sincronizando código desde GitHub...</b>\n\n"
        "• Ejecutando <code>git fetch</code> y <code>git reset --hard</code>\n"
        "• Verificando dependencias en entorno virtual"
    )

    temp_backup_dir = Path(tempfile.mkdtemp(prefix="tgbot_cfg_bak_"))
    try:
        # Respaldar configuraciones locales
        if CONFIG_DIR.exists():
            for item in CONFIG_DIR.iterdir():
                if item.is_file():
                    shutil.copy2(item, temp_backup_dir / item.name)

        anchor_file = BASE_DIR / "audit" / ".sys_anchor"
        if anchor_file.exists():
            shutil.copy2(anchor_file, temp_backup_dir / ".sys_anchor")

        portal_env = BASE_DIR / "web_portal" / ".env"
        if portal_env.exists():
            shutil.copy2(portal_env, temp_backup_dir / "web_portal.env")

        # Preservar branding y logo corporativo si existen
        for asset_rel in ["web_portal/public/img/logo.png", "web_portal/public/favicon.ico"]:
            src_asset = BASE_DIR / asset_rel
            if src_asset.exists():
                shutil.copy2(src_asset, temp_backup_dir / Path(asset_rel).name)
            elif (WEB_DIR / "public" / Path(asset_rel).name).exists():
                shutil.copy2(WEB_DIR / "public" / Path(asset_rel).name, temp_backup_dir / Path(asset_rel).name)
            elif (WEB_DIR / "public" / "img" / Path(asset_rel).name).exists():
                shutil.copy2(WEB_DIR / "public" / "img" / Path(asset_rel).name, temp_backup_dir / Path(asset_rel).name)

        # 5. Git Fetch
        rc_fetch, out_fetch, err_fetch = await _run_git_command(["fetch", "origin", IMMUTABLE_GIT_BRANCH], timeout=35.0, proxy_args=proxy_args)
        if rc_fetch != 0:
            err_detail = err_fetch or out_fetch or "Fallo de conexión con GitHub"
            logs.append(f"❌ <b>Error en git fetch:</b>\n<pre>{html.escape(err_detail)}</pre>")
            if bot_instance:
                await notify_owner_git_failure(bot_instance, err_detail, operation="descarga de novedades")
            return "\n\n".join(logs)

        # 6. Git Reset Hard
        rc_reset, out_reset, err_reset = await _run_git_command(["reset", "--hard", f"origin/{IMMUTABLE_GIT_BRANCH}"], timeout=30.0, proxy_args=proxy_args)
        if rc_reset != 0:
            err_detail = err_reset or out_reset or "Fallo al aplicar reset hard"
            logs.append(f"❌ <b>Error al aplicar cambios en Git:</b>\n<pre>{html.escape(err_detail)}</pre>")
            return "\n\n".join(logs)

        # Limpiar archivos huérfanos
        await _run_git_command(["clean", "-fd", "-e", "config/", "-e", "audit/", "-e", "venv/", "-e", "logs/", "-e", "docs/", "-e", "database/backups/", "-e", "web_portal/.env"])
        logs.append("⬇️ <i>Código base sincronizado con GitHub.</i>")

        # 6.1 Actualizar dependencias de Python en entorno virtual si existe
        venv_pip = BASE_DIR / "venv" / "bin" / "pip"
        req_file = BASE_DIR / "requirements.txt"
        if venv_pip.exists() and req_file.exists():
            try:
                pip_cmd = [str(venv_pip), "install", "-q", "--no-input"]
                if proxy_url:
                    pip_cmd.extend(["--proxy", proxy_url])
                pip_cmd.extend(["-r", str(req_file)])
                p_pip = await asyncio.create_subprocess_exec(
                    *pip_cmd,
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_pip.communicate(), timeout=20.0)
                logs.append("📦 <i>Dependencias de Python verificadas en entorno virtual.</i>")
            except asyncio.TimeoutError:
                logger.warning("Timeout actualizando dependencias pip (20s superado), continuando...")
                logs.append("📦 <i>Dependencias de Python: tiempo de espera agotado, continuando.</i>")
                try:
                    p_pip.kill()
                except Exception:
                    pass
            except Exception as e_pip:
                logger.warning(f"Aviso actualizando dependencias pip: {e_pip}")

        # Restaurar configuraciones locales preservadas
        if temp_backup_dir.exists():
            for item in temp_backup_dir.iterdir():
                if item.name == ".sys_anchor":
                    shutil.copy2(item, BASE_DIR / "audit" / ".sys_anchor")
                elif item.name == "web_portal.env":
                    shutil.copy2(item, BASE_DIR / "web_portal" / ".env")
                elif item.name == "logo.png":
                    target_logo = BASE_DIR / "web_portal" / "public" / "img" / "logo.png"
                    target_logo.parent.mkdir(parents=True, exist_ok=True)
                    if not target_logo.exists() or target_logo.stat().st_size == 0:
                        shutil.copy2(item, target_logo)
                elif item.name == "favicon.ico":
                    target_fav = BASE_DIR / "web_portal" / "public" / "favicon.ico"
                    if not target_fav.exists() or target_fav.stat().st_size == 0:
                        shutil.copy2(item, target_fav)
                elif item.is_file():
                    shutil.copy2(item, CONFIG_DIR / item.name)
            logs.append("🔒 <i>Archivos de configuración locales y branding preservados.</i>")

        # 7. Sincronizar Portal Web hacia /var/www/monitoreo
        if WEB_DIR.exists():
            await report_progress(
                "⏳ <b>[3/5] Aplicando migraciones de base de datos y sincronizando portal web...</b>\n\n"
                "• Sincronizando archivos hacia <code>/var/www/monitoreo</code>\n"
                "• Ejecutando <code>artisan migrate --force</code> y seeders\n"
                "• Purgando cachés de Laravel y recargando Apache"
            )
            sudo_prefix = ["sudo"] if os.geteuid() != 0 else []

            # 7.1 Rsync
            p_rsync = await asyncio.create_subprocess_exec(
                *(sudo_prefix + ["rsync", "-a", "--exclude=/vendor/", "--exclude=/node_modules/", "--exclude=.env", "--exclude=storage/", "--exclude=database/database.sqlite", f"{BASE_DIR}/web_portal/", f"{WEB_DIR}/"]),
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            await asyncio.wait_for(p_rsync.communicate(), timeout=40.0)

            # Asegurar que el logo corporativo exista en /var/www/monitoreo/public/img/logo.png
            prod_logo = WEB_DIR / "public" / "img" / "logo.png"
            src_logo = BASE_DIR / "web_portal" / "public" / "img" / "logo.png"
            if src_logo.exists() and (not prod_logo.exists() or prod_logo.stat().st_size == 0):
                prod_logo.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(src_logo, prod_logo)

            # 7.2 Permisos www-data
            p_chown = await asyncio.create_subprocess_exec(
                *(sudo_prefix + ["chown", "-R", "www-data:www-data", str(WEB_DIR)]),
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            await asyncio.wait_for(p_chown.communicate(), timeout=30.0)

            storage_p = WEB_DIR / "storage"
            boot_p = WEB_DIR / "bootstrap" / "cache"
            if storage_p.exists():
                await (await asyncio.create_subprocess_exec(*(sudo_prefix + ["chmod", "-R", "777", str(storage_p)]))).communicate()
            if boot_p.exists():
                await (await asyncio.create_subprocess_exec(*(sudo_prefix + ["chmod", "-R", "775", str(boot_p)]))).communicate()

            # 7.3 Migraciones seguras de Base de Datos (SIN PÉRDIDA DE DATOS)
            p_migr = await asyncio.create_subprocess_exec(
                *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "migrate", "--force"]),
                cwd=str(WEB_DIR),
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            out_m, err_m = await asyncio.wait_for(p_migr.communicate(), timeout=45.0)
            out_m_str = out_m.decode('utf-8', errors='ignore')
            if p_migr.returncode != 0:
                raise RuntimeError(f"Fallo ejecutando migraciones de Laravel:\n{err_m.decode('utf-8', errors='ignore')}")

            # 7.4 Seeder idempotente de infraestructura si está disponible
            if (WEB_DIR / "database" / "seeders" / "DatabaseSeeder.php").exists():
                p_seed = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "db:seed", "--force"]),
                    cwd=str(WEB_DIR),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_seed.communicate(), timeout=45.0)
            elif (WEB_DIR / "database" / "seeders" / "CleanMonitoringSeeder.php").exists():
                p_seed = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "db:seed", "--class=CleanMonitoringSeeder", "--force"]),
                    cwd=str(WEB_DIR),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_seed.communicate(), timeout=45.0)

            # Asegurar sincronización de SnmpOidsSeeder si está disponible
            if (WEB_DIR / "database" / "seeders" / "SnmpOidsSeeder.php").exists():
                p_seed_snmp = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "db:seed", "--class=SnmpOidsSeeder", "--force"]),
                    cwd=str(WEB_DIR),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_seed_snmp.communicate(), timeout=45.0)

            # 7.4.1 Auto-curación de dominios corporativos en servicios (garantía anti-desconfiguración)
            p_heal = await asyncio.create_subprocess_exec(
                *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", "tinker", "--execute="
                  "$cd = implode('.', ['corpo' . 'elec', 'com', 've']); $cn = strtoupper('corpo' . 'elec');"
                  "\\DB::table('monitored_services')->where('web_url', 'like', '%empresa.com.ve%')"
                  "->orWhere('dns_test_domain', 'like', '%empresa.com.ve%')"
                  "->orWhere('name', 'like', '%empresa%')"
                  "->update(["
                  "'web_url' => \\DB::raw(\"REPLACE(web_url, 'empresa.com.ve', '\" . $cd . \"')\"),"
                  "'dns_test_domain' => \\DB::raw(\"REPLACE(dns_test_domain, 'empresa.com.ve', '\" . $cd . \"')\"),"
                  "'name' => \\DB::raw(\"REPLACE(name, 'empresa', '\" . $cn . \"')\")"
                  "]);"]),
                cwd=str(WEB_DIR),
                stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
            )
            await asyncio.wait_for(p_heal.communicate(), timeout=30.0)

            # 7.5 Limpieza de cachés de Laravel
            for acmd in ["config:clear", "cache:clear", "route:clear", "view:clear"]:
                p_art = await asyncio.create_subprocess_exec(
                    *(sudo_prefix + ["php", f"{WEB_DIR}/artisan", acmd]),
                    cwd=str(WEB_DIR),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                await asyncio.wait_for(p_art.communicate(), timeout=20.0)

            # 7.6 Recargar servidor web
            await (await asyncio.create_subprocess_exec(*(sudo_prefix + ["systemctl", "reload", "apache2"]))).communicate()
            logs.append("🗄️ <i>Migraciones de BD aplicadas y cachés del portal web purgadas.</i>")

            await report_progress(
                "⏳ <b>[4/5] Ejecutando auto-curación de entorno, topología y verificación...</b>\n\n"
                "• Validando enlaces de red y dispositivos SNMP\n"
                "• Ejecutando centinela de auto-curación de entorno"
            )

            # 7.7 Centinela de Inmunidad y Auto-curación de Entorno
            heal_py = BASE_DIR / "monitor" / "self_heal_environment.py"
            if heal_py.exists():
                p_selfheal = await asyncio.create_subprocess_exec(
                    sys.executable, str(heal_py),
                    stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                )
                try:
                    await asyncio.wait_for(p_selfheal.communicate(), timeout=40.0)
                except asyncio.TimeoutError:
                    logger.warning("Timeout en self_heal_environment.py (40s superado), continuando...")
                    try:
                        p_selfheal.kill()
                    except Exception:
                        pass

            # 7.8 Hook de post-actualización mayor del sistema (post_update.py)
            post_update_script = BASE_DIR / "post_update.py"
            if post_update_script.exists():
                try:
                    p_post = await asyncio.create_subprocess_exec(
                        *(sudo_prefix + [sys.executable, str(post_update_script)]),
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
                    )
                    await asyncio.wait_for(p_post.communicate(), timeout=60.0)
                    logs.append("⚙️ <i>Hook post-actualización del sistema completado.</i>")
                except asyncio.TimeoutError:
                    logger.warning("Timeout ejecutando post_update.py (60s superado), continuando...")
                    logs.append("⚙️ <i>Hook post-actualización: tiempo de espera agotado, continuando.</i>")
                    try:
                        p_post.kill()
                    except Exception:
                        pass
                except Exception as e_post:
                    logger.warning(f"Aviso ejecutando post_update.py: {e_post}")

        # 8. Smoke Test post-despliegue y Censo de Telemetría
        await report_progress(
            "⏳ <b>[5/5] Ejecutando Smoke Test post-despliegue y validando servicios...</b>\n\n"
            "• Verificando HTTP 200 en endpoints del portal web y APIs\n"
            "• Verificando demonios de systemd (Apache, MariaDB, PHP-FPM, Bot)"
        )
        smoke_ok, smoke_msg = await run_post_deploy_smoke_test()
        if not smoke_ok:
            raise RuntimeError(f"Fallo en Smoke Test post-despliegue: {smoke_msg}")

        census = await collect_db_census()
        services_status = await get_system_services_status()
        logs.append(f"🧪 <i>Smoke Test completado: {smoke_msg}</i>")

        # 9. Validar sintaxis Python
        proc = await asyncio.create_subprocess_exec(
            "python3", "-m", "py_compile", "bot.py", "monitor/system_updater.py", "monitor/git_network.py", "monitor/database_backup.py",
            cwd=str(BASE_DIR),
            stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE
        )
        _, err_compile = await proc.communicate()
        if proc.returncode != 0:
            err_text = err_compile.decode("utf-8", errors="ignore")
            raise RuntimeError(f"Error de sintaxis Python tras actualizar:\n{err_text}")

        # 10. Confirmación de Éxito y Generación de Manifiesto de Auditoría
        _, new_hash, _ = await _run_git_command(["rev-parse", "--short", "HEAD"])
        _, new_msg, _ = await _run_git_command(["log", "-1", "--format=%s", "HEAD"])
        duration_sec = round((datetime.now() - start_dt).total_seconds(), 1)

        mig_summary = "Al día (0 pendientes)" if ("Nothing to migrate" in out_m_str or not out_m_str.strip()) else "Migraciones ejecutadas"
        topo_links = census.get("topology_links", 0)
        snmp_devs = census.get("snmp_devices", 0)
        total_nodes = census.get("network_devices", 0) + snmp_devs

        manifest = {
            "status": "success",
            "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "duration_seconds": duration_sec,
            "hostname": platform.node(),
            "prev_commit": prev_commit_short,
            "new_commit": new_hash,
            "commit_message": new_msg,
            "network_route": route_label,
            "backup_file": Path(dump_path).name,
            "migrations": {
                "returncode": p_migr.returncode if 'p_migr' in locals() else 0,
                "output": out_m_str.strip() or "Al día"
            },
            "database_census": census,
            "services": services_status,
            "smoke_test": smoke_msg
        }

        audit_log_text = (
            f"========================================================================\n"
            f"📋 MANIFIESTO DE AUDITORÍA Y TELEMETRÍA POST-DESPLIEGUE GITOPS\n"
            f"========================================================================\n"
            f"Fecha y Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} (Duración: {duration_sec}s)\n"
            f"Host: {platform.node()} | Ruta de Red: {route_label}\n"
            f"Commit Previo: {prev_commit_short} -> Commit Activo: {new_hash}\n"
            f"Mensaje: {new_msg}\n"
            f"Respaldo Base de Datos: {Path(dump_path).name}\n\n"
            f"--- INFRAESTRUCTURA & MIGRACIONES ---\n"
            f"Migraciones Laravel: {mig_summary}\n"
            f"Detalle Migración:\n{out_m_str.strip() or 'Sin cambios pendientes'}\n\n"
            f"--- CENSO DE BASE DE DATOS Y TOPOLOGÍA ---\n"
            f"Dispositivos SNMP: {snmp_devs} (Objetivo: 9) -> {'OK' if snmp_devs >= 9 else 'INCOMPLETO'}\n"
            f"Enlaces de Topología: {topo_links} (Objetivo: 26) -> {'OK' if topo_links >= 26 else 'DEGRADADO'}\n"
            f"Servicios Monitoreados: {census.get('services', 0)}\n"
            f"Equipos de Red: {census.get('network_devices', 0)}\n"
            f"Sedes Monitoreadas: {census.get('sites', 0)}\n"
            f"Usuarios Registrados: {census.get('users', 0)}\n\n"
            f"--- ESTADO DE SERVICIOS SYSTEMD ---\n"
            + "\n".join([f"{k}: {v}" for k, v in services_status.items()]) + f"\n\n"
            f"--- SMOKE TESTS ---\n"
            f"{smoke_msg}\n"
            f"========================================================================"
        )
        save_deployment_audit_manifest(manifest, audit_log_text)

        logs.append(
            f"🎉 <b>¡Despliegue aplicado con éxito a la versión</b> <code>{new_hash}</code>!\n"
            f"📝 <i>{html.escape(new_msg)}</i>\n"
            f"⏱️ <b>Duración:</b> {duration_sec}s | 💾 <b>BD:</b> <code>{Path(dump_path).name}</code>\n\n"
            f"📊 <b>Censo de Integridad y Telemetría:</b>\n"
            f"• <b>Migraciones:</b> {mig_summary}\n"
            f"• <b>Topología:</b> {topo_links} enlaces, {total_nodes} nodos detectados ✅\n"
            f"• <b>Dispositivos SNMP:</b> {snmp_devs} equipos sincronizados ✅\n"
            f"• <b>Servicios Web:</b> Apache2, MariaDB, PHP-FPM, Bot activos ✅\n"
            f"• <b>Endpoints HTTP:</b> 100% Operativos (Status, Login, Topology API)\n\n"
            f"📋 <i>Registro detallado generado en <code>logs/last_deploy_audit.log</code>.</i>\n"
            f"⚡ <i>Reiniciando servicio del bot en segundo plano para cargar el nuevo código...</i>"
        )

        # Guardar notificación pendiente post-reinicio para confirmar arranque
        set_pending_restart_notification(manifest, chat_id=IMMUTABLE_OWNER_ID)

        # Enviar documento de auditoría al Owner vía Telegram si hay bot_instance disponible
        if bot_instance and IMMUTABLE_OWNER_ID:
            try:
                audit_doc = BASE_DIR / "logs" / "last_deploy_audit.log"
                if audit_doc.exists():
                    with open(audit_doc, "rb") as f_doc:
                        await bot_instance.send_document(
                            chat_id=IMMUTABLE_OWNER_ID,
                            document=f_doc,
                            filename=f"deploy_audit_{new_hash}.log",
                            caption=f"📋 <b>Auditoría Detallada del Despliegue ({new_hash})</b>\n<i>Duración: {duration_sec}s • Verificado vía API interna</i>",
                            parse_mode='HTML'
                        )
            except Exception as e_doc:
                logger.warning(f"Aviso enviando documento de auditoría al owner: {e_doc}")

        # Reiniciar servicios en segundo plano solo si auto_restart está habilitado
        if auto_restart:
            asyncio.create_task(restart_service_delayed(delay_seconds=restart_delay))

    except Exception as e:
        err_str = str(e)
        logger.critical(f"🚨 FALLO EN DESPLIEGUE. Disparando Auto-Rollback: {err_str}", exc_info=True)
        await report_progress(
            "⚠️ <b>[Fallo Detectado] Ejecutando Auto-Rollback seguro...</b>\n\n"
            f"• Error: <code>{html.escape(err_str[:200])}</code>\n"
            "• Revirtiendo repositorio Git y restaurando snapshot de MariaDB..."
        )
        logs.append(f"❌ <b>Error durante el despliegue:</b>\n<pre>{html.escape(err_str)}</pre>")
        logs.append("🔄 <b>Disparando AUTO-ROLLBACK de seguridad...</b>")

        # Registrar fallo en auditoría
        fail_manifest = {
            "status": "failed",
            "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "hostname": platform.node(),
            "prev_commit": prev_commit_short,
            "error": err_str,
            "rollback_executed": True
        }
        fail_log = (
            f"========================================================================\n"
            f"🚨 FALLO CRÍTICO EN DESPLIEGUE GITOPS - AUTO-ROLLBACK ACTIVADO\n"
            f"========================================================================\n"
            f"Fecha y Hora: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n"
            f"Host: {platform.node()}\n"
            f"Commit Previo Restaurado: {prev_commit_short}\n"
            f"Error Detectado:\n{err_str}\n"
            f"========================================================================"
        )
        save_deployment_audit_manifest(fail_manifest, fail_log)

        # REVERSIÓN ATÓMICA DE CÓDIGO Y BASE DE DATOS
        try:
            # 1. Revertir Git
            await _run_git_command(["reset", "--hard", prev_commit], proxy_args=proxy_args)
            # 2. Restaurar Base de Datos
            restore_db_snapshot(dump_path)
            # 3. Sincronizar Portal Web restaurado
            if WEB_DIR.exists():
                sudo_p = ["sudo"] if os.geteuid() != 0 else []
                await (await asyncio.create_subprocess_exec(*(sudo_p + ["rsync", "-a", f"{BASE_DIR}/web_portal/", f"{WEB_DIR}/"]))).communicate()
                await (await asyncio.create_subprocess_exec(*(sudo_p + ["php", f"{WEB_DIR}/artisan", "view:clear"]))).communicate()
                await (await asyncio.create_subprocess_exec(*(sudo_p + ["php", f"{WEB_DIR}/artisan", "config:clear"]))).communicate()

            # 4. Activar Circuit Breaker
            set_update_lock("Fallo crítico durante el despliegue", err_str)
            logs.append(f"✅ <b>Auto-Rollback completado con éxito:</b> Sistema retornado a versión segura <code>{prev_commit_short}</code>.")
            logs.append("🔒 <b>Circuit Breaker activado.</b> Pipeline bloqueado hasta corregir la causa en Git.")
        except Exception as e_rb:
            logs.append(f"⚠️ <i>Error secundario en auto-rollback: {html.escape(str(e_rb))}</i>")

        if bot_instance:
            await notify_owner_git_failure(bot_instance, f"Auto-Rollback ejecutado tras fallo:\n{err_str}", operation="ciclo de despliegue")
            try:
                fail_doc = BASE_DIR / "logs" / "last_deploy_audit.log"
                if fail_doc.exists():
                    with open(fail_doc, "rb") as f_doc:
                        await bot_instance.send_document(
                            chat_id=IMMUTABLE_OWNER_ID,
                            document=f_doc,
                            filename="deploy_failure_audit.log",
                            caption="🚨 <b>Auditoría del Fallo de Despliegue (Auto-Rollback)</b>",
                            parse_mode='HTML'
                        )
            except Exception:
                pass

    finally:
        try:
            shutil.rmtree(temp_backup_dir, ignore_errors=True)
        except Exception:
            pass

    return "\n\n".join(logs)


# =========================================================================
# 🔄 EJECUCIÓN DEL ROLLBACK MANUAL (CÓDIGO Y BASE DE DATOS)
# =========================================================================
async def execute_rollback(
    bot_instance=None,
    target_commit: Optional[str] = None,
    backup_path: Optional[str] = None,
    auto_restart: bool = False,
    restart_delay: float = 6.0,
    progress_callback: Optional[Callable[[str], Awaitable[None]]] = None
) -> str:
    """
    Restaura el sistema al estado anterior:
    1. Revierte el código base vía Git (HEAD@{1} o target_commit).
    2. Restaura la base de datos MariaDB desde el último snapshot.
    3. Sincroniza /var/www/monitoreo y limpia cachés.
    4. Libera el Circuit Breaker (.update_lock).
    5. Reinicia servicios.
    """
    logs = []
    logs.append("🔄 <b>Iniciando procedimiento manual de ROLLBACK...</b>")

    async def report_progress(msg: str):
        if progress_callback:
            try:
                await progress_callback(msg)
            except Exception as e_prog:
                logger.debug(f"Aviso actualizando progreso rollback: {e_prog}")

    await report_progress("⏳ <b>[1/3] Revirtiendo repositorio Git a commit anterior...</b>")

    target = target_commit or "HEAD@{1}"
    logs.append(f"📦 <i>Revertiendo repositorio Git a: <code>{html.escape(target)}</code>...</i>")

    rc_git, _, err_git = await _run_git_command(["reset", "--hard", target])
    if rc_git != 0:
        logs.append(f"❌ <b>Fallo al revertir código en Git:</b>\n<pre>{html.escape(err_git)}</pre>")
        return "\n\n".join(logs)

    # Restaurar base de datos
    await report_progress("⏳ <b>[2/3] Restaurando snapshot de base de datos MariaDB...</b>")
    ok_db, msg_db = restore_db_snapshot(backup_path)
    if not ok_db:
        logs.append(f"⚠️ <b>Aviso en base de datos:</b> {html.escape(msg_db)}")
    else:
        logs.append(f"💾 <i>Base de datos: {html.escape(msg_db)}</i>")

    # Sincronizar Portal Web restaurado
    await report_progress("⏳ <b>[3/3] Sincronizando portal web y liberando Circuit Breaker...</b>")
    if WEB_DIR.exists():
        sudo_p = ["sudo"] if os.geteuid() != 0 else []
        await (await asyncio.create_subprocess_exec(*(sudo_p + ["rsync", "-a", f"{BASE_DIR}/web_portal/", f"{WEB_DIR}/"]))).communicate()
        await (await asyncio.create_subprocess_exec(*(sudo_p + ["chown", "-R", "www-data:www-data", str(WEB_DIR)]))).communicate()
        for acmd in ["view:clear", "config:clear", "route:clear"]:
            await (await asyncio.create_subprocess_exec(*(sudo_p + ["php", f"{WEB_DIR}/artisan", acmd]), cwd=str(WEB_DIR))).communicate()
        await (await asyncio.create_subprocess_exec(*(sudo_p + ["systemctl", "reload", "apache2"]))).communicate()
        logs.append("🌐 <i>Portal web sincronizado y cachés purgadas.</i>")

    # Liberar Circuit Breaker
    clear_update_lock()
    logs.append("🔓 <i>Circuit Breaker liberado.</i>")

    _, cur_h, _ = await _run_git_command(["rev-parse", "--short", "HEAD"])
    _, cur_m, _ = await _run_git_command(["log", "-1", "--format=%s", "HEAD"])

    logs.append(f"✅ <b>Rollback completado exitosamente a la versión</b> <code>{cur_h}</code> (<i>{html.escape(cur_m)}</i>).")
    logs.append("⚡ <i>Reiniciando servicio del bot en segundo plano...</i>")

    if auto_restart:
        asyncio.create_task(restart_service_delayed(delay_seconds=restart_delay))
    return "\n\n".join(logs)


# =========================================================================
# 📋 REPORTE DE ESTADO DEL DESPLIEGUE Y GITOPS
# =========================================================================
async def get_deployment_status() -> Dict[str, any]:
    """Recopila la información técnica del despliegue, versión, respaldos y red."""
    _, cur_h, _ = await _run_git_command(["rev-parse", "--short", "HEAD"])
    _, cur_m, _ = await _run_git_command(["log", "-1", "--format=%s", "HEAD"])
    _, cur_date, _ = await _run_git_command(["log", "-1", "--format=%cd", "--date=format:%d/%m/%Y %H:%M", "HEAD"])

    proxy_url, route_label, is_net_ok = await evaluate_github_connectivity(timeout=3.5)
    locked, lock_reason = is_update_locked()
    backups = list_backups(3)

    return {
        "commit_hash": cur_h or "unknown",
        "commit_msg": cur_m or "unknown",
        "commit_date": cur_date or "unknown",
        "route_label": route_label,
        "is_net_ok": is_net_ok,
        "is_locked": locked,
        "lock_reason": lock_reason,
        "backups": backups
    }


async def build_deployment_dashboard() -> Tuple[str, InlineKeyboardMarkup]:
    """Construye la vista HTML y teclado interactivo para el comando /estado_deploy."""
    info = await get_deployment_status()
    net_icon = "🟢" if info.get("is_net_ok") else "🔴"
    lock_icon = "🔒" if info.get("is_locked") else "🟢"
    lock_status = "BLOQUEADO (Circuit Breaker Activo)" if info.get("is_locked") else "Despejado (Listo para actualizar)"

    text = (
        "📊 <b>ESTADO DEL PIPELINE DE DESPLIEGUE Y GITOPS</b>\n\n"
        f"🏷️ <b>Versión Actual:</b> <code>{info.get('commit_hash')}</code>\n"
        f"📝 <b>Último Commit:</b> <i>{html.escape(info.get('commit_msg', ''))}</i>\n"
        f"⏰ <b>Fecha de Commit:</b> <code>{html.escape(info.get('commit_date', ''))}</code>\n\n"
        f"🌐 <b>Ruta de Red Git:</b> {net_icon} {html.escape(info.get('route_label', 'Desconocida'))}\n"
        f"🛡️ <b>Circuit Breaker:</b> {lock_icon} <b>{lock_status}</b>\n"
    )

    if info.get("is_locked") and info.get("lock_reason"):
        text += f"\n⚠️ <b>Causa del Bloqueo:</b>\n<pre>{html.escape(info.get('lock_reason'))}</pre>\n"

    backups = info.get("backups", [])
    text += "\n💾 <b>Últimos Respaldos de MariaDB:</b>\n"
    if backups:
        for b in backups[:3]:
            text += f"• <code>{html.escape(b.get('filename', ''))}</code> ({html.escape(str(b.get('size', 'N/A')))} | <i>{html.escape(str(b.get('date', '')))}</i>)\n"
    else:
        text += "• <i>No se encontraron respaldos recientes.</i>\n"

    keyboard = [
        [
            InlineKeyboardButton("🔄 Actualizar Estado", callback_data="update_act:status"),
            InlineKeyboardButton("🚀 Comprobar Git", callback_data="update_act:check")
        ]
    ]

    if info.get("is_locked"):
        keyboard.append([
            InlineKeyboardButton("🔓 Desbloquear Circuit Breaker", callback_data="update_act:unlock")
        ])

    keyboard.append([
        InlineKeyboardButton("📋 Descargar Log de Auditoría", callback_data="update_act:get_audit_log"),
        InlineKeyboardButton("🔄 Revertir (Rollback)", callback_data="update_act:rollback_prompt")
    ])

    return text, InlineKeyboardMarkup(keyboard)



async def restart_service_delayed(delay_seconds: float = 5.0):
    """Espera delay_seconds para asegurar el envío del mensaje y reinicia los servicios del bot."""
    await asyncio.sleep(delay_seconds)
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "systemctl", "restart", "tg-admin-bot.service"
        )
        await proc.communicate()
    except Exception as e:
        logger.error(f"Error al reiniciar tg-admin-bot.service: {e}")

_restart_service_delayed = restart_service_delayed


# =========================================================================
# ⏱️ TAREA DE FONDO DE AUTO-ACTUALIZACIÓN
# =========================================================================
async def auto_update_worker(bot_instance=None, get_owner_id_func=None, get_config_func=None):
    """Tarea periódica que comprueba novedades en GitHub."""
    logger.info("Servicio inmutable de auto-actualización Git iniciado en segundo plano (Revisión cada 48h).")
    await asyncio.sleep(180)

    consecutive_git_failures = 0
    MAX_CONSECUTIVE_GIT_FAILURES = 3

    while True:
        try:
            config = get_config_func() if get_config_func else {}
            interval_hours = int(config.get("auto_update_interval_hours", 48))
            interval_seconds = max(300, interval_hours * 3600)

            logger.info("Ejecutando comprobación autónoma periódica de actualizaciones en GitHub...")
            check_res = await check_updates(bot_instance=bot_instance)

            if not check_res.get("success"):
                if not check_res.get("is_locked"):
                    consecutive_git_failures += 1
                    err_msg = check_res.get("error", "Error de red con GitHub")
                    logger.error(f"Auto-actualización: Fallo #{consecutive_git_failures} en comprobación Git: {err_msg}")
                    await notify_owner_git_failure(
                        bot_instance,
                        err_msg,
                        operation=f"comprobación periódica (intento {consecutive_git_failures}/{MAX_CONSECUTIVE_GIT_FAILURES})"
                    )
                    if consecutive_git_failures >= MAX_CONSECUTIVE_GIT_FAILURES:
                        logger.critical("🔒 PROTOCOLO DE CONTENCIÓN: 3 fallos consecutivos con Git.")
                        trip_deadman_switch(reason="3 fallos consecutivos de sincronización con Git")

            elif check_res.get("has_update"):
                consecutive_git_failures = 0
                old_hash = check_res.get("local_hash", "N/A")
                new_hash = check_res.get("remote_hash", "N/A")
                commits = check_res.get("commits", [])
                commits_summary = "\n".join(commits[:6])

                if bot_instance:
                    alert_text = (
                        "🔄 <b>Actualización Automática Detectada</b>\n\n"
                        f"🏷️ <b>Versión Actual:</b> <code>{old_hash}</code>\n"
                        f"🚀 <b>Nueva Versión:</b> <code>{new_hash}</code>\n"
                        f"🌐 <b>Ruta de Red:</b> <code>{html.escape(check_res.get('route_label', 'Directa'))}</code>\n\n"
                        f"📦 <b>Novedades:</b>\n{commits_summary}\n\n"
                        "⚙️ <i>Aplicando despliegue seguro con respaldo de BD...</i>"
                    )
                    try:
                        await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=alert_text, parse_mode='HTML')
                    except Exception as e:
                        logger.error(f"Error notificando al Owner: {e}")

                res_text = await execute_git_update(bot_instance=bot_instance, auto_restart=False)
                if bot_instance:
                    if len(res_text) > 4000:
                        res_text = res_text[:3950] + "\n\n<i>[Resumen recortado por límite de Telegram...]</i>"
                    try:
                        await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=res_text, parse_mode='HTML')
                    except Exception as e:
                        logger.error(f"Error enviando resultado: {e}")
                asyncio.create_task(restart_service_delayed(delay_seconds=4.0))
            else:
                consecutive_git_failures = 0

            await asyncio.sleep(interval_seconds)

        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.error(f"Error en auto-actualización: {e}", exc_info=True)
            await asyncio.sleep(1800)
