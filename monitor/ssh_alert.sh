#!/usr/bin/env bash
# ==============================================================================
# 🚀 HOOK PAM DE SEGURIDAD Y CONTROL DE ACCESO: ssh_alert.sh (@IA_ValleSeco_bot)
# Despacho desacoplado para SSH y contención autónoma estricta para sudo
# Ubicación: /scripts/telegram-admin-bot/monitor/ssh_alert.sh
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

# 0. Si hay Override Maestro del Owner activo, permitir inmediatamente (0 ms)
if [ -f "/dev/shm/shield_master_override_active" ]; then
    exit 0
fi

# 1. Si hay ventana de mantenimiento autorizada vigente, permitir inmediatamente (0 ms)
if [ -f "/dev/shm/terminal_shield_grace_period" ]; then
    EXP_TS=$(cat /dev/shm/terminal_shield_grace_period 2>/dev/null || echo 0)
    NOW_TS=$(date +%s)
    if [ "$NOW_TS" -lt "${EXP_TS%.*}" ] 2>/dev/null; then
        exit 0
    fi
fi

PYTHON_EXEC="/scripts/telegram-admin-bot/venv/bin/python"
SHIELD_SCRIPT="/scripts/telegram-admin-bot/monitor/terminal_shield.py"
ALERT_SCRIPT="/scripts/telegram-admin-bot/monitor/ssh_alert.py"

# Detección de procesos del Asistente Técnico / agente de desarrollo
CALLER_OF_SUDO=$(ps -o ppid= -p ${PPID:-1} 2>/dev/null | tr -d ' ')
IS_ASSISTANT_FLAG=""

if [ -n "${ANTIGRAVITY_AGENT:-}" ] || [ -n "${ANTIGRAVITY_CONVERSATION_ID:-}" ]; then
    IS_ASSISTANT_FLAG="--is-agent"
fi

if [ -z "$IS_ASSISTANT_FLAG" ]; then
    CHECK_PID="${PPID:-1}"
    for i in 1 2 3 4; do
        if [ "$CHECK_PID" -le 1 ] 2>/dev/null; then
            break
        fi
        if grep -q -a -E "ANTIGRAVITY|agy" /proc/$CHECK_PID/cmdline 2>/dev/null || grep -q -a -E "ANTIGRAVITY|agy" /proc/$CHECK_PID/environ 2>/dev/null; then
            IS_ASSISTANT_FLAG="--is-agent"
            break
        fi
        CHECK_PID=$(ps -o ppid= -p "$CHECK_PID" 2>/dev/null | tr -d ' ')
    done
fi

# 2. Control autónomo estricto para sudo / su interactivo (Opción B)
if [ "${PAM_SERVICE:-}" = "sudo" ] || [ "${PAM_SERVICE:-}" = "su" ] || [ "${PAM_SERVICE:-}" = "su-l" ]; then
    # Si proviene del agente de IA / entorno asistido, no aplicar contención
    if [ -z "$IS_ASSISTANT_FLAG" ]; then
        IS_INTERACTIVE=0
        if [ -n "$PAM_TTY" ] && [ "$PAM_TTY" != "none" ] && [ "$PAM_TTY" != "?" ]; then
            case "$PAM_TTY" in
                pts/*|tty*|/dev/pts/*|/dev/tty*)
                    IS_INTERACTIVE=1
                    ;;
            esac
        fi

    if [ "$IS_INTERACTIVE" -eq 1 ] && [ "${PAM_TYPE:-open_session}" = "open_session" ]; then
        if [ -x "$PYTHON_EXEC" ] && [ -f "$SHIELD_SCRIPT" ]; then
            # Determinar el usuario invocador real y el comando ejecutado
            CALLER_USER="${SUDO_USER:-${PAM_RUSER:-}}"
            if [ -z "$CALLER_USER" ] || [ "$CALLER_USER" = "root" ]; then
                CALLER_USER="$(logname 2>/dev/null || id -un 2>/dev/null || whoami)"
            fi

            if [ -n "$SUDO_COMMAND" ]; then
                CMD_DESC="$SUDO_COMMAND"
            elif [ "${PAM_SERVICE:-}" = "su" ] || [ "${PAM_SERVICE:-}" = "su-l" ]; then
                CMD_DESC="su / su - (cambio a ${PAM_USER:-root})"
            else
                CMD_DESC="sudo / escalación"
            fi

            ENFORCE_OUT=$("$PYTHON_EXEC" "$SHIELD_SCRIPT" \
                --action enforce_sudo \
                --user "$CALLER_USER" \
                --sudo-user "${PAM_USER:-root}" \
                --sudo-cmd "$CMD_DESC" \
                --tty "$PAM_TTY" \
                --ppid "$PPID" 2>/dev/null)

            if echo "$ENFORCE_OUT" | grep -q '"allowed": false'; then
                INFRAC=$(echo "$ENFORCE_OUT" | grep -oP '"infractions":\s*\K[0-9]+' || echo "1")
                echo ""
                echo -e "\033[1;31m🛑 [SEGURIDAD DEL SISTEMA] ACCESO ADMINISTRATIVO DENEGADO.\033[0m"
                echo -e "\033[1;33m⚠️  Intento no autorizado de escalación de privilegios ('${PAM_SERVICE:-sudo}') interceptado.\033[0m"
                if [ "$INFRAC" -ge 3 ]; then
                    echo -e "\033[1;31m🚨 LÍMITE MÁXIMO DE REINCIDENCIAS ALCANZADO ($INFRAC/3). REINICIANDO EL EQUIPO EN 10s...\033[0m"
                else
                    echo -e "\033[1;37m   Infracción $INFRAC de 3 registrada. Clausurando sesión interactiva por seguridad.\033[0m"
                    echo -e "\033[0;36m   Para autorizar tareas legítimas ejecute previamente: shield-override\033[0m"
                fi
                echo ""
                exit 1
            fi
        fi
    fi
    fi
fi

# 3. Despacho estándar para auditoría PAM general (SSH login/logout y sudo no interactivo)
if [ -x "$PYTHON_EXEC" ] && [ -f "$ALERT_SCRIPT" ]; then
    (
        /usr/bin/timeout --signal=SIGTERM 15s "$PYTHON_EXEC" "$ALERT_SCRIPT" \
            --user "$PAM_USER" \
            --rhost "$PAM_RHOST" \
            --tty "$PAM_TTY" \
            --service "$PAM_SERVICE" \
            --type "$PAM_TYPE" \
            --sudo-user "$SUDO_USER" \
            --sudo-cmd "$SUDO_COMMAND" \
            $IS_ASSISTANT_FLAG \
            ${CALLER_OF_SUDO:+--caller-pid "$CALLER_OF_SUDO"} >/dev/null 2>&1 &
    ) >/dev/null 2>&1
fi

exit 0
