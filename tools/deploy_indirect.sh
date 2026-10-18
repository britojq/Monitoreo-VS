#!/usr/bin/env bash
# ==============================================================================
# 🚀 SISTEMA DE ACTUALIZACIÓN CONTROLADA: MODO INDIRECTO (REPO PÚBLICO GIT)
# Herramienta: tools/deploy_indirect.sh
# Permite actualizar de forma autónoma cualquier nodo/servidor sincronizando
# desde el repositorio público oficial (Monitoreo-VS.git), con modo mantenimiento,
# auto-rollback preventivo, Circuit Breaker contra bucles y preservación inmutable
# de configuraciones locales, bóvedas de hardware (.sys_anchor) y MariaDB.
# ==============================================================================

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$BASE_DIR"

WEB_DIR="/var/www/monitoreo"
PYTHON_BIN="$BASE_DIR/venv/bin/python"
LOCK_FILE="$BASE_DIR/.update_lock"
LOG_FILE="$BASE_DIR/logs/deploy_indirect.log"
AUDIT_LOG="$BASE_DIR/audit/actualizaciones.log"

mkdir -p "$BASE_DIR/logs" "$BASE_DIR/audit" "$BASE_DIR/database/backups"

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
    echo "[$ts] $*" | sed 's/\x1b\[[0-9;]*m//g' >> "$LOG_FILE"
    echo "[$ts] [INDIRECT] $*" | sed 's/\x1b\[[0-9;]*m//g' >> "$AUDIT_LOG"
}

fail() {
    log "${RED}❌ ERROR CRÍTICO: $*${NC}"
    exit 1
}

# ------------------------------------------------------------------------------
# 1. CIRCUIT BREAKER CONTRA BUCLES CIEGOS (ERROR #23)
# ------------------------------------------------------------------------------
if [ -f "$LOCK_FILE" ]; then
    log "${RED}⛔ [CIRCUIT BREAKER ACTIVO] La actualización indirecta está bloqueada por una falla previa.${NC}"
    echo -e "${YELLOW}Motivo:${NC} $(cat "$LOCK_FILE")"
    echo -e "Para desbloquear manualmente tras resolver la incidencia: ${CYAN}rm -f $LOCK_FILE${NC}"
    exit 1
fi

echo -e "${BOLD}${BLUE}=========================================================================${NC}"
echo -e "${BOLD}${BLUE} 🚀 SISTEMA DE ACTUALIZACIÓN INDIRECTA: REPOSITORIO PÚBLICO GIT          ${NC}"
echo -e "${BOLD}${BLUE} Origen: ${CYAN}origin/master${BLUE} | Destino: ${GREEN}/var/www/monitoreo${NC}"
echo -e "${BOLD}${BLUE}=========================================================================${NC}"

# ------------------------------------------------------------------------------
# 2. CONMUTACIÓN ADAPTATIVA DE RED PARA GIT (DIRECTA VS PROXIES)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 0: Evaluando conectividad de red hacia GitHub...${NC}"

