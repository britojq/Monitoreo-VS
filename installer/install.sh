#!/usr/bin/env bash
# ==============================================================================
# 🚀 INSTALADOR MAESTRO UNIFICADO: SISTEMA DE MONITOREO CORPORATIVO
# Despliegue completo: Bot Telegram, Portal Web Laravel y Playwright
# Ubicación: /opt/sonar/installer/install.sh
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

PROJECT_DIR="/opt/sonar"
WEB_DIR="/opt/sonar/web_portal"
DB_NAME="monitoreo_vs"
DB_USER="monitoreo_user"
DB_PASS="DemoSecurePassword2026!"
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
    usermod -aG sudo "$SYS_USER" 2>/dev/null || true
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

if [ "$PROJECT_DIR/web_portal" != "$WEB_DIR" ] && [ -d "$PROJECT_DIR/web_portal" ]; then
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
# PASO 5: CONFIGURACIÓN DE APACHE, SSL Y DOMINIO LOCAL
# =========================================================================
echo -e "${BLUE}[*] Paso 5: Configurando Apache, Certificados SSL y VirtualHost para '${DOMAIN_NAME}'...${NC}"

# 1. Asegurar certificados SSL (autofirmados corporativos si no existen)
if [ ! -f /etc/ssl/certs/monitoreo-vs.crt ] || [ ! -f /etc/ssl/private/monitoreo-vs.key ]; then
    echo -e "${YELLOW}[*] Generando certificado SSL corporativo para HTTPS (puerto 443)...${NC}"
    mkdir -p /etc/ssl/certs /etc/ssl/private
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/ssl/private/monitoreo-vs.key \
        -out /etc/ssl/certs/monitoreo-vs.crt \
        -subj "/C=VE/ST=Carabobo/L=Puerto Cabello/O=Valle Seco/OU=Tecnologia/CN=${DOMAIN_NAME}" 2>/dev/null || true
    chmod 0600 /etc/ssl/private/monitoreo-vs.key 2>/dev/null || true
fi

# 2. Desplegar configuración canónica de VirtualHost desde system/templates/
if [ -f "$PROJECT_DIR/system/templates/apache2/laravel.conf" ]; then
    cp "$PROJECT_DIR/system/templates/apache2/laravel.conf" /etc/apache2/sites-available/laravel.conf
    chmod 0644 /etc/apache2/sites-available/laravel.conf
fi

# 3. Habilitar módulos de Apache requeridos (HTTP, HTTPS, WebSSH y noVNC)
a2enmod rewrite ssl proxy proxy_http proxy_wstunnel headers >/dev/null 2>&1 || true
a2ensite laravel.conf >/dev/null 2>&1 || true
a2dissite 000-default.conf >/dev/null 2>&1 || true
systemctl reload apache2 || systemctl restart apache2

# Registrar dominio en /etc/hosts si no está presente
if ! grep -q "${DOMAIN_NAME}" /etc/hosts; then
    sed -i "s/127.0.0.1\tlocalhost/127.0.0.1\tlocalhost ${DOMAIN_NAME}/" /etc/hosts 2>/dev/null || echo "127.0.0.1 ${DOMAIN_NAME}" >> /etc/hosts
fi
echo -e "${GREEN}[+] Apache configurado y respondiendo en http://${DOMAIN_NAME}/ y https://${DOMAIN_NAME}/${NC}\n"

# =========================================================================
# PASO 6: ENTORNO VIRTUAL PYTHON Y CAPTURAS PLAYWRIGHT
# =========================================================================
echo -e "${BLUE}[*] Paso 6: Configurando entorno virtual Python y navegador Playwright...${NC}"
mkdir -p "$PROJECT_DIR/docs"

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
chmod +x "$PROJECT_DIR/tools/estatus" "$PROJECT_DIR/tools/checkpoint" "$PROJECT_DIR/tools/rollback" "$PROJECT_DIR/tools/activar" "$PROJECT_DIR/tools/temperatura" "$PROJECT_DIR/tools/limpiador.sh" "$PROJECT_DIR/monitor/shield_override.sh" 2>/dev/null || true
ln -sf "$PROJECT_DIR/tools/estatus" /usr/local/bin/estatus 2>/dev/null || true
ln -sf "$PROJECT_DIR/tools/checkpoint" /usr/local/bin/checkpoint 2>/dev/null || true
ln -sf "$PROJECT_DIR/tools/rollback" /usr/local/bin/rollback 2>/dev/null || true
ln -sf "$PROJECT_DIR/tools/activar" /usr/local/bin/activar 2>/dev/null || true
ln -sf "$PROJECT_DIR/tools/temperatura" /usr/local/bin/temperatura 2>/dev/null || true
ln -sf "$PROJECT_DIR/tools/limpiador.sh" /usr/local/bin/limpiador 2>/dev/null || true
ln -sf "$PROJECT_DIR/monitor/shield_override.sh" /usr/local/bin/shield-override 2>/dev/null || true
echo -e "${GREEN}[+] Herramientas 'estatus', 'checkpoint', 'rollback', 'activar', 'temperatura', 'limpiador' y 'shield-override' listas en /usr/local/bin.${NC}\n"

