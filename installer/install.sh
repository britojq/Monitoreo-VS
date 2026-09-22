#!/usr/bin/env bash
# ==============================================================================
# 🚀 INSTALADOR MAESTRO UNIFICADO: SISTEMA DE MONITOREO CORPORATIVO
# Despliegue completo: Bot Telegram, Portal Web Laravel y Playwright
# Ubicación: /scripts/telegram-admin-bot/installer/install.sh
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
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
echo "    CENTRO DE OPERACIONES Y MONITOREO DE REDES VALLE SECO"
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
    fail2ban \
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
    php-sqlite3 \
    php-ldap \
    novnc \
    websockify \
    python3-websockify

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
mkdir -p "$WEB_DIR/storage/app/public/avatars" \
         "$WEB_DIR/storage/framework/cache/data" \
         "$WEB_DIR/storage/framework/sessions" \
         "$WEB_DIR/storage/framework/views" \
         "$WEB_DIR/storage/logs" \
         "$WEB_DIR/bootstrap/cache"

chown -R www-data:www-data "$WEB_DIR"
chown -R www-data:"$SYS_USER" "$WEB_DIR/storage" "$WEB_DIR/bootstrap/cache"
chmod -R 775 "$WEB_DIR/storage" "$WEB_DIR/bootstrap/cache"
chmod -R 777 "$WEB_DIR/storage/app/public" 2>/dev/null || true

# Crear enlace simbólico para almacenamiento público de avatares y archivos
php artisan storage:link --force || true

# Limpiar y reconstruir cachés de Laravel
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
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

    # Proxy WebSocket hacia Websockify para noVNC (debe ir antes del Alias)
    ProxyPreserveHost On
    ProxyPass /novnc/websockify ws://127.0.0.1:6080/websockify retry=0 timeout=3600
    ProxyPassReverse /novnc/websockify ws://127.0.0.1:6080/websockify
    ProxyPass /websockify ws://127.0.0.1:6080/websockify retry=0 timeout=3600
    ProxyPassReverse /websockify ws://127.0.0.1:6080/websockify

    # Soporte noVNC HTML5 estático
    Alias /novnc /usr/share/novnc
    <Directory /usr/share/novnc>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/laravel_error.log
    CustomLog \${APACHE_LOG_DIR}/laravel_access.log combined
</VirtualHost>
APACHE_EOF

a2enmod rewrite proxy proxy_http proxy_wstunnel >/dev/null 2>&1 || true
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
chmod +x "$PROJECT_DIR/estatus" "$PROJECT_DIR/checkpoint" "$PROJECT_DIR/rollback" "$PROJECT_DIR/activar" 2>/dev/null || true
ln -sf "$PROJECT_DIR/estatus" /usr/local/bin/estatus 2>/dev/null || true
ln -sf "$PROJECT_DIR/checkpoint" /usr/local/bin/checkpoint 2>/dev/null || true
ln -sf "$PROJECT_DIR/rollback" /usr/local/bin/rollback 2>/dev/null || true
ln -sf "$PROJECT_DIR/activar" /usr/local/bin/activar 2>/dev/null || true
ln -sf "$PROJECT_DIR/temperatura" /usr/local/bin/temperatura 2>/dev/null || true
echo -e "${GREEN}[+] Herramientas 'estatus', 'checkpoint', 'rollback', 'activar' y 'temperatura' listas en /usr/local/bin.${NC}\n"

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

# 3. Proxy WebSocket Websockify para noVNC
mkdir -p /etc/websockify
touch /etc/websockify/tokens.cfg
chown -R www-data:www-data /etc/websockify
chmod 755 /etc/websockify
chmod 644 /etc/websockify/tokens.cfg

cat <<SERVICE_EOF > /etc/systemd/system/websockify.service
[Unit]
Description=Websockify WebSocket Proxy for noVNC
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/bin/websockify --token-plugin TokenFile --token-source /etc/websockify/tokens.cfg 127.0.0.1:6080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
SERVICE_EOF

# 4. Guardián Térmico y de Protección Física de CPU
cat <<SERVICE_EOF > /etc/systemd/system/tg-thermal-guard.service
[Unit]
Description=Guardián Térmico y de Protección Física de CPU
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/thermal_guard.py --daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICE_EOF

# 5. Cron de Escaneo Web Dinámico (frecuencia administrada desde el portal web)
cat <<CRON_EOF > /etc/cron.d/monitoreo_web
# /etc/cron.d/monitoreo_web - Sincronizacion Web Dinamica de Monitoreo
* * * * * $SYS_USER $PROJECT_DIR/estatus web > /dev/null 2>&1
CRON_EOF
chmod 644 /etc/cron.d/monitoreo_web

