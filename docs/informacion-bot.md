# 📘 Manual Técnico y Arquitectura del Sistema: Bot Administrador con IA

> **Nombre Oficial del Bot:** Monitor Valle Seco (`@IA_ValleSeco_bot`)  
> **Organización:** CORPOELEC - Centro de Operaciones Valle Seco / CENCARATIT  
> **Autor / Administrador:** José A. Brito H. (`@britojab` / `@britojq`), [https://britojab.com](https://britojab.com)  
> **Licencia:** GNU Affero General Public License v3.0 (AGPLv3)  
> **Copyright:** (c) 2026 Jose A. Brito H.  
> **Entorno Operativo:** Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server  

---

## 📑 Tabla de Contenidos
1. [Visión General y Alcance del Proyecto](#1-visión-general-y-alcance-del-proyecto)
2. [Requisitos Previos del Sistema y Dependencias](#2-requisitos-previos-del-sistema-y-dependencias)
3. [Instalación, Configuración y Seguridad del Motor de IA (Ollama)](#3-instalación-configuración-y-seguridad-del-motor-de-ia-ollama)
   - [Instalación y Creación del Modelo Corporativo](#a-instalación-y-creación-del-modelo-corporativo)
   - [Aislamiento Estricto de Contexto de Monitoreo](#b-aislamiento-estricto-de-contexto-de-monitoreo)
   - [Filtro de PII y Anti-Fuga de Datos Sensibles](#c-filtro-de-pii-y-anti-fuga-de-datos-sensibles)
   - [Rate Limiting Anti-DoS en Memoria](#d-rate-limiting-anti-dos-en-memoria)
4. [Estructura del Proyecto y Módulos](#4-estructura-del-proyecto-y-módulos)
5. [Arquitectura de Seguridad, DRM y Anclaje a Placa Base](#5-arquitectura-de-seguridad-drm-y-anclaje-a-placa-base)
6. [Comportamiento del Sistema en Eventos Críticos](#6-comportamiento-del-sistema-en-eventos-críticos)
   - [Arranque y Detección de Falla Eléctrica vs Reinicio Limpio](#a-arranque-y-detección-de-falla-eléctrica-vs-reinicio-limpio)
   - [Apagado Limpio y Reinicio Ordenado](#b-apagado-limpio-y-reinicio-ordenado)
   - [Alertas de Acceso SSH en Tiempo Real (PAM)](#c-alertas-de-acceso-ssh-en-tiempo-real-pam)
   - [Protocolo de Re-Validación ante Migración o Primer Arranque](#d-protocolo-de-re-validación-ante-migración-o-primer-arranque)
   - [Dead Man's Switch por Aislamiento de Red](#e-dead-mans-switch-por-aislamiento-de-red)
7. [Panel de Control de Emergencia y Contingencia (`/emergencia`)](#7-panel-de-control-de-emergencia-y-contingencia-emergencia)
8. [Protección y Aislamiento de Comandos en Grupos](#8-protección-y-aislamiento-de-comandos-en-grupos)
9. [Diagnóstico de Almacenamiento y Limpiador del Sistema (`/limpiador`)](#9-diagnóstico-de-almacenamiento-y-limpiador-del-sistema-limpiador)
10. [Módulos de Monitoreo, Herramientas de Red y CLI Global (`estatus`)](#10-módulos-de-monitoreo-herramientas-de-red-y-cli-global-estatus)
11. [Inventario Completo de Comandos en Telegram](#11-inventario-completo-de-comandos-en-telegram)
12. [Servicios Systemd y Configuración del Sistema Operativo](#12-servicios-systemd-y-configuración-del-sistema-operativo)
13. [Guía Paso a Paso para Despliegue en un Nuevo Servidor](#13-guía-paso-a-paso-para-despliegue-en-un-nuevo-servidor)

---

## 1. Visión General y Alcance del Proyecto

El bot **Monitor Valle Seco** es una plataforma integral de administración de servidores, auditoría continua, telemetría de infraestructura y ciberseguridad corporativa, complementada con un asistente conversacional potenciado por **Inteligencia Artificial local**.

### Capacidades Principales:
* **🧠 Asistente Virtual con IA Local (Ollama):** Procesamiento de lenguaje natural 100% privado en el servidor local con el modelo corporativo `qwen-empresa`, conociendo sedes, topología de red, direccionamiento IP, enlaces troncales y protocolos internos de CORPOELEC.
* **🛡️ Seguridad de IA & Anti-Fuga de Datos:** Filtro PII regex para cédulas venezolanas y números telefónicos, aislamiento estricto de contexto de monitoreo y Rate Limiting Anti-DoS de 5 peticiones/minuto.
* **🌐 Telemetría y Diagnóstico de Red en Tiempo Real:** Análisis concurrente de enlaces (`/sedes`), servicios corporativos (`/servicios`), análisis forense de paquetes (.pcap) con `tcpdump` y `tshark` (`/analisis_red`), y detección de hosts no autorizados mediante lista blanca MAC.
* **🔒 Seguridad Inmutable y DRM de Hardware (`SecureCore`):** Anclaje criptográfico vinculado a la placa base del servidor (DMI BIOS Lenovo, `/etc/machine-id`, hostname, arch), totalmente independiente de interfaces de red o Wi-Fi.
* **🚨 Panel de Control de Contingencia (`/emergencia`):** Herramienta interactiva para apagado inmediato del servicio, activación de modo mantenimiento o restauración (*rollback*) de configuraciones seguras.
* **🧹 Diagnóstico y Limpieza Interactiva (`/limpiador`):** Supervisión de espacio en `/`, detección de inodos, directorios no estándar y depuración de temporales con confirmación interactiva.
* **⏰ CLI Global (`estatus`) y Automatización CRON:** Herramienta de consola `/usr/local/bin/estatus` con protección anti-concurrencia por *Lockfile* atómico para reportes programados (7:30 AM y 4:00 PM).
* **🔔 Alertas Críticas Proactivas:** Notificación de reinicio limpio vs corte de energía eléctrica, accesos SSH vía PAM con geolocalización, e intentos de acceso no autorizados en tiempo real.

---

## 2. Requisitos Previos del Sistema y Dependencias

Para el despliegue en cualquier servidor base (físico o máquina virtual autorizada), se requiere un sistema **Debian 12 / 13 (o derivado Ubuntu Server)** con arquitectura `amd64`.

### A. Paquetes del Sistema Operativo (APT)
Instalar los paquetes requeridos como `root` o con `sudo`:

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

### B. Configuración de Capacidades de Red
Para permitir que las capturas de paquetes y escaneos ARP operen sin privilegios de superusuario en cada ejecución:

```bash
sudo setcap cap_net_raw,cap_net_admin=eip /usr/bin/tcpdump
sudo setcap cap_net_raw,cap_net_admin=eip /usr/sbin/arp-scan
```

### C. Entorno Virtual de Python
El proyecto utiliza un entorno virtual aislado en `/scripts/telegram-admin-bot/venv`:

```bash
cd /scripts/telegram-admin-bot
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install python-telegram-bot>=21.0 httpx>=0.27.0 requests>=2.31.0
```

---

## 3. Instalación, Configuración y Seguridad del Motor de IA (Ollama)

El bot integra un modelo de lenguaje local que procesa las consultas de los usuarios sin enviar datos a servidores externos.

### A. Instalación y Creación del Modelo Corporativo
```bash
# 1. Instalar Ollama
curl -fsSL https://ollama.com/install.sh | sh

# 2. Descargar modelo base
ollama run qwen2.5:7b

# 3. Compilar el modelo corporativo personalizado
cd /scripts/telegram-admin-bot
ollama create qwen-empresa -f ai/Modelfile.txt
```

### B. Aislamiento Estricto de Contexto de Monitoreo
* Los outputs generados por los scripts y comandos de monitoreo (`/servicios`, `/sedes`, `/monitoreo`, `/analisis_red`) se envían a Telegram como mensajes independientes.
* **Bajo ninguna circunstancia** estos reportes se añaden (`append`) al historial de mensajes de Ollama (`context.chat_data["history"]`).
* Esto garantiza que la memoria conversacional de la IA permanezca limpia, enfocada en las consultas del usuario y sin contaminación de telemetría de red pasada.

### C. Filtro de PII y Anti-Fuga de Datos Sensibles
Función nativa `sanitizar_pii(texto: str) -> str` que intercepta todo texto plano del usuario antes de enviarlo a Ollama o guardarlo en historial:
* **Cédulas de Identidad:** Expresión regular que detecta números de 7 u 8 dígitos consecutivos (con o sin prefijos `V-`, `V`, `E-`, `CI`) y los sustituye por `[C.I. OCULTA]`.
* **Teléfonos Locales / Celulares:** Expresión regular que detecta prefijos venezolanos (`0412`, `0414`, `0424`, `0416`, `0426`, `02XX`, con o sin `+58` o guiones) y los sustituye por `[TLF. OCULTO]`.
* **Preservación Técnica:** Diseñado para no alterar direcciones IP (`192.168.1.10`), puertos (`8080`, `22`), ni identificadores técnicos corporativos.

### D. Rate Limiting Anti-DoS en Memoria
Función `check_ai_rate_limit(entity_id, max_requests=5, window_seconds=60.0)`:
* Controla las consultas de texto plano a la IA mediante una ventana deslizante de **máximo 5 mensajes por minuto** por usuario o chat.
* Si el usuario excede la tasa, el bot responde de inmediato:
  > `Limite de consultas a la IA excedido. Espere 60 segundos.`
* La llamada a Ollama se aborta al instante, protegiendo la CPU/GPU del servidor de sobrecargas intencionales o accidentales.

---

## 4. Estructura del Proyecto y Módulos

El proyecto es **100% autónomo y autocontenido** en `/scripts/telegram-admin-bot`:

```
/scripts/telegram-admin-bot/
├── bot.py                            # Demonio principal de Telegram, IA y enrutamiento
├── estatus                           # Wrapper y CLI global (/usr/local/bin/estatus)
├── installer/
│   └── install.sh                    # Instalador maestro automatizado (Servicios, PAM, Venv)
├── ai/
│   ├── Modelfile.txt                 # Prompt de sistema corporativo de CORPOELEC
│   └── parametros.txt                # Parámetros térmicos y de contexto de Ollama
├── config/
│   ├── config.json                   # Configuración operativa visual y lista de proxies
│   ├── bot.conf                      # Plantillas y parámetros de compatibilidad
│   ├── monitoreo.conf                # Definición de sedes, servicios corporativos, IPs y puertos
│   ├── mensajes.conf                 # Plantillas de reportes limpios (sin líneas de separación)
│   ├── commands.json                 # Inventario y descripciones de comandos dinámicos
│   ├── mac_whitelist.txt             # Lista blanca de direcciones MAC autorizadas
│   ├── oui.txt                       # Base de datos de fabricantes OUI
│   └── .backup_golden/               # Copia de seguridad dorada para restauración de emergencia
├── monitor/
│   ├── core_shield.py                # DRM de hardware a placa base, Token Canario y SecureCore
│   ├── system_updater.py             # Actualizador autónomo forzado con GitHub (48h)
│   ├── boot_alert.py                 # Detección de arranque limpio vs corte eléctrico (failover proxy)
│   ├── ssh_alert.py                  # Notificador PAM de accesos SSH con geolocalización
│   ├── ssh_alert.sh                  # Hook desacoplado de ejecución rápida para PAM
│   ├── monitor_engine.py             # Orquestador unificado de reportes y CLI con Lockfile
│   ├── monitor_servicios.py          # Chequeo asíncrono de servicios corporativos
│   ├── monitor_sedes.py              # Chequeo asíncrono de sedes y subestaciones
│   ├── network_analyzer.py           # Análisis profundo de red, tcpdump, tshark y arp-scan
│   ├── system_cleaner.py             # Diagnóstico de almacenamiento, inodos y limpiador interactivo
│   ├── checker_base.py               # Chequeo de protocolos (HTTP, DNS, SMTP, LDAP, CUPS, Ping)
│   ├── config_parser.py              # Parser unificado de configuraciones (.conf)
│   └── telegram_dispatcher.py        # Despachador HTTP con failover automático directo/proxies
├── audit/                            # Logs de accesos, anclaje seguro (.sys_anchor) y reportes
├── docs/                             # Documentación técnica e historial de intervenciones (.gitignore)
└── venv/                             # Entorno virtual aislado de Python
```

---

## 5. Arquitectura de Seguridad, DRM y Anclaje a Placa Base

El bot implementa un esquema de **Digital Rights Management (DRM) de Hardware (`SecureCore`)** en el módulo [`monitor/core_shield.py`](file:///scripts/telegram-admin-bot/monitor/core_shield.py):

```
┌────────────────────────────────────────────────────────────────────────┐
│                        NÚCLEO DE SEGURIDAD DRM                         │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Huella Física Inmutable del Servidor:                               │
│    - /etc/machine-id (OS Unique Identifier)                            │
│    - DMI BIOS Firmware (sys_vendor, product_name, board_name, ver)     │
│    - Hostname del Sistema & Arquitectura CPU                           │
│    * CERO dependencia de tarjetas de red / Wi-Fi (Inmune a red)       │
│                                                                        │
│ 2. Derivación Criptográfica PBKDF2-HMAC (SHA-256) -> Clave de Hardware │
│                                                                        │
│ 3. Desenvolvimiento de Master Key desde 'audit/.sys_anchor'            │
│    ├── Coincide -> Estado OPERATIONAL (Desofuscación exitosa)          │
│    └── Mismatch -> Estado PENDING_VALIDATION (Protocolo Canario)       │
└────────────────────────────────────────────────────────────────────────┘
```

### Principios Blindados:
1. **Credenciales Inmutables:** El Administrador, el Token principal del Bot, la URL del repositorio Git (`https://github.com/britojq/tgbot-pyt-bashfull.git`) y la rama `master` están cifrados y protegidos. Cualquier edición externa en archivos JSON es ignorada por el núcleo.
2. **Independencia de Tarjetas de Red:** Para evitar falsos positivos al reiniciar en entornos con interfaces dinámicas, la huella de hardware se ancla exclusivamente a la placa base del equipo.
3. **Ofuscación Multicapa:** Predicados opacos matemáticos, control-flow flattening (máquina de estados), e inyección de código muerto para impedir ingeniería inversa.
4. **Fallo Silencioso (Anti-Tamper):** Si un atacante manipula los payloads cifrados, el sistema entra en fallo silencioso generando entropía inválida sin arrojar descripciones de error.

---

## 6. Comportamiento del Sistema en Eventos Críticos

### A. Arranque y Detección de Falla Eléctrica vs Reinicio Limpio
* El servicio systemd `boot-alert.service` se ejecuta en cada inicio del sistema operativo.
* Evalúa la presencia del archivo marcador `audit/.clean_shutdown`:
  * **Si el archivo EXISTE:** Significa que el servidor fue apagado de forma ordenada por el administrador (`sudo reboot` o `sudo shutdown`). Envía la alerta: `🟢 Reinicio Limpio o Apagado Controlado`. Luego elimina el marcador.
  * **Si el archivo NO EXISTE:** Significa que hubo un **corte de energía eléctrica, apagado abrupto o desconexión del cable**. Envía la alerta inmediata: `🚨 Falla Eléctrica / Apagado Forzado Inesperado`.
* La notificación incluye fecha, hora, hostname y todas las direcciones IP locales asignadas.

---

### B. Apagado Limpio y Reinicio Ordenado (Blindaje e Inmunidad)
> [!IMPORTANT]
> **🔒 REGLA DE ORO INMUTABLE (CONGELACIÓN PERMANENTE):**  
> Los archivos [`monitor/boot_alert.py`](file:///scripts/telegram-admin-bot/monitor/boot_alert.py) y [`/etc/systemd/system/boot-alert.service`](file:///etc/systemd/system/boot-alert.service) son **estrictamente intocables y quedan congelados de forma inmutable**. Ninguna modificación futura a comandos o módulos del bot debe alterar sus parámetros.

#### ⚙️ Arquitectura de Ejecución Garantizada:
* **Conexiones Globales NetworkManager (System-Wide):** Las conexiones de red están configuradas sin restricciones de usuario (`connection.permissions: ""` / `--`), impidiendo que NetworkManager destruya la conexión cuando la sesión de usuario finaliza durante un `sudo reboot`.
* **Aislamiento en `system.slice` (Root):** El servicio corre bajo `Slice=system.slice` con `User=root`, inmune al cierre de `user-1000.slice`.
* **Ordenamiento Estricto de Systemd:**
  * `Before=shutdown.target reboot.target halt.target poweroff.target`: Garantiza que `ExecStop` se ejecute **antes** de que el kernel inicie la parada de hardware.
  * `After=network-online.target NetworkManager.service systemd-resolved.service networking.service`: Mantiene la red y resolución DNS vivas durante el despacho.
* **Envío Ultra-Rápido Prioritario (`send_shutdown_alert_fast`):**
  1. Crea el testigo `audit/.clean_shutdown` con permisos `0o666` y volcado físico inmediato a disco (`os.sync()`).
  2. Despacha el mensaje HTTP a Telegram en **menos de 1 segundo (0.76s)** con timeout directo prioritario.

---

### C. Alertas de Acceso SSH en Tiempo Real (PAM)
* Integrado en el módulo PAM (`/etc/pam.d/sshd`) con `pam_exec.so`.
* Cada vez que un usuario abre una sesión SSH:
  1. Se ejecuta `monitor/ssh_alert.sh` en segundo plano desacoplado con **timeout estricto de 15 segundos** (`/usr/bin/timeout --signal=SIGTERM 15s`), evitando el bloqueo del login interactivo y la generación de procesos zombi.
  2. Invoca el módulo nativo `monitor/ssh_alert.py` pasando `$PAM_USER`, `$PAM_RHOST` y `$PAM_TTY`.
  3. Extrae usuario, IP remota, terminal y fecha.
  4. Si la IP es pública, consulta la geolocalización para determinar Ciudad, País y Proveedor de Internet (ISP).
  5. Despacha la alerta en tiempo real a Telegram al Administrador con soporte de conmutación de proxies.

---

### D. Protocolo de Re-Validación ante Migración o Primer Arranque
Si el proyecto es clonado o transferido a un nuevo equipo o máquina virtual:
1. `SecureCore` detecta que la huella física no coincide con `audit/.sys_anchor` (o que `audit/.sys_anchor` no existe en un primer arranque).
2. Entra en estado `PENDING_VALIDATION` y genera un **Serial Challenge** antifalsificación (`AUTH-XXXX-XXXX-XXXX-XXXX`) con validez de **10 minutos**.
3. Envía una notificación de emergencia al Administrador usando el **Token Canario** de respaldo.
4. **Respuesta del Administrador:** El bot permanece 100% activo en Telegram escuchando el serial. Al responder con el serial exacto (o mediante `/activar <SERIAL>`), se deriva la nueva clave de hardware, se genera el anclaje seguro y el bot pasa a modo `OPERATIONAL`.
5. **Si pasan 10 minutos o el serial es incorrecto:** Se activa la autodestrucción del anclaje y el bot se bloquea permanentemente.

---

### E. Dead Man's Switch por Aislamiento de Red
* El actualizador autónomo (`monitor/system_updater.py`) comprueba el repositorio de GitHub cada 48 horas.
* Si ocurren **3 fallos consecutivos** de sincronización con GitHub, el bot asume que ha sido aislado o robado de su entorno oficial.
* Dispara `trip_deadman_switch()`, destruyendo `audit/.sys_anchor` y purgando las credenciales de memoria.

---

## 7. Panel de Control de Emergencia y Contingencia (`/emergencia`)

Comando exclusivo para el Administrador en **chat privado** (alias `/panico`, `/contingencia`), diseñado para responder ante contingencias operativas:

```
🚨 PANEL DE CONTROL DE EMERGENCIA (EXCLUSIVO OWNER)

Este panel permite ejecutar acciones de contingencia inmediata sobre el servicio del bot y su configuración:

• Estado del Servicio: ACTIVO (Running)
• Modo Mantenimiento: DESACTIVADO (Operación normal)
• Respaldo de Configuración: Disponible

[🛑 Detener Servicio del Bot]
[⏸️ Activar Mantenimiento]  [🔄 Restaurar Configuración]
[❌ Cerrar Panel]
```

### Funciones Disponibles:
1. 🛑 **Detener Servicio del Bot:** Envía confirmación previa y ejecuta la parada segura de `tg-admin-bot.service`. El bot queda inactivo hasta que se reactive manualmente en consola o por SSH (`sudo systemctl start tg-admin-bot.service`).
2. ⏸️ **Modo Mantenimiento Interactivo:** Bloquea el acceso a todos los usuarios y grupos sin apagar el proceso, respondiendo amablemente que el sistema está en mantenimiento, mientras el Administrador conserva acceso total.
3. 🔄 **Restaurar Configuración Segura (*Rollback*):** Restaura de inmediato los archivos funcionales desde la copia de seguridad dorada (`config/.backup_golden`) y recarga los parámetros en memoria en caliente.

---

## 8. Protección y Aislamiento de Comandos en Grupos

Para evitar la fuga accidental de información técnica o de configuración en canales grupales públicos:

1. **Aislamiento Estricto de Comandos Administrativos:**
   * Comandos como `/estatus`, `/permisos`, `/emergencia`, `/limpiador`, `/bloqueo_comandos`, `/debug_*`, `/actualizar`, `/mensaje`, `/migrar_token` solo se ejecutan en **chat privado**.
2. **Auto-Eliminación en Grupos:**
   * Si el Administrador o cualquier usuario escribe un comando administrativo en un grupo permitido, el bot **elimina inmediatamente el mensaje** del grupo.
   * Envía un aviso temporal informativo que se auto-destruye en 8 segundos:
     > `⚠️ Comando Restringido: Este comando administrativo es exclusivo para el chat privado del Administrador y no puede ser usado en este grupo.`
   * Si fue el Administrador quien lo escribió por error, el bot le envía una notificación privada recordándole que puede ejecutarlo de forma segura en su chat privado.
3. **Menús de Bienvenida Diferenciados (`/start` / `/ayuda`):**
   * **Owner en Privado:** Despliega el Panel Maestro de Control.
   * **Usuario Autorizado en Privado:** Muestra un mensaje corto enfocado exclusivamente en el Asistente IA.
   * **Grupos Autorizados:** Muestra los comandos operativos de infraestructura (`/servicios`, `/sedes`, `/monitoreo`, `/internet`, `/analisis_red`, `/info`).

---

## 9. Diagnóstico de Almacenamiento y Limpiador del Sistema (`/limpiador`)

Módulo modular y asíncrono ([`monitor/system_cleaner.py`](file:///scripts/telegram-admin-bot/monitor/system_cleaner.py)) accesible mediante `/limpiador` o `estatus limpiar`:

* **Supervisión de Espacio en `/`:** Muestra espacio Total, Usado, Disponible y porcentaje de Inodos.
* **Detección de Directorios No Estándar:** Identifica carpetas creadas en `/` fuera del estándar FHS de Linux.
* **Limpieza Interactiva con Confirmación:**
  * Limpieza de cachés de paquetes APT (`apt clean`, `apt autoremove`).
  * Vaciado seguro de `/tmp/` respetando sockets en uso.
  * Truncado controlado de logs de auditoría antiguos (`*.log` > 30 días).
  * Purga de capturas forenses temporales (`audit/*.pcap`, `audit/*.html`).

---

## 10. Módulos de Monitoreo, Herramientas de Red y CLI Global (`estatus`)

### Herramienta de Línea de Comandos Global: `estatus`
Ubicación: `/scripts/telegram-admin-bot/estatus` (Enlazado globalmente en `/usr/local/bin/estatus`).

Permite ejecutar los motores de chequeo directamente desde la terminal o mediante CRON, despachando automáticamente los reportes con formato limpio a Telegram con soporte multi-proxy.

```bash
# Reporte de Servicios Corporativos y despacho a Telegram (Modo estándar CRON)
estatus servicios

# Reporte de Sedes y Subestaciones y despacho a Telegram
estatus sedes

# Reporte Completo (Servicios + Sedes en paralelo) y despacho a Telegram
estatus completo

# Diagnóstico Profundo de Red (Captura .pcap + Análisis TShark + Escaneo ARP)
estatus analisis_red

# Ejecutar chequeo en modo local sin enviar a Telegram (Solo salida en terminal)
estatus servicios --no-send

# Ejecutar en modo depuración (Muestra detalles técnicos y envía log adjunto)
estatus servicios --debug

# Restaurar estado seguro del bot (Rollback)
estatus rollback
```

### B. Sistema Integral de Resiliencia, Checkpoints y Rollback (`checkpoint` / `rollback`)
* **Comando `checkpoint [descripción]`:** Crea un punto de control con metadatos JSON y copia segura de configuración (`config.json`, `mensajes.conf`, `.env`, DRM), además de generar un tag local en Git.
* **Comando `rollback`:** Restaura inmediatamente el código y las configuraciones al último estado operativo conocido (`rollback --list`, `rollback --to <ID>`), validando la sintaxis y reiniciando el servicio de forma limpia.
* **Autorrecuperación en Arranque (`ExecStartPre`):** Si un apagón interrumpe la edición de un archivo, `rollback --pre-start-check` detecta la corrupción sintáctica, aplica un auto-rollback atómico y despacha una notificación de contingencia a Telegram.

### C. Endurecimiento y Anti-Forensia en el Análisis de Red (`/analisis_red`)
* **Límite de Paquetes en `tcpdump` (`-c 5000`):** La captura de tráfico en vivo incorpora una cota estricta de 5.000 paquetes máximos para evitar que una tormenta de broadcast o una red corporativa saturada llene el disco o agote la memoria RAM del servidor.
* **Destrucción Inmediata de Evidencia Cruda (`.pcap`):** Inmediatamente después de que `tshark` realiza la extracción de MACs, volumen TX/RX y estadísticas de red, el archivo `.pcap` temporal es eliminado atómicamente del disco (`unlink()`). Cero persistencia de payloads de tráfico crudo en el sistema.
* **Entrega Segura:** El bot despacha hacia Telegram únicamente los reportes estructurados consolidados en Markdown (`.md`) y HTML interactivo.

---

## 11. Inventario Completo de Comandos en Telegram

### 👑 Comandos Exclusivos del Administrador (Chat Privado):

| Comando | Alias | Descripción |
| :--- | :--- | :--- |
| `/emergencia` | `/panico`, `/contingencia` | Panel interactivo de emergencia (detener servicio, modo mantenimiento, rollback de config). |
| `/permisos` | `/autorizados`, `/whitelist` | Gestión interactiva de usuarios y grupos autorizados con revocación en caliente. |
| `/bloqueo_comandos` | `/bloquear_comandos` | Interruptor global para bloquear o permitir el uso de comandos a usuarios y grupos. |
| `/botstatus` | `/estatus`, `/status`, `/estado_bot` | Diagnóstico de conectividad, conmutación de proxies y accesos denegados. |
| `/limpiador` | `/limpieza`, `/cleaner` | Diagnóstico de almacenamiento, inodos y panel interactivo de limpieza de disco. |
| `/actualizar` | `/update`, `/git_update` | Comprobar y forzar sincronización con el repositorio oficial en GitHub (`git reset --hard`). |
| `/migrar_token` | `/migrartoken`, `/cambiar_token` | Asistente conversacional interactivo para actualizar y re-cifrar el Token de Telegram con DRM. |
| `/mensaje <texto>` | `/broadcast`, `/comunicado` | Envío de comunicados oficiales masivos a todos los usuarios y grupos autorizados. |
| `/debug_servicios` | `/servicios_debug` | Reporte exhaustivo de servicios (A a Z) con plantilla `MENSAJEDEBUGA` + `servicelog.txt`. |
| `/debug_sedes` | `/sedes_debug` | Reporte exhaustivo de sedes y equipos con plantilla `MENSAJEDEBUGB` + `servicelog.txt`. |
| `/debug_completo` | `/debug_monitoreo` | Reporte técnico integral exhaustivo (Servicios + Sedes) + `servicelog.txt`. |
| `/debug_monitor` | `/monitordebug` | Conmutador del modo depuración global para todos los reportes. |

### 👥 Comandos Disponibles para Usuarios y Grupos Autorizados:

| Comando | Alias | Descripción |
| :--- | :--- | :--- |
| `/start` | `/help`, `/ayuda` | Despliega el menú interactivo adaptado (IA en chat privado / Monitoreo en grupos). |
| `/servicios` | `/reporte_servicios` | Chequeo en tiempo real de los 19 Servicios Corporativos (A a Z). |
| `/sedes` | `/reporte_sedes`, `/sitios` | Chequeo en tiempo real de las 8 Sedes y Enlaces de Comunicación. |
| `/monitoreo` | `/reporte_completo` | Reporte unificado integral (Servicios Corporativos + Sedes CIAU). |
| `/internet` | `/proxy`, `/proxies` | Diagnóstico de latencia y estado de salidas a internet y proxies (oculta conexión directa a no-owners). |
| `/analisis_red` | `/red`, `/escaner_red` | Captura de tráfico en vivo (120s), análisis forense `tshark` y reporte HTML. |
| `/reset_ia` | `/borrar_chat` | Reinicia el contexto y la memoria de conversación con el asistente de IA Ollama. |
| `/info` | `/aviso`, `/legal` | Información institucional, aviso legal y políticas de seguridad del sistema. |

---

## 12. Servicios Systemd y Configuración del Sistema Operativo

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

### B. Servicio de Alertas de Arranque y Apagado (`boot-alert.service`)
Archivo: `/etc/systemd/system/boot-alert.service`

```ini
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
WorkingDirectory=/scripts/telegram-admin-bot
ExecStart=/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/boot_alert.py start
ExecStop=/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/monitor/boot_alert.py stop
TimeoutStartSec=180
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
```

### C. Hook de Acceso SSH en PAM
Archivo: `/etc/pam.d/sshd` (al final del archivo):

```text
# Alerta de conexion SSH a Telegram (Monitor Valle Seco)
session optional pam_exec.so seteuid /bin/bash /scripts/telegram-admin-bot/monitor/ssh_alert.sh
```

---

## 13. Guía Paso a Paso para Despliegue en un Nuevo Servidor

### Paso 1: Clonar el Repositorio
```bash
sudo mkdir -p /scripts
sudo chown -R $USER:$USER /scripts
cd /scripts
git clone https://github.com/britojq/tgbot-pyt-bashfull.git telegram-admin-bot
```

### Paso 2: Ejecutar el Instalador Maestro Automatizado
```bash
cd /scripts/telegram-admin-bot
sudo /bin/bash installer/install.sh
```
*El instalador instalará todos los paquetes APT, configurará capabilities de red (`setcap`), instalará Ollama, compilará el modelo corporativo `qwen-empresa`, creará el entorno virtual `venv`, enlazará `/usr/local/bin/estatus`, desplegará los servicios Systemd y configurará el hook PAM de SSH.*

### Paso 3: Iniciar el Servicio
```bash
sudo systemctl start tg-admin-bot.service
```

### Paso 4: Autorización Inicial de Hardware (First-Boot Handshake)
1. Al iniciar por primera vez, el bot entra en estado `FIRST_BOOT_PENDING` y envía un mensaje de emergencia con el **Serial Challenge** al Administrador vía Token Canario:
   ```text
   🔐 [ACTIVACIÓN REQUERIDA] Monitor Valle Seco
   Serial: AUTH-XXXX-XXXX-XXXX-XXXX
   ```
2. Responde al bot en Telegram con el Serial exacto (o escribe `/activar AUTH-XXXX-XXXX-XXXX-XXXX`) dentro de los 10 minutos.
3. El sistema derivará la clave de hardware, generará el anclaje seguro (`audit/.sys_anchor`) y quedará 100% **OPERACIONAL**.

### Paso 5: Programar Monitoreo Automático en CRON
Editar el crontab del sistema (`crontab -e`) y agregar:

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
