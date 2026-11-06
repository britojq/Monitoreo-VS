"""
Enrutador y orquestador de registro para todos los manejadores de administración y seguridad.
Principio OCP: Registra los módulos independientes desacoplados sin monolitos.
"""

from __future__ import annotations
import logging
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    ChatMemberHandler,
    CommandHandler,
    ConversationHandler,
    MessageHandler,
    filters,
)

# 1. Terminal Shield
from bot_core.handlers.shield import (
    cmd_lockdown,
    cmd_unlock,
    cmd_autorizar_consola,
    handle_shield_callback,
    handle_soy_yo_text_message,
)

# 2. System Maintenance
from bot_core.handlers.system_maintenance import (
    cmd_limpiador,
    cmd_recursos_sistema,
    handle_cleaner_callback,
    handle_optimizer_callback,
)

# 3. Thermal Guard
from bot_core.handlers.thermal import (
    cmd_temperatura,
    handle_thermal_callback,
)

# 4. Asistente Corporativo (AI Control)
from bot_core.handlers.ai_control import (
    cmd_ia_control,
    handle_ia_callback,
)

# 5. GitOps & Updates
from bot_core.handlers.gitops import (
    cmd_actualizar,
    cmd_rollback,
    cmd_estado_deploy,
    cmd_desbloquear_update,
    cmd_reiniciar_bot,
    cmd_log_actualizacion,
    handle_update_callback,
)

# 6. Emergency & Reboot
from bot_core.handlers.emergency import (
    cmd_emergencia,
    cmd_reinicia,
    handle_emergency_callback,
)

# 7. Crons
from bot_core.handlers.crons import (
    cmd_cron_control,
    handle_cron_callback,
)

# 8. Groups Guard
from bot_core.handlers.groups_guard import (
    cmd_grupos,
    cmd_salir_grupo,
    on_my_chat_member,
    on_new_chat_members,
)

# 9. RBAC & Permissions
from bot_core.handlers.rbac import (
    manage_permissions,
    handle_auth_callback,
)

# 10. Token Migration
from bot_core.handlers.token_migration import (
    MIGRAR_TOKEN_WAITING,
    cmd_migrar_token_start,
    handle_new_token_input,
    cancel_migrar_token,
    cmd_activar_manual,
)

# 11. Diagnostics & Broadcast
from bot_core.handlers.diagnostics import (
    cmd_broadcast_mensaje,
    cmd_info,
    toggle_debug_monitor,
    toggle_commands_lock,
    bot_status,
)

logger = logging.getLogger("bot_core.handlers.admin_router")


