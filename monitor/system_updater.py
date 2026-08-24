"""
Módulo de actualización y verificación de versiones desde el repositorio Git.
Permite al Administrador (Owner) verificar si existen commits/cambios en GitHub,
inspeccionar novedades y aplicar la actualización de forma segura preservando la carpeta config/.
"""

from __future__ import annotations

import asyncio
import html
import logging
import os
import shutil
import tempfile
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup

logger = logging.getLogger("monitor.system_updater")

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"


async def _run_git_command(args: List[str], timeout: float = 15.0) -> Tuple[int, str, str]:
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
        return -1, "", "Timeout: El comando Git tardó demasiado."
    except Exception as e:
        return -1, "", str(e)


async def check_updates() -> Dict[str, any]:
    """
    Comprueba si existen actualizaciones en el repositorio remoto (origin/master).
    Devuelve un diccionario con el estado, hashes, commits y archivos afectados.
    """
    # 1. Ejecutar git fetch
    rc_fetch, _, err_fetch = await _run_git_command(["fetch", "origin", "master"], timeout=15.0)
    if rc_fetch != 0:
        return {
            "success": False,
            "error": f"Error conectando con el repositorio remoto: {err_fetch or 'Fallo de red o credenciales'}"
        }

    # 2. Obtener hashes locales y remotos
    _, local_hash, _ = await _run_git_command(["rev-parse", "HEAD"])
    _, remote_hash, _ = await _run_git_command(["rev-parse", "origin/master"])

    # 3. Obtener información del commit actual
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
    _, commits_raw, _ = await _run_git_command(["log", "HEAD..origin/master", "--format=• <code>%h</code>: %s (%cr)"])
    commits_list = [c for c in commits_raw.splitlines() if c.strip()]

    # 5. Obtener lista de archivos modificados
    _, diff_files_raw, _ = await _run_git_command(["diff", "--name-status", "HEAD", "origin/master"])
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


async def build_update_dashboard() -> Tuple[str, Optional[InlineKeyboardMarkup]]:
    """Construye el texto y botones para el panel de actualización."""
    check_result = await check_updates()

    if not check_result.get("success"):
        error_msg = html.escape(check_result.get("error", "Error desconocido"))
        text = (
            "🔄 <b>PANEL DE ACTUALIZACIÓN DE SISTEMA (GIT)</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            f"❌ <b>Error al verificar actualizaciones:</b>\n{error_msg}\n\n"
            "<i>Verifica la conexión a Internet o el acceso a GitHub.</i>"
        )
        keyboard = [[InlineKeyboardButton("🔄 Reintentar Verificación", callback_data="update_act:check")]]
        return text, InlineKeyboardMarkup(keyboard)

    if not check_result.get("has_update"):
        current_info = html.escape(check_result.get("current_commit", "N/A"))
        text = (
            "🔄 <b>PANEL DE ACTUALIZACIÓN DE SISTEMA (GIT)</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            "✅ <b>¡El sistema se encuentra 100% actualizado!</b>\n\n"
            f"🏷️ <b>Versión Actual:</b> <code>{check_result.get('local_hash')}</code>\n"
            f"📋 <b>Último Commit:</b> {current_info}\n\n"
            "<i>No hay commits pendientes por descargar desde GitHub.</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🔄 Comprobar de Nuevo", callback_data="update_act:check"),
                InlineKeyboardButton("⚡ Forzar Reinstalación", callback_data="update_act:force")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    # Si hay actualizaciones disponibles
    local_h = check_result.get("local_hash")
    remote_h = check_result.get("remote_hash")
    commits = check_result.get("commits", [])
    files = check_result.get("files", [])

    commits_text = "\n".join(commits[:10])
    if len(commits) > 10:
        commits_text += f"\n<i>... y {len(commits) - 10} commit(s) más.</i>"

    files_text = "\n".join(files[:12])
    if len(files) > 12:
        files_text += f"\n<i>... y {len(files) - 12} archivo(s) más.</i>"

    text = (
        "🔄 <b>NUEVA ACTUALIZACIÓN DISPONIBLE (GIT)</b>\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        f"🏷️ <b>Versión Local:</b> <code>{local_h}</code>\n"
        f"🚀 <b>Versión Remota:</b> <code>{remote_h}</code>\n\n"
        f"📦 <b>Novedades y Commits ({len(commits)}):</b>\n"
        f"{commits_text}\n\n"
        f"📂 <b>Archivos Afectados ({len(files)}):</b>\n"
        f"{files_text}\n\n"
        "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
        "🛡️ <i>Tus archivos de configuración (<code>config/</code>) serán respaldados y preservados intactos.</i>"
    )

    keyboard = [
        [
            InlineKeyboardButton("🚀 Descargar e Instalar Ahora", callback_data="update_act:apply"),
            InlineKeyboardButton("🔄 Recomprobar", callback_data="update_act:check")
        ]
    ]
    return text, InlineKeyboardMarkup(keyboard)


