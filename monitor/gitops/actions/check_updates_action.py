# monitor/gitops/actions/check_updates_action.py
import asyncio
import html
import logging
from typing import Any, Dict, List, Optional, Tuple

from telegram import InlineKeyboardButton, InlineKeyboardMarkup

from monitor.shield.secure_core import (
    get_core_repo_url,
    get_core_branch,
)
from monitor.gitops.git_network import evaluate_github_connectivity, get_git_proxy_args
from monitor.tools.database_backup import list_backups
from monitor.gitops.services.update_lock_service import UpdateLockService
from monitor.gitops.services.git_client import GitClient
from monitor.gitops.dtos.gitops_dtos import UpdateCheckResultDto, DeploymentStatusDto

logger = logging.getLogger("monitor.gitops.check")

class CheckUpdatesAction:
    """Acción de consulta de versiones remotas y dashboards interactivos."""

    @staticmethod
    async def check_updates(bot_instance: Any = None) -> Dict[str, Any]:
        """
        Comprueba si existen actualizaciones en el repositorio remoto oficial (origin/master).
        Utiliza el evaluador multi-ruta (Directa vs Proxies) y reporta el estado del Circuit Breaker.
        """
        # 0. Comprobar si hay bloqueo activo
        locked, lock_reason = UpdateLockService.is_locked()
        if locked:
            return UpdateCheckResultDto(
                success=False,
                is_locked=True,
                error=f"⛔ CIRCUIT BREAKER ACTIVO: Las actualizaciones están bloqueadas por un fallo previo.\n\n{lock_reason}\n\nUse /desbloquear_update tras corregir el repositorio.",
            ).to_dict()

        # 1. Evaluar salida de red
        proxy_url, route_label, is_net_ok = await evaluate_github_connectivity(timeout=4.0)
        if not is_net_ok:
            err_msg = "No se detectó salida a GitHub por ninguna de las rutas (Directa ni Proxies corporativos)."
            logger.warning(err_msg)
            return UpdateCheckResultDto(
                success=False,
                is_locked=False,
                error=err_msg,
                route_label=route_label,
            ).to_dict()

        proxy_args = get_git_proxy_args(proxy_url)
        repo_url = get_core_repo_url()
        branch = get_core_branch()

        # 2. Asegurar URL remota inmutable oficial
        await GitClient.run_command(["remote", "set-url", "origin", repo_url])

        # 3. Ejecutar git fetch con reintentos
        rc_fetch, err_fetch = -1, ""
        for attempt in range(1, 3):
            rc_fetch, _, err_fetch = await GitClient.run_command(["fetch", "origin", branch], timeout=25.0, proxy_args=proxy_args)
            if rc_fetch == 0:
                break
            if attempt < 2:
                await asyncio.sleep(4.0)

        if rc_fetch != 0:
            err_msg = err_fetch or "Fallo de conexión con GitHub usando la ruta seleccionada"
            logger.error(f"Error en git fetch ({repo_url}): {err_msg}")
            if bot_instance:
                await GitClient.notify_owner_failure(bot_instance, err_msg, operation="comprobación de actualizaciones")
            return UpdateCheckResultDto(
                success=False,
                is_locked=False,
                error=f"Error conectando con el repositorio remoto: {err_msg}",
                route_label=route_label,
            ).to_dict()

        # 4. Obtener hashes locales y remotos
        _, local_hash, _ = await GitClient.run_command(["rev-parse", "HEAD"])
        _, remote_hash, _ = await GitClient.run_command(["rev-parse", f"origin/{branch}"])

        # 5. Obtener información del commit actual
        _, current_commit_info, _ = await GitClient.run_command(["log", "-1", "--format=%h - %s (%cd)", "--date=format:%d/%m/%Y %H:%M", "HEAD"])

        if local_hash == remote_hash:
            return UpdateCheckResultDto(
                success=True,
                has_update=False,
                is_locked=False,
                local_hash=local_hash[:7] if local_hash else "N/A",
                current_commit=current_commit_info,
                route_label=route_label,
                message="El sistema se encuentra en la versión más reciente.",
            ).to_dict()

        # 6. Obtener lista de commits pendientes
        _, commits_raw, _ = await GitClient.run_command(["log", f"HEAD..origin/{branch}", "--format=• <code>%h</code>: %s (%cr)"])
        commits_list = [c for c in commits_raw.splitlines() if c.strip()]

        if not commits_list:
            return UpdateCheckResultDto(
                success=True,
                has_update=False,
                is_locked=False,
                local_hash=local_hash[:7] if local_hash else "N/A",
                remote_hash=remote_hash[:7] if remote_hash else "N/A",
                current_commit=current_commit_info,
                route_label=route_label,
                message="El bot se encuentra al día o adelantado respecto al remoto.",
            ).to_dict()

        # 7. Obtener lista de archivos modificados
        _, diff_files_raw, _ = await GitClient.run_command(["diff", "--name-status", "HEAD", f"origin/{branch}"])
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

        return UpdateCheckResultDto(
            success=True,
            has_update=True,
            is_locked=False,
            local_hash=local_hash[:7] if local_hash else "N/A",
            remote_hash=remote_hash[:7] if remote_hash else "N/A",
            current_commit=current_commit_info,
            commits=commits_list,
            files=changed_files,
            has_migrations=has_migrations,
            route_label=route_label,
        ).to_dict()

    @staticmethod
    async def build_update_dashboard(bot_instance: Any = None) -> Tuple[str, InlineKeyboardMarkup]:
        """Genera el texto formateado HTML y el teclado interactivo con el estado de Git."""
        check_result = await CheckUpdatesAction.check_updates(bot_instance=bot_instance)

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

        if not check_result.get("success"):
            err_msg = html.escape(check_result.get("error", "Error desconocido"))
            route = html.escape(check_result.get("route_label", "Desconocida"))
            text = (
                "⚠️ <b>Error Comprobando Actualizaciones</b>\n\n"
                f"🌐 <b>Ruta evaluada:</b> <code>{route}</code>\n"
                f"❌ <b>Detalle:</b>\n<pre>{err_msg}</pre>"
            )
            keyboard = [[InlineKeyboardButton("🔄 Reintentar", callback_data="update_act:check")]]
            return text, InlineKeyboardMarkup(keyboard)

        if not check_result.get("has_update"):
            cur_comm = html.escape(check_result.get("current_commit", ""))
            route = html.escape(check_result.get("route_label", "Directa"))
            text = (
                "✅ <b>El sistema se encuentra actualizado</b>\n\n"
                f"🏷️ <b>Versión local:</b> <code>{check_result.get('local_hash')}</code>\n"
                f"📝 <b>Último Commit:</b> <i>{cur_comm}</i>\n"
                f"🌐 <b>Ruta Git:</b> <code>{route}</code>\n\n"
                "<i>No hay commits pendientes en el repositorio oficial.</i>"
            )
            keyboard = [
                [
                    InlineKeyboardButton("🔄 Recomprobar", callback_data="update_act:check"),
                    InlineKeyboardButton("⚡ Forzar Reinstalación", callback_data="update_act:force_reinstall")
                ],
                [
                    InlineKeyboardButton("📊 Estado del Pipeline", callback_data="update_act:status")
                ]
            ]
            return text, InlineKeyboardMarkup(keyboard)

        commits_text = "\n".join(check_result.get("commits", [])[:8])
        if len(check_result.get("commits", [])) > 8:
            commits_text += f"\n<i>... y {len(check_result.get('commits', [])) - 8} commits más.</i>"

        files_text = "\n".join(check_result.get("files", [])[:8])
        if len(check_result.get("files", [])) > 8:
            files_text += f"\n<i>... y {len(check_result.get('files', [])) - 8} archivos más.</i>"

        migr_notice = "⚠️ <b>Contiene migraciones de base de datos.</b>\n" if check_result.get("has_migrations") else ""

        text = (
            "🚀 <b>¡Nueva Actualización Disponible!</b>\n\n"
            f"🏷️ <b>Local:</b> <code>{check_result.get('local_hash')}</code> ➔ <b>Remoto:</b> <code>{check_result.get('remote_hash')}</code>\n"
            f"🌐 <b>Ruta Conexión:</b> <code>{html.escape(check_result.get('route_label', 'Directa'))}</code>\n"
            f"{migr_notice}\n"
            f"📦 <b>Commits Pendientes:</b>\n{commits_text}\n\n"
            f"📂 <b>Archivos Modificados:</b>\n{files_text}\n\n"
            "<i>¿Desea proceder con el despliegue automático con respaldo de BD?</i>"
        )
        keyboard = [
            [
                InlineKeyboardButton("🚀 Actualizar Ahora", callback_data="update_act:confirm"),
                InlineKeyboardButton("🔄 Recomprobar", callback_data="update_act:check")
            ]
        ]
        return text, InlineKeyboardMarkup(keyboard)

    @staticmethod
    async def get_deployment_status() -> Dict[str, Any]:
        """Recopila la información técnica del despliegue, versión, respaldos y red."""
        _, cur_h, _ = await GitClient.run_command(["rev-parse", "--short", "HEAD"])
        _, cur_m, _ = await GitClient.run_command(["log", "-1", "--format=%s", "HEAD"])
        _, cur_date, _ = await GitClient.run_command(["log", "-1", "--format=%cd", "--date=format:%d/%m/%Y %H:%M", "HEAD"])

        proxy_url, route_label, is_net_ok = await evaluate_github_connectivity(timeout=3.5)
        locked, lock_reason = UpdateLockService.is_locked()
        backups = list_backups(3)

        return DeploymentStatusDto(
            commit_hash=cur_h or "unknown",
            commit_msg=cur_m or "unknown",
            commit_date=cur_date or "unknown",
            route_label=route_label,
            is_net_ok=is_net_ok,
            is_locked=locked,
            lock_reason=lock_reason,
            backups=backups,
        ).to_dict()

    @staticmethod
    async def build_deployment_dashboard() -> Tuple[str, InlineKeyboardMarkup]:
        """Construye la vista HTML y teclado interactivo para el comando /estado_deploy."""
        info = await CheckUpdatesAction.get_deployment_status()
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
