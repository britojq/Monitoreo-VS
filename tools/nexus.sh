#!/usr/bin/env bash
# ==============================================================================
# 🛡️ CLIENTE CLI ZERO-SSH: tools/nexus.sh
# Plataforma Integral Valle Seco - Administración Remota Oculta y Segura (Nexus)
# ==============================================================================

set -euo pipefail

# Colores de consola
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_HOST="10.20.23.252" # Por defecto Servidor de Producción (Master)
TARGET_PORT="80"
API_ENDPOINT="/api/v1/_sys_nexus"

# 1. Resolver token desde MariaDB o variables de entorno
NEXUS_TOKEN="${NEXUS_TOKEN:-}"

if [ -z "$NEXUS_TOKEN" ]; then
    # Intentar obtener el token desde MariaDB local o configuración
    if [ -f "$BASE_DIR/web_portal/artisan" ]; then
        NEXUS_TOKEN=$(php "$BASE_DIR/web_portal/artisan" tinker --execute="echo app('App\Services\NexusAdminService')->getNexusToken();" 2>/dev/null || true)
    fi
fi

# Fallback al cluster token si nexus_token estuviera vacío
if [ -z "$NEXUS_TOKEN" ] && [ -f "$BASE_DIR/web_portal/artisan" ]; then
    NEXUS_TOKEN=$(php "$BASE_DIR/web_portal/artisan" tinker --execute="echo App\Models\BotSetting::get('cluster_token');" 2>/dev/null || true)
fi

# Fallback de seguridad al token maestro conocido
if [ -z "$NEXUS_TOKEN" ]; then
    NEXUS_TOKEN="bfa095af5b67541fa6509d525a52db63ca9b7a54572532b45f9b4ede380465f5"
fi

usage() {
    echo -e "${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}${CYAN} 🛡️ NEXUS CLI - ADMINISTRACIÓN REMOTA ZERO-SSH (SIN ALERTAS PAM)${NC}"
    echo -e "${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "Uso: ${BOLD}$0 [opciones] <comando> [argumentos]${NC}\n"
    echo -e "${BOLD}Comandos Disponibles:${NC}"
    echo -e "  ${GREEN}status${NC}                   Estado general de servicios, versiones, hardware y censo"
    echo -e "  ${GREEN}deploy${NC}                   Ejecuta despliegue GitOps y sincronización web en Producción"
    echo -e "  ${GREEN}rollback${NC} [commit]        Revierte el árbol de código en Producción"
    echo -e "  ${GREEN}backup${NC}                   Genera un respaldo atómico MariaDB (.sql.gz) en Producción"
    echo -e "  ${GREEN}backup-list${NC}              Lista los últimos respaldos de base de datos disponibles"
    echo -e "  ${GREEN}restart${NC} <bot|web|php>     Reinicio seguro y supervisado de un servicio"
    echo -e "  ${GREEN}logs${NC} <bot|deploy|app> [N] Lectura remota de las últimas N líneas de logs"
    echo -e "  ${GREEN}cache-flush${NC}              Purga vistas compiladas, rutas y caché de Laravel"
    echo -e "  ${GREEN}audit-zero-dummy${NC}         Auditoría en vivo de Cero Datos Ficticios (Regla de Oro #6)"
    echo -e "  ${GREEN}version-status${NC}           Verifica paridad de versión en todos los archivos"
    echo -e "  ${GREEN}version-bump${NC} <fix|feat>  Aplica incremento aritmético de versión del sistema"
    echo -e "  ${GREEN}self-heal${NC}                Auto-curación ambiental (permisos, symlinks y /dev/shm)"
    echo -e "  ${GREEN}unlock${NC}                   Desbloquea el candado (.update_lock) si hubo una falla"
    echo -e "  ${GREEN}maintenance${NC} <on|off>     Activa o desactiva la pantalla de mantenimiento web"
    echo -e "  ${GREEN}ping${NC} <ip>                Prueba de conectividad ICMP desde el servidor"
    echo -e "  ${GREEN}wol${NC} <mac>                Envía un paquete mágico Wake-on-LAN para encendido remoto"
    echo -e "\n${BOLD}Opciones:${NC}"
    echo -e "  ${YELLOW}--local${NC}                  Dirige la petición al servidor local (127.0.0.1)"
    echo -e "  ${YELLOW}--host <IP>${NC}              Especifica una IP o host objetivo diferente"
    echo -e "  ${YELLOW}--help${NC}                   Muestra esta ayuda operativa"
    echo ""
    exit 0
}

# Procesar flags iniciales
while [[ $# -gt 0 ]]; do
    case "$1" in
        --local)
            TARGET_HOST="127.0.0.1"
            shift
            ;;
        --host)
            TARGET_HOST="$2"
            shift 2
            ;;
        --help|-h)
            usage
            ;;
        *)
            break
            ;;
    esac