async def execute_git_update() -> str:
    """
    Ejecuta el ciclo completo de actualización segura:
    1. Respaldo de seguridad de config/.
    2. Git pull origin master.
    3. Restauración y protección de config/.
    4. Verificación de sintaxis de Python (py_compile).
    5. Reinicio del servicio systemd.
    """
    logs = []
    logs.append("📦 <b>Iniciando actualización segura del sistema...</b>")

    temp_backup_dir = Path(tempfile.mkdtemp(prefix="tgbot_cfg_bak_"))
    try:
        # 1. Respaldar config/
        if CONFIG_DIR.exists():
            for item in CONFIG_DIR.iterdir():
                if item.is_file():
                    shutil.copy2(item, temp_backup_dir / item.name)
            logs.append("🛡️ <i>Copia de seguridad local de configuración creada.</i>")

        # 2. Ejecutar Git pull
        rc_pull, out_pull, err_pull = await _run_git_command(["pull", "origin", "master"], timeout=30.0)
        if rc_pull != 0:
            logs.append(f"❌ <b>Error en Git Pull:</b>\n<pre>{html.escape(err_pull or out_pull)}</pre>")
            return "\n\n".join(logs)

        logs.append("⬇️ <i>Código y scripts actualizados desde el repositorio.</i>")

        # 3. Restaurar archivos de configuración preservados
        if temp_backup_dir.exists():
            for item in temp_backup_dir.iterdir():
                if item.is_file():
                    shutil.copy2(item, CONFIG_DIR / item.name)
            logs.append("🔒 <i>Archivos de configuración preservados intactos.</i>")

        # 4. Validar sintaxis de Python
        proc = await asyncio.create_subprocess_exec(
            "python3", "-m", "py_compile", "bot.py",
            cwd=str(BASE_DIR),
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        _, err_compile = await proc.communicate()
        if proc.returncode != 0:
            err_text = err_compile.decode("utf-8", errors="ignore")
            logs.append(f"⚠️ <b>Error de sintaxis tras actualizar:</b>\n<pre>{html.escape(err_text)}</pre>")
            logs.append("❌ <i>No se reinició el servicio para evitar interrupción.</i>")
            return "\n\n".join(logs)

        logs.append("✅ <i>Sintaxis y validación de código completada exitosamente.</i>")

        # 5. Obtener nuevo commit activo
        _, new_hash, _ = await _run_git_command(["rev-parse", "--short", "HEAD"])
        _, new_msg, _ = await _run_git_command(["log", "-1", "--format=%s", "HEAD"])

        logs.append(f"🎉 <b>Actualización aplicada con éxito a la versión</b> <code>{new_hash}</code>: <i>{html.escape(new_msg)}</i>")
        logs.append("⚡ <b>Reiniciando servicio del bot en segundo plano...</b>")

        # 6. Reiniciar servicio systemd en segundo plano (asíncrono desvinculado)
        asyncio.create_task(_restart_service_delayed())

    except Exception as e:
        logger.error(f"Excepción durante actualización: {e}", exc_info=True)
        logs.append(f"❌ <b>Fallo inesperado:</b> {html.escape(str(e))}")
    finally:
        try:
            shutil.rmtree(temp_backup_dir, ignore_errors=True)
        except Exception:
            pass

    return "\n\n".join(logs)


async def _restart_service_delayed():
    """Espera 1.5 segundos para permitir el envío del mensaje de confirmación y reinicia el servicio."""
    await asyncio.sleep(1.5)
    try:
        proc = await asyncio.create_subprocess_exec(
            "sudo", "systemctl", "restart", "tg-admin-bot.service"
        )
        await proc.communicate()
    except Exception as e:
        logger.error(f"Error al reiniciar servicio tras actualización: {e}")


async def auto_update_worker(bot_instance=None, get_owner_id_func=None, get_config_func=None):
    """
    Tarea en segundo plano que comprueba periódicamente (cada 48 horas por defecto)
    si existen nuevas actualizaciones en el repositorio Git de GitHub.
    De existir, aplica la actualización protegiendo config/ y notifica al Owner.
    """
    logger.info("Servicio de auto-actualización Git iniciado en segundo plano (Revisión cada 48h).")
    # Espera inicial de 120 segundos para permitir el arranque completo del bot
    await asyncio.sleep(120)

    while True:
        try:
            config = get_config_func() if get_config_func else {}
            is_enabled = bool(config.get("auto_update_enabled", True))
            interval_hours = int(config.get("auto_update_interval_hours", 48))
            interval_seconds = max(300, interval_hours * 3600)  # Mínimo 5 minutos por seguridad

            if is_enabled:
                logger.info("Ejecutando comprobación autónoma de actualizaciones en GitHub...")
                check_res = await check_updates()
                if check_res.get("success") and check_res.get("has_update"):
                    old_hash = check_res.get("local_hash", "N/A")
                    new_hash = check_res.get("remote_hash", "N/A")
                    commits = check_res.get("commits", [])
                    commits_summary = "\n".join(commits[:8])
                    if len(commits) > 8:
                        commits_summary += f"\n<i>... y {len(commits) - 8} commit(s) adicionales.</i>"

                    logger.info(f"Actualización Git detectada: {old_hash} -> {new_hash}. Aplicando de forma autónoma...")

                    owner_id = get_owner_id_func() if get_owner_id_func else 0
                    if owner_id and bot_instance:
                        alert_text = (
                            "🔄 <b>Actualización Automática Detectada</b>\n"
                            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                            "El sistema ha verificado de manera autónoma el repositorio en GitHub y ha encontrado una nueva versión.\n\n"
                            f"🏷️ <b>Versión Actual:</b> <code>{old_hash}</code>\n"
                            f"🚀 <b>Nueva Versión:</b> <code>{new_hash}</code>\n\n"
                            f"📦 <b>Novedades ({len(commits)}):</b>\n"
                            f"{commits_summary}\n\n"
                            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                            "⚙️ <i>Aplicando actualización segura y preservando <code>config/</code>...</i>"
                        )
                        try:
                            await bot_instance.send_message(chat_id=owner_id, text=alert_text, parse_mode='HTML')
                        except Exception as e:
                            logger.error(f"No se pudo notificar al Owner antes del auto-update: {e}")

                    # Ejecutar actualización segura
                    res_text = await execute_git_update()

                    if owner_id and bot_instance:
                        try:
                            await bot_instance.send_message(chat_id=owner_id, text=res_text, parse_mode='HTML')
                        except Exception as e:
                            logger.error(f"No se pudo notificar al Owner el resultado del auto-update: {e}")

            await asyncio.sleep(interval_seconds)

        except asyncio.CancelledError:
            logger.info("Servicio de auto-actualización Git detenido.")
            break
        except Exception as e:
            logger.error(f"Error en ciclo de auto-actualización Git: {e}", exc_info=True)
            await asyncio.sleep(3600)