# =========================================================================
# PASO 9: SERVICIOS SYSTEMD Y TAREAS CRON
# =========================================================================
echo -e "${BLUE}[*] Paso 9: Desplegando servicios Systemd y Cron desde plantillas canónicas...${NC}"

# 1. Limpieza de nombres de unidades obsoletos o dispares si existieran
rm -f /etc/systemd/system/tg-boot-alert.service /etc/systemd/system/sentinel_bot.service 2>/dev/null || true

# 2. Despliegue de servicios Systemd desde system/templates/systemd/
for unit in boot-alert.service tg-admin-bot.service tg-sentinel.service tg-thermal-guard.service tg-push-telemetry.service; do
    if [ -f "$PROJECT_DIR/system/templates/systemd/$unit" ]; then
        cp "$PROJECT_DIR/system/templates/systemd/$unit" "/etc/systemd/system/$unit"
        chmod 0644 "/etc/systemd/system/$unit"
    fi
done

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
chmod 0644 /etc/systemd/system/websockify.service

# 4. Despliegue de tareas Cron desde system/templates/cron.d/
for cronf in monitoreo_web monitoreo_radar sonar_laravel_schedule; do
    if [ -f "$PROJECT_DIR/system/templates/cron.d/$cronf" ]; then
        cp "$PROJECT_DIR/system/templates/cron.d/$cronf" "/etc/cron.d/$cronf"
        chmod 0644 "/etc/cron.d/$cronf"
    fi
done

# 5. Cron Runner Dinámico de Monitoreo cada minuto y Auto-Discovery cada 15 min
(crontab -u "$SYS_USER" -l 2>/dev/null | grep -v "cron-runner" | grep -v "estatus servicios" | grep -v "estatus discovery" ; echo "* * * * * $PROJECT_DIR/tools/estatus cron-runner >/dev/null 2>&1" ; echo "*/15 * * * * $PROJECT_DIR/tools/estatus discovery --all >/dev/null 2>&1") | crontab -u "$SYS_USER" -

systemctl daemon-reload
systemctl enable boot-alert.service
systemctl enable tg-thermal-guard.service
systemctl restart tg-thermal-guard.service || true
systemctl enable websockify.service
systemctl restart websockify.service || true

# 6. Verificación inteligente de estado de validación de hardware (DRM)
IS_OPERATIONAL=0
if [ -f "$PROJECT_DIR/monitor/core_shield.py" ] && [ -f "$PROJECT_DIR/venv/bin/python" ]; then
    if "$PROJECT_DIR/venv/bin/python" -c "import sys; sys.path.insert(0, '$PROJECT_DIR'); from monitor.core_shield import is_core_operational; sys.exit(0 if is_core_operational() else 1)" 2>/dev/null; then
        IS_OPERATIONAL=1
    fi
fi

if [ "$IS_OPERATIONAL" -eq 1 ]; then
    systemctl enable tg-admin-bot.service
    systemctl restart tg-admin-bot.service || true
    systemctl disable --now tg-sentinel.service 2>/dev/null || true
    echo -e "${GREEN}[+] Sistema ya validado con firma criptográfica. Bot Principal (tg-admin-bot) activo.${NC}"
else
    # En nueva instalación limpia:
    # Desactivar tg-admin-bot para evitar colisión de polling con producción
    systemctl stop tg-admin-bot.service 2>/dev/null || true
    systemctl disable tg-admin-bot.service 2>/dev/null || true
    # Activar Sentinel Bot para el onboarding y autorización remota
    systemctl enable tg-sentinel.service
    systemctl restart tg-sentinel.service || true
    echo -e "${YELLOW}[!] Nueva instalación detectada. Sentinel Bot (tg-sentinel) activado para Onboarding Seguro.${NC}"
    echo -e "${YELLOW}[!] Bot principal desactivado preventivamente para evitar colisiones.${NC}"
    echo -e "${GREEN}[+] Alerta despachada al Telegram privado del Administrador para su validación.${NC}"
fi
echo -e "${GREEN}[+] Servicios Systemd y Cron configurados y sincronizados correctamente.${NC}\n"

