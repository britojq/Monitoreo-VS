#!/usr/bin/env bash
# ==============================================================================
# 📦 RESPALDO ATÓMICO PRE-DESPLIEGUE A PRODUCCIÓN: backup_produccion.sh
# Genera un punto de retorno integral (Base de Datos MariaDB, Web, Scripts y Config)
# Ubicación: /scripts/telegram-admin-bot/tools/backup_produccion.sh
# ==============================================================================

set -euo pipefail

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="/home/data_monitoreo/backups"
MANIFEST="${BACKUP_DIR}/manifest_${TIMESTAMP}.txt"

# Colores de consola
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
BLUE="\033[0;34m"
CYAN="\033[0;36m"
BOLD="\033[1m"
NC="\033[0m"

echo -e "${BOLD}${BLUE}====================================================================${NC}"
echo -e "${BOLD}${BLUE}   📦 INICIANDO RESPALDO ATÓMICO PRE-DESPLIEGUE A PRODUCCIÓN        ${NC}"
echo -e "${BOLD}${BLUE}   TIMESTAMP: ${TIMESTAMP}                                          ${NC}"
echo -e "${BOLD}${BLUE}====================================================================${NC}"

# 1. Crear directorio seguro de respaldos
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

echo -e "Generando manifiesto de respaldo en: ${CYAN}${MANIFEST}${NC}"
cat <<EOF > "${MANIFEST}"
MANIFEST DE RESPALDO PRE-DESPLIEGUE
Fecha: $(date -R)
Timestamp: ${TIMESTAMP}
Servidor: $(hostname -I | awk '{print $1}')
Usuario: $(whoami)
EOF

# 2. Respaldo de Base de Datos MariaDB (SSOT)
DB_BACKUP="${BACKUP_DIR}/mariadb_monitoreo_vs_${TIMESTAMP}.sql.gz"
echo -e "${YELLOW}[1/5] Respaldando Base de Datos MariaDB ('monitoreo_vs')...${NC}"

# Detectar credenciales desde el .env del portal web
ENV_FILE="/var/www/monitoreo/.env"
DB_USER="monitoreo_user"
DB_PASS=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="monitoreo_vs"

if [ -f "${ENV_FILE}" ]; then
    DB_USER="$(grep '^DB_USERNAME=' "${ENV_FILE}" | cut -d'=' -f2- | tr -d '\"'\'' ' || echo 'monitoreo_user')"
    DB_PASS="$(grep '^DB_PASSWORD=' "${ENV_FILE}" | cut -d'=' -f2- | tr -d '\"'\'' ' || echo '')"
    DB_HOST="$(grep '^DB_HOST=' "${ENV_FILE}" | cut -d'=' -f2- | tr -d '\"'\'' ' || echo '127.0.0.1')"
    DB_PORT="$(grep '^DB_PORT=' "${ENV_FILE}" | cut -d'=' -f2- | tr -d '\"'\'' ' || echo '3306')"
    DB_NAME="$(grep '^DB_DATABASE=' "${ENV_FILE}" | cut -d'=' -f2- | tr -d '\"'\'' ' || echo 'monitoreo_vs')"
fi

if [ -n "${DB_PASS}" ]; then
    MYSQL_PWD="${DB_PASS}" mysqldump -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" --single-transaction --quick "${DB_NAME}" | gzip > "${DB_BACKUP}"
else
    sudo mysqldump --single-transaction --quick "${DB_NAME}" | gzip > "${DB_BACKUP}"
fi

echo "mariadb_backup: ${DB_BACKUP} (Tamaño: $(du -h "${DB_BACKUP}" | awk '{print $1}'))" >> "${MANIFEST}"
echo -e "${GREEN}✓ Base de datos respaldada con éxito.${NC}"

# 3. Respaldo del Directorio Web /var/www/monitoreo
WEB_BACKUP="${BACKUP_DIR}/var_www_monitoreo_${TIMESTAMP}.tar.gz"
echo -e "${YELLOW}[2/5] Respaldando portal web /var/www/monitoreo (excluyendo storage masivo)...${NC}"
tar -czf "${WEB_BACKUP}" \
    --exclude='var/www/monitoreo/storage/app/public' \
    --exclude='var/www/monitoreo/storage/logs/*' \
    --exclude='var/www/monitoreo/storage/framework/sessions/*' \
    --exclude='var/www/monitoreo/storage/framework/cache/*' \
    -C / var/www/monitoreo