# 6. Cron Runner Dinámico de Monitoreo cada minuto y Auto-Discovery cada 15 min
(crontab -u "$SYS_USER" -l 2>/dev/null | grep -v "cron-runner" | grep -v "estatus servicios" | grep -v "estatus discovery" ; echo "* * * * * $PROJECT_DIR/estatus cron-runner >/dev/null 2>&1" ; echo "*/15 * * * * $PROJECT_DIR/estatus discovery --all >/dev/null 2>&1") | crontab -u "$SYS_USER" -

systemctl daemon-reload
systemctl enable boot-alert.service
systemctl enable tg-admin-bot.service
systemctl enable tg-thermal-guard.service
systemctl restart tg-thermal-guard.service || true
systemctl enable websockify.service
systemctl restart websockify.service || true
echo -e "${GREEN}[+] Servicios Systemd (Bot, Guardián Térmico) y Cron configurados y habilitados.${NC}\n"

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

PAM_SUDO="/etc/pam.d/sudo"
if [ -f "$PAM_SUDO" ] && ! grep -q "ssh_alert.sh" "$PAM_SUDO"; then
    echo "" >> "$PAM_SUDO"
    echo "# Alerta y auditoria de sesiones sudo a Telegram y MariaDB" >> "$PAM_SUDO"
    echo "$PAM_HOOK" >> "$PAM_SUDO"
fi
echo -e "${GREEN}[+] Hooks de PAM para alertas SSH y sudo configurados exitosamente.${NC}\n"

# =========================================================================
# PASO 10B: CONFIGURACIÓN DEFENSIVA FAIL2BAN (SSH, APACHE, VNC)
# =========================================================================
if command -v fail2ban-client >/dev/null 2>&1; then
    echo -e "${BLUE}[*] Paso 10B: Desplegando reglas perimetrales Fail2ban...${NC}"
    mkdir -p /etc/fail2ban/action.d /etc/fail2ban/jail.d /etc/fail2ban/filter.d

    cat << 'EOF' > /etc/fail2ban/action.d/telegram-alert.conf
[Definition]
actionstart = 
actionstop = 
actioncheck = 
actionban = /scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/fail2ban_alert.py --action ban --jail "<name>" --ip "<ip>" --failures "<failures>" --bantime "<bantime>" --port "<port>"
actionunban = /scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/fail2ban_alert.py --action unban --jail "<name>" --ip "<ip>" --failures "<failures>" --bantime "<bantime>" --port "<port>"

[Init]
name = default
port = 
failures = 1
bantime = 3600
EOF

    cat << 'EOF' > /etc/fail2ban/filter.d/vnc-bruteforce.conf
[Definition]
failregex = ^.*\[VNC-BRUTEFORCE\]\s+.*SRC=<HOST>\s+
            ^.*VNC-BRUTEFORCE.*SRC=<HOST>
            ^.*krfb.*Connection.*from <HOST>.*closed
ignoreregex = 
EOF

    cat << 'EOF' > /etc/fail2ban/jail.d/monitoreo_security.local
[DEFAULT]
ignoreip = 127.0.0.1/8 ::1 10.20.23.221 10.20.23.252 10.20.23.1 10.20.23.0/24
bantime  = 3600
findtime = 600
maxretry = 4
banaction = nftables-multiport
action = %(banaction)s[port="%(port)s", protocol="%(protocol)s", chain="%(chain)s"]
         telegram-alert[name=%(__name__)s, port="%(port)s"]

[sshd]
enabled  = true
port     = ssh
backend  = systemd
maxretry = 3
findtime = 600
bantime  = 7200

