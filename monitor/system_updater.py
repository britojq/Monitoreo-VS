"""
# ==============================================================================
# 🔄 MÓDULO DE ACTUALIZACIÓN GIT: system_updater.py (@IA_ValleSeco_bot)
# Sincronización inmutable, respaldo y verificación de versiones desde GitHub
# Ubicación: /scripts/telegram-admin-bot/monitor/system_updater.py
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
import shutil
import tempfile
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup

logger = logging.getLogger("monitor.system_updater")

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"

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


async def _run_git_command(args: List[str], timeout: float = 20.0) -> Tuple[int, str, str]:
    """Ejecuta un comando de git en el directorio del proyecto de forma asíncrona."""
    try:
        proc = await asyncio.create_subprocess_exec(
            "git", *args,
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


async def check_updates(bot_instance=None) -> Dict[str, any]:
    """
    Comprueba si existen actualizaciones en el repositorio remoto oficial (origin/master).
    Garantiza que la URL remota no haya sido alterada y reporta fallos al Owner si aplica.
    """
    repo_url = get_core_repo_url()
    branch = get_core_branch()

    # 0. Asegurar URL remota inmutable oficial
    await _run_git_command(["remote", "set-url", "origin", repo_url])

    # 1. Ejecutar git fetch con hasta 2 reintentos si la red recién arranca
    rc_fetch, err_fetch = -1, ""
    for attempt in range(1, 3):
        rc_fetch, _, err_fetch = await _run_git_command(["fetch", "origin", branch], timeout=25.0)
        if rc_fetch == 0:
            break
        if attempt < 2:
            await asyncio.sleep(5.0)

    if rc_fetch != 0:
        err_msg = err_fetch or "Fallo de conexión de red o credenciales con GitHub"
        logger.error(f"Error en git fetch ({repo_url}): {err_msg}")
        if bot_instance:
            await notify_owner_git_failure(bot_instance, err_msg, operation="comprobación de actualizaciones")
        return {
            "success": False,
            "error": f"Error conectando con el repositorio remoto: {err_msg}"
        }

    # 2. Obtener hashes locales y remotos
    _, local_hash, _ = await _run_git_command(["rev-parse", "HEAD"])
    _, remote_hash, _ = await _run_git_command(["rev-parse", f"origin/{branch}"])

    # 3. Obtener información del commit actual
    _, current_commit_info, _ = await _run_git_command(["log", "-1", "--format=%h - %s (%cd)", "--date=format:%d/%m/%Y %H:%M", "HEAD"])
    _, current_commit_info, _ = await _run_git_command(["log", "-1", "--format=%h - %s (%cd)", "--date=format:%d/%m/%Y %H:%M", "HEAD"])

    if local_hash == remote_hash:
        return {
            "success": True,
            "has_update": False,
            "local_hash": local_hash[:7] if local_hash else "N/A",
            "current_commit": current_commit_info,
            "message": "El bot ya se encuentra en la versión más reciente."
        }

    # 4. Obtener lista de commits pendientes
    _, commits_raw, _ = await _run_git_command(["log", f"HEAD..origin/{IMMUTABLE_GIT_BRANCH}", "--format=• <code>%h</code>: %s (%cr)"])
    commits_list = [c for c in commits_raw.splitlines() if c.strip()]

    if not commits_list:
        return {
            "success": True,
            "has_update": False,
            "local_hash": local_hash[:7] if local_hash else "N/A",
            "remote_hash": remote_hash[:7] if remote_hash else "N/A",
            "current_commit": current_commit_info,
            "message": "El bot se encuentra en una versión al día o adelantada respecto al remoto."
        }

    # 5. Obtener lista de archivos modificados
    _, diff_files_raw, _ = await _run_git_command(["diff", "--name-status", "HEAD", f"origin/{IMMUTABLE_GIT_BRANCH}"])
    changed_files = []
    for line in diff_files_raw.splitlines():
        parts = line.split(maxsplit=1)
        if len(parts) == 2:
            status_code, filename = parts[0], parts[1]
            status_label = "📝 Modificado" if status_code == "M" else "➕ Agregado" if status_code == "A" else "🗑️ Eliminado" if status_code == "D" else status_code
            changed_files.append(f"• {status_label}: <code>{filename}</code>")

    return {
        "success": True,
        "has_update": True,
        "local_hash": local_hash[:7] if local_hash else "N/A",
        "remote_hash": remote_hash[:7] if remote_hash else "N/A",
        "current_commit": current_commit_info,
        "commits": commits_list,
        "files": changed_files
    }


async def build_update_dashboard(bot_instance=None) -> Tuple[str, Optional[InlineKeyboardMarkup]]:
    """Construye el texto y botones para el panel de actualización."""
    check_result = await check_updates(bot_instance=bot_instance)

    if not check_result.get("success"):
        error_msg = html.escape(check_result.get("error", "Error desconocido"))
        repo_url = get_core_repo_url()
        text = (
            "🔄 <b>PANEL DE ACTUALIZACIÓN DE SISTEMA (GIT)</b>\n\n"
            f"❌ <b>Error al verificar actualizaciones:</b>\n<pre>{error_msg}</pre>\n\n"
            f"🔗 <b>Repositorio Obligatorio:</b> <code>{repo_url}</code>\n"
            "<i>Se ha generado un registro de auditoría y alerta al Administrador.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 Reintentar Verificación", callback_data="update_act:check"),
                InlineKeyboardButton("⚡ Forzar Actualización", callback_data="update_act:force")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    if not check_result.get("has_update"):
        current_info = html.escape(check_result.get("current_commit", "N/A"))
        repo_url = get_core_repo_url()
        text = (
            "🔄 <b>PANEL DE ACTUALIZACIÓN DE SISTEMA (GIT)</b>\n\n"
            "✅ <b>¡El sistema se encuentra 100% sincronizado con GitHub!</b>\n\n"
            f"🏷️ <b>Versión Actual:</b> <code>{check_result.get('local_hash')}</code>\n"
            f"📋 <b>Último Commit:</b> {current_info}\n"
            f"🔗 <b>Repositorio:</b> <code>{repo_url}</code>\n\n"
            "<i>No hay cambios pendientes por descargar.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 Comprobar de Nuevo", callback_data="update_act:check"),
                InlineKeyboardButton("⚡ Forzar Sincronización", callback_data="update_act:force")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    # Si hay actualizaciones disponibles
    local_h = check_result.get("local_hash")
    remote_h = check_result.get("remote_hash")
    commits = check_result.get("commits", [])
    files = check_result.get("files", [])
    repo_url = get_core_repo_url()

    commits_text = "\n".join(commits[:10])
    if len(commits) > 10:
        commits_text += f"\n<i>... y {len(commits) - 10} commit(s) más.</i>"

    files_text = "\n".join(files[:12])
    if len(files) > 12:
        files_text += f"\n<i>... y {len(files) - 12} archivo(s) más.</i>"

    text = (
        "🔄 <b>NUEVA ACTUALIZACIÓN DISPONIBLE (GIT)</b>\n\n"
        f"🏷️ <b>Versión Local:</b> <code>{local_h}</code>\n"
        f"🚀 <b>Versión Remota:</b> <code>{remote_h}</code>\n"
        f"🔗 <b>Origen:</b> <code>{repo_url}</code>\n\n"
        f"📦 <b>Novedades y Commits ({len(commits)}):</b>\n"
        f"{commits_text}\n\n"
        f"📂 <b>Archivos Afectados ({len(files)}):</b>\n"
        f"{files_text}\n\n"
        "🛡️ <i>Tus archivos de configuración (<code>config/</code>) serán preservados intactos.</i>"
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


async def execute_git_update(bot_instance=None) -> str:
    """
    Ejecuta el ciclo de actualización forzada con el repositorio oficial:
    1. Asegurar URL remota oficial inmutable.
    2. Respaldo de seguridad de config/.
    3. Git fetch origin master.
    4. Git reset --hard origin/master (Forzado obligatorio de sincronización).
    5. Git clean de archivos huérfanos fuera de carpetas críticas.
    6. Restauración y protección de config/.
    7. Verificación de sintaxis de Python (py_compile).
    8. Reinicio del servicio systemd.
    """
    logs = []
    logs.append("📦 <b>Iniciando sincronización forzada del sistema con GitHub...</b>")

    temp_backup_dir = Path(tempfile.mkdtemp(prefix="tgbot_cfg_bak_"))
    try:
        # 0. Asegurar URL oficial del repositorio
        await _run_git_command(["remote", "set-url", "origin", IMMUTABLE_GIT_REPO_URL])

        # 1. Respaldar config/ y archivos sensibles locales
        if CONFIG_DIR.exists():
            for item in CONFIG_DIR.iterdir():
                if item.is_file():
                    shutil.copy2(item, temp_backup_dir / item.name)
            logs.append("🛡️ <i>Copia de seguridad local de configuración creada.</i>")

        anchor_file = BASE_DIR / "audit" / ".sys_anchor"
        if anchor_file.exists():
            shutil.copy2(anchor_file, temp_backup_dir / ".sys_anchor")

        portal_env = BASE_DIR / "web_portal" / ".env"
        if portal_env.exists():
            shutil.copy2(portal_env, temp_backup_dir / "web_portal.env")

        portal_db = BASE_DIR / "web_portal" / "database" / "database.sqlite"
        if portal_db.exists():
            shutil.copy2(portal_db, temp_backup_dir / "database.sqlite")

        # 2. Descargar últimos cambios (fetch)
        rc_fetch, out_fetch, err_fetch = await _run_git_command(["fetch", "origin", IMMUTABLE_GIT_BRANCH], timeout=30.0)
        if rc_fetch != 0:
            err_detail = err_fetch or out_fetch or "Fallo de conexión con GitHub"
            logs.append(f"❌ <b>Error al descargar desde GitHub:</b>\n<pre>{html.escape(err_detail)}</pre>")
            if bot_instance:
                await notify_owner_git_failure(bot_instance, err_detail, operation="descarga de actualización (git fetch)")
            return "\n\n".join(logs)

        # 3. Forzar actualización sobrescribiendo archivos con origin/master (Regla 2: SIEMPRE FORZAR)
        rc_reset, out_reset, err_reset = await _run_git_command(["reset", "--hard", f"origin/{IMMUTABLE_GIT_BRANCH}"], timeout=30.0)
        if rc_reset != 0:
            err_detail = err_reset or out_reset or "Fallo al aplicar reset hard"
            logs.append(f"❌ <b>Error forzando actualización (git reset):</b>\n<pre>{html.escape(err_detail)}</pre>")
            if bot_instance:
                await notify_owner_git_failure(bot_instance, err_detail, operation="sincronización forzada (git reset --hard)")
            return "\n\n".join(logs)

        # Limpiar archivos no rastreados protegiendo directorios locales y documentación
        await _run_git_command(["clean", "-fd", "-e", "config/", "-e", "audit/", "-e", "venv/", "-e", "logs/", "-e", "docs/", "-e", "web_portal/.env", "-e", "web_portal/database/database.sqlite"])
        logs.append("⬇️ <i>Código y scripts sincronizados exactamente con el repositorio remoto.</i>")

        # 4. Restaurar archivos de configuración preservados
        if temp_backup_dir.exists():
            for item in temp_backup_dir.iterdir():
                if item.name == ".sys_anchor":
                    shutil.copy2(item, BASE_DIR / "audit" / ".sys_anchor")
                elif item.name == "web_portal.env":
                    shutil.copy2(item, BASE_DIR / "web_portal" / ".env")
                elif item.name == "database.sqlite":
                    shutil.copy2(item, BASE_DIR / "web_portal" / "database" / "database.sqlite")
                elif item.is_file():
                    shutil.copy2(item, CONFIG_DIR / item.name)
            logs.append("🔒 <i>Archivos de configuración preservados intactos.</i>")

        # 4.1. Desplegar y sincronizar Portal Web en /var/www/monitoreo si existe
        web_dir = Path("/var/www/monitoreo")
        portal_src = BASE_DIR / "web_portal"
        if web_dir.exists() and portal_src.exists():
            try:
                # 1. Sincronizar archivos preservando credenciales y dependencias
                sudo_prefix = ["sudo"] if os.geteuid() != 0 else []
                rsync_cmd = sudo_prefix + [
                    "rsync", "-a",
                    "--exclude=vendor/",
                    "--exclude=node_modules/",
                    "--exclude=.env",
                    "--exclude=storage/",
                    "--exclude=database/database.sqlite",
                    f"{portal_src}/",
                    f"{web_dir}/"
                ]
                p_rsync = await asyncio.create_subprocess_exec(*rsync_cmd, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                await p_rsync.communicate()

                # 2. Ajustar permisos para el usuario www-data
                chown_cmd = sudo_prefix + ["chown", "-R", "www-data:www-data", str(web_dir)]
                p_chown = await asyncio.create_subprocess_exec(*chown_cmd, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                await p_chown.communicate()

                storage_p = web_dir / "storage"
                boot_p = web_dir / "bootstrap" / "cache"
                if storage_p.exists() and boot_p.exists():
                    chmod_cmd = sudo_prefix + ["chmod", "-R", "777", str(storage_p)]
                    p_chmod = await asyncio.create_subprocess_exec(*chmod_cmd, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                    await p_chmod.communicate()
                    chmod_boot = sudo_prefix + ["chmod", "-R", "775", str(boot_p)]
                    p_boot = await asyncio.create_subprocess_exec(*chmod_boot, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                    await p_boot.communicate()

                # 3. Purgar caché de vistas compiladas y rutas en Laravel
                artisan_bin = web_dir / "artisan"
                if artisan_bin.exists():
                    cmd_view = (sudo_prefix + ["-u", "www-data"]) if sudo_prefix else []
                    cmd_view += ["php", str(artisan_bin), "view:clear"]
                    p_view = await asyncio.create_subprocess_exec(
                        *cmd_view,
                        cwd=str(web_dir),
                        stdout=asyncio.subprocess.PIPE,
                        stderr=asyncio.subprocess.PIPE
                    )
                    await p_view.communicate()

                    cmd_route = (sudo_prefix + ["-u", "www-data"]) if sudo_prefix else []
                    cmd_route += ["php", str(artisan_bin), "route:clear"]
                    p_route = await asyncio.create_subprocess_exec(
                        *cmd_route,
                        cwd=str(web_dir),
                        stdout=asyncio.subprocess.PIPE,
                        stderr=asyncio.subprocess.PIPE
                    )
                    await p_route.communicate()

                # 4. Recargar Apache y PHP-FPM para invalidar OPcache de PHP
                cmd_reload = sudo_prefix + ["systemctl", "reload", "apache2"]
                p_reload = await asyncio.create_subprocess_exec(*cmd_reload, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                await p_reload.communicate()

                # Recargar servicio php-fpm si está activo
                try:
                    cmd_fpm = sudo_prefix + ["systemctl", "reload", "php8.4-fpm"]
                    p_fpm = await asyncio.create_subprocess_exec(*cmd_fpm, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                    await p_fpm.communicate()
                except Exception:
                    pass

                # 5. Asegurar cron de escaneo web dinámico en /etc/cron.d/monitoreo_web
                cron_path = Path("/etc/cron.d/monitoreo_web")
                try:
                    needs_update = True
                    if cron_path.exists():
                        content = cron_path.read_text(encoding="utf-8")
                        if "* * * * * britojab" in content:
                            needs_update = False
                    if needs_update:
                        cmd_cron = sudo_prefix + ["bash", "-c", "cat << 'CRON_EOF' > /etc/cron.d/monitoreo_web\n# /etc/cron.d/monitoreo_web - Sincronizacion Web Dinamica de Monitoreo\n* * * * * britojab /scripts/telegram-admin-bot/estatus web > /dev/null 2>&1\nCRON_EOF\nchmod 644 /etc/cron.d/monitoreo_web"]
                        p_cron = await asyncio.create_subprocess_exec(*cmd_cron, stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE)
                        await p_cron.communicate()
                        logs.append("⏱️ <i>Cron de escaneo web configurado a ejecución dinámica por minuto.</i>")
                except Exception as e_cron:
                    logger.warning(f"No fue posible actualizar /etc/cron.d/monitoreo_web: {e_cron}")

                logs.append("🌐 <i>Portal Web desplegado en /var/www/monitoreo, caché purgada y servicios web recargados.</i>")
            except Exception as e_web:
                logger.warning(f"Advertencia al sincronizar portal web en /var/www/monitoreo: {e_web}")
                logs.append(f"⚠️ <i>Aviso en despliegue web: {html.escape(str(e_web))}</i>")

        # 5. Validar sintaxis de Python en todo el proyecto
        proc = await asyncio.create_subprocess_exec(
            "python3", "-m", "py_compile", "bot.py", "monitor/checker_base.py", "monitor/system_updater.py", "monitor/boot_alert.py",
            cwd=str(BASE_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        _, err_compile = await proc.communicate()
        if proc.returncode != 0:
            err_text = err_compile.decode("utf-8", errors="ignore")
            logs.append(f"⚠️ <b>Error de sintaxis tras actualizar:</b>\n<pre>{html.escape(err_text)}</pre>")
            logs.append("❌ <i>No se reinició el servicio para evitar interrupción.</i>")
            if bot_instance:
                await notify_owner_git_failure(bot_instance, f"Error de sintaxis Python tras actualizar:\n{err_text}", operation="validación de sintaxis")
            return "\n\n".join(logs)

        logs.append("✅ <i>Sintaxis y validación de código completada exitosamente.</i>")

        # 6. Obtener nuevo commit activo
        _, new_hash, _ = await _run_git_command(["rev-parse", "--short", "HEAD"])
        _, new_msg, _ = await _run_git_command(["log", "-1", "--format=%s", "HEAD"])

        logs.append(f"🎉 <b>Actualización forzada aplicada con éxito a la versión</b> <code>{new_hash}</code>: <i>{html.escape(new_msg)}</i>")
        logs.append("⚡ <b>Reiniciando servicio del bot en segundo plano...</b>")

        # 7. Reiniciar servicio systemd en segundo plano
        asyncio.create_task(_restart_service_delayed())

    except Exception as e:
        logger.error(f"Excepción durante actualización: {e}", exc_info=True)
        logs.append(f"❌ <b>Fallo inesperado:</b> {html.escape(str(e))}")
        if bot_instance:
            await notify_owner_git_failure(bot_instance, str(e), operation="proceso general de actualización")
    finally:
        try:
            shutil.rmtree(temp_backup_dir, ignore_errors=True)
        except Exception:
            pass

    return "\n\n".join(logs)


async def _restart_service_delayed():
    """Espera 1.5 segundos para permitir el envío del mensaje de confirmación y reinicia los servicios."""
    await asyncio.sleep(1.5)
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "systemctl", "restart", "tg-admin-bot.service", "tg-sentinel-bot.service"
        )
        await proc.communicate()
    except Exception as e:
        logger.error(f"Error al reiniciar servicios tras actualización: {e}")




async def auto_update_worker(bot_instance=None, get_owner_id_func=None, get_config_func=None):
    """
    Tarea en segundo plano que comprueba periódicamente (cada 48 horas por defecto)
    si existen nuevas actualizaciones en el repositorio Git de GitHub.
    REGLA INMUTABLE: Jamás se desactiva. Si falla 3 veces consecutivas, activa el Dead Man's Switch.
    """
    logger.info("Servicio inmutable de auto-actualización Git iniciado en segundo plano (Revisión cada 48h).")
    # Espera inicial de 180 segundos para permitir el arranque completo del sistema y la red
    await asyncio.sleep(180)

    consecutive_git_failures = 0
    MAX_CONSECUTIVE_GIT_FAILURES = 3

    while True:
        try:
            config = get_config_func() if get_config_func else {}
            # REGLA 1: La auto-actualización es inmutablemente obligatoria
            is_enabled = is_core_auto_update_enabled()
            interval_hours = int(config.get("auto_update_interval_hours", 48))
            interval_seconds = max(300, interval_hours * 3600)  # Mínimo 5 minutos por seguridad

            logger.info("Ejecutando comprobación autónoma periódica de actualizaciones en GitHub...")
            check_res = await check_updates(bot_instance=bot_instance)

            # Si falló la comprobación (red, DNS, git), notificar de inmediato al Owner
            if not check_res.get("success"):
                consecutive_git_failures += 1
                err_msg = check_res.get("error", "Error desconocido de sincronización con Git")
                logger.error(f"Auto-actualización: Fallo #{consecutive_git_failures} en comprobación Git: {err_msg}")
                await notify_owner_git_failure(
                    bot_instance,
                    err_msg,
                    operation=f"comprobación periódica (intento {consecutive_git_failures}/{MAX_CONSECUTIVE_GIT_FAILURES})"
                )

                # DEAD MAN'S SWITCH: Si falla 3 veces consecutivas, asumir aislamiento del servidor
                if consecutive_git_failures >= MAX_CONSECUTIVE_GIT_FAILURES:
                    logger.critical("🔒 DEAD MAN'S SWITCH: 3 fallos consecutivos con Git. Invalidando anclaje criptográfico local...")
                    trip_deadman_switch(reason="3 fallos consecutivos de sincronización con el repositorio oficial (sospecha de aislamiento/robo)")

            elif check_res.get("has_update"):
                # Resetear contador de fallos al tener éxito
                consecutive_git_failures = 0
                old_hash = check_res.get("local_hash", "N/A")
                new_hash = check_res.get("remote_hash", "N/A")
                commits = check_res.get("commits", [])
                commits_summary = "\n".join(commits[:8])
                if len(commits) > 8:
                    commits_summary += f"\n<i>... y {len(commits) - 8} commit(s) adicionales.</i>"

                repo_url = get_core_repo_url()
                logger.info(f"Actualización Git detectada: {old_hash} -> {new_hash}. Aplicando de forma forzada...")

                if bot_instance:
                    alert_text = (
                        "🔄 <b>Actualización Automática Detectada</b>\n\n"
                        "El sistema ha verificado de manera autónoma el repositorio oficial y ha encontrado una nueva versión.\n\n"
                        f"🏷️ <b>Versión Actual:</b> <code>{old_hash}</code>\n"
                        f"🚀 <b>Nueva Versión:</b> <code>{new_hash}</code>\n"
                        f"🔗 <b>Repositorio:</b> <code>{repo_url}</code>\n\n"
                        f"📦 <b>Novedades ({len(commits)}):</b>\n"
                        f"{commits_summary}\n\n"
                        "⚙️ <i>Aplicando sincronización forzada y preservando <code>config/</code>...</i>"
                    )
                    try:
                        await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=alert_text, parse_mode='HTML')
                    except Exception as e:
                        logger.error(f"No se pudo notificar al Owner antes del auto-update: {e}")

                # Ejecutar actualización forzada
                res_text = await execute_git_update(bot_instance=bot_instance)

                if bot_instance:
                    try:
                        await bot_instance.send_message(chat_id=IMMUTABLE_OWNER_ID, text=res_text, parse_mode='HTML')
                    except Exception as e:
                        logger.error(f"No se pudo notificar al Owner el resultado del auto-update: {e}")

            else:
                # Comprobación exitosa sin actualizaciones: resetear contador
                consecutive_git_failures = 0

            await asyncio.sleep(interval_seconds)

        except asyncio.CancelledError:
            logger.info("Servicio de auto-actualización Git detenido.")
            break
        except Exception as e:
            logger.error(f"Error inesperado en ciclo de auto-actualización Git: {e}", exc_info=True)
            if bot_instance:
                await notify_owner_git_failure(bot_instance, f"Excepción no controlada en hilo de auto-actualización:\n{e}", operation="hilo de auto-actualización")
            await asyncio.sleep(1800)
