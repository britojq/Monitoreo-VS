#!/usr/bin/env bash
# ==============================================================================
# 🚀 VALLE SECO ITAM - SCRIPT DE INSTALACIÓN Y DESPLIEGUE GLPI AGENT (LINUX / XUBUNTU)
# Instalación desatendida, configuración de servidor y forzado de inventario
# ==============================================================================

set -e

# Configuración por defecto
SERVER_URL="${SERVER_URL:-http://10.20.23.221/api/v1/inventory/receiver/ValleSeco_GLPI_Token_2026_Secure}"
BASE_URL="${BASE_URL:-http://10.20.23.221/instaladores}"
DEB_FILE="${DEB_FILE:-glpi-agent_all.deb}"
TAG="${TAG:-ValleSeco-ITAM}"

# Colores para salida de consola
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${CYAN}============================================================${NC}"
echo -e "${CYAN}  VALLE SECO ITAM - INSTALADOR REMOTO DE AGENTE GLPI (LINUX)${NC}"
echo -e "${CYAN}============================================================${NC}"

# 1. Verificar privilegios root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[!] ERROR: Este script debe ejecutarse como root o con sudo.${NC}"
    echo -e "${YELLOW}    Ejecute: sudo bash $0${NC}"
    exit 1
fi

echo -e "${GREEN}[+] Servidor destino: ${SERVER_URL}${NC}"
echo -e "${GREEN}[+] Repositorio base: ${BASE_URL}${NC}"

# 2. Comprobar si ya está instalado
if command -v glpi-agent >/dev/null 2>&1; then
    echo -e "${CYAN}[*] El paquete glpi-agent ya está instalado en el sistema.${NC}"
    echo -e "${YELLOW}[*] Actualizando configuración de servidor objetivo...${NC}"
    
    CFG_FILE="/etc/glpi-agent/agent.cfg"
    mkdir -p "$(dirname "$CFG_FILE")"
    
    if [ -f "$CFG_FILE" ]; then
        if grep -q "^server[[:space:]]*=" "$CFG_FILE"; then
            sed -i "s|^server[[:space:]]*=.*|server = ${SERVER_URL}|" "$CFG_FILE"
        else
            echo "server = ${SERVER_URL}" >> "$CFG_FILE"
        fi
        
        if grep -q "^tag[[:space:]]*=" "$CFG_FILE"; then
            sed -i "s|^tag[[:space:]]*=.*|tag = ${TAG}|" "$CFG_FILE"
        else
            echo "tag = ${TAG}" >> "$CFG_FILE"
        fi
    else
        cat <<EOF > "$CFG_FILE"
server = ${SERVER_URL}
tag = ${TAG}
logger = syslog
logfile-priority = info
runnow = 1
EOF
    fi

    # Reiniciar servicio
    systemctl restart glpi-agent 2>/dev/null || true
    echo -e "${GREEN}[+] Servicio glpi-agent reiniciado con nuevo servidor.${NC}"

else
    # 3. Instalación de dependencias mínimas requeridas por Xubuntu / Debian
    echo -e "${CYAN}[*] Instalando dependencias base (Perl, DMI, PCI, SSL)...${NC}"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq || true
    apt-get install -y -qq dmidecode pciutils usbutils curl perl libio-socket-ssl-perl libnet-ssleay-perl liblwp-protocol-https-perl >/dev/null 2>&1 || true

    # 4. Descargar paquete deb desde el servidor local
    TEMP_DEB="/tmp/${DEB_FILE}"
    DOWNLOAD_URL="${BASE_URL}/${DEB_FILE}"
    echo -e "${CYAN}[*] Descargando paquete desde ${DOWNLOAD_URL}...${NC}"
    
    curl -fsSL "$DOWNLOAD_URL" -o "$TEMP_DEB"
    if [ ! -s "$TEMP_DEB" ]; then
        echo -e "${RED}[!] ERROR: No se pudo descargar el paquete .deb desde el servidor local.${NC}"
        exit 1
    fi

    # 5. Instalar paquete .deb
    echo -e "${CYAN}[*] Instalando paquete .deb...${NC}"
    dpkg -i "$TEMP_DEB" >/dev/null 2>&1 || apt-get install -f -y -qq >/dev/null 2>&1

    # 6. Configurar servidor
    CFG_FILE="/etc/glpi-agent/agent.cfg"
    mkdir -p "$(dirname "$CFG_FILE")"
    cat <<EOF > "$CFG_FILE"
server = ${SERVER_URL}
tag = ${TAG}
logger = syslog
logfile-priority = info
runnow = 1
EOF

    # 7. Habilitar e iniciar servicio
    systemctl daemon-reload 2>/dev/null || true
    systemctl enable --now glpi-agent 2>/dev/null || true
    rm -f "$TEMP_DEB"
    echo -e "${GREEN}[+] Paquete glpi-agent instalado y servicio habilitado.${NC}"
fi

# 8. Forzar recolección y envío del primer inventario
echo -e "${CYAN}[*] Forzando envío de telemetría e inventario hacia el servidor...${NC}"
glpi-agent --force || true

echo -e "${CYAN}============================================================${NC}"
echo -e "${GREEN}  ✅ DESPLIEGUE FINALIZADO EXITOSAMENTE${NC}"
echo -e "${CYAN}============================================================${NC}"
