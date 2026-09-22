#!/usr/bin/env bash
# ==============================================================================
# 🛡️ SHORTCUT GLOBAL: shield-override (@IA_ValleSeco_bot)
# Desbloqueo y gestión de Override Maestro para el Owner
# Ubicación de despliegue: /usr/local/bin/shield-override
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

PYTHON_EXEC="/scripts/telegram-admin-bot/venv/bin/python"
SHIELD_SCRIPT="/scripts/telegram-admin-bot/monitor/terminal_shield.py"

if [ ! -x "$PYTHON_EXEC" ] || [ ! -f "$SHIELD_SCRIPT" ]; then
    echo "Error: Componentes de Sentinel Shield no encontrados en /scripts/telegram-admin-bot" >&2
    exit 1
fi

case "$1" in
    --status|-s|status)
        exec "$PYTHON_EXEC" "$SHIELD_SCRIPT" --action status
        ;;
    --rearm|-r|rearm|lock)
        exec "$PYTHON_EXEC" "$SHIELD_SCRIPT" --action rearm
        ;;
    --unlock|unlock)
        exec "$PYTHON_EXEC" "$SHIELD_SCRIPT" --action unlock
        ;;
    *)
        exec "$PYTHON_EXEC" "$SHIELD_SCRIPT" --action override "$@"
        ;;
esac
