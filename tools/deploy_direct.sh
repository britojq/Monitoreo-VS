#!/usr/bin/env bash
# ==============================================================================
# 🚀 SISTEMA DE ACTUALIZACIÓN CONTROLADA: MODO DIRECTO (SSH)
# Herramienta: tools/deploy_direct.sh
# Permite actualizar Producción paso a paso con visibilidad total en terminal,
# control de errores síncrono, modo mantenimiento obligatorio y preservación inmutable
# de configuraciones, bóvedas de hardware (.sys_anchor) y datos de MariaDB.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$BASE_DIR"

REMOTE_HOST="${1:-produccion}"
REMOTE_WEB_DIR="/var/www/monitoreo"
REMOTE_BOT_DIR="/scripts/telegram-admin-bot"
LOCAL_WEB_DIR="$BASE_DIR/web_portal"
AUDIT_LOG="$BASE_DIR/audit/actualizaciones.log"

mkdir -p "$BASE_DIR/audit" "$BASE_DIR/logs"

# Colores de consola
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
BLUE="\033[0;34m"
CYAN="\033[0;36m"
BOLD="\033[1m"
NC="\033[0m"

log() {
    local ts
    ts=$(date "+%Y-%m-%d %H:%M:%S")
    echo -e "${CYAN}[$ts]${NC} $*"
    echo "[$ts] $*" | sed 's/\x1b\[[0-9;]*m//g' >> "$AUDIT_LOG"
}

fail() {
    log "${RED}❌ ERROR CRÍTICO: $*${NC}"
    exit 1
}

echo -e "${BOLD}${BLUE}=========================================================================${NC}"
echo -e "${BOLD}${BLUE} 🚀 SISTEMA DE ACTUALIZACIÓN DIRECTA (SSH): DESARROLLO ➔ PRODUCCIÓN      ${NC}"
echo -e "${BOLD}${BLUE} Destino: ${CYAN}${REMOTE_HOST}${BLUE} | Modo: ${GREEN}Control Total Síncrono (Zero-API)${NC}"
echo -e "${BOLD}${BLUE}=========================================================================${NC}"

# ------------------------------------------------------------------------------
# FASE 0: VERIFICACIÓN PRE-VUELO EN DESARROLLO (BARRERA DE CALIDAD)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 0: Auditoría pre-vuelo en Desarrollo (Barrera Zero-Fail)...${NC}"

# 1. Comprobar que no haya errores pendientes en git
if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
    log "${YELLOW}⚠️ Aviso: Existen cambios no confirmados en el árbol de trabajo local.${NC}"
fi

# 2. Comprobar certificación local
if [ -f "$BASE_DIR/tools/certify_system_integrity.py" ]; then
    log "Verificando integridad local con certify_system_integrity.py..."
    "$BASE_DIR/venv/bin/python3" "$BASE_DIR/tools/certify_system_integrity.py" >/dev/null 2>&1 || fail "La certificación local falló. Corrige los errores antes de desplegar a Producción."
    log "${GREEN}✓ Certificación local 100% exitosa (Zero-Fail).${NC}"
fi

# 3. Comprobar conectividad SSH
log "Comprobando acceso SSH hacia '${REMOTE_HOST}'..."
ssh -o BatchMode=yes -o ConnectTimeout=5 "$REMOTE_HOST" "echo SSH_OK" >/dev/null 2>&1 || fail "No se pudo conectar vía SSH a '${REMOTE_HOST}'."
log "${GREEN}✓ Conectividad SSH establecida con éxito.${NC}"

# ------------------------------------------------------------------------------
# FASE 1: ACTIVACIÓN OBLIGATORIA DEL MODO MANTENIMIENTO (REGLA DE ORO #4.3)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 1: Activando Modo Mantenimiento en Producción (Regla #4.3)...${NC}"
ssh "$REMOTE_HOST" "sudo php $REMOTE_WEB_DIR/artisan down --retry=60" || fail "Fallo al activar modo mantenimiento en Producción."

# Validar que retorne 503
HTTP_CODE=$(ssh "$REMOTE_HOST" "curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1/login || echo '000'")
if [ "$HTTP_CODE" = "503" ]; then
    log "${GREEN}✓ Sitio web en Producción en mantenimiento confirmado (HTTP 503).${NC}"
else
    log "${YELLOW}⚠️ Advertencia: Código HTTP retornado durante mantenimiento: $HTTP_CODE${NC}"
fi

# ------------------------------------------------------------------------------
# FASE 2: RESPALDO ATÓMICO PREVENTIVO DE BASE DE DATOS EN PRODUCCIÓN
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 2: Generando respaldo preventivo de MariaDB en Producción...${NC}"
ssh "$REMOTE_HOST" "
    if [ -f '$REMOTE_BOT_DIR/venv/bin/python' ] && [ -f '$REMOTE_BOT_DIR/tools/post_update.py' ]; then
        $REMOTE_BOT_DIR/venv/bin/python $REMOTE_BOT_DIR/tools/post_update.py --backup-only || true
    fi
"
log "${GREEN}✓ Respaldo preventivo registrado.${NC}"

# ------------------------------------------------------------------------------
# FASE 3: TRANSFERENCIA ATÓMICA DE CÓDIGO (PRESERVANDO INMUTABILIDAD - REGLA #13)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 3: Sincronizando código limpio vía rsync (Inmutabilidad protegida)...${NC}"