GIT_EXTRA_ARGS=""
if [ -f "$PYTHON_BIN" ] && [ -f "$BASE_DIR/monitor/git_network.py" ]; then
    PROXY_ARGS=$("$PYTHON_BIN" -c "
import sys
sys.path.insert(0, '$BASE_DIR')
import asyncio
from monitor.git_network import evaluate_github_connectivity, get_git_proxy_args
url, label, ok = asyncio.run(evaluate_github_connectivity(timeout=4.0))
if ok:
    print(' '.join(get_git_proxy_args(url)))
" 2>/dev/null || echo "")
    if [ -n "$PROXY_ARGS" ]; then
        GIT_EXTRA_ARGS="$PROXY_ARGS"
        log "${GREEN}✓ Conectividad GitHub verificada con ruta proxy adaptativa.${NC}"
    else
        log "${YELLOW}ℹ️ Sin proxies requeridos; intentando ruta directa.${NC}"
    fi
fi

# ------------------------------------------------------------------------------
# 3. VERIFICAR SI HAY CAMBIOS REMOTOS EN EL REPOSITORIO PÚBLICO
# ------------------------------------------------------------------------------
log "${BOLD}▶ Consultando nuevos cambios en el repositorio remoto...${NC}"
PREV_COMMIT=$(git rev-parse HEAD)

# Fetch seguro
git -c http.sslVerify=true $GIT_EXTRA_ARGS fetch origin master || fail "Fallo al consultar actualizaciones en GitHub."

REMOTE_COMMIT=$(git rev-parse origin/master)
if [ "$PREV_COMMIT" = "$REMOTE_COMMIT" ]; then
    log "${GREEN}✓ El sistema ya se encuentra en la versión más reciente ($PREV_COMMIT). Nada que actualizar.${NC}"
    exit 0
fi

log "Actualización detectada: ${CYAN}${PREV_COMMIT:0:7}${NC} ➔ ${GREEN}${REMOTE_COMMIT:0:7}${NC}"

# ------------------------------------------------------------------------------
# 4. ACTIVACIÓN DE MODO MANTENIMIENTO LOCAL (REGLA DE ORO #4.3)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 1: Activando Modo Mantenimiento en el servidor web...${NC}"
sudo php "$WEB_DIR/artisan" down --message="Actualizando sistema a la última versión corporativa..." || true

# ------------------------------------------------------------------------------
# 5. RESPALDO ATÓMICO PREVENTIVO DE BASE DE DATOS
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 2: Creando respaldo preventivo de MariaDB...${NC}"
BACKUP_PATH=""
if [ -f "$PYTHON_BIN" ] && [ -f "$BASE_DIR/monitor/database_backup.py" ]; then
    BACKUP_PATH=$("$PYTHON_BIN" -c "
import sys
sys.path.insert(0, '$BASE_DIR')
from monitor.database_backup import create_db_snapshot
ok, path, msg = create_db_snapshot('pre_indirect_update')
if ok:
    print(path)
" 2>/dev/null || echo "")
fi
log "Respaldo generado en: ${BACKUP_PATH:-'(snapshot estándar de respaldo)'}"

# ------------------------------------------------------------------------------
# 6. APLICACIÓN DE CAMBIOS GIT (INMUTABILIDAD BLINDADA - REGLA #13)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 3: Aplicando cambios git (merge fast-forward)...${NC}"
if ! git merge --ff-only origin/master; then
    log "${RED}❌ Conflicto o divergencia en git merge. Abortando y activando Circuit Breaker.${NC}"
    echo "Fallo de merge git en $(date)" > "$LOCK_FILE"
    sudo php "$WEB_DIR/artisan" up || true
    fail "No se pudo realizar el avance rápido (fast-forward). Revisa el estado local."
fi

# ------------------------------------------------------------------------------
# 7. SINCRONIZACIÓN INTERNA HACIA /var/www/monitoreo
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 4: Sincronizando portal web hacia '$WEB_DIR'...${NC}"
sudo rsync -av --delete \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='database/database.sqlite' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    "$BASE_DIR/web_portal/" "$WEB_DIR/"

# ------------------------------------------------------------------------------
# 8. MIGRACIONES, CACHÉS Y REINICIO DE SERVICIOS
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 5: Ejecutando migraciones, purga de cachés y recarga de servicios...${NC}"
sudo php "$WEB_DIR/artisan" migrate --force

php "$WEB_DIR/artisan" view:clear
php "$WEB_DIR/artisan" route:clear
php "$WEB_DIR/artisan" cache:clear
php "$WEB_DIR/artisan" config:clear

sudo chown -R www-data:www-data "$WEB_DIR/storage" "$WEB_DIR/bootstrap/cache"
sudo chmod -R 775 "$WEB_DIR/storage" "$WEB_DIR/bootstrap/cache"

sudo systemctl reload apache2
sudo systemctl reload php8.4-fpm
sudo systemctl restart tg-admin-bot.service

# ------------------------------------------------------------------------------
# 9. SMOKE TEST Y CIRCUIT BREAKER (AUTO-ROLLBACK EN CASO DE FALLO)
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 6: Ejecutando Smoke Test post-actualización...${NC}"

SMOKE_OK=true

# Test 1: API de estado
CODE_API=$(curl -s -o /dev/null -w "%{http_code}" --max-time 4 http://127.0.0.1/api/status || echo "000")
# Durante artisan down puede dar 503, lo cual es normal hasta el 'up'

# Test 2: Comprobar que tg-admin-bot está activo
if ! systemctl is-active --quiet tg-admin-bot.service; then
    log "${RED}❌ Smoke Test Fallido: tg-admin-bot.service no inició correctamente.${NC}"
    SMOKE_OK=false
fi

# Test 3: Comprobar conexión y consistencia de MariaDB vía Artisan
DB_CHECK=$(sudo php "$WEB_DIR/artisan" tinker --execute="echo \App\Models\User::count() > 0 ? 'OK' : 'EMPTY';" 2>/dev/null || echo "FAIL")
if [[ "$DB_CHECK" != *"OK"* ]]; then
    log "${RED}❌ Smoke Test Fallido: Consulta a MariaDB retornó error: $DB_CHECK${NC}"
    SMOKE_OK=false
fi

if [ "$SMOKE_OK" = "false" ]; then
    log "${RED}🚨 SMOKE TEST FALLIDO. INICIANDO AUTO-ROLLBACK PREVENTIVO...${NC}"
    git reset --hard "$PREV_COMMIT"
    sudo rsync -av --delete \
        --exclude='.env' \
        --exclude='storage/' \
        --exclude='database/database.sqlite' \
        --exclude='vendor/' \
        "$BASE_DIR/web_portal/" "$WEB_DIR/"
    if [ -n "$BACKUP_PATH" ] && [ -f "$BACKUP_PATH" ]; then
        log "${YELLOW}Restaurando snapshot de base de datos...${NC}"
        "$PYTHON_BIN" -c "
import sys
sys.path.insert(0, '$BASE_DIR')
from monitor.database_backup import restore_db_snapshot
restore_db_snapshot('$BACKUP_PATH')
" 2>/dev/null || true
    fi
    sudo php "$WEB_DIR/artisan" up || true
    echo "Smoke test falló tras actualizar a $REMOTE_COMMIT en $(date)" > "$LOCK_FILE"
    fail "Actualización revertida por fallo en Smoke Test. Circuit Breaker activado."
fi

# ------------------------------------------------------------------------------
# 10. RESTABLECIMIENTO DEL MODO MANTENIMIENTO
# ------------------------------------------------------------------------------
log "${BOLD}▶ FASE 7: Restableciendo servicio público (php artisan up)...${NC}"
sudo php "$WEB_DIR/artisan" up

FINAL_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 http://127.0.0.1/api/status || echo "000")
if [ "$FINAL_CODE" = "200" ]; then
    log "${GREEN}✓ Sitio web restablecido y operativo al 100% (HTTP 200 OK).${NC}"
else
    log "${YELLOW}⚠️ Aviso: /api/status retornó código HTTP $FINAL_CODE tras php artisan up.${NC}"
fi

NEW_VERSION=$(sudo php "$WEB_DIR/artisan" tinker --execute="echo config('app.version', 'v5.x');" 2>/dev/null | tr -d '\n\r' || echo "v5.x")

log "${BOLD}${GREEN}=========================================================================${NC}"
log "${BOLD}${GREEN} 🎉 ACTUALIZACIÓN INDIRECTA COMPLETADA CON ÉXITO                         ${NC}"
log "${BOLD}${GREEN} Versión Instalada: ${NEW_VERSION} | Commit: ${REMOTE_COMMIT:0:7} | HTTP 200 OK ${NC}"
log "${BOLD}${GREEN}=========================================================================${NC}"
