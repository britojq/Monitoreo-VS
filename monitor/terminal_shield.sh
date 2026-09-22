#!/usr/bin/env bash
# ==============================================================================
# 🛡️ HOOK GLOBAL DE TERMINAL INTERACTIVA: terminal_shield.sh (@IA_ValleSeco_bot)
# Ubicación de despliegue: /etc/profile.d/terminal_shield.sh
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

# 0. Si hay Override Maestro del Owner activo, omitir toda comprobación y permitir acceso completo
if [ -f "/dev/shm/shield_master_override_active" ]; then
    return 0 2>/dev/null || exit 0
fi

# Evitar ejecuciones duplicadas dentro de la misma instancia de shell
if [ "${_SENTINEL_SHIELD_EVALUATED_PID:-}" = "$$" ]; then
    return 0 2>/dev/null || exit 0
fi
_SENTINEL_SHIELD_EVALUATED_PID="$$"

# Solo actuar en shells interactivas con terminal física o pseudo-terminal asignada
if [[ $- == *i* ]] && [ -t 0 ]; then
    CURRENT_TTY="$(tty 2>/dev/null || echo '')"
    if [ -n "$CURRENT_TTY" ] && [ "$CURRENT_TTY" != "not a tty" ]; then
        # 1. Si hay Lockdown activo en el host, expulsar y cerrar inmediatamente
        if [ -f "/dev/shm/system_lockdown_active" ]; then
            echo -e "\n\033[1;31m🛑 [LOCKDOWN DE SEGURIDAD ACTIVO]\033[0m"
            echo "El acceso interactivo a terminales está bloqueado por el Administrador."
            kill -9 $$ 2>/dev/null
            exit 1
        fi

        PYTHON_EXEC="/scripts/telegram-admin-bot/venv/bin/python"
        SHIELD_SCRIPT="/scripts/telegram-admin-bot/monitor/terminal_shield.py"
        if [ -x "$PYTHON_EXEC" ] && [ -f "$SHIELD_SCRIPT" ]; then
            # Lanzar en subshell desacoplada sin control de trabajos de bash (evita el aviso '[1]+ Hecho' en la terminal)
            (
                /usr/bin/timeout --signal=SIGTERM 15s "$PYTHON_EXEC" "$SHIELD_SCRIPT" \
                    --action alert_terminal \
                    --pid "$$" \
                    --ppid "$PPID" \
                    --user "${USER:-$(whoami)}" \
                    --tty "$CURRENT_TTY" \
                    --display "${DISPLAY:-}" >/dev/null 2>&1 &
            ) >/dev/null 2>&1
        fi
    fi
fi
