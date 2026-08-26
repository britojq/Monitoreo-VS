#!/usr/bin/env bash
# ==============================================================================
# 🚀 INSTALADOR MAESTRO: MONITOR VALLE SECO (@IA_ValleSeco_bot)
# Ubicación: /scripts/telegram-admin-bot/installer/install.sh
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# ==============================================================================

set -e

# Colores para consola
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # Sin color

PROJECT_DIR="/scripts/telegram-admin-bot"
SYS_USER="britojab"

echo -e "${CYAN}${BOLD}"
echo "======================================================================"
echo "    🚀 INSTALADOR MAESTRO: MONITOR VALLE SECO (@IA_ValleSeco_bot)"
echo "    CENCARATIT • CENTRO DE OPERACIONES VALLE SECO • CORPOELEC"
echo "======================================================================"
echo -e "${NC}"

# 0. Verificación de privilegios de superusuario
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Este instalador debe ejecutarse con privilegios root (sudo).${NC}" >&2
    exit 1
fi

echo -e "${BLUE}[*] Paso 1: Preparación del sistema y actualización de paquetes APT...${NC}"
export DEBIAN_FRONTEND=noninteractive

# Preconfigurar debconf para wireshark-common (Permitir captura no-root de forma desatendida)
echo "wireshark-common wireshark-common/install-setuid boolean true" | debconf-set-selections

apt-get update -y
apt-get install -y --no-install-recommends \
    python3 \
    python3-venv \
    python3-pip \
    git \
    curl \
    wget \
    iproute2 \
    iputils-ping \
    arp-scan \
    tcpdump \
    tshark \
    wireshark-common \
    libpam-modules \
    sudo

echo -e "${GREEN}[+] Paquetes del sistema instalados exitosamente.${NC}\n"

# 2. Configuración de capacidades y permisos de red
echo -e "${BLUE}[*] Paso 2: Configurando permisos de bajo nivel para herramientas de red...${NC}"
if command -v tcpdump >/dev/null 2>&1; then
    setcap cap_net_raw,cap_net_admin=eip /usr/bin/tcpdump 2>/dev/null || true
fi

if command -v arp-scan >/dev/null 2>&1; then
    setcap cap_net_raw,cap_net_admin=eip /usr/sbin/arp-scan 2>/dev/null || true
fi

# Agregar usuario al grupo wireshark si existe
if id "$SYS_USER" >/dev/null 2>&1; then
    usermod -aG wireshark "$SYS_USER" 2>/dev/null || true
fi
echo -e "${GREEN}[+] Capacidades de red (tcpdump / arp-scan) configuradas.${NC}\n"

# 3. Instalación y configuración del Motor de IA (Ollama)
echo -e "${BLUE}[*] Paso 3: Verificando / Instalando motor de Inteligencia Artificial (Ollama)...${NC}"
if ! command -v ollama >/dev/null 2>&1; then
    echo -e "${YELLOW}[!] Ollama no detectado. Descargando e instalando...${NC}"
    curl -fsSL https://ollama.com/install.sh | sh
fi

# Asegurar que el servicio de Ollama esté activo
systemctl daemon-reload
systemctl enable ollama.service
systemctl start ollama.service || true
sleep 3

# Descargar modelo base y compilar modelo corporativo si Modelfile existe
echo -e "${CYAN}[*] Configurando modelo de IA corporativo (qwen-empresa)...${NC}"
if [ -f "$PROJECT_DIR/ai/Modelfile.txt" ]; then
    # Verificar si qwen-empresa ya existe
    if ! ollama list | grep -q "qwen-empresa"; then
        echo -e "${YELLOW}[*] Descargando modelo base qwen2.5:7b...${NC}"
        ollama pull qwen2.5:7b || true
        echo -e "${YELLOW}[*] Compilando modelo personalizado qwen-empresa desde Modelfile...${NC}"
        ollama create qwen-empresa -f "$PROJECT_DIR/ai/Modelfile.txt" || true
    else
        echo -e "${GREEN}[+] Modelo qwen-empresa ya existe en Ollama.${NC}"
    fi
fi
echo -e "${GREEN}[+] Motor de Inteligencia Artificial listo.${NC}\n"

# 4. Entorno Virtual de Python y dependencias
echo -e "${BLUE}[*] Paso 4: Configurando entorno virtual de Python en $PROJECT_DIR/venv...${NC}"
mkdir -p "$PROJECT_DIR/audit" "$PROJECT_DIR/config" "$PROJECT_DIR/docs"

if [ ! -d "$PROJECT_DIR/venv" ]; then
    python3 -m venv "$PROJECT_DIR/venv"
