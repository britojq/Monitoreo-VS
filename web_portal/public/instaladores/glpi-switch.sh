#!/usr/bin/env bash
# ==============================================================================
# 🔄 VALLE SECO ITAM - CONMUTADOR DE ENTORNO (LINUX / XUBUNTU)
# Cambia el servidor destino de GLPI-Agent entre Desarrollo y Producción
# ==============================================================================

set -e

TARGET="${1:-prod}"

if [ "$EUID" -ne 0 ]; then
    echo "[!] ERROR: Este script debe ejecutarse como root o con sudo."
    exit 1
fi

if [ "$TARGET" = "prod" ]; then
    SERVER_URL="http://10.20.23.252/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96"
else
    SERVER_URL="http://10.20.23.221/api/v1/inventory/receiver/d8f58b76e1039c94f58a741362d2bc4a896f610e72c842b15cf4817d23a50e96"
fi

CFG_FILE="/etc/glpi-agent/agent.cfg"

if [ ! -f "$CFG_FILE" ]; then
    echo "[!] ERROR: Archivo $CFG_FILE no encontrado. ¿Está instalado glpi-agent?"
    exit 1
fi

echo "[*] Conmutando agente GLPI hacia: $TARGET ($SERVER_URL)..."
sed -i "s|^server[[:space:]]*=.*|server = ${SERVER_URL}|" "$CFG_FILE"
systemctl restart glpi-agent 2>/dev/null || true
glpi-agent --force || true
echo "[+] Agente GLPI conmutado exitosamente a $TARGET."