[apache-auth]
enabled  = true
port     = http,https
logpath  = /var/log/apache2/*error.log
maxretry = 4
findtime = 600
bantime  = 7200

[apache-badbots]
enabled  = true
port     = http,https
logpath  = /var/log/apache2/*access.log
maxretry = 2
findtime = 1800
bantime  = 86400

[apache-botsearch]
enabled  = true
port     = http,https
logpath  = /var/log/apache2/*error.log
maxretry = 3
findtime = 600
bantime  = 43200

[apache-noscript]
enabled  = true
port     = http,https
logpath  = /var/log/apache2/*error.log
maxretry = 3
findtime = 600
bantime  = 43200

[vnc-bruteforce]
enabled  = true
port     = 5900
filter   = vnc-bruteforce
backend  = systemd
maxretry = 5
findtime = 120
bantime  = 7200
EOF

    systemctl restart fail2ban 2>/dev/null || true
    systemctl enable fail2ban 2>/dev/null || true
    echo -e "${GREEN}[+] Fail2ban configurado y activo con jails (SSH, Apache, VNC).${NC}\n"
fi

# =========================================================================
# PASO 10C: ESCUDO DE TERMINAL INTERACTIVA & LOCKDOWN (SENTINEL SHIELD)
# =========================================================================
echo -e "${BLUE}[*] Paso 10C: Desplegando Sentinel Terminal Shield y reglas de contención...${NC}"
chmod +x "$PROJECT_DIR/monitor/terminal_shield.py" "$PROJECT_DIR/monitor/terminal_shield.sh" 2>/dev/null || true

# 1. Hook de apertura de terminal interactiva en el perfil del sistema
cp -f "$PROJECT_DIR/monitor/terminal_shield.sh" /etc/profile.d/terminal_shield.sh 2>/dev/null || true
chmod 0644 /etc/profile.d/terminal_shield.sh 2>/dev/null || true

# 1.1 Inyectar en /etc/bash.bashrc para cubrir emuladores no-login (Konsole, xterm, VNC)
if [ -f /etc/bash.bashrc ] && ! grep -q "terminal_shield.sh" /etc/bash.bashrc; then
    echo "" >> /etc/bash.bashrc
    echo "# Sentinel Terminal Shield Hook (Konsole, xterm, shells interactivas)" >> /etc/bash.bashrc
    echo "if [ -f /etc/profile.d/terminal_shield.sh ]; then" >> /etc/bash.bashrc
    echo "    . /etc/profile.d/terminal_shield.sh" >> /etc/bash.bashrc
    echo "fi" >> /etc/bash.bashrc
fi

# 2. Reglas sudoers para Fail2ban y control perimetral desde portal web
SUDOERS_F2B="/etc/sudoers.d/www-fail2ban"
echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/fail2ban-client, /usr/bin/fail2ban-client *" > "$SUDOERS_F2B" 2>/dev/null || true
chmod 0440 "$SUDOERS_F2B" 2>/dev/null || true
visudo -c -f "$SUDOERS_F2B" 2>/dev/null || rm -f "$SUDOERS_F2B"

echo -e "${GREEN}[+] Sentinel Terminal Shield y reglas sudoers desplegados exitosamente.${NC}\n"

# =========================================================================
# PASO 11: PERMISOS FINALES DE ARCHIVOS
# =========================================================================
echo -e "${BLUE}[*] Paso 11: Asegurando permisos sobre directorios de trabajo...${NC}"
if id "$SYS_USER" >/dev/null 2>&1; then
    chown -R "$SYS_USER:$SYS_USER" /scripts/
fi
echo -e "${GREEN}[+] Permisos de usuario aplicados correctamente.${NC}\n"

# =========================================================================
# PASO 12: HOOK DE POST-INSTALACIÓN / CAMBIOS MAYORES
# =========================================================================
if [ -f "$PROJECT_DIR/post_update.py" ] && [ -f "$PROJECT_DIR/venv/bin/python" ]; then
    echo -e "${BLUE}[*] Paso 12: Ejecutando hook de post-actualización y capacidades de red...${NC}"
    "$PROJECT_DIR/venv/bin/python" "$PROJECT_DIR/post_update.py" || true
    echo -e "${GREEN}[+] Hook de post-instalación completado con éxito.${NC}\n"
fi

# =========================================================================
# RESUMEN FINAL DE INSTALACIÓN
# =========================================================================
echo -e "${GREEN}${BOLD}======================================================================"
echo "    ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE (0 A 100 LISTO)"
echo "======================================================================"
echo -e "${NC}"
echo -e "🌐 ${BOLD}Portal Web:${NC}              http://${DOMAIN_NAME}/"
echo -e "🗄️ ${BOLD}Base de Datos:${NC}            ${DB_NAME} (Usuario: ${DB_USER})"
echo -e "🖥️ ${BOLD}Escritorio Remoto VNC:${NC}   noVNC + Websockify activo (systemctl status websockify)"
echo -e "📡 ${BOLD}Bot de Monitoreo:${NC}         Habilitado (systemctl status tg-admin-bot)"
echo -e "🔔 ${BOLD}Notificador SSH & Boot:${NC}   Activos en PAM y systemd"
echo -e "⏱️ ${BOLD}Cron de Sincronización:${NC}   Dinámico / Configurable desde Portal Web (/etc/cron.d/monitoreo_web)"
echo ""
echo -e "${CYAN}Si este es un servidor nuevo o clonado, revise el Serial recibido en su"
echo -e "Telegram privado y ejecute la validación por consola:${NC}"
echo -e "${BOLD}  activar [SERIAL]${NC}"
echo -e "${CYAN}Para verificar el estado del servicio ejecute:${NC}"
echo -e "${BOLD}  sudo systemctl status tg-admin-bot.service${NC}"
echo ""