# =========================================================================
# PASO 10: ALERTA Y BLINDAJE DE SEGURIDAD EN TIEMPO REAL (PAM: SSH, SUDO, SU)
# =========================================================================
echo -e "${BLUE}[*] Paso 10: Configurando blindaje y alertas en PAM (SSH, sudo, su)...${NC}"
chmod +x "$PROJECT_DIR/monitor/ssh_alert.sh" "$PROJECT_DIR/monitor/ssh_alert.py" 2>/dev/null || true
chmod +x "$PROJECT_DIR/monitor/terminal_shield.sh" "$PROJECT_DIR/monitor/terminal_shield.py" 2>/dev/null || true

# 1. Alertas de conexión SSH (optional)
PAM_SSHD="/etc/pam.d/sshd"
PAM_SSHD_RULE="session optional pam_exec.so seteuid /bin/bash $PROJECT_DIR/monitor/ssh_alert.sh"
if [ -f "$PAM_SSHD" ]; then
    sed -i '\|/ssh_alert.sh|d' "$PAM_SSHD" 2>/dev/null || true
    echo "" >> "$PAM_SSHD"
    echo "# Alerta de conexion SSH a Telegram (Monitor Valle Seco)" >> "$PAM_SSHD"
    echo "$PAM_SSHD_RULE" >> "$PAM_SSHD"
fi

# 2. Alerta y contención estricta de sesiones sudo (pila account)
PAM_SUDO="/etc/pam.d/sudo"
PAM_SUDO_RULE="account requisite pam_exec.so quiet seteuid stdout /bin/bash $PROJECT_DIR/monitor/ssh_alert.sh"
if [ -f "$PAM_SUDO" ]; then
    sed -i '\|/ssh_alert.sh|d' "$PAM_SUDO" 2>/dev/null || true
    if grep -q "@include common-account" "$PAM_SUDO"; then
        sed -i "/@include common-account/i # Sentinel Terminal Shield: Control y contencion de sesiones sudo via Telegram\n$PAM_SUDO_RULE" "$PAM_SUDO"
    else
        echo -e "\n# Sentinel Terminal Shield: Control y contencion de sesiones sudo via Telegram\n$PAM_SUDO_RULE" >> "$PAM_SUDO"
    fi
fi

# 3. Alerta y contención de sesiones su / su - / su -l (pila account)
PAM_SU="/etc/pam.d/su"
PAM_SU_RULE="account requisite pam_exec.so quiet seteuid stdout /bin/bash $PROJECT_DIR/monitor/ssh_alert.sh"
if [ -f "$PAM_SU" ]; then
    sed -i '\|/ssh_alert.sh|d' "$PAM_SU" 2>/dev/null || true
    if grep -q "@include common-account" "$PAM_SU"; then
        sed -i "/@include common-account/i # Sentinel Terminal Shield: Control y contencion de sesiones su via Telegram\n$PAM_SU_RULE" "$PAM_SU"
    else
        echo -e "\n# Sentinel Terminal Shield: Control y contencion de sesiones su via Telegram\n$PAM_SU_RULE" >> "$PAM_SU"
    fi
fi
echo -e "${GREEN}[+] Hooks de PAM para alertas SSH, sudo y su configurados con blindaje activo.${NC}\n"

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
actionban = /opt/sonar/venv/bin/python /opt/sonar/monitor/fail2ban_alert.py --action ban --jail "<name>" --ip "<ip>" --failures "<failures>" --bantime "<bantime>" --port "<port>"
actionunban = /opt/sonar/venv/bin/python /opt/sonar/monitor/fail2ban_alert.py --action unban --jail "<name>" --ip "<ip>" --failures "<failures>" --bantime "<bantime>" --port "<port>"

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
ignoreip = 127.0.0.1/8 ::1 192.168.1.220 192.168.1.250 192.168.1.1 192.168.1.0/24
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

# 3. Reglas sudoers para ejecución desatendida de NET Radar y Monitoreo Web
SUDOERS_RADAR="/etc/sudoers.d/www-data-netradar"
cat << 'RADAR_EOF' > "$SUDOERS_RADAR"
# Reglas de ejecucion desatendida para NET Radar y Monitoreo Web - S.O.N.A.R.
www-data ALL=(ALL) NOPASSWD: /opt/sonar/venv/bin/python *, /usr/bin/tcpdump *, /usr/bin/tshark *, /bin/kill *, /usr/bin/kill *
RADAR_EOF
chmod 0440 "$SUDOERS_RADAR" 2>/dev/null || true
visudo -c -f "$SUDOERS_RADAR" 2>/dev/null || rm -f "$SUDOERS_RADAR"

echo -e "${GREEN}[+] Sentinel Terminal Shield y reglas sudoers desplegados exitosamente.${NC}\n"