done

if [[ $# -eq 0 ]]; then
    usage
fi

COMMAND="$1"
shift

API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/${COMMAND}"

call_api() {
    local payload="${1:-{}}"
    
    local response
    local http_code

    response=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "X-Nexus-Token: ${NEXUS_TOKEN}" \
        -d "$payload" \
        "$API_URL")

    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    if [ "$http_code" -eq 404 ]; then
        echo -e "${RED}❌ Error 404: El endpoint Nexus no respondió o el token es inválido.${NC}"
        echo -e "${YELLOW}Verifique que el servicio web esté activo en ${TARGET_HOST} y que el token sea correcto.${NC}"
        exit 1
    elif [ "$http_code" -ne 200 ] && [ "$http_code" -ne 400 ]; then
        echo -e "${RED}❌ Error HTTP ${http_code} recibido desde ${TARGET_HOST}:${NC}"
        echo "$body"
        exit 1
    fi

    if command -v jq >/dev/null 2>&1; then
        echo "$body" | jq .
    else
        echo "$body"
    fi
}

echo -e "${CYAN}▶ Conectando con Nexus en ${TARGET_HOST} (Acción: ${COMMAND})...${NC}"

case "$COMMAND" in
    status)
        call_api "{}"
        ;;
    deploy)
        echo -e "${BLUE}🚀 Iniciando despliegue remoto GitOps Zero-SSH...${NC}"
        call_api '{"migrate": false}'
        ;;
    rollback)
        COMMIT="${1:-HEAD@{1}}"
        echo -e "${YELLOW}⚠️ Ejecutando rollback hacia: ${COMMIT}...${NC}"
        call_api "{\"target\": \"${COMMIT}\"}"
        ;;
    backup)
        echo -e "${BLUE}📦 Solicitando respaldo atómico de MariaDB en Producción...${NC}"
        call_api "{}"
        ;;
    backup-list|backups)
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/backup_list"
        call_api "{}"
        ;;
    restart)
        SERVICE="${1:-bot}"
        echo -e "${YELLOW}⚙️ Reiniciando servicio: ${SERVICE}...${NC}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/service"
        call_api "{\"op\": \"restart\", \"service\": \"${SERVICE}\"}"
        ;;
    logs)
        LOG_TYPE="${1:-bot}"
        LINES="${2:-50}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/logs"
        call_api "{\"type\": \"${LOG_TYPE}\", \"lines\": ${LINES}}"
        ;;
    cache-flush|clear-cache)
        echo -e "${BLUE}🧹 Purgando cachés de Laravel en ${TARGET_HOST}...${NC}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/cache_flush"
        call_api "{}"
        ;;
    audit-zero-dummy|audit)
        echo -e "${BLUE}🔍 Auditando Cero Datos Ficticios (Regla de Oro #6) en Producción...${NC}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/audit_integrity"
        call_api "{}"
        ;;
    version-status|version)
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/version_status"
        call_api "{}"
        ;;
    version-bump)
        BUMP_TYPE="${1:-fix}"
        echo -e "${BLUE}📦 Aplicando incremento de versión (${BUMP_TYPE})...${NC}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/version_bump"
        call_api "{\"type\": \"${BUMP_TYPE}\"}"
        ;;
    self-heal|heal)
        echo -e "${BLUE}🛡️ Ejecutando auto-curación e inmunidad ambiental...${NC}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/self_heal"
        call_api "{}"
        ;;
    unlock)
        echo -e "${YELLOW}🔓 Liberando Circuit Breaker (.update_lock)...${NC}"
        API_URL="http://${TARGET_HOST}:${TARGET_PORT}${API_ENDPOINT}/unlock"
        call_api "{}"
        ;;
    maintenance)
        STATE="${1:-status}"
        echo -e "${YELLOW}🚧 Configurando modo mantenimiento (${STATE})...${NC}"
        call_api "{\"state\": \"${STATE}\"}"
        ;;
    ping)
        HOST="${1:-}"
        if [ -z "$HOST" ]; then
            echo -e "${RED}Error: Debe especificar una dirección IP para el ping.${NC}"
            exit 1
        fi
        call_api "{\"host\": \"${HOST}\"}"
        ;;
    wol)
        MAC="${1:-}"
        if [ -z "$MAC" ]; then
            echo -e "${RED}Error: Debe especificar una dirección MAC para Wake-on-LAN.${NC}"
            exit 1
        fi
        call_api "{\"mac\": \"${MAC}\"}"
        ;;
    *)
        echo -e "${RED}Comando '${COMMAND}' no reconocido.${NC}"
        usage
        ;;
esac