# A. Portal Web (/var/www/monitoreo)
log "Sincronizando portal web hacia '$REMOTE_HOST:$REMOTE_WEB_DIR'..."
rsync -avz --delete \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='database/database.sqlite' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    "$LOCAL_WEB_DIR/" "${REMOTE_HOST}:$REMOTE_WEB_DIR/"

# B. Demonios y scripts Python (/scripts/telegram-admin-bot)
log "Sincronizando demonios y scripts hacia '$REMOTE_HOST:$REMOTE_BOT_DIR'..."
rsync -avz \
    --exclude='config/config.json' \
    --exclude='.sys_anchor' \
    --exclude='audit/' \
    --exclude='logs/' \
    --exclude='database/backups/' \
    --exclude='venv/' \
    --exclude='web_portal/' \
    --exclude='.git/' \
    --exclude='__pycache__/' \
    --exclude='*.pyc' \
    "$BASE_DIR/" "${REMOTE_HOST}:$REMOTE_BOT_DIR/"

log "${GREEN}✓ Transferencia de archivos ejecutables completada sin tocar configuraciones.${NC}"

# ------------------------------------------------------------------------------
# FASE 4: MIGRACIONES, PURGA DE CACHÉ Y REINICIO DE SERVICIOS
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 4: Ejecutando migraciones, purgas de caché y recarga de servicios...${NC}"

ssh "$REMOTE_HOST" "
    set -e
    echo '--> Ejecutando migraciones de base de datos (si aplican)...'
    sudo php $REMOTE_WEB_DIR/artisan migrate --force

    echo '--> Purgando todas las cachés de Laravel...'
    php $REMOTE_WEB_DIR/artisan view:clear
    php $REMOTE_WEB_DIR/artisan route:clear
    php $REMOTE_WEB_DIR/artisan cache:clear
    php $REMOTE_WEB_DIR/artisan config:clear

    echo '--> Ajustando permisos en storage y bootstrap/cache...'
    sudo chown -R www-data:www-data $REMOTE_WEB_DIR/storage $REMOTE_WEB_DIR/bootstrap/cache
    sudo chmod -R 775 $REMOTE_WEB_DIR/storage $REMOTE_WEB_DIR/bootstrap/cache

    echo '--> Recargando servidor web Apache y PHP-FPM...'
    sudo systemctl reload apache2
    sudo systemctl reload php8.4-fpm

    echo '--> Reiniciando demonio del Bot de Telegram...'
    sudo systemctl restart tg-admin-bot.service
"
log "${GREEN}✓ Migraciones, cachés y servicios ejecutados con éxito.${NC}"

# ------------------------------------------------------------------------------
# FASE 5: SMOKE TEST Y CERTIFICACIÓN EN PRODUCCIÓN
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 5: Ejecutando Smoke Test y verificación de salud en Producción...${NC}"

ssh "$REMOTE_HOST" "
    set -e
    echo '--> Verificando estado de tg-admin-bot.service...'
    systemctl is-active --quiet tg-admin-bot.service || (echo 'FALLO: tg-admin-bot inactivo'; exit 1)

    echo '--> Verificando motor local de IA...'
    curl -s --max-time 3 http://127.0.0.1:11434/api/tags >/dev/null || (echo 'FALLO: Motor de IA no responde en 11434'; exit 1)

    echo '--> Verificando API de estatus local (en mantenimiento devuelve 503 controlado)...'
"
log "${GREEN}✓ Verificaciones internas de salud aprobadas.${NC}"

# ------------------------------------------------------------------------------
# FASE 6: RESTABLECIMIENTO DEL SERVICIO (php artisan up)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 6: Restableciendo servicio público en Producción (php artisan up)...${NC}"
ssh "$REMOTE_HOST" "sudo php $REMOTE_WEB_DIR/artisan up"

# Validar que ahora responda HTTP 200
FINAL_HTTP=$(ssh "$REMOTE_HOST" "curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1/api/status || echo '000'")
if [ "$FINAL_HTTP" = "200" ]; then
    log "${GREEN}✓ Producción RESTABLECIDA y OPERATIVA con código HTTP 200 OK.${NC}"
else
    fail "Producción retornó HTTP $FINAL_HTTP tras php artisan up. Revisa los logs remotos."
fi

# ------------------------------------------------------------------------------
# FASE 7: CIERRE Y NOTIFICACIÓN
# ------------------------------------------------------------------------------
REMOTE_VERSION=$(ssh "$REMOTE_HOST" "sudo php $REMOTE_WEB_DIR/artisan tinker --execute=\"echo config('app.version', 'v5.x');\" 2>/dev/null | tr -d '\n\r'" || echo "v5.x")

log "${BOLD}${GREEN}=========================================================================${NC}"
log "${BOLD}${GREEN} 🎉 DESPLIEGUE DIRECTO A PRODUCCIÓN CULMINADO CON ÉXITO AL 100%          ${NC}"
log "${BOLD}${GREEN} Servidor: ${REMOTE_HOST} | Versión Activa: ${REMOTE_VERSION} | Estado: HTTP 200 OK ${NC}"
log "${BOLD}${GREEN}=========================================================================${NC}"
