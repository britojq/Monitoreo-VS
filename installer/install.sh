#!/usr/bin/env bash
# ==============================================================================
# 🚀 INSTALADOR MAESTRO UNIFICADO: SISTEMA DE MONITOREO CORPORATIVO
# Despliegue completo: Bot Telegram, Bot Centinela, Portal Web Laravel y Playwright
# Ubicación: /scripts/telegram-admin-bot/installer/install.sh
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================

set -e

# Colores para consola
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
BOLD='\033[1m'
NC='\033[0m' # Sin color

PROJECT_DIR="/scripts/telegram-admin-bot"
WEB_DIR="/var/www/monitoreo"
DB_NAME="monitoreo_vs"
DB_USER="monitoreo_user"
DB_PASS="VsMonit#2026!SecureKey"
DOMAIN_NAME="monitoreo-vs.local"

# Detección inteligente del usuario propietario del sistema
if [ -n "$SUDO_USER" ] && [ "$SUDO_USER" != "root" ]; then
    SYS_USER="$SUDO_USER"
else
    SYS_USER="$(logname 2>/dev/null || echo "britojab")"
fi

echo -e "${CYAN}${BOLD}"
echo "======================================================================"
echo "    🚀 INSTALADOR MAESTRO: SISTEMA DE MONITOREO & SEGURIDAD"
echo "    CENTRO DE OPERACIONES VALLE SECO • CORPOELEC (CENCARATIT)"
echo "======================================================================"
echo -e "${NC}"
echo -e "${YELLOW}[*] Usuario objetivo del sistema detectado:${NC} ${BOLD}${SYS_USER}${NC}"
echo -e "${YELLOW}[*] Directorio de scripts:${NC} ${BOLD}${PROJECT_DIR}${NC}"
echo -e "${YELLOW}[*] Directorio del Portal Web:${NC} ${BOLD}${WEB_DIR}${NC}"
echo -e "${YELLOW}[*] Base de datos Corporativa:${NC} ${BOLD}${DB_NAME} (Usuario: ${DB_USER})${NC}\n"

# 0. Verificación de privilegios de superusuario
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Este instalador debe ejecutarse con privilegios root (sudo).${NC}" >&2
    exit 1
fi

# =========================================================================
# PASO 1: INSTALACIÓN DE PAQUETES DEL SISTEMA (APT)
# =========================================================================
echo -e "${BLUE}[*] Paso 1: Instalando dependencias base del sistema y pila LAMP...${NC}"
export DEBIAN_FRONTEND=noninteractive

# Preconfigurar debconf para wireshark-common (Permitir captura no-root desatendida)
echo "wireshark-common wireshark-common/install-setuid boolean true" | debconf-set-selections

apt-get update -y
apt-get install -y --no-install-recommends \
    python3 \
    python3-venv \
    python3-pip \
    git \
    curl \
    wget \
    rsync \
    sudo \
    iproute2 \
    iputils-ping \
    arp-scan \
    tcpdump \
    tshark \
    wireshark-common \
    libpam-modules \
    apache2 \
    mariadb-server \
    mariadb-client \
    composer \
    php \
    php-cli \
    php-mysql \
    php-xml \
    php-mbstring \
    php-curl \
    php-zip \
    php-sqlite3

echo -e "${GREEN}[+] Paquetes del sistema y pila LAMP instalados exitosamente.${NC}\n"

# =========================================================================
# PASO 2: CAPACIDADES Y PERMISOS DE RED
# =========================================================================
echo -e "${BLUE}[*] Paso 2: Configurando permisos de bajo nivel y red corporativa...${NC}"
if command -v tcpdump >/dev/null 2>&1; then
    setcap cap_net_raw,cap_net_admin=eip /usr/bin/tcpdump 2>/dev/null || true
fi

if command -v arp-scan >/dev/null 2>&1; then
    setcap cap_net_raw,cap_net_admin=eip /usr/sbin/arp-scan 2>/dev/null || true
fi

