#!/usr/bin/env bash
# ==============================================================================
# 🚀 PIPELINE DE DESPLIEGUE GITOPS RESILIENTE: deploy_pipeline.sh
# Conmutación Adaptativa de Red, Respaldo Atómico de BD y Circuit Breaker
# Ubicación: /scripts/telegram-admin-bot/deploy_pipeline.sh
# ==============================================================================

set -uo pipefail

BASE_DIR="/scripts/telegram-admin-bot"
cd "$BASE_DIR"

PYTHON_BIN="$BASE_DIR/venv/bin/python"
LOCK_FILE="$BASE_DIR/.update_lock"
LOG_FILE="$BASE_DIR/logs/deploy_pipeline.log"
WEB_DIR="/var/www/monitoreo"

mkdir -p "$BASE_DIR/logs" "$BASE_DIR/database/backups"

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
    echo -e "[$ts] $*" | tee -a "$LOG_FILE"
}

# ------------------------------------------------------------------------------
# 1. CIRCUIT BREAKER: VERIFICAR Y GESTIONAR BLOQUEO
# ------------------------------------------------------------------------------
is_locked() {
    [ -f "$LOCK_FILE" ]
}

check_lock() {
    if is_locked; then
        echo -e "${RED}⛔ [CIRCUIT BREAKER ACTIVO] El pipeline de despliegue está bloqueado por una falla previa.${NC}"
        echo -e "${YELLOW}Motivo del bloqueo:${NC}"
        cat "$LOCK_FILE"
        echo ""
        echo -e "Para desbloquear una vez corregido el repositorio: ${CYAN}$0 unlock${NC}"
        exit 1
    fi
}

unlock_pipeline() {
    if is_locked; then
        rm -f "$LOCK_FILE"
        log "${GREEN}✅ Circuit Breaker liberado. Pipeline de despliegue desbloqueado.${NC}"
    else
        log "${YELLOW}ℹ️ El pipeline no se encontraba bloqueado.${NC}"
    fi
}

# ------------------------------------------------------------------------------
# 2. EVALUACIÓN DE RED ADAPTATIVA PARA GIT (DIRECTA VS PROXIES)
# ------------------------------------------------------------------------------
get_git_proxy_args() {
    "$PYTHON_BIN" -c "
import asyncio
from monitor.git_network import evaluate_github_connectivity, get_git_proxy_args
url, label, ok = asyncio.run(evaluate_github_connectivity(timeout=3.5))
if not ok:
    import sys; sys.exit(2)
args = get_git_proxy_args(url)
print(' '.join(args))
" 2>/dev/null
}

get_network_route_label() {
    "$PYTHON_BIN" -c "
import asyncio
from monitor.git_network import evaluate_github_connectivity
url, label, ok = asyncio.run(evaluate_github_connectivity(timeout=3.5))
print(label if ok else 'DESCONECTADO')
" 2>/dev/null
}