echo "web_backup: ${WEB_BACKUP} (Tamaño: $(du -h "${WEB_BACKUP}" | awk '{print $1}'))" >> "${MANIFEST}"
echo -e "${GREEN}✓ Portal web respaldado con éxito.${NC}"

# 4. Respaldo del Directorio /scripts/telegram-admin-bot
SCRIPTS_BACKUP="${BACKUP_DIR}/scripts_bot_${TIMESTAMP}.tar.gz"
echo -e "${YELLOW}[3/5] Respaldando repositorio /scripts/telegram-admin-bot...${NC}"
tar -czf "${SCRIPTS_BACKUP}" \
    --exclude='scripts/telegram-admin-bot/venv' \
    --exclude='scripts/telegram-admin-bot/web_portal/vendor' \
    --exclude='scripts/telegram-admin-bot/web_portal/node_modules' \
    --exclude='scripts/telegram-admin-bot/logs/*' \
    -C / scripts/telegram-admin-bot

echo "scripts_backup: ${SCRIPTS_BACKUP} (Tamaño: $(du -h "${SCRIPTS_BACKUP}" | awk '{print $1}'))" >> "${MANIFEST}"
echo -e "${GREEN}✓ Repositorio y scripts respaldados con éxito.${NC}"

# 5. Respaldo de Archivo .env de Producción
ENV_BACKUP="${BACKUP_DIR}/env_prod_${TIMESTAMP}.backup"
echo -e "${YELLOW}[4/5] Respaldando archivo de entorno .env de producción...${NC}"
if [ -f "/var/www/monitoreo/.env" ]; then
    cp -a /var/www/monitoreo/.env "${ENV_BACKUP}"
    chmod 600 "${ENV_BACKUP}"
    echo "env_backup: ${ENV_BACKUP}" >> "${MANIFEST}"
    echo -e "${GREEN}✓ Archivo .env respaldado con éxito.${NC}"
else
    echo -e "${RED}⚠️ No se encontró /var/www/monitoreo/.env.${NC}"
fi

# 6. Respaldo Preventivo de Storage Masivo Físico
STORAGE_DIR="/home/data_monitoreo/storage/app/public"
STORAGE_BACKUP="${BACKUP_DIR}/storage_public_${TIMESTAMP}.tar.gz"
echo -e "${YELLOW}[5/5] Respaldando almacenamiento masivo (${STORAGE_DIR})...${NC}"
if [ -d "${STORAGE_DIR}" ]; then
    tar -czf "${STORAGE_BACKUP}" -C /home/data_monitoreo/storage/app public
    echo "storage_backup: ${STORAGE_BACKUP} (Tamaño: $(du -h "${STORAGE_BACKUP}" | awk '{print $1}'))" >> "${MANIFEST}"
    echo -e "${GREEN}✓ Almacenamiento masivo respaldado con éxito.${NC}"
else
    echo -e "${YELLOW}ℹ️ No existe directorio separado de storage masivo; omitiendo paso.${NC}"
fi

echo -e "\n${BOLD}${GREEN}====================================================================${NC}"
echo -e "${BOLD}${GREEN}   ✅ RESPALDO ATÓMICO FINALIZADO CON ÉXITO                         ${NC}"
echo -e "${BOLD}${GREEN}====================================================================${NC}"
echo -e "Punto de Retorno (Timestamp): ${BOLD}${CYAN}${TIMESTAMP}${NC}"
echo -e "Ubicación de archivos:        ${CYAN}${BACKUP_DIR}${NC}"
echo -e "\n${BOLD}En caso de falla, ejecute inmediatamente:${NC}"
echo -e "   ${BOLD}${YELLOW}sudo /scripts/telegram-admin-bot/tools/rollback_produccion.sh ${TIMESTAMP}${NC}\n"