def register_admin_routes(application: Application) -> None:
    """Registra todos los comandos y callbacks de administración de forma desacoplada."""
    # 1. Comandos de Mantenimiento y Recursos
    application.add_handler(CommandHandler(["limpiador", "limpieza", "cleaner"], cmd_limpiador))
    application.add_handler(CommandHandler(["recursos", "memoria", "optimizar", "plasma", "recursos_sistema", "optimizer"], cmd_recursos_sistema))
    application.add_handler(CommandHandler(["temperatura", "temp", "termal", "cpu_temp", "thermal"], cmd_temperatura))
    application.add_handler(CommandHandler(["ia", "motor_ia", "toggle_ia", "servicio_ia", "ia_control"], cmd_ia_control))

    # 2. Comandos de Comunicación e Información
    application.add_handler(CommandHandler(["mensaje", "broadcast", "difusion", "anuncio", "comunicado"], cmd_broadcast_mensaje))
    application.add_handler(CommandHandler(["info", "aviso", "legal", "terminos", "auditoria"], cmd_info))

    # 3. Comandos de GitOps y Despliegue
    application.add_handler(CommandHandler(["actualizar", "update", "upgrade", "git_update", "check_update", "deploy"], cmd_actualizar))
    application.add_handler(CommandHandler(["rollback", "revertir", "git_rollback", "deshacer"], cmd_rollback))
    application.add_handler(CommandHandler(["estado_deploy", "deploy_status", "git_status", "pipeline"], cmd_estado_deploy))
    application.add_handler(CommandHandler(["desbloquear_update", "unlock_update", "clear_lock"], cmd_desbloquear_update))
    application.add_handler(CommandHandler(["reiniciar_bot", "restart_bot", "bot_restart", "reiniciar"], cmd_reiniciar_bot))
    application.add_handler(CommandHandler(["log_actualizacion", "log_deploy", "deploy_log", "auditoria_deploy"], cmd_log_actualizacion))

    # 4. Comandos de Emergencia y Control Host
    application.add_handler(CommandHandler(["emergencia", "panico", "contingencia", "panic", "parada"], cmd_emergencia))
    application.add_handler(CommandHandler(["reinicia", "reboot"], cmd_reinicia))

    # 5. Comandos de Terminal Shield y Seguridad PAM
    application.add_handler(CommandHandler(["lockdown", "bloqueo_total", "bloqueo_perimetral"], cmd_lockdown))
    application.add_handler(CommandHandler(["unlock", "desbloqueo_total", "desbloqueo_perimetral"], cmd_unlock))
    application.add_handler(CommandHandler(["autorizar_consola", "consola", "auth_terminal", "auth_console"], cmd_autorizar_consola))

    # 6. Comandos de Programación (Cron)
    application.add_handler(CommandHandler(["cron", "envios", "reportes_programados", "programacion", "horarios_cron", "envios_cron"], cmd_cron_control))

    # 7. Comandos de Control de Grupos (Regla de Oro #1)
    application.add_handler(CommandHandler(["grupos", "mis_grupos", "listar_grupos", "chat_grupos", "audit_grupos"], cmd_grupos))
    application.add_handler(CommandHandler(["salir_grupo", "leave_group", "expulsar_grupo", "abandonar_grupo"], cmd_salir_grupo))

    # 8. Comandos de Activación y Permisos
    application.add_handler(CommandHandler(["activar", "autorizar_hardware", "auth_hw"], cmd_activar_manual))
    application.add_handler(CommandHandler(["permisos", "autorizados", "whitelist", "usuarios", "accesos"], manage_permissions))
    application.add_handler(CommandHandler(["debug_monitor", "monitordebug", "modo_debug"], toggle_debug_monitor))
    application.add_handler(CommandHandler(["bloqueo_comandos", "bloquear_comandos", "lock_commands", "pausar_comandos", "control_comandos"], toggle_commands_lock))
    application.add_handler(CommandHandler(["botstatus", "statusbot", "estado_bot", "estatus", "status", "estado", "bot_status", "diagnostico_bot"], bot_status))

    # 9. Flujo conversacional de migración de token
    conv_migrar_token = ConversationHandler(
        entry_points=[CommandHandler("migrar_token", cmd_migrar_token_start)],
        states={
            MIGRAR_TOKEN_WAITING: [
                MessageHandler(filters.TEXT & ~filters.COMMAND, handle_new_token_input),
                CommandHandler("cancelar", cancel_migrar_token),
            ]
        },
        fallbacks=[CommandHandler("cancelar", cancel_migrar_token)],
        per_chat=True,
        per_user=True,
    )
    application.add_handler(conv_migrar_token)

    # 10. Callbacks interactivos desacoplados
    application.add_handler(CallbackQueryHandler(handle_cleaner_callback, pattern=r"^(clean|cleaner_act):"))
    application.add_handler(CallbackQueryHandler(handle_optimizer_callback, pattern=r"^sys_opt:"))
    application.add_handler(CallbackQueryHandler(handle_thermal_callback, pattern=r"^thermal:"))
    application.add_handler(CallbackQueryHandler(handle_ia_callback, pattern=r"^ia_act:"))
    application.add_handler(CallbackQueryHandler(handle_update_callback, pattern=r"^update_act:"))
    application.add_handler(CallbackQueryHandler(handle_emergency_callback, pattern=r"^emergencia:"))
    application.add_handler(CallbackQueryHandler(handle_shield_callback, pattern=r"^shield_"))
    application.add_handler(CallbackQueryHandler(handle_cron_callback, pattern=r"^cron_act:"))
    application.add_handler(CallbackQueryHandler(handle_auth_callback, pattern=r"^auth_"))

    # 11. Eventos de chat y miembros
    application.add_handler(ChatMemberHandler(on_my_chat_member, ChatMemberHandler.MY_CHAT_MEMBER))
    application.add_handler(MessageHandler(filters.StatusUpdate.NEW_CHAT_MEMBERS, on_new_chat_members))
    logger.info("Manejadores de administración y seguridad registrados satisfactoriamente.")