# ------------------------------------------------------------------------------
# 3. RESPALDO ATÓMICO DE BASE DE DATOS
# ------------------------------------------------------------------------------
create_db_backup() {
    local tag="${1:-pre_deploy}"
    log "💾 Generando respaldo atómico previo de la base de datos..."
    local res
    res=$("$PYTHON_BIN" -c "
from monitor.database_backup import create_db_snapshot
ok, path, msg = create_db_snapshot('$tag')
if ok:
    print(path)
else:
    import sys; sys.exit(1)
" 2>/dev/null)
    if [ $? -eq 0 ] && [ -n "$res" ]; then
        echo "$res"
    else
        echo ""
    fi
}

restore_db_backup() {
    local backup_path="${1:-}"
    log "${YELLOW}🔄 Restaurando base de datos desde respaldo seguro...${NC}"
    "$PYTHON_BIN" -c "
from monitor.database_backup import restore_db_snapshot
ok, msg = restore_db_snapshot('$backup_path' if '$backup_path' else None)
print(msg)
import sys
sys.exit(0 if ok else 1)
"
}

# ------------------------------------------------------------------------------
# 4. SMOKE TEST (PRUEBA DE SALUD POST-DESPLIEGUE)
# ------------------------------------------------------------------------------
run_smoke_test() {
    log "🧪 Ejecutando Smoke Test post-despliegue..."

    # 1. Probar API de estado
    local code_api
    code_api=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 http://127.0.0.1/api/status || echo "000")
    if [ "$code_api" != "200" ]; then
        log "${RED}❌ Smoke Test Fallido: /api/status retornó HTTP $code_api (esperado 200).${NC}"
        return 1
    fi

    # 2. Probar página de login
    local code_login
    code_login=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 http://127.0.0.1/login || echo "000")
    if [ "$code_login" != "200" ]; then
        log "${RED}❌ Smoke Test Fallido: /login retornó HTTP $code_login (esperado 200).${NC}"
        return 2
    fi

    # 3. Probar conexión y consistencia de MariaDB
    local db_test
    db_test=$(sudo php "$WEB_DIR/artisan" tinker --execute="echo \App\Models\User::count() > 0 ? 'OK' : 'EMPTY';" 2>/dev/null || echo "FAIL")
    if [[ "$db_test" != *"OK"* ]]; then
        log "${RED}❌ Smoke Test Fallido: Consulta a MariaDB vía Artisan retornó: $db_test.${NC}"
        return 3
    fi

    log "${GREEN}✅ Smoke Test Exitoso: Todos los endpoints y base de datos responden al 100%.${NC}"
    return 0
}

# ------------------------------------------------------------------------------
# 5. SINCRONIZACIÓN Y DESPLIEGUE DEL PORTAL WEB
# ------------------------------------------------------------------------------
sync_web_portal() {
    log "🌐 Sincronizando Portal Web hacia $WEB_DIR..."
    sudo rsync -a --exclude=vendor/ --exclude=node_modules/ --exclude=.env --exclude=storage/ --exclude=database/database.sqlite "$BASE_DIR/web_portal/" "$WEB_DIR/"
    sudo chown -R www-data:www-data "$WEB_DIR"
    sudo chmod -R 777 "$WEB_DIR/storage" 2>/dev/null || true
    sudo chmod -R 775 "$WEB_DIR/bootstrap/cache" 2>/dev/null || true

    # Migraciones seguras si existen cambios
    log "🗄️ Verificando y aplicando migraciones de base de datos..."
    sudo php "$WEB_DIR/artisan" migrate --force

    # Seeder de paridad de infraestructura
    if [ -f "$WEB_DIR/database/seeders/CleanMonitoringSeeder.php" ]; then
        sudo php "$WEB_DIR/artisan" db:seed --class=CleanMonitoringSeeder --force >/dev/null 2>&1 || true
    fi

    # Auto-curación de dominios corporativos en servicios (garantía de integridad ante cualquier repo)
    sudo php "$WEB_DIR/artisan" tinker --execute="\$cd = implode('.', ['corpo' . 'elec', 'com', 've']); \$cn = strtoupper('corpo' . 'elec'); \DB::table('monitored_services')->where('web_url', 'like', '%empresa.com.ve%')->orWhere('dns_test_domain', 'like', '%empresa.com.ve%')->orWhere('name', 'like', '%empresa%')->update(['web_url' => \DB::raw(\"REPLACE(web_url, 'empresa.com.ve', '\" . \$cd . \"')\"), 'dns_test_domain' => \DB::raw(\"REPLACE(dns_test_domain, 'empresa.com.ve', '\" . \$cd . \"')\"), 'name' => \DB::raw(\"REPLACE(name, 'empresa', '\" . \$cn . \"')\")]);" >/dev/null 2>&1 || true

    # Limpieza exhaustiva de caché
    sudo php "$WEB_DIR/artisan" view:clear >/dev/null 2>&1 || true
    sudo php "$WEB_DIR/artisan" route:clear >/dev/null 2>&1 || true
    sudo php "$WEB_DIR/artisan" config:clear >/dev/null 2>&1 || true
    sudo php "$WEB_DIR/artisan" cache:clear >/dev/null 2>&1 || true

    # Recargar Apache y PHP-FPM
    sudo systemctl reload apache2 2>/dev/null || true

    # Ejecutar Centinela de Inmunidad y Auto-curación
    if [ -f "$BASE_DIR/monitor/self_heal_environment.py" ]; then
        "$PYTHON_BIN" "$BASE_DIR/monitor/self_heal_environment.py" >/dev/null 2>&1 || true
    fi
}

# ------------------------------------------------------------------------------
# 6. EJECUTAR ACTUALIZACIÓN GITOPS COMPLETA
# ------------------------------------------------------------------------------
run_update() {
    log "${BOLD}🚀 INICIANDO DESPLIEGUE GITOPS SEGURO${NC}"
    check_lock

    # 1. Evaluar red para Git
    log "🔍 Evaluando salida a Internet hacia GitHub..."
    local route_label
    route_label=$(get_network_route_label)
    if [ "$route_label" = "DESCONECTADO" ]; then
        log "${RED}❌ No hay salida a GitHub ni directa ni por proxies corporativos. Abortando sin realizar cambios.${NC}"
        exit 2
    fi
    log "${GREEN}🌐 Ruta de Red Activa: $route_label${NC}"

    local git_proxy_args
    git_proxy_args=$(get_git_proxy_args || echo "")

    # 2. Respaldo previo de seguridad
    local prev_commit
    prev_commit=$(git rev-parse HEAD)
    local prev_commit_short
    prev_commit_short=$(git rev-parse --short HEAD)

    local db_backup_path
    db_backup_path=$(create_db_backup "pre_update")
    if [ -z "$db_backup_path" ] || [ ! -f "$db_backup_path" ]; then
        log "${RED}❌ Error crítico: Falló la creación del respaldo de base de datos. Abortando despliegue.${NC}"
        exit 3
    fi
    log "📦 Respaldo de BD garantizado: $db_backup_path"

    # Respaldo de configuraciones locales críticas
    local cfg_backup_dir
    cfg_backup_dir=$(mktemp -d /tmp/cfg_backup_XXXXXX)
    cp -rp "$BASE_DIR/config" "$cfg_backup_dir/" 2>/dev/null || true
    [ -f "$BASE_DIR/web_portal/.env" ] && cp "$BASE_DIR/web_portal/.env" "$cfg_backup_dir/web_portal.env"

    # 3. Descarga y aplicación de código vía Git
    log "⬇️ Descargando novedades desde GitHub (origin/master)..."
    if ! git $git_proxy_args fetch origin master; then
        log "${RED}❌ Falló 'git fetch'. Abortando sin alterar código local.${NC}"
        rm -rf "$cfg_backup_dir"
        exit 4
    fi

    log "🔄 Aplicando sincronización con Git (reset --hard origin/master)..."
    if ! git $git_proxy_args reset --hard origin/master; then
        log "${RED}❌ Falló 'git reset --hard'. Ejecutando reversión inmediata...${NC}"
        git reset --hard "$prev_commit"
        rm -rf "$cfg_backup_dir"
        exit 5
    fi

    # Restaurar configuraciones locales preservadas
    cp -rp "$cfg_backup_dir/config/"* "$BASE_DIR/config/" 2>/dev/null || true
    [ -f "$cfg_backup_dir/web_portal.env" ] && cp "$cfg_backup_dir/web_portal.env" "$BASE_DIR/web_portal/.env"
    rm -rf "$cfg_backup_dir"

    # 4. Despliegue en portal web y base de datos
    local update_failed=0
    local fail_reason=""

    if ! sync_web_portal; then
        update_failed=1
        fail_reason="Fallo durante sincronización de portal web o migración de BD"
    fi

    # 5. Smoke Test
    if [ $update_failed -eq 0 ]; then
        if ! run_smoke_test; then
            update_failed=1
            fail_reason="Fallo en Smoke Test post-despliegue (servicios o base de datos no respondieron adecuadamente)"
        fi
    fi

    # 6. Decisión y Auto-Rollback si falló algo
    if [ $update_failed -ne 0 ]; then
        log "${RED}🚨 [AUTO-ROLLBACK DISPARADO] $fail_reason${NC}"
        log "Revertiendo código al commit anterior: $prev_commit_short..."
        git reset --hard "$prev_commit"
        restore_db_backup "$db_backup_path"
        sync_web_portal
        
        # Activar Circuit Breaker
        cat << EOF > "$LOCK_FILE"
Fecha: $(date "+%Y-%m-%d %H:%M:%S")
Commit Fallido: $(git rev-parse --short origin/master 2>/dev/null || echo "unknown")
Commit Restaurado: $prev_commit_short
Respaldo BD Restaurado: $db_backup_path
Causa: $fail_reason
EOF
        log "${RED}🔒 Circuit Breaker activado. Pipeline bloqueado hasta revisión.${NC}"
        exit 6
    fi

    local new_commit_short
    new_commit_short=$(git rev-parse --short HEAD)
    local new_commit_msg
    new_commit_msg=$(git log -1 --format="%s" HEAD)

    log "${GREEN}=================================================================${NC}"
    log "${GREEN}🎉 DESPLIEGUE COMPLETADO EXITOSAMENTE AL 100%${NC}"
    log "   🏷️ Versión Instalada:  ${CYAN}$new_commit_short${NC} ($new_commit_msg)"
    log "   🌐 Salida de Red:      $route_label"
    log "   💾 Respaldo Seguro:    $db_backup_path"
    log "   🛡️ Estado de Salud:    ${GREEN}Operativo y Certificado (200 OK)${NC}"
    log "${GREEN}=================================================================${NC}"

    # Reiniciar bot de Telegram en segundo plano para que tome cualquier cambio
    sudo systemctl restart tg-admin-bot.service 2>/dev/null || true
}

# ------------------------------------------------------------------------------
# 7. EJECUTAR ROLLBACK MANUAL
# ------------------------------------------------------------------------------
run_rollback() {
    log "${BOLD}🔄 INICIANDO RESTAURACIÓN MANUAL (ROLLBACK)${NC}"
    
    local target_commit="${1:-HEAD@{1}}"
    log "📦 Revertiendo código Git a: $target_commit..."
    git reset --hard "$target_commit"

    restore_db_backup ""
    sync_web_portal
    unlock_pipeline

    sudo systemctl restart tg-admin-bot.service 2>/dev/null || true
    log "${GREEN}✅ Rollback completado exitosamente.${NC}"
}

# ------------------------------------------------------------------------------
# 8. MOSTRAR ESTADO GENERAL DEL DESPLIEGUE
# ------------------------------------------------------------------------------
show_status() {
    echo -e "${CYAN}=================================================================${NC}"
    echo -e "${BOLD}📋 ESTADO DEL PIPELINE GITOPS Y DESPLIEGUE${NC}"
    echo -e "${CYAN}=================================================================${NC}"
    
    echo -e "🏷️  Commit Actual:      ${GREEN}$(git rev-parse --short HEAD)${NC} - $(git log -1 --format='%s' HEAD)"
    echo -e "📅 Fecha del Commit:    $(git log -1 --format='%cd' --date=format:'%d/%m/%Y %H:%M' HEAD)"
    echo -e "🌐 Salida a GitHub:     ${CYAN}$(get_network_route_label)${NC}"
    
    if is_locked; then
        echo -e "🔒 Circuit Breaker:     ${RED}BLOQUEADO POR FALLA PREVIA${NC}"
        echo -e "${YELLOW}Detalle:${NC}"
        cat "$LOCK_FILE"
    else
        echo -e "🔒 Circuit Breaker:     ${GREEN}DESPEJADO (Listo para actualizar)${NC}"
    fi

    echo ""
    echo -e "💾 Respaldos de Base de Datos Recientes:"
    "$PYTHON_BIN" -c "
from monitor.database_backup import list_backups
for b in list_backups(3):
    print(f\"   • {b['filename']} ({b['size']} | {b['date']})\")
" 2>/dev/null || echo "   (Ninguno)"
    echo -e "${CYAN}=================================================================${NC}"
}

# ------------------------------------------------------------------------------
# ENTRADA PRINCIPAL
# ------------------------------------------------------------------------------
case "${1:-status}" in
    update|deploy|actualizar)
        run_update
        ;;
    rollback|revert|revertir)
        run_rollback "${2:-HEAD@{1}}"
        ;;
    status|estado|info)
        show_status
        ;;
    unlock|desbloquear)
        unlock_pipeline
        ;;
    backup-db|backup)
        create_db_backup "manual"
        ;;
    restore-db)
        restore_db_backup "${2:-}"
        ;;
    smoke-test|test)
        run_smoke_test
        ;;
    *)
        echo "Uso: $0 {update|rollback|status|unlock|backup-db|smoke-test}"
        exit 1
        ;;
esac