# =========================================================================
# PASO 11: PERMISOS FINALES DE ARCHIVOS Y BÓVEDA DRM
# =========================================================================
echo -e "${BLUE}[*] Paso 11: Asegurando permisos sobre directorios de trabajo y bóveda DRM...${NC}"
if id "$SYS_USER" >/dev/null 2>&1; then
    chown -R "$SYS_USER:www-data" "$PROJECT_DIR"
fi
chmod -R 775 "$PROJECT_DIR" 2>/dev/null || true

# Blindaje estricto de la Bóveda de Hardware DRM
mkdir -p "$PROJECT_DIR/system/vault"
chmod 0750 "$PROJECT_DIR/system/vault" 2>/dev/null || true
chown "$SYS_USER:www-data" "$PROJECT_DIR/system/vault" 2>/dev/null || true
if [ -f "$PROJECT_DIR/system/vault/.sys_anchor" ]; then
    chmod 0640 "$PROJECT_DIR/system/vault/.sys_anchor" 2>/dev/null || true
    chown "$SYS_USER:www-data" "$PROJECT_DIR/system/vault/.sys_anchor" 2>/dev/null || true
fi
echo -e "${GREEN}[+] Permisos de usuario aplicados y bóveda DRM blindada en 0750/0640.${NC}\n"

# =========================================================================
# PASO 12: HOOK DE POST-INSTALACIÓN Y SINCRONIZACIÓN IaC
# =========================================================================
if [ -f "$PROJECT_DIR/venv/bin/python" ]; then
    if [ -f "$PROJECT_DIR/tools/system_sync.py" ]; then
        echo -e "${BLUE}[*] Paso 12A: Sincronizando Infraestructura como Código (system_sync.py)...${NC}"
        "$PROJECT_DIR/venv/bin/python" "$PROJECT_DIR/tools/system_sync.py" || true
    fi

    if [ -f "$PROJECT_DIR/tools/post_update.py" ]; then
        echo -e "${BLUE}[*] Paso 12B: Ejecutando hook de post-actualización y capacidades de red...${NC}"
        "$PROJECT_DIR/venv/bin/python" "$PROJECT_DIR/tools/post_update.py" || true
        echo -e "${GREEN}[+] Hook de post-instalación completado con éxito.${NC}\n"
    fi
fi

# =========================================================================
# RESUMEN FINAL DE INSTALACIÓN
# =========================================================================
echo -e "${GREEN}${BOLD}======================================================================"
echo "    ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE (0 A 100 LISTO)"
echo "======================================================================"
echo -e "${NC}"
echo -e "🌐 ${BOLD}Portal Web:${NC}              http://${DOMAIN_NAME}/ | https://${DOMAIN_NAME}/"
echo -e "🗄️ ${BOLD}Base de Datos:${NC}            ${DB_NAME} (Usuario: ${DB_USER})"
echo -e "🖥️ ${BOLD}Escritorio Remoto VNC:${NC}   noVNC + Websockify activo (systemctl status websockify)"
if [ "$IS_OPERATIONAL" -eq 1 ]; then
    echo -e "📡 ${BOLD}Bot de Monitoreo:${NC}         Activo y Validado (systemctl status tg-admin-bot)"
else
    echo -e "🛡️ ${BOLD}Sentinel Bot (Onboarding):${NC} Activo (systemctl status tg-sentinel)"
    echo -e "📡 ${BOLD}Bot Principal:${NC}            Pendiente de activación (evita colisiones con producción)"
fi
echo -e "🔔 ${BOLD}Notificador SSH & Boot:${NC}   Activos con blindaje PAM y systemd (boot-alert.service)"
echo -e "⏱️ ${BOLD}Cron de Sincronización:${NC}   Dinámico / Configurable desde Portal Web (/etc/cron.d/monitoreo_web)"
echo ""
if [ "$IS_OPERATIONAL" -eq 0 ]; then
    echo -e "${YELLOW}${BOLD}⚠️  PASO FINAL OBLIGATORIO DE ACTIVACIÓN (NUEVA INSTALACIÓN):${NC}"
    echo -e "${CYAN}Revise el Serial recibido en su Telegram privado y autorice mediante:${NC}"
    echo -e "  1️⃣  ${BOLD}Vía Telegram:${NC}  /activar [SERIAL] <TOKEN_BOT>"
    echo -e "  2️⃣  ${BOLD}Vía Consola:${NC}   activar [SERIAL] [TOKEN_BOT]"
    echo -e "${CYAN}Al activarse, Sentinel Bot transferirá el control automáticamente al Bot Principal.${NC}"
else
    echo -e "${GREEN}El servidor se encuentra validado y 100% operativo.${NC}"
fi
echo ""
