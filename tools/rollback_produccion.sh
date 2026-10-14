#!/usr/bin/env bash
# ==============================================================================
# 🔄 RESTAURACIÓN TOTAL Y ROLLBACK DE PRODUCCIÓN: rollback_produccion.sh
# Restaura el estado íntegro previo a la actualización (BD, Código, Storage, Env)
# Ubicación: /scripts/telegram-admin-bot/tools/rollback_produccion.sh
# Uso: sudo /scripts/telegram-admin-bot/tools/rollback_produccion.sh [TIMESTAMP]
# ==============================================================================

set -euo pipefail

BACKUP_DIR="/home/data_monitoreo/backups"

# Colores de consola
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
BLUE="\033[0;34m"
CYAN="\033[0;36m"
BOLD="\033[1m"
NC="\033[0m"

echo -e "${BOLD}${RED}====================================================================${NC}"
echo -e "${BOLD}${RED}   🔄 INICIANDO PROCEDIMIENTO DE ROLLBACK EN PRODUCCIÓN             ${NC}"
echo -e "${BOLD}${RED}====================================================================${NC}"

# 1. Determinar Timestamp a restaurar
TIMESTAMP="${1:-}"

if [ -z "${TIMESTAMP}" ]; then
    echo -e "${YELLOW}No se especificó timestamp. Buscando respaldos disponibles en ${BACKUP_DIR}...${NC}"
    if [ ! -d "${BACKUP_DIR}" ]; then
        echo -e "${RED}Error: El directorio de respaldos ${BACKUP_DIR} no existe.${NC}"
        exit 1
    fi

    LATEST_MANIFEST="$(ls -t "${BACKUP_DIR}"/manifest_*.txt 2>/dev/null | head -n 1 || true)"
    if [ -z "${LATEST_MANIFEST}" ]; then
        echo -e "${RED}Error: No se encontraron respaldos ni manifiestos en ${BACKUP_DIR}.${NC}"
        exit 1
    fi

    TIMESTAMP="$(basename "${LATEST_MANIFEST}" | sed 's/manifest_//; s/\.txt//')"
    echo -e "Seleccionando el respaldo más reciente: ${BOLD}${CYAN}${TIMESTAMP}${NC}"
fi

DB_BACKUP="${BACKUP_DIR}/mariadb_monitoreo_vs_${TIMESTAMP}.sql.gz"
WEB_BACKUP="${BACKUP_DIR}/var_www_monitoreo_${TIMESTAMP}.tar.gz"
SCRIPTS_BACKUP="${BACKUP_DIR}/scripts_bot_${TIMESTAMP}.tar.gz"
ENV_BACKUP="${BACKUP_DIR}/env_prod_${TIMESTAMP}.backup"
STORAGE_BACKUP="${BACKUP_DIR}/storage_public_${TIMESTAMP}.tar.gz"

# 2. Validar existencia de los archivos clave de respaldo
echo -e "\n${YELLOW}Validando integridad de los archivos de respaldo...${NC}"
for f in "${DB_BACKUP}" "${WEB_BACKUP}" "${SCRIPTS_BACKUP}" "${ENV_BACKUP}"; do
    if [ ! -f "${f}" ]; then
        echo -e "${RED}Error crítico: No se encontró el archivo de respaldo: ${f}${NC}"
        exit 1
    fi
    echo -e " ✓ Encontrado: ${CYAN}${f}${NC}"
done

# 3. Forzar Modo Mantenimiento en Web para aislar el servidor
echo -e "\n${YELLOW}[1/7] Activando modo mantenimiento en el portal web...${NC}"
if [ -f "/var/www/monitoreo/artisan" ]; then
    php /var/www/monitoreo/artisan down --render="errors.503" || true
fi

# 4. Detener Demonios en Segundo Plano
echo -e "${YELLOW}[2/7] Deteniendo demonios y servicios de monitoreo...${NC}"
systemctl stop monitoreo-engine.service monitoreo-web-sync.service 2>/dev/null || true

# 5. Restaurar Base de Datos MariaDB
echo -e "${YELLOW}[3/7] Restaurando Base de Datos MariaDB desde ${DB_BACKUP}...${NC}"
ENV_FILE="${ENV_BACKUP}"
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
    gunzip -c "${DB_BACKUP}" | MYSQL_PWD="${DB_PASS}" mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" "${DB_NAME}"
else
    gunzip -c "${DB_BACKUP}" | mysql -u root "${DB_NAME}"
fi
echo -e "${GREEN}✓ Base de datos restaurada al estado previo.${NC}"

# 6. Restaurar Directorio Web /var/www/monitoreo
echo -e "${YELLOW}[4/7] Restaurando directorio web /var/www/monitoreo...${NC}"
tar -xzf "${WEB_BACKUP}" -C /
cp -a "${ENV_BACKUP}" /var/www/monitoreo/.env
chmod 600 /var/www/monitoreo/.env
echo -e "${GREEN}✓ Archivos del portal web y .env restaurados.${NC}"

# 7. Restaurar Repositorio /scripts/telegram-admin-bot
echo -e "${YELLOW}[5/7] Restaurando repositorio /scripts/telegram-admin-bot...${NC}"
tar -xzf "${SCRIPTS_BACKUP}" -C /
echo -e "${GREEN}✓ Scripts de monitoreo y bot restaurados.${NC}"

# 8. Restaurar Enlaces Simbólicos y Permisos de Storage
echo -e "${YELLOW}[6/7] Restableciendo enlaces simbólicos y permisos...${NC}"
mkdir -p /home/data_monitoreo/storage/app/public
rm -rf /var/www/monitoreo/storage/app/public
ln -sfn /home/data_monitoreo/storage/app/public /var/www/monitoreo/storage/app/public
rm -rf /var/www/monitoreo/public/storage
ln -sfn /home/data_monitoreo/storage/app/public /var/www/monitoreo/public/storage

chown -R britojab:www-data /var/www/monitoreo /scripts/telegram-admin-bot /home/data_monitoreo/storage
chmod -R 775 /var/www/monitoreo/storage /var/www/monitoreo/bootstrap/cache /home/data_monitoreo/storage

# 9. Purgar Cachés y Reactivar Servicios
echo -e "${YELLOW}[7/7] Vaciando cachés, reactivando servicios y levantando servidor...${NC}"
if [ -f "/var/www/monitoreo/artisan" ]; then
    php /var/www/monitoreo/artisan view:clear || true
    php /var/www/monitoreo/artisan route:clear || true
    php /var/www/monitoreo/artisan cache:clear || true
    php /var/www/monitoreo/artisan config:clear || true
    php /var/www/monitoreo/artisan up || true
fi

systemctl restart apache2 php8.4-fpm 2>/dev/null || true
systemctl restart monitoreo-engine.service monitoreo-web-sync.service 2>/dev/null || true

echo -e "\n${BOLD}${GREEN}====================================================================${NC}"
echo -e "${BOLD}${GREEN}   ✅ ROLLBACK COMPLETADO EXITOSAMENTE                              ${NC}"
echo -e "${BOLD}${GREEN}   El servidor de Producción ha retornado al estado: ${TIMESTAMP}   ${NC}"
echo -e "${BOLD}${GREEN}====================================================================${NC}\n"
