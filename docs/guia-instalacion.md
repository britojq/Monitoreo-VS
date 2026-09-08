# 🚀 Guía Rápida de Uso: Instalador Maestro (`installer/install.sh`)

> **Proyecto:** Monitor Valle Seco (`@IA_ValleSeco_bot`)  
> **Sistema Objetivo:** Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server  
> **Ubicación:** `/scripts/telegram-admin-bot/installer/install.sh`

---

## 📋 1. Requisitos Previos

Antes de comenzar la instalación en un servidor nuevo o máquina virtual, asegúrate de:
* Tener acceso a la terminal como usuario `root` o con privilegios `sudo`.
* Conectividad a Internet (para descarga de paquetes APT, Ollama y dependencias Python).
* Usuario de sistema predeterminado: `britojab` (si el usuario es diferente, el instalador lo adaptará).

---

## ⚡ 2. Despliegue en 3 Pasos

### Paso 1: Clonar el Repositorio Oficial
Ejecuta en la terminal de tu nuevo servidor:

```bash
sudo mkdir -p /scripts && sudo chown -R $USER:$USER /scripts
cd /scripts
git clone https://github.com/britojq/tgbot-pyt-bashfull.git telegram-admin-bot
cd /scripts/telegram-admin-bot
```

---

### Paso 2: Ejecutar el Instalador Maestro
Ejecuta el script con privilegios de superusuario:

```bash
sudo /bin/bash installer/install.sh
```

#### 🛠️ ¿Qué hace el instalador de forma automática?
1. **Paquetes APT:** Instala `python3`, `python3-venv`, `tcpdump`, `tshark`, `arp-scan`, `libpam-modules`, etc.
2. **Permisos de Red:** Aplica `setcap` a `tcpdump` y `arp-scan` y añade al usuario al grupo `wireshark` para permitir análisis de red sin ser root.
3. **Motor de IA:** Instala Ollama, activa el servicio systemd, descarga `qwen2.5:7b` y compila el modelo corporativo `qwen-empresa`.
4. **Entorno Python:** Crea el `venv` e instala `python-telegram-bot`, `httpx` y `requests`.
5. **CLI Global:** Enlaza el comando `/usr/local/bin/estatus -> /scripts/telegram-admin-bot/estatus`.
6. **Servicios Systemd:** Configura y habilita `tg-admin-bot.service` y `boot-alert.service`.
7. **PAM SSH:** Configura el hook en `/etc/pam.d/sshd` para alertas de login en tiempo real.
8. **Permisos:** Asigna propiedad `britojab:britojab` sobre `/scripts`.
9. **Arranque Controlado:** Deja `tg-admin-bot.service` habilitado pero detenido.

---

### Paso 3: Primer Arranque y Activación con el Owner (DRM Handshake)

1. Inicia el servicio del bot por primera vez:
   ```bash
   sudo systemctl start tg-admin-bot.service
   ```

2. **Revisa tu Telegram:**  
   Recibirás un mensaje de emergencia en tu chat privado (`38914901`) a través del Token Canario:
   ```text
   🔐 [ACTIVACIÓN REQUERIDA] Monitor Valle Seco
   Se ha detectado una nueva instalación en el servidor CENCARATIT.

   🔑 Serial de Activación:
   AUTH-XXXX-XXXX-XXXX-XXXX

   ⏳ Tiempo Límite: 10 Minutos
   ```

3. **Valida el Bot:**  
   Responde al bot en Telegram escribiendo el **Serial exacto** recibido (ejemplo: `AUTH-A1B2-C3D4-E5F6-7890`).

4. **Confirmación:**  
   El bot responderá:
   ```text
   ✅ [ACTIVACIÓN EXITOSA] Hardware anclado correctamente. El bot se encuentra ahora 100% OPERATIVO.
   ```
   A partir de este momento, todas las funciones quedan desbloqueadas y ancladas criptográficamente al nuevo hardware.

---

## ⏰ 3. Programación Automática de Reportes en CRON

Para que el bot envíe los reportes de servicios corporativos al grupo automáticamente a las **7:30 AM** y **4:00 PM (16:00)**:

1. Abre el editor de CRON:
   ```bash
   crontab -e
   ```
2. Agrega al final las siguientes dos líneas:
   ```cron
   # Reporte de Servicios Corporativos a las 7:30 AM
   30 7 * * * /usr/local/bin/estatus servicios >/dev/null 2>&1

   # Reporte de Servicios Corporativos a las 4:00 PM (16:00)
   0 16 * * * /usr/local/bin/estatus servicios >/dev/null 2>&1
   ```

---

## 🔄 4. Migración de Token de Telegram en Vivo (`/migrar_token`)

Si en el futuro necesitas cambiar el Token del bot sin reinstalar el sistema ni romper la vinculación de hardware:

1. Ve a un **chat privado** con el bot y envía el comando:
   ```text
   /migrar_token
   ```
2. El bot te solicitará el nuevo token de `@BotFather` (con un límite de 2 minutos).
3. Pega y envía el nuevo token.
4. El bot validará el token contra la API de Telegram, lo re-cifrará con la Clave de Hardware DRM y se reiniciará automáticamente bajo la nueva identidad.

---

## 🔍 5. Comandos de Verificación y Diagnóstico

| Acción | Comando |
| :--- | :--- |
| **Ver estado del servicio** | `sudo systemctl status tg-admin-bot.service` |
| **Ver logs en tiempo real** | `sudo journalctl -u tg-admin-bot.service -f` |
| **Probar reporte de servicios en terminal** | `estatus servicios --no-send` |
| **Probar reporte de sedes en terminal** | `estatus sedes --no-send` |
| **Probar análisis profundo de red (.pcap)** | `estatus analisis_red` |
| **Reiniciar servicio manualmente** | `sudo systemctl restart tg-admin-bot.service` |

---

*Manual operativo compilado para Administración y Operaciones Valle Seco • CORPOELEC.*