if id "$SYS_USER" >/dev/null 2>&1; then
    usermod -aG wireshark "$SYS_USER" 2>/dev/null || true
    usermod -aG www-data "$SYS_USER" 2>/dev/null || true
fi

# Asegurar conexiones de red globales en NetworkManager
if command -v nmcli >/dev/null 2>&1; then
    nmcli -t -f UUID con show 2>/dev/null | while read -r uuid; do
        [ -n "$uuid" ] && nmcli con mod uuid "$uuid" connection.permissions "" 2>/dev/null || true
    done
fi
echo -e "${GREEN}[+] Capacidades de red asignadas correctamente.${NC}\n"

# =========================================================================
# PASO 3: APROVISIONAMIENTO DE BASE DE DATOS (MARIADB/MYSQL)
# =========================================================================
echo -e "${BLUE}[*] Paso 3: Aprovisionando base de datos '${DB_NAME}' con credenciales fuertes...${NC}"
systemctl enable mariadb.service
systemctl start mariadb.service

# Crear base de datos y usuario corporativo con clave fuerte
mysql -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
"

# Importar esquema y catálogos de monitoreo
if [ -f "$PROJECT_DIR/database/schema_monitoreo.sql" ]; then
    echo -e "${CYAN}[*] Importando esquema y catálogos iniciales desde schema_monitoreo.sql...${NC}"
    mysql "${DB_NAME}" < "$PROJECT_DIR/database/schema_monitoreo.sql"
    echo -e "${GREEN}[+] Esquema importado con éxito en ${DB_NAME}.${NC}"
fi
echo -e "${GREEN}[+] Base de datos '${DB_NAME}' lista y protegida.${NC}\n"

# =========================================================================
# PASO 4: DESPLIEGUE DEL PORTAL WEB LARAVEL
# =========================================================================
echo -e "${BLUE}[*] Paso 4: Desplegando Portal Web en ${WEB_DIR}...${NC}"
mkdir -p "$WEB_DIR"

if [ -d "$PROJECT_DIR/web_portal" ]; then
    echo -e "${CYAN}[*] Sincronizando archivos del portal web...${NC}"
    rsync -a --delete \
        --exclude='vendor/' \
        --exclude='node_modules/' \
        --exclude='.env' \
        --exclude='storage/logs/*' \
        "$PROJECT_DIR/web_portal/" "$WEB_DIR/"
fi

# Configuración del archivo de entorno (.env) de Laravel
if [ ! -f "$WEB_DIR/.env" ]; then
    if [ -f "$WEB_DIR/.env.example" ]; then
        cp "$WEB_DIR/.env.example" "$WEB_DIR/.env"
    else
        cat <<ENVEOF > "$WEB_DIR/.env"
APP_NAME="Monitoreo Valle Seco"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://${DOMAIN_NAME}
APP_TIMEZONE=America/Caracas
APP_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD="${DB_PASS}"

SESSION_DRIVER=database
SESSION_LIFETIME=120
ENVEOF
    fi
fi

# Asegurar credenciales exactas en .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "$WEB_DIR/.env"
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "$WEB_DIR/.env"
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=\"${DB_PASS}\"|" "$WEB_DIR/.env"
sed -i "s|^APP_URL=.*|APP_URL=http://${DOMAIN_NAME}|" "$WEB_DIR/.env"

# Instalar dependencias PHP de Laravel
cd "$WEB_DIR"
if [ ! -d "$WEB_DIR/vendor" ]; then
    echo -e "${CYAN}[*] Instalando dependencias de Composer...${NC}"
    composer install --no-dev --optimize-autoloader --no-interaction --quiet || composer install --no-interaction --quiet || true
fi

# Generar clave de aplicación si no está presente
if ! grep -q "^APP_KEY=base64:" "$WEB_DIR/.env" 2>/dev/null; then
    php artisan key:generate --force || true
fi

