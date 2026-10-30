"""
Manejador de actualizaciones desde Git, auditoría de despliegue y callbacks de GitOps.
"""

from __future__ import annotations
import asyncio
import html
import json
import logging
from pathlib import Path
from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
from telegram.ext import ContextTypes

from bot_core.handlers.gitops.dtos import UpdateActionPayload
from bot_core.services.config_manager import load_config
from monitor.core_shield import IMMUTABLE_OWNER_ID

logger = logging.getLogger("bot_core.handlers.gitops.updater")


def _is_owner(user_id: int) -> bool:
    cfg = load_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    return user_id in (owner_id, IMMUTABLE_OWNER_ID)


async def _send_last_deployment_audit_doc(context: ContextTypes.DEFAULT_TYPE, target_chat_id: int) -> bool:
    """Extrae el último registro de despliegue desde MariaDB audit_logs y lo envía como documento efímero."""
    log_content: str | None = None
    commit_info = "N/A"

    try:
        from monitor.monitor_db import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT description, new_values, created_at
                    FROM audit_logs
                    WHERE event = 'DEPLOYMENT'
                    ORDER BY id DESC
                    LIMIT 1
                    """
                )
                row = cur.fetchone()
                if row:
                    desc, new_val_raw, created_at = row
                    details: dict = {}
                    if new_val_raw:
                        try:
                            details = json.loads(new_val_raw) if isinstance(new_val_raw, str) else new_val_raw
                        except Exception:
                            pass

                    commit_info = details.get("commit_hash", "N/A")[:10]
                    log_content = (
                        f"======================================================================\n"
                        f"  S.O.N.A.R. DEPLOYMENT AUDIT MANIFEST (SSOT MARIADB)\n"
                        f"======================================================================\n"
                        f"Fecha y Hora : {created_at}\n"
                        f"Descripción  : {desc}\n"
                        f"Commit Hash  : {details.get('commit_hash', 'N/A')}\n"
                        f"Commit Msg   : {details.get('commit_message', 'N/A')}\n"
                        f"Autor Git    : {details.get('commit_author', 'N/A')}\n"
                        f"Estado Final : {details.get('deployment_status', 'SUCCESS')}\n"
                        f"Duración     : {details.get('duration_seconds', 0.0):.2f}s\n"
                        f"----------------------------------------------------------------------\n"
                        f"DETALLE DE EJECUCIÓN (SMOKE TESTS Y CENSO):\n"
                        f"----------------------------------------------------------------------\n"
                        f"{json.dumps(details, indent=2, ensure_ascii=False)}\n"
                        f"======================================================================\n"
                    )
        finally:
            conn.close()
    except Exception as e:
        logger.error(f"Error consultando audit_logs en MariaDB: {e}", exc_info=True)

    if not log_content:
        return False

    temp_doc = Path(f"/tmp/deploy_audit_{commit_info}.txt")
    try:
        temp_doc.write_text(log_content, encoding="utf-8")
        with open(temp_doc, "rb") as f:
            await context.bot.send_document(
                chat_id=target_chat_id,
                document=f,
                filename=f"auditoria_deploy_{commit_info}.txt",
                caption=f"📄 <b>Registro Forense de Despliegue ({commit_info})</b>\nRecuperado directamente de MariaDB <code>audit_logs</code>.",
                parse_mode="HTML"
            )
        return True
    except Exception as e:
        logger.error(f"Error enviando documento de auditoría: {e}", exc_info=True)
        return False
    finally:
        try:
            temp_doc.unlink(missing_ok=True)
        except Exception:
            pass


async def cmd_actualizar(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Verifica y aplica actualizaciones desde Git (Exclusivo Owner en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    from monitor.system_updater import (
        build_update_dashboard,
        execute_git_update,
        restart_service_delayed,
        safe_deliver_update_result,
    )

    if context.args:
        arg = context.args[0].lower()
        if arg in ("now", "apply", "aplicar", "force", "instalar", "si"):
            wait_msg = await update.message.reply_text(
                "⏳ <b>[1/5] Iniciando despliegue de actualización...</b>",
                parse_mode="HTML"
            )

            async def progress_hook(status_text: str) -> None:
                try:
                    await wait_msg.edit_text(status_text, parse_mode="HTML")
                except Exception as e_hook:
                    logger.debug(f"Aviso actualizando wait_msg en cmd_actualizar: {e_hook}")

            try:
                res = await execute_git_update(
                    bot_instance=context.bot,
                    auto_restart=False,
                    progress_callback=progress_hook
                )
            except Exception as e_exec:
                logger.error(f"Error imprevisto ejecutando git update: {e_exec}", exc_info=True)
                res = f"❌ <b>Error inesperado durante la ejecución de la actualización:</b>\n<pre>{html.escape(str(e_exec))}</pre>"

            await safe_deliver_update_result(update.message, res, edit_msg=wait_msg)
            asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
            return

    wait_msg = await update.message.reply_text(
        "⏳ <i>Comprobando repositorio remoto oficial en GitHub (origin/master)...</i>",
        parse_mode="HTML"
    )
    dashboard_text, keyboard = await build_update_dashboard(bot_instance=context.bot)
    try:
        await wait_msg.delete()
    except Exception:
        pass

    await update.message.reply_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)


async def handle_update_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Maneja las acciones de actualización del sistema presionadas por el Owner."""
    query = update.callback_query
    if not query or not query.data:
        return

    clicker_id = query.from_user.id
    if not _is_owner(clicker_id):
        await query.answer("⛔ Solo el creador y administrador del bot puede gestionar actualizaciones.", show_alert=True)
        return

    from monitor.system_updater import (
        build_deployment_dashboard,
        build_update_dashboard,
        clear_update_lock,
        execute_git_update,
        execute_rollback,
        restart_service_delayed,
        safe_deliver_update_result,
    )

    payload = UpdateActionPayload.from_callback_data(query.data)
    action = payload.action

    if action in ("check", "refresh"):
        await query.answer("🔄 Verificando repositorio...")
        dashboard_text, keyboard = await build_update_dashboard(bot_instance=context.bot)
        try:
            await query.edit_message_text(dashboard_text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    elif action in ("apply", "force"):
        await query.answer("🚀 Aplicando actualización...")
        try:
            await query.edit_message_text(
                "⏳ <b>[1/5] Iniciando despliegue de actualización...</b>\n\n"
                "• Evaluando conectividad y proxy...\n"
                "• Respaldando configuración en MariaDB (bot_settings)...\n"
                "• Generando respaldo comprimido de base de datos MariaDB...",
                parse_mode="HTML"
            )
        except Exception:
            pass

        async def progress_hook(status_text: str) -> None:
            try:
                await query.edit_message_text(status_text, parse_mode="HTML")
            except Exception as e_hook:
                logger.debug(f"Aviso actualizando query en handle_update_callback: {e_hook}")

        try:
            res = await execute_git_update(
                bot_instance=context.bot,
                auto_restart=False,
                progress_callback=progress_hook
            )
        except Exception as e_exec:
            logger.error(f"Error imprevisto ejecutando git update: {e_exec}", exc_info=True)
            res = f"❌ <b>Error inesperado durante la ejecución de la actualización:</b>\n<pre>{html.escape(str(e_exec))}</pre>"

        await safe_deliver_update_result(query, res)
        asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
        return

    elif action == "status":
        await query.answer("📊 Consultando estado de despliegue...")
        text, keyboard = await build_deployment_dashboard()
        try:
            await query.edit_message_text(text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    elif action == "unlock":
        await query.answer("🔓 Desbloqueando Circuit Breaker...")
        clear_update_lock()
        text, keyboard = await build_deployment_dashboard()
        try:
            await query.edit_message_text("✅ <b>Circuit Breaker liberado.</b>\n\n" + text, parse_mode="HTML", reply_markup=keyboard)
        except Exception:
            pass
        return

    elif action == "rollback_prompt":
        await query.answer()
        keyboard = [
            [
                InlineKeyboardButton("⚠️ Confirmar Reversión Inmediata", callback_data="update_act:rollback_confirm"),
                InlineKeyboardButton("❌ Cancelar", callback_data="update_act:status")
            ]
        ]
        prompt_text = (
            "⚠️ <b>¿Confirma que desea revertir el sistema a la versión anterior?</b>\n\n"
            "• Repositorio Git volverá a <code>HEAD@{1}</code>.\n"
            "• MariaDB restaurará el último snapshot comprimido.\n"
            "• El portal web se sincronizará y se purgará la caché.\n"
            "• El bot se reiniciará automáticamente."
        )
        try:
            await query.edit_message_text(prompt_text, parse_mode="HTML", reply_markup=InlineKeyboardMarkup(keyboard))
        except Exception:
            pass
        return

    elif action == "rollback_confirm":
        await query.answer("🔄 Ejecutando Rollback...")
        try:
            await query.edit_message_text(
                "⏳ <b>[1/3] Ejecutando Rollback seguro del sistema...</b>\n\n"
                "• Revirtiendo repositorio Git a commit anterior...\n"
                "• Restaurando snapshot de base de datos MariaDB...",
                parse_mode="HTML"
            )
        except Exception:
            pass

        async def progress_hook_rb(status_text: str) -> None:
            try:
                await query.edit_message_text(status_text, parse_mode="HTML")
            except Exception as e_hook:
                logger.debug(f"Aviso actualizando query rollback: {e_hook}")

        try:
            res = await execute_rollback(
                bot_instance=context.bot,
                auto_restart=False,
                progress_callback=progress_hook_rb
            )
        except Exception as e_rb:
            logger.error(f"Error imprevisto ejecutando rollback: {e_rb}", exc_info=True)
            res = f"❌ <b>Error inesperado durante el rollback:</b>\n<pre>{html.escape(str(e_rb))}</pre>"

        await safe_deliver_update_result(query, res)
        asyncio.create_task(restart_service_delayed(delay_seconds=5.0))
        return

    elif action == "get_audit_log":
        await query.answer("📋 Obteniendo log de auditoría...")
        sent = await _send_last_deployment_audit_doc(context, clicker_id)
        if not sent and query.message:
            await query.message.reply_text("ℹ️ No se ha encontrado un registro de despliegue en MariaDB audit_logs.")
        return


async def cmd_estado_deploy(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Muestra el estado técnico de GitOps, conectividad, Circuit Breaker y respaldos."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    from monitor.system_updater import build_deployment_dashboard

    wait_msg = await update.message.reply_text(
        "⏳ <i>Consultando estado técnico del pipeline y enlaces de red...</i>",
        parse_mode="HTML"
    )
    text, keyboard = await build_deployment_dashboard()
    try:
        await wait_msg.delete()
    except Exception:
        pass

    await update.message.reply_text(text, parse_mode="HTML", reply_markup=keyboard)


async def cmd_log_actualizacion(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Envía el archivo de auditoría del último despliegue al Owner (Exclusivo en privado)."""
    chat = update.effective_chat
    user = update.effective_user
    if not chat or chat.type != "private" or not user or not update.message:
        return

    if not _is_owner(user.id):
        await update.message.reply_text("⛔ Comando exclusivo del Administrador en chat privado.")
        return

    sent = await _send_last_deployment_audit_doc(context, user.id)
    if not sent:
        await update.message.reply_text("ℹ️ No se ha encontrado un registro de despliegue en MariaDB audit_logs.")