fi

"$PROJECT_DIR/venv/bin/pip" install --upgrade pip --quiet
"$PROJECT_DIR/venv/bin/pip" install --quiet \
    "python-telegram-bot>=21.0" \
    "httpx>=0.27.0" \
    "requests>=2.31.0"

echo -e "${GREEN}[+] Entorno virtual y dependencias Python instaladas.${NC}\n"

# 5. Configurar CLI Global `estatus`
echo -e "${BLUE}[*] Paso 5: Instalando herramienta CLI global /usr/local/bin/estatus...${NC}"
chmod +x "$PROJECT_DIR/estatus" 2>/dev/null || true
ln -sf "$PROJECT_DIR/estatus" /usr/local/bin/estatus
echo -e "${GREEN}[+] Comando global 'estatus' vinculado en /usr/local/bin/estatus.${NC}\n"

# 6. Despliegue de Servicios Systemd
echo -e "${BLUE}[*] Paso 6: Configurando servicios Systemd (tg-admin-bot y boot-alert)...${NC}"

# Servicio de Arranque y Apagado
cat <<EOF > /etc/systemd/system/boot-alert.service
[Unit]
Description=Notificacion de Arranque y Apagado de Servidor a Telegram
DefaultDependencies=no
After=network-online.target NetworkManager.service systemd-resolved.service
Wants=network-online.target
Conflicts=shutdown.target reboot.target halt.target poweroff.target
Before=shutdown.target reboot.target halt.target poweroff.target network.target network-online.target NetworkManager.service

[Service]
Type=oneshot
RemainAfterExit=yes
User=$SYS_USER
Group=$SYS_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/boot_alert.py start
ExecStop=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/boot_alert.py stop
TimeoutStartSec=300
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
EOF

# Servicio Principal del Bot
cat <<EOF > /etc/systemd/system/tg-admin-bot.service
[Unit]
Description=Telegram Admin Bot
Wants=network-online.target
After=network-online.target

[Service]
User=$SYS_USER
Group=$SYS_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable boot-alert.service
systemctl enable tg-admin-bot.service
echo -e "${GREEN}[+] Servicios Systemd configurados y habilitados para auto-arranque.${NC}\n"

# 7. Hook de PAM para alertas SSH en tiempo real
echo -e "${BLUE}[*] Paso 7: Configurando alertas SSH en PAM (/etc/pam.d/sshd)...${NC}"
chmod +x "$PROJECT_DIR/monitor/ssh_alert.sh" "$PROJECT_DIR/monitor/ssh_alert.py" 2>/dev/null || true

PAM_SSHD="/etc/pam.d/sshd"
PAM_HOOK="session optional pam_exec.so seteuid /bin/bash $PROJECT_DIR/monitor/ssh_alert.sh"

sed -i '\|/scripts/monitor/ssh_alert.sh|d' "$PAM_SSHD"
sed -i '\|/scripts/telegram-admin-bot/monitor/ssh_alert.sh|d' "$PAM_SSHD"

echo "" >> "$PAM_SSHD"
echo "# Alerta de conexion SSH a Telegram (Monitor Valle Seco)" >> "$PAM_SSHD"
echo "$PAM_HOOK" >> "$PAM_SSHD"
echo -e "${GREEN}[+] Hook de PAM para alertas SSH configurado exitosamente.${NC}\n"

# 8. Permisos de Usuario
echo -e "${BLUE}[*] Paso 8: Aplicando permisos de usuario sobre /scripts...${NC}"
if id "$SYS_USER" >/dev/null 2>&1; then
    chown -R "$SYS_USER:$SYS_USER" /scripts/
fi
echo -e "${GREEN}[+] Permisos de usuario aplicados.${NC}\n"

# 9. Arranque Controlado (tg-admin-bot habilitado pero detenido para activación controlada)
echo -e "${YELLOW}${BOLD}======================================================================"
echo "    ✅ INSTALACIÓN COMPLETADA CON ÉXITO"
echo "======================================================================"
echo -e "${NC}"
echo -e "El servicio ${BOLD}tg-admin-bot.service${NC} ha quedado ${GREEN}HABILITADO${NC} en el arranque del sistema."
echo -e "Para iniciar el Protocolo de Activación Inicial con el Owner, ejecute:"
echo -e "${CYAN}${BOLD}  sudo systemctl start tg-admin-bot.service${NC}"
echo -e "O pruebe manualmente en primer plano:"
echo -e "${CYAN}${BOLD}  /scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/bot.py${NC}"
echo ""
