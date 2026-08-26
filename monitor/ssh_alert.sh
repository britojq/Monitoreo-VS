#!/usr/bin/env bash
# ==============================================================================
# 🚀 WRAPPER PAM DE ALERTAS SSH: ssh_alert.sh (@IA_ValleSeco_bot)
# Ubicación: /scripts/telegram-admin-bot/monitor/ssh_alert.sh
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
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
