#!/usr/bin/env bash
# ==============================================================================
# 🧹 SCRIPT DE SANEAMIENTO PARA SERVIDOR DE PRODUCCIÓN (DISCO CLONADO)
# Ubicación: /scripts/telegram-admin-bot/sanitize_production_clone.sh
# Propósito: Purgar datos personales, corregir fstab, regenerar machine-id
#            y preparar el anclaje limpio de hardware en producción.
# ==============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Este script debe ejecutarse como root (sudo).${NC}"
    exit 1
fi

echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW} ⚠️  ADVERTENCIA CRÍTICA: SANEAMIENTO DE SISTEMA CLONADO    ⚠️ ${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "Este script eliminará de forma IRREVERSIBLE:"
echo -e " • Perfiles de correo Thunderbird, perfiles de navegadores y mensajería."
echo -e " • Carpetas personales (Documentos, Imágenes, Descargas, Vídeos, Respaldos)."
echo -e " • Tokens personales de desarrollo (.gemini, .cursor, .vscode, .git-credentials)."
echo -e " • Historial de comandos (.bash_history) y cachés."
echo -e " • La entrada del disco secundario en /etc/fstab (/home/britojab/compartida)."
echo -e " • El machine-id del sistema y el anclaje criptográfico previo del bot."
echo ""
echo -e "${RED}¿Está COMPLETAMENTE SEGURO de que este es el servidor de PRODUCCIÓN CLONADO?${NC}"
echo -n "Escriba 'SANEAR-PRODUCCION' para continuar: "
read -r CONFIRMATION

if [ "$CONFIRMATION" != "SANEAR-PRODUCCION" ]; then
    echo -e "${BLUE}Operación cancelada. No se realizó ningún cambio.${NC}"
    exit 0
fi

echo ""
echo -e "${BLUE}▶ 1. Deteniendo servicios del bot...${NC}"
systemctl stop tg-admin-bot.service 2>/dev/null || true

echo -e "${BLUE}▶ 2. Saneando /etc/fstab (Removiendo disco compartido huérfano)...${NC}"
FSTAB_FILE="/etc/fstab"
if grep -q "compartida" "$FSTAB_FILE"; then
    cp "$FSTAB_FILE" "/etc/fstab.bak_$(date +%Y%m%d_%H%M%S)"
    # Comentar la línea del disco NTFS compartido
    sed -i '/compartida/s/^/# [PURGADO EN PRODUCCION] /' "$FSTAB_FILE"
    echo -e "${GREEN}✓ Línea de disco compartido desactivada en /etc/fstab.${NC}"
    systemctl daemon-reload
fi

# Desmontar y remover directorio si aún existe
if mountpoint -q /home/britojab/compartida 2>/dev/null; then
    umount -l /home/britojab/compartida 2>/dev/null || true
fi
rm -rf /home/britojab/compartida 2>/dev/null || true

echo -e "${BLUE}▶ 3. Purgando datos personales y sensibles de /home/britojab...${NC}"
USER_HOME="/home/britojab"

# 3.1 Perfiles de aplicaciones personales y mensajería
rm -rf "$USER_HOME/.thunderbird"
rm -rf "$USER_HOME/.mozilla"
rm -rf "$USER_HOME/.config/google-chrome"
rm -rf "$USER_HOME/.config/chromium"
rm -rf "$USER_HOME/Telegram"
rm -rf "$USER_HOME/.local/share/TelegramDesktop"
rm -rf "$USER_HOME/.anydesk"
rm -rf "$USER_HOME/.nx"
rm -rf "$USER_HOME/.vnc"

# 3.2 Carpetas personales de usuario
rm -rf "$USER_HOME/Descargas"/* 2>/dev/null || true
rm -rf "$USER_HOME/Documentos"/* 2>/dev/null || true
rm -rf "$USER_HOME/Imágenes"/* 2>/dev/null || true
rm -rf "$USER_HOME/Vídeos"/* 2>/dev/null || true
rm -rf "$USER_HOME/Música"/* 2>/dev/null || true
rm -rf "$USER_HOME/Desktop"/* 2>/dev/null || true
rm -rf "$USER_HOME/Escritorio"/* 2>/dev/null || true
rm -rf "$USER_HOME/Mi-RESPALDO"
rm -rf "$USER_HOME/Cursos"
rm -rf "$USER_HOME/VirtualBox VMs"
rm -rf "$USER_HOME/.PlayOnLinux"
rm -rf "$USER_HOME/.wine"
rm -rf "$USER_HOME/gdrive"
rm -rf "$USER_HOME/APP-LOGIA"
rm -rf "$USER_HOME/github"
rm -rf "$USER_HOME/GIT-REPOS"

# 3.3 Entornos de desarrollo, tokens y asistentes IA personales
rm -rf "$USER_HOME/.gemini"
rm -rf "$USER_HOME/.cursor"
rm -rf "$USER_HOME/.vscode"
rm -rf "$USER_HOME/.vscode-shared"
rm -rf "$USER_HOME/.windsurf"
rm -rf "$USER_HOME/.devin"
rm -rf "$USER_HOME/.copilot"
rm -rf "$USER_HOME/.claude"
rm -rf "$USER_HOME/.qwen"
rm -rf "$USER_HOME/.cache"/* 2>/dev/null || true

# 3.4 Credenciales y registros de historial
rm -f "$USER_HOME/.git-credentials"
rm -f "$USER_HOME/.bash_history"
rm -f "$USER_HOME/.viminfo"

# Preservar estructura base limpia
touch "$USER_HOME/.bash_history"
chown -R britojab:britojab "$USER_HOME"
echo -e "${GREEN}✓ Directorio personal saneado exitosamente.${NC}"

echo -e "${BLUE}▶ 4. Regenerando identidad de hardware del Sistema Operativo...${NC}"
# Regenerar Machine-ID para evitar conflictos de red/DHCP con el equipo original
rm -f /etc/machine-id /var/lib/dbus/machine-id
systemd-machine-id-setup
dbus-uuidgen --ensure=/var/lib/dbus/machine-id 2>/dev/null || true
echo -e "${GREEN}✓ Nuevo Machine ID generado: $(cat /etc/machine-id)${NC}"

echo -e "${BLUE}▶ 5. Limpiando anclaje anterior del Bot para arranque limpio...${NC}"
rm -f /scripts/telegram-admin-bot/audit/.sys_anchor
rm -f /tmp/.last_sentinel_*
rm -f /tmp/.auto_rollback_occurred 2>/dev/null || true
echo -e "${GREEN}✓ Anclaje criptográfico reseteado a modo 'Primer Arranque'.${NC}"

echo -e "${BLUE}▶ 6. Saneando logs del sistema...${NC}"
journalctl --rotate --vacuum-time=1s 2>/dev/null || true
echo -e "${GREEN}✓ Logs antiguos rotados y depurados.${NC}"

echo -e "${BLUE}▶ 7. Verificando permisos en el proyecto del Bot y Web Portal...${NC}"
chown -R britojab:britojab /scripts/telegram-admin-bot
chmod -R 775 /scripts/telegram-admin-bot/audit /scripts/telegram-admin-bot/config
chown -R www-data:www-data /var/www/monitoreo/storage /var/www/monitoreo/bootstrap/cache 2>/dev/null || true
echo -e "${GREEN}✓ Permisos verificados.${NC}"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN} ✅ SANEAMIENTO COMPLETADO EXITOSAMENTE                      ${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "Próximos pasos recomendados:"
echo -e " 1. Ajuste el nombre del equipo si lo desea: ${YELLOW}hostnamectl set-hostname SRV-PROD${NC}"
echo -e " 2. Reinicie el servidor: ${YELLOW}sudo reboot${NC}"
echo -e " 3. Al reiniciar, el bot solicitará activación al Owner en Telegram."