# Crear estructura de carpetas de almacenamiento y asignar permisos
mkdir -p "$WEB_DIR/storage/app/public" \
         "$WEB_DIR/storage/framework/cache/data" \
         "$WEB_DIR/storage/framework/sessions" \
         "$WEB_DIR/storage/framework/views" \
         "$WEB_DIR/storage/logs" \
         "$WEB_DIR/bootstrap/cache"

chown -R www-data:www-data "$WEB_DIR"
chown -R www-data:"$SYS_USER" "$WEB_DIR/storage" "$WEB_DIR/bootstrap/cache"
chmod -R 775 "$WEB_DIR/storage" "$WEB_DIR/bootstrap/cache"
chmod -R 777 "$WEB_DIR/storage/app/public" 2>/dev/null || true

# Limpiar y reconstruir cachés de Laravel
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true
echo -e "${GREEN}[+] Portal Web configurado en ${WEB_DIR}.${NC}\n"

# =========================================================================
# PASO 5: CONFIGURACIÓN DE APACHE Y DOMINIO LOCAL
# =========================================================================
echo -e "${BLUE}[*] Paso 5: Configurando Apache y VirtualHost para '${DOMAIN_NAME}'...${NC}"

cat <<APACHE_EOF > /etc/apache2/sites-available/laravel.conf
<VirtualHost *:80>
    ServerAdmin admin@${DOMAIN_NAME}
    ServerName ${DOMAIN_NAME}
    ServerAlias localhost 127.0.0.1 *
    DocumentRoot ${WEB_DIR}/public

    <Directory />
        Options FollowSymLinks
        AllowOverride None
    </Directory>
    <Directory ${WEB_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/laravel_error.log
    CustomLog \${APACHE_LOG_DIR}/laravel_access.log combined
</VirtualHost>
APACHE_EOF

a2enmod rewrite >/dev/null 2>&1 || true
a2ensite laravel.conf >/dev/null 2>&1 || true
a2dissite 000-default.conf >/dev/null 2>&1 || true
systemctl reload apache2 || systemctl restart apache2

# Registrar dominio en /etc/hosts si no está presente
if ! grep -q "${DOMAIN_NAME}" /etc/hosts; then
    sed -i "s/127.0.0.1\tlocalhost/127.0.0.1\tlocalhost ${DOMAIN_NAME}/" /etc/hosts 2>/dev/null || echo "127.0.0.1 ${DOMAIN_NAME}" >> /etc/hosts
fi
echo -e "${GREEN}[+] Apache configurado y respondiendo en http://${DOMAIN_NAME}/${NC}\n"

# =========================================================================
# PASO 6: ENTORNO VIRTUAL PYTHON Y CAPTURAS PLAYWRIGHT
# =========================================================================
echo -e "${BLUE}[*] Paso 6: Configurando entorno virtual Python y navegador Playwright...${NC}"
mkdir -p "$PROJECT_DIR/audit" "$PROJECT_DIR/config" "$PROJECT_DIR/docs" "$PROJECT_DIR/logs"

if [ ! -d "$PROJECT_DIR/venv" ]; then
    python3 -m venv "$PROJECT_DIR/venv"
fi

"$PROJECT_DIR/venv/bin/pip" install --upgrade pip --quiet
"$PROJECT_DIR/venv/bin/pip" install --quiet \
    "python-telegram-bot>=21.0" \
    "httpx>=0.27.0" \
    "requests>=2.31.0" \
    "pymysql>=1.1.0" \
    "playwright>=1.40.0"

# Instalar binarios de Chromium y librerías del sistema para Playwright
echo -e "${CYAN}[*] Instalando motor Chromium headless de Playwright...${NC}"
"$PROJECT_DIR/venv/bin/playwright" install --with-deps chromium || true
echo -e "${GREEN}[+] Entorno virtual y motor Playwright listos.${NC}\n"

# =========================================================================
# PASO 7: MOTOR DE INTELIGENCIA ARTIFICIAL (OLLAMA)
# =========================================================================
echo -e "${BLUE}[*] Paso 7: Verificando / Instalando Inteligencia Artificial (Ollama)...${NC}"
if ! command -v ollama >/dev/null 2>&1; then
    echo -e "${YELLOW}[!] Ollama no detectado. Descargando e instalando...${NC}"
    curl -fsSL https://ollama.com/install.sh | sh || true
fi

systemctl daemon-reload
systemctl enable ollama.service 2>/dev/null || true
systemctl start ollama.service 2>/dev/null || true
sleep 2

if [ -f "$PROJECT_DIR/ai/Modelfile.txt" ] && command -v ollama >/dev/null 2>&1; then
    if ! ollama list 2>/dev/null | grep -q "qwen-empresa"; then
        echo -e "${YELLOW}[*] Descargando modelo base qwen2.5:7b...${NC}"
        ollama pull qwen2.5:7b || true
        echo -e "${YELLOW}[*] Compilando modelo personalizado qwen-empresa...${NC}"
        ollama create qwen-empresa -f "$PROJECT_DIR/ai/Modelfile.txt" || true
    else
        echo -e "${GREEN}[+] Modelo qwen-empresa ya existe.${NC}"
    fi
fi
echo -e "${GREEN}[+] Inteligencia Artificial lista.${NC}\n"

# =========================================================================
# PASO 8: COMANDOS GLOBALES CLI (/usr/local/bin)
# =========================================================================
echo -e "${BLUE}[*] Paso 8: Vinculando comandos globales en /usr/local/bin...${NC}"
chmod +x "$PROJECT_DIR/estatus" "$PROJECT_DIR/checkpoint" "$PROJECT_DIR/rollback" "$PROJECT_DIR/sentinel_bot.py" 2>/dev/null || true
ln -sf "$PROJECT_DIR/estatus" /usr/local/bin/estatus
ln -sf "$PROJECT_DIR/checkpoint" /usr/local/bin/checkpoint
ln -sf "$PROJECT_DIR/rollback" /usr/local/bin/rollback
echo -e "${GREEN}[+] Herramientas 'estatus', 'checkpoint' y 'rollback' listas en /usr/local/bin.${NC}\n"

# =========================================================================
# PASO 9: SERVICIOS SYSTEMD Y TAREAS CRON
# =========================================================================
echo -e "${BLUE}[*] Paso 9: Desplegando servicios Systemd y Cron de monitoreo...${NC}"

# 1. Notificador de Arranque y Apagado
cat <<SERVICE_EOF > /etc/systemd/system/boot-alert.service
[Unit]
Description=Notificacion de Arranque y Apagado de Servidor a Telegram
Wants=network-online.target
After=network-online.target NetworkManager.service systemd-resolved.service networking.service
Before=shutdown.target reboot.target halt.target poweroff.target

[Service]
Type=oneshot
RemainAfterExit=yes
Slice=system.slice
User=root
Group=root
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/boot_alert.py start
ExecStop=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/boot_alert.py stop
TimeoutStartSec=180
TimeoutStopSec=30
SuccessExitStatus=0 1 2 15 SIGTERM SIGKILL

[Install]
WantedBy=multi-user.target
SERVICE_EOF

# 2. Servicio Principal del Bot de Monitoreo
cat <<SERVICE_EOF > /etc/systemd/system/tg-admin-bot.service
[Unit]
Description=Telegram Admin Bot (Monitoreo Valle Seco)
Wants=network-online.target
After=network-online.target

[Service]
User=$SYS_USER
Group=$SYS_USER
WorkingDirectory=$PROJECT_DIR
ExecStartPre=$PROJECT_DIR/rollback --pre-start-check
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
SERVICE_EOF

# 3. Servicio del Bot Centinela (Seguridad, DRM y Migración en Caliente)
cat <<SERVICE_EOF > /etc/systemd/system/tg-sentinel-bot.service
[Unit]
Description=Telegram Sentinel Security Bot (Licensing & Anti-Tamper Core)
Wants=network-online.target
After=network-online.target

[Service]
User=$SYS_USER
Group=$SYS_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/sentinel_bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
SERVICE_EOF

# 4. Cron de Escaneo Web cada 5 minutos
cat <<CRON_EOF > /etc/cron.d/monitoreo_web
# /etc/cron.d/monitoreo_web - Sincronizacion Web de Monitoreo cada 5 minutos
*/5 * * * * $SYS_USER $PROJECT_DIR/estatus web > /dev/null 2>&1
CRON_EOF
chmod 644 /etc/cron.d/monitoreo_web

systemctl daemon-reload
systemctl enable boot-alert.service
systemctl enable tg-admin-bot.service
systemctl enable tg-sentinel-bot.service
systemctl restart tg-sentinel-bot.service || true
echo -e "${GREEN}[+] Servicios Systemd y Cron configurados y habilitados.${NC}\n"

# =========================================================================
# PASO 10: ALERTA DE CONEXIONES SSH EN TIEMPO REAL (PAM)
# =========================================================================
echo -e "${BLUE}[*] Paso 10: Configurando alertas SSH en PAM (/etc/pam.d/sshd)...${NC}"
chmod +x "$PROJECT_DIR/monitor/ssh_alert.sh" "$PROJECT_DIR/monitor/ssh_alert.py" 2>/dev/null || true

PAM_SSHD="/etc/pam.d/sshd"
PAM_HOOK="session optional pam_exec.so seteuid /bin/bash $PROJECT_DIR/monitor/ssh_alert.sh"

sed -i '\|/scripts/monitor/ssh_alert.sh|d' "$PAM_SSHD" 2>/dev/null || true
sed -i '\|/scripts/telegram-admin-bot/monitor/ssh_alert.sh|d' "$PAM_SSHD" 2>/dev/null || true

echo "" >> "$PAM_SSHD"
echo "# Alerta de conexion SSH a Telegram (Monitor Valle Seco)" >> "$PAM_SSHD"
echo "$PAM_HOOK" >> "$PAM_SSHD"
echo -e "${GREEN}[+] Hook de PAM para alertas SSH configurado exitosamente.${NC}\n"

# =========================================================================
# PASO 11: PERMISOS FINALES DE ARCHIVOS
# =========================================================================
echo -e "${BLUE}[*] Paso 11: Asegurando permisos sobre directorios de trabajo...${NC}"
if id "$SYS_USER" >/dev/null 2>&1; then
    chown -R "$SYS_USER:$SYS_USER" /scripts/
fi
echo -e "${GREEN}[+] Permisos de usuario aplicados correctamente.${NC}\n"

# =========================================================================
# RESUMEN FINAL DE INSTALACIÓN
# =========================================================================
echo -e "${GREEN}${BOLD}======================================================================"
echo "    ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE (0 A 100 LISTO)"
echo "======================================================================"
echo -e "${NC}"
echo -e "🌐 ${BOLD}Portal Web:${NC}              http://${DOMAIN_NAME}/"
echo -e "🗄️ ${BOLD}Base de Datos:${NC}            ${DB_NAME} (Usuario: ${DB_USER})"
echo -e "🤖 ${BOLD}Bot Centinela:${NC}            Activo (systemctl status tg-sentinel-bot)"
echo -e "📡 ${BOLD}Bot de Monitoreo:${NC}         Habilitado (systemctl status tg-admin-bot)"
echo -e "🔔 ${BOLD}Notificador SSH & Boot:${NC}   Activos en PAM y systemd"
echo -e "⏱️ ${BOLD}Cron de Sincronización:${NC}   Activo cada 5 min (/etc/cron.d/monitoreo_web)"
echo ""
echo -e "${CYAN}Si este es un servidor nuevo, abre tu Bot Centinela en Telegram para"
echo -e "autorizar el nuevo hardware presionando el botón 'Activar' o 'Migrar'.${NC}"
echo -e "${CYAN}Para iniciar manualmente el Bot de Monitoreo ejecute:${NC}"
echo -e "${BOLD}  sudo systemctl start tg-admin-bot.service${NC}"
echo ""
