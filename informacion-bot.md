# 📘 Manual Técnico y Arquitectura del Sistema: Bot Administrador con IA

> **Nombre Oficial del Bot:** Monitor Valle Seco (`@IA_ValleSeco_bot`)  
> **Organización:** CORPOELEC - Centro de Operaciones Valle Seco / CENCARATIT  
> **Autor / Propietario:** José Brito (`@britojab` / `@britojq` • ID Telegram: `38914901`)  
> **Entorno Operativo:** Debian GNU/Linux (amd64)

---

## 📑 Tabla de Contenidos
1. [Visión General y Alcance del Proyecto](#1-visión-general-y-alcance-del-proyecto)
2. [Requisitos Previos del Sistema y Dependencias](#2-requisitos-previos-del-sistema-y-dependencias)
3. [Instalación y Configuración del Motor de IA (Ollama)](#3-instalación-y-configuración-del-motor-de-ia-ollama)
4. [Estructura del Proyecto y Componentes](#4-estructura-del-proyecto-y-componentes)
5. [Arquitectura de Seguridad, DRM y Protección de Hardware](#5-arquitectura-de-seguridad-drm-y-protección-de-hardware)
6. [Comportamiento del Sistema en Eventos Críticos](#6-comportamiento-del-sistema-en-eventos-críticos)
   - [Arranque y Detección de Falla Eléctrica / Apagado Forzado](#a-arranque-y-detección-de-falla-eléctrica--apagado-forzado)
   - [Apagado Limpio y Reinicio Ordenado](#b-apagado-limpio-y-reinicio-ordenado)
   - [Alertas de Acceso SSH en Tiempo Real](#c-alertas-de-acceso-ssh-en-tiempo-real)
   - [Protocolo de Re-Validación ante Migración de Servidor](#d-protocolo-de-re-validación-ante-migración-de-servidor)
   - [Dead Man's Switch por Aislamiento de Red](#e-dead-mans-switch-por-aislamiento-de-red)
7. [Módulos de Monitoreo y Herramientas de Red](#7-módulos-de-monitoreo-y-herramientas-de-red)
8. [Servicios Systemd y Configuración del Sistema Operativo](#8-servicios-systemd-y-configuración-del-sistema-operativo)
9. [Guía Paso a Paso para Despliegue en un Nuevo Servidor](#9-guía-paso-a-paso-para-despliegue-en-un-nuevo-servidor)

---

## 1. Visión General y Alcance del Proyecto

El bot **Monitor Valle Seco** es una solución integral de administración, ciberseguridad, auditoría y monitoreo continuo para infraestructuras críticas corporativas.

### Capacidades Principales:
* **Asistente Virtual con IA Local (Ollama):** Procesamiento de lenguaje natural ejecutado 100% en el servidor local con el modelo corporativo `qwen-empresa`, conociendo sedes, topología de red, direccionamiento IP, enlaces troncales y protocolos internos de CORPOELEC.
* **Telemetría y Diagnóstico de Red en Tiempo Real:** Análisis de enlaces (`/sedes`), servicios críticos (`/servicios`), análisis forense de paquetes (.pcap) con `tcpdump` y `tshark` (`/analisis_red`), y detección de hosts no autorizados mediante lista blanca de direcciones MAC.
* **Seguridad Inmutable y DRM Vinculado a Hardware:** Protección contra clonación de servidor, robo de código o manipulación de credenciales mediante derivación criptográfica ligada a los identificadores del hardware físico.
* **Auditoría y Alertas Continuas:** Registro detallado de accesos SSH, intentos no autorizados de uso del bot, fallas eléctricas y actualizaciones automáticas con el repositorio oficial de GitHub.

---

## 2. Requisitos Previos del Sistema y Dependencias

Para el despliegue en cualquier servidor base (físico o máquina virtual autorizada), se requiere un sistema **Debian 12 / 13 (o derivado Ubuntu Server)** con acceso a internet.

### A. Paquetes del Sistema Operativo (APT)
Instalar los siguientes paquetes esenciales como `root` o con `sudo`:

```bash
sudo apt update && sudo apt install -y \
  python3 \
  python3-venv \
  python3-pip \
  git \
  curl \
  wget \
  iproute2 \
  iputils-ping \
  arp-scan \
  tcpdump \
  tshark \
  wireshark-common \
  libpam-modules \
  sudo
```

### B. Configuración de Permisos para Herramientas de Red
Para permitir que los chequeos de red y capturas de paquetes operen sin bloquearse:

1. **Wireshark / TShark:**
   ```bash
   sudo dpkg-reconfigure wireshark-common
   # Seleccionar "Sí" para permitir que usuarios no root capturen paquetes.
   sudo usermod -aG wireshark $USER
   ```
2. **Capacidades de Tcpdump y Arp-scan:**
   ```bash
   sudo setcap cap_net_raw,cap_net_admin=eip /usr/bin/tcpdump
   sudo setcap cap_net_raw,cap_net_admin=eip /usr/sbin/arp-scan
   ```

### C. Dependencias de Python (Entorno Virtual)
El proyecto utiliza un entorno virtual aislado en `/scripts/telegram-admin-bot/venv`:

```bash
cd /scripts/telegram-admin-bot
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install python-telegram-bot>=21.0 httpx>=0.27.0 requests>=2.31.0
```

---

## 3. Instalación y Configuración del Motor de IA (Ollama)

El bot integra un modelo de lenguaje local que procesa las consultas de los usuarios sin enviar datos a servidores externos.

### A. Instalación de Ollama
```bash
curl -fsSL https://ollama.com/install.sh | sh
```
Verificar que el servicio esté activo:
```bash
sudo systemctl status ollama
```

### B. Descarga del Modelo Base
Se requiere el modelo base Qwen 2.5:
```bash
ollama run qwen2.5:7b
```

### C. Creación del Modelo Personalizado (`qwen-empresa`)
El proyecto incluye la definición del asistente corporativo en `ai/Modelfile.txt`. Para compilar y activar el modelo:

```bash
cd /scripts/telegram-admin-bot
ollama create qwen-empresa -f ai/Modelfile.txt
```

Para probarlo localmente:
```bash
ollama run qwen-empresa "¿Cuáles son las sedes de Valle Seco?"
```

---

## 4. Estructura del Proyecto y Componentes

El proyecto es **100% autónomo y autocontenido** en `/scripts/telegram-admin-bot`:

```
/scripts/telegram-admin-bot/
├── bot.py                            # Demonio principal del bot de Telegram
├── installer/
│   └── install.sh                    # Instalador automatizado de servicios Systemd y hooks PAM
├── ai/
│   ├── Modelfile.txt                 # Definición del modelo y system prompt corporativo
│   └── parametros.txt                # Parámetros térmicos y de contexto Ollama
├── config/
│   ├── config.json                   # Configuración operativa visual
│   ├── bot.conf                      # Configuración de proxies y tokens secundarios
│   ├── monitoreo.conf                # Definición de sedes, servicios, IPs y puertos
│   ├── commands.json                 # Textos y descripciones de comandos
│   ├── mac_whitelist.txt             # Lista blanca de direcciones MAC autorizadas
│   └── oui.txt                       # Base de datos de fabricantes de red
├── monitor/
│   ├── core_shield.py                # DRM de hardware, Token Canario y SecureCore
│   ├── system_updater.py             # Actualizador autónomo forzado con GitHub (48h)
│   ├── boot_alert.py                 # Detección de arranque limpio vs caída eléctrica (con failover proxy)
│   ├── ssh_alert.py                  # Notificador nativo de accesos SSH (con failover proxy)
│   ├── ssh_alert.sh                  # Wrapper de ejecución rápida para PAM
│   ├── network_analyzer.py           # Análisis de red, tcpdump, tshark y arp-scan
│   ├── monitor_servicios.py          # Chequeo de puertos TCP/HTTP/DNS/LDAP/SMB
│   ├── monitor_sedes.py              # Chequeo de conectividad ICMP de sedes
│   ├── checker_base.py               # Utilidades base de red y formateador HTML
│   ├── config_parser.py              # Parser unificado de configuraciones
│   └── telegram_dispatcher.py        # Despachador HTTP con conmutación inteligente Directo/Proxies
├── audit/                            # Logs de accesos, anclaje de hardware (.sys_anchor) y reportes (.pcap)
├── docs/                             # Documentación confidencial excluida de Git (.gitignore)
└── venv/                             # Entorno virtual aislado de Python
```

---

## 5. Arquitectura de Seguridad, DRM y Protección de Hardware

El bot implementa un esquema de **Digital Rights Management (DRM) basado en hardware** en el módulo `monitor/core_shield.py`:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        NÚCLEO DE SEGURIDAD DRM                         │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Huella Física del Servidor:                                         │
│    - /etc/machine-id                                                   │
│    - MAC Address (uuid.getnode())                                      │
│    - Hostname del Sistema                                              │
│                                                                        │
│ 2. Derivación PBKDF2-HMAC (SHA-256) -> Clave de Hardware               │
│                                                                        │
│ 3. Desenvolvimiento de Master Key desde 'audit/.sys_anchor'            │
│    ├── Coincide -> Estado OPERATIONAL (Desofuscación exitosa)          │
│    └── Mismatch -> Estado PENDING_VALIDATION (Protocolo Canario)       │
└────────────────────────────────────────────────────────────────────────┘
```

### Principios Blindados:
1. **Credenciales Inmutables:** El `owner_id` (`38914901`), el `bot_token`, la URL del repositorio Git (`https://github.com/britojq/tgbot-pyt-bashfull.git`) y la rama `master` están cifrados y hardcodeados. Cualquier modificación en `config.json` es ignorada en memoria.
2. **Ofuscación Multicapa:** Predicados opacos matemáticos, control-flow flattening (máquina de estados), e inyección de código muerto para impedir ingeniería inversa.
3. **Fallo Silencioso (Anti-Tamper):** Si un atacante modifica los strings cifrados en disco, el sistema entra en fallo silencioso generando basura de alta entropía sin mostrar errores informativos.

---

## 6. Comportamiento del Sistema en Eventos Críticos

### A. Arranque y Detección de Falla Eléctrica / Apagado Forzado
* El servicio systemd `boot-alert.service` se dispara en cada inicio (`ExecStart`).
* Espera de forma activa hasta 240 segundos a que la red y Telegram estén en línea.
* Evalúa la presencia del archivo testigo `audit/.clean_shutdown`:
  * **Si el archivo EXISTE:** Significa que el servidor fue apagado de forma ordenada por el administrador (`sudo reboot` o `sudo shutdown`). Envía la alerta: `🟢 Reinicio Limpio o Apagado Controlado`. Luego elimina el archivo.
  * **Si el archivo NO EXISTE:** Significa que hubo un **corte de energía eléctrica, apagado abrupto o desconexión del cable**. Envía la alerta inmediata: `🚨 Falla Eléctrica / Apagado Forzado Inesperado`.
* La notificación incluye fecha, hora, hostname y todas las direcciones IP locales asignadas.

---

### B. Apagado Limpio y Reinicio Ordenado
* Cuando el sistema recibe una orden de apagado o reinicio, systemd ejecuta `ExecStop` de `boot-alert.service`.
* Crea el archivo testigo `audit/.clean_shutdown` en disco con sincronización inmediata (`os.sync()`).
* Envía un mensaje instantáneo a Telegram al Owner: `🛑 Alerta: Servidor Apagándose / Reiniciando (Parada limpia / reinicio ordenado por el sistema)`.

---

### C. Alertas de Acceso SSH en Tiempo Real
* Configurado a través del módulo PAM (`/etc/pam.d/sshd`) con `pam_exec.so`.
* Cada vez que un usuario inicia sesión SSH:
  1. Se ejecuta `/scripts/telegram-admin-bot/monitor/ssh_alert.sh` en segundo plano (sin retrasar la terminal).
  2. Invoca el módulo nativo `monitor/ssh_alert.py` con el entorno virtual del bot.
  3. Extrae usuario (`$PAM_USER`), IP remota (`$PAM_RHOST`), terminal (`$TTY_SSH`) y fecha.
  4. Si la IP es pública, consulta la API de geolocalización para determinar Ciudad, País y Proveedor de Internet (ISP).
  5. Despacha la alerta a Telegram utilizando el token inmutable del núcleo:
     ```
     🔑 Acceso SSH Detectado
     Servidor: CENCARATIT
     Usuario: britojab
     IP Origen: 192.168.1.50
     Fecha: 2026-08-25 12:00:00
     Terminal: pts/0
     ```

---

### D. Protocolo de Re-Validación ante Migración de Servidor
Si el proyecto es clonado o transferido a un nuevo equipo o máquina virtual:
1. `SecureCore` detecta que la huella física no coincide con `audit/.sys_anchor`.
2. Entra en estado `PENDING_VALIDATION` y genera un **Serial Challenge** antifalsificación (`AUTH-XXXX-XXXX-XXXX-XXXX`) con validez de **10 minutos**.
3. Envía una notificación de emergencia al Owner usando el **Token Canario** de respaldo.
4. **Si el Owner responde con el Serial exacto dentro de los 10 minutos:** El sistema re-ancla la Master Key al nuevo hardware, actualiza `audit/.sys_anchor`, confirma la re-vinculación y reanuda el bot normalmente.
5. **Si pasan 10 minutos o el serial es incorrecto:** Se activa la autodestrucción del anclaje y el proceso finaliza.

---

### E. Dead Man's Switch por Aislamiento de Red
* El actualizador autónomo (`monitor/system_updater.py`) comprueba el repositorio de GitHub cada 48 horas.
* Si ocurren **3 fallos consecutivos** de conexión con GitHub, el bot asume que ha sido aislado o robado de su entorno oficial.
* Dispara `trip_deadman_switch()`, sobrescribiendo `audit/.sys_anchor` con bytes aleatorios y purgando las claves de memoria.

---

## 7. Módulos de Monitoreo y Herramientas de Red

| Comando / Módulo | Descripción | Herramientas Utilizadas |
| :--- | :--- | :--- |
| `/sedes` | Estado de conectividad de todas las sedes y subestaciones de CORPOELEC. | ICMP Ping concurrente |
| `/servicios` | Verificación de puertos y servicios corporativos (OTRS, Intranet, DNS, LDAP, SMB, CUPS). | Sockets TCP, HTTP requests, DNS lookup |
| `/analisis_red` | Diagnóstico profundo de tráfico LAN, tormentas broadcast, latencias y hosts no autorizados. | `tcpdump`, `tshark`, `arp-scan`, `mac_whitelist.txt` |
| `/actualizar` | Comprobación y forzado de sincronización con el repositorio oficial (`git reset --hard`). | Git, PyCompile, Systemctl |
| `/reset_ia` | Reinicio de la memoria conversacional del asistente con Ollama. | Ollama Context Manager |

---

## 8. Servicios Systemd y Configuración del Sistema Operativo

### A. Servicio Principal del Bot (`tg-admin-bot.service`)
Archivo: `/etc/systemd/system/tg-admin-bot.service`

```ini
[Unit]
Description=Telegram Admin Bot
Wants=network-online.target
After=network-online.target

[Service]
User=britojab
Group=britojab
WorkingDirectory=/scripts/telegram-admin-bot
ExecStart=/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Habilitar y arrancar:
```bash
sudo systemctl daemon-reload
sudo systemctl enable tg-admin-bot.service
sudo systemctl start tg-admin-bot.service
```

---

### B. Servicio de Alertas de Arranque y Apagado (`boot-alert.service`)
Archivo: `/etc/systemd/system/boot-alert.service`

```ini
[Unit]
Description=Notificacion de Arranque y Apagado de Servidor a Telegram
After=network-online.target NetworkManager.service
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
User=britojab
Group=britojab
WorkingDirectory=/scripts/telegram-admin-bot
ExecStart=/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/boot_alert.py start
ExecStop=/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/boot_alert.py stop
TimeoutStartSec=300
TimeoutStopSec=15

[Install]
WantedBy=multi-user.target
```

Habilitar y arrancar:
```bash
sudo systemctl daemon-reload
sudo systemctl enable boot-alert.service
sudo systemctl start boot-alert.service
```

---

### C. Hook de Acceso SSH en PAM
Archivo: `/etc/pam.d/sshd` (al final del archivo):

```text
# Alerta de conexion SSH a Telegram (Monitor Valle Seco)
session optional pam_exec.so seteuid /bin/bash /scripts/telegram-admin-bot/monitor/ssh_alert.sh
```

---

## 9. Guía Paso a Paso para Despliegue en un Nuevo Servidor

Para desplegar este bot en un servidor completamente nuevo, sigue este checklist:

### Paso 1: Clonar el Repositorio
```bash
sudo mkdir -p /scripts
sudo chown -R $USER:$USER /scripts
cd /scripts
git clone https://github.com/britojq/tgbot-pyt-bashfull.git telegram-admin-bot
```

### Paso 2: Instalar Paquetes y Configurar Permisos
```bash
sudo apt update && sudo apt install -y \
  python3 python3-venv python3-pip git curl iproute2 \
  iputils-ping arp-scan tcpdump tshark wireshark-common libpam-modules

sudo setcap cap_net_raw,cap_net_admin=eip /usr/bin/tcpdump
sudo setcap cap_net_raw,cap_net_admin=eip /usr/sbin/arp-scan
```

### Paso 3: Instalar Ollama y Compilar el Modelo
```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama run qwen2.5:7b
cd /scripts/telegram-admin-bot
ollama create qwen-empresa -f ai/Modelfile.txt
```

### Paso 4: Configurar el Entorno Virtual de Python
```bash
cd /scripts/telegram-admin-bot
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### Paso 5: Instalar los Hooks de Sistema y Servicios
Ejecutar el instalador automatizado:
```bash
sudo /bin/bash /scripts/installer/install.sh
```

Crear y habilitar `tg-admin-bot.service`:
```bash
sudo cp /scripts/telegram-admin-bot/config/tg-admin-bot.service /etc/systemd/system/tg-admin-bot.service 2>/dev/null || true
# O crear manualmente /etc/systemd/system/tg-admin-bot.service con el contenido de la sección 8A.
sudo systemctl daemon-reload
sudo systemctl enable --now tg-admin-bot.service
```

### Paso 6: Autorización Inicial de Hardware (Re-Validación)
1. Al arrancar en el nuevo hardware, recibirás un mensaje de Telegram en tu chat privado (`38914901`):
   ```
   ⚠️ [ALERTA DE SEGURIDAD] Entorno Modificado / Migración Detectada
   🔑 Serial de Validación: AUTH-XXXX-XXXX-XXXX-XXXX
   ```
2. Responde al bot con el Serial exacto dentro de los 10 minutos.
3. El sistema confirmará la autenticación y el bot quedará permanentemente activo y vinculado al nuevo servidor.

### Paso 7: Configuración de Tareas Programadas en CRON (Monitoreo Automático)
Para programar los reportes automáticos de servicios corporativos a las **7:30 AM** y **4:00 PM** (16:00), editar el crontab del sistema:

```bash
crontab -e
```

Agregar las siguientes líneas al final del archivo:
```cron
# ==============================================================================
# ⏰ MONITOREO AUTOMÁTICO VALLE SECO (Reportes a Grupo y Administrador)
# ==============================================================================
# Reporte de Servicios Corporativos a las 7:30 AM
30 7 * * * /usr/local/bin/estatus servicios >/dev/null 2>&1

# Reporte de Servicios Corporativos a las 4:00 PM (16:00)
0 16 * * * /usr/local/bin/estatus servicios >/dev/null 2>&1
```

---
*Manual técnico compilado para uso exclusivo de Administración y Operaciones Valle Seco.*
