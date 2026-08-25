#!/usr/bin/env bash
# ==============================================================================
# Script: ssh_alert.sh
# Descripción: Wrapper PAM para ejecutar monitor/ssh_alert.py en segundo plano
# Ubicación: /scripts/telegram-admin-bot/monitor/ssh_alert.sh
# ==============================================================================

# Solo actuar cuando se abre una sesión SSH
if [ "$PAM_TYPE" != "open_session" ] || [ "$PAM_SERVICE" != "sshd" ]; then
    exit 0
fi

PYTHON_EXEC="/scripts/telegram-admin-bot/venv/bin/python"
SCRIPT_EXEC="/scripts/telegram-admin-bot/monitor/ssh_alert.py"

if [ -x "$PYTHON_EXEC" ] && [ -f "$SCRIPT_EXEC" ]; then
    # Ejecutar en segundo plano desacoplado para no bloquear el login interactivo SSH
    ( "$PYTHON_EXEC" "$SCRIPT_EXEC" >/dev/null 2>&1 & )
fi

exit 0
