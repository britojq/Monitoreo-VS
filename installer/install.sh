#!/usr/bin/env bash
# ==============================================================================
# Script: install.sh
# Ubicación: /scripts/telegram-admin-bot/installer/install.sh
# Descripción: Instala y configura los servicios Systemd y el hook de PAM para SSH.
#              100% autónomo y autocontenido dentro de /scripts/telegram-admin-bot
# ==============================================================================

if [ "$EUID" -ne 0 ]; then
    echo "[ERROR] Este instalador debe ejecutarse con privilegios de superusuario (sudo)." >&2
    exit 1
fi

PROJECT_DIR="/scripts/telegram-admin-bot"

echo "======================================================================"
echo "    INSTALACIÓN DE SERVICIOS Y ALERTAS: MONITOR VALLE SECO"
echo "======================================================================"
echo ""

# 1. Configurar permisos
echo "[*] Configurando permisos de ejecución..."
chmod +x "$PROJECT_DIR/monitor/ssh_alert.sh"
chmod +x "$PROJECT_DIR/monitor/ssh_alert.py"
echo "[+] Permisos configurados."

# 2. Configurar servicio de Arranque y Apagado (boot-alert.service)
echo "[*] Configurando boot-alert.service en Systemd..."
cat <<EOF > /etc/systemd/system/boot-alert.service
[Unit]
Description=Notificacion de Arranque y Apagado de Servidor a Telegram
After=network-online.target NetworkManager.service
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
User=britojab
Group=britojab
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/boot_alert.py start
ExecStop=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/monitor/boot_alert.py stop
TimeoutStartSec=300
TimeoutStopSec=15

[Install]
WantedBy=multi-user.target
EOF

# 3. Configurar servicio principal del bot (tg-admin-bot.service)
echo "[*] Configurando tg-admin-bot.service en Systemd..."
cat <<EOF > /etc/systemd/system/tg-admin-bot.service
[Unit]
Description=Telegram Admin Bot
Wants=network-online.target
After=network-online.target

[Service]
User=britojab
Group=britojab
WorkingDirectory=$PROJECT_DIR
ExecStart=$PROJECT_DIR/venv/bin/python $PROJECT_DIR/bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

# Habilitar servicios
systemctl daemon-reload
systemctl enable boot-alert.service
systemctl enable tg-admin-bot.service
systemctl start boot-alert.service
systemctl restart tg-admin-bot.service
echo "[+] Servicios Systemd habilitados y activos."

# 4. Configurar PAM para alertas SSH
PAM_SSHD="/etc/pam.d/sshd"
PAM_HOOK="session optional pam_exec.so seteuid /bin/bash $PROJECT_DIR/monitor/ssh_alert.sh"

echo "[*] Configurando hook de PAM en $PAM_SSHD..."
# Limpiar cualquier referencia antigua a /scripts/monitor/ssh_alert.sh
sed -i '\|/scripts/monitor/ssh_alert.sh|d' "$PAM_SSHD"
sed -i '\|/scripts/telegram-admin-bot/monitor/ssh_alert.sh|d' "$PAM_SSHD"

echo "" >> "$PAM_SSHD"
echo "# Alerta de conexion SSH a Telegram (Monitor Valle Seco)" >> "$PAM_SSHD"
echo "$PAM_HOOK" >> "$PAM_SSHD"
echo "[+] Hook de PAM actualizado correctamente apuntando a $PROJECT_DIR/monitor/ssh_alert.sh"

echo ""
echo "======================================================================"
echo "    ¡INSTALACIÓN COMPLETADA EXITOSAMENTE!"
echo "======================================================================"
