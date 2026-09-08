#!/usr/bin/env bash
# ==============================================================================
# 🚀 PUBLICADOR AUTOMATIZADO AL REPOSITORIO PÚBLICO: Monitoreo-VS (master)
# Ubicación: /scripts/telegram-admin-bot/publicar_repositorio_publico.sh
# Propósito: Exportar, sanitizar (sin docs, sin credenciales) y publicar a GitHub
# ==============================================================================

set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

BASE_DIR="/scripts/telegram-admin-bot"
PUBLIC_REPO="https://github.com/britojq/Monitoreo-VS.git"
PUBLIC_BRANCH="master"
BUILD_DIR="/tmp/monitoreo_vs_public_build"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE} 🌐 PUBLICANDO A REPOSITORIO PÚBLICO: Monitoreo-VS (${PUBLIC_BRANCH}) ${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# 1. Asegurar que estamos en el directorio base
cd "$BASE_DIR"

# 2. Limpiar directorio temporal previo
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# 3. Clonar o inicializar repositorio público
echo -e "${BLUE}▶ 1. Conectando con el repositorio público remoto...${NC}"
if git clone --depth 1 "$PUBLIC_REPO" "$BUILD_DIR" 2>/dev/null; then
    echo -e "${GREEN}✓ Repositorio existente clonado exitosamente.${NC}"
    cd "$BUILD_DIR"
    git checkout -B "$PUBLIC_BRANCH"
else
    echo -e "${YELLOW}ℹ Repositorio público nuevo/vacío. Inicializando estructura...${NC}"
    cd "$BUILD_DIR"
    git init
    git checkout -b "$PUBLIC_BRANCH"
    git remote add origin "$PUBLIC_REPO"
fi

# 4. Sincronizar archivos del proyecto excluyendo material sensible o privado
echo -e "${BLUE}▶ 2. Exportando y sincronizando archivos del proyecto...${NC}"
rsync -av --delete \
    --exclude=".git/" \
    --exclude="venv/" \
    --exclude="__pycache__/" \
    --exclude="*.pyc" \
    --exclude="docs/" \
    --exclude="config/.backup_golden/" \
    --exclude="config/.checkpoints/" \
    --exclude="config/config.json" \
    --exclude="config/bot.conf" \
    --exclude="config/monitoreo.conf" \
    --exclude="config/mensajes.conf" \
    --exclude="config/bot.conf.generic" \
    --exclude="config/monitoreo.conf.generic" \
    --exclude="config/mensajes.conf.generic" \
    --exclude="database/schema_monitoreo.sql" \
    --exclude="audit/*.log" \
    --exclude="audit/*.txt" \
    --exclude="audit/*.html" \
    --exclude="audit/*.md" \
    --exclude="audit/*.pcap" \
    --exclude="audit/.sys_anchor" \
    --exclude="audit/.active_challenge" \
    --exclude=".agents/" \
    --exclude=".gemini/" \
    --exclude=".env" \
    --exclude="web_portal/.env" \
    --exclude="web_portal/database/database.sqlite" \
    --exclude="web_portal/vendor/" \
    --exclude="web_portal/node_modules/" \
    --exclude="web_portal/storage/logs/*.log" \
    --exclude="web_portal/storage/framework/cache/data/*" \
    --exclude="web_portal/storage/framework/sessions/*" \
    --exclude="web_portal/storage/framework/views/*" \
    --exclude="publicar_repositorio_publico.sh" \
    "$BASE_DIR/" "$BUILD_DIR/"

# 5. Inyectar plantillas de configuración genéricas
echo -e "${BLUE}▶ 3. Inyectando plantillas de configuración genéricas...${NC}"
cp "$BASE_DIR/config/bot.conf.generic" "$BUILD_DIR/config/bot.conf"
cp "$BASE_DIR/config/monitoreo.conf.generic" "$BUILD_DIR/config/monitoreo.conf"
cp "$BASE_DIR/config/mensajes.conf.generic" "$BUILD_DIR/config/mensajes.conf"

# Sanitizar seeder de base de datos en web_portal si existe
SEEDER_FILE="$BUILD_DIR/web_portal/database/seeders/InitialMonitoringSeeder.php"
if [ -f "$SEEDER_FILE" ]; then
    sed -i "s/A1746281:Abrito2026\.\*/usuario_proxy:clave_proxy/g" "$SEEDER_FILE"
    sed -i "s/jbrito:Octubre2022\./usuario_proxy:clave_proxy/g" "$SEEDER_FILE"
    sed -i "s/A1746281:Carabobo01\*/usuario_proxy:clave_proxy/g" "$SEEDER_FILE"
fi

# Crear .gitkeep en audit
mkdir -p "$BUILD_DIR/audit"
touch "$BUILD_DIR/audit/.gitkeep"

# Asegurar .gitignore estricto en el repositorio público
cat << 'GITIGNORE_EOF' > "$BUILD_DIR/.gitignore"
# Entorno virtual
venv/
__pycache__/
*.pyc

# Archivos de configuración con credenciales sensibles
.env
config.json
config/config.json
OLD-config.json
*.token
config/*.token
config/.backup_golden/
config/.checkpoints/

# Logs de auditoría, anclajes y ejecución
*.log
audit/*.log
audit/*.txt
audit/*.html
audit/*.md
audit/*.pcap
audit/.sys_anchor
intentos_acceso.log
!audit/.gitkeep

# Documentación interna confidencial (Excluida de repo público)
docs/
docs/*

# Agentes y Habilidades Locales
.agents/skills/
.agents/skills/*
.agents/

# Web Portal Laravel exclusions
web_portal/vendor/
web_portal/node_modules/
web_portal/.env
web_portal/database/database.sqlite
web_portal/storage/logs/*.log
web_portal/storage/framework/cache/data/*
web_portal/storage/framework/sessions/*
web_portal/storage/framework/views/*
web_portal/storage/app/public/*
web_portal/bootstrap/cache/*.php
!web_portal/bootstrap/cache/.gitignore
GITIGNORE_EOF

# 6. Escaneo de seguridad preventivo (Sanity Check)
echo -e "${BLUE}▶ 4. Ejecutando auditoría de seguridad preventiva previa a la publicación...${NC}"
FORBIDDEN_PATTERNS=(
    "Abrito2026"
    "Carabobo01"
    "Octubre2022"
    "11746281"
    "A1746281"
    "1595888148:AAE"
    "8791276974:AAH"
    "CORPOELEC"
    "Corpoelec"
    "corpoelec"
    "División de ATIT Carabobo"
    "Gerencia de ATIT Región Central"
)
LEAKS_FOUND=0

for pattern in "${FORBIDDEN_PATTERNS[@]}"; do
    if grep -rnl "$pattern" "$BUILD_DIR" 2>/dev/null; then
        echo -e "${RED}❌ ALERTA CRÍTICA: Se encontró el patrón sensible '${pattern}' en el build público.${NC}"
        LEAKS_FOUND=1
    fi
done

if [ "$LEAKS_FOUND" -ne 0 ]; then
    echo -e "${RED}⛔ Publicación abortada de inmediato por violación de seguridad.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Auditoría superada: 0 credenciales ni secretos corporativos encontrados.${NC}"

# 7. Obtener mensaje del commit de desarrollo
LAST_COMMIT_MSG=$(git -C "$BASE_DIR" log -1 --format="%s")
LAST_COMMIT_HASH=$(git -C "$BASE_DIR" log -1 --format="%h")

# 8. Confirmar y publicar en GitHub
echo -e "${BLUE}▶ 5. Publicando versión pública en GitHub (${PUBLIC_BRANCH})...${NC}"
cd "$BUILD_DIR"
git add -A

if git diff-index --quiet HEAD -- 2>/dev/null; then
    echo -e "${YELLOW}ℹ No hay cambios nuevos respecto a la versión pública existente.${NC}"
else
    git commit -m "release: ${LAST_COMMIT_MSG} (sincronizado desde dev ${LAST_COMMIT_HASH})"
fi

git branch -M "$PUBLIC_BRANCH"
git push -u origin "$PUBLIC_BRANCH"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN} ✅ PUBLICACIÓN AL REPOSITORIO PÚBLICO COMPLETADA CON ÉXITO   ${NC}"
echo -e "${GREEN} 🔗 URL: ${PUBLIC_REPO}                                      ${NC}"
echo -e "${GREEN} 🌿 Rama: ${PUBLIC_BRANCH}                                  ${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# 9. Limpieza de directorio temporal
rm -rf "$BUILD_DIR"
