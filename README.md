# 🤖 Monitor Valle Seco (@IA_ValleSeco_bot) - Plataforma de Administración y Monitoreo con IA Local

> **Organización:** CORPOELEC - Centro de Operaciones Valle Seco / CENCARATIT  
> **Autor / Administrador:** José Brito (`@britojab` / `@britojq`)  
> **Sistema Operativo:** Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server  
> **Licencia:** GNU Affero General Public License v3.0 (AGPLv3)  

---

## 🌟 Visión General y Capacidades Principales

**Monitor Valle Seco** es una plataforma integral de administración de servidores, auditoría continua, telemetría de red y monitoreo para infraestructuras corporativas, operando en conjunto con un asistente conversacional potenciado por **Inteligencia Artificial local**.

### 🚀 Capacidades Destacadas:
1. 🧠 **IA Local (Ollama):** Procesamiento de lenguaje natural 100% privado y local, especializado en la topología de red, direccionamiento IP, enlaces troncales y servicios corporativos.
2. 🌐 **Telemetría y Diagnóstico de Red:** Chequeo concurrente de sedes y subestaciones (`/sedes`), servicios corporativos (`/servicios`), y análisis forense de paquetes `.pcap` con `tcpdump`, `tshark` y lista blanca MAC (`/analisis_red`).
3. ⏰ **CLI Global (`estatus`) y Compatibilidad CRON:** Herramienta de consola `/usr/local/bin/estatus` con protección por *Lockfile* atómico para reportes programados (7:30 AM y 4:00 PM).
4. 🚨 **Notificación de Eventos del Servidor:**
   - Detección de arranque limpio vs **falla eléctrica / apagado forzado** (`boot-alert.service`).
   - Notificación de **inicios de sesión SSH en tiempo real** mediante hook de PAM con geolocalización IP pública.
5. 🔀 **Conmutación Inteligente Directo / Proxies (Failover):** Despacho automático probando conexión directa primero y conmutando en cascada hacia proxies corporativos (`DEFAULTPROXYA`, `DEFAULTPROXYB`, `DEFAULTPROXYC`).
6. 🧹 **Mantenimiento y Limpieza del Sistema (`/limpiador`):** Diagnóstico de almacenamiento, inodos y depuración interactiva de disco.
7. 🚨 **Panel de Control de Emergencia (`/emergencia`):** Opciones de contingencia para detención del servicio, modo mantenimiento y restauración de configuración segura.
8. 📦 **Instalador Maestro (`installer/install.sh`):** Despliegue automatizado y desatendido de paquetes, permisos, modelos de IA, entornos virtuales y servicios systemd.

---

## 📂 Estructura del Proyecto (100% Autocontenido)

```text
/scripts/telegram-admin-bot/
├── bot.py                            # Demonio principal del bot de Telegram
├── estatus                           # Wrapper y herramienta CLI global del sistema
├── installer/
│   └── install.sh                    # Instalador maestro automatizado
├── ai/
│   ├── Modelfile.txt                 # Definición del modelo y system prompt corporativo
│   └── parametros.txt                # Parámetros térmicos y de contexto de IA
├── config/
│   ├── config.json                   # Configuración operativa del bot
│   ├── config.example.json           # Plantilla base de configuración
│   ├── bot.conf                      # Configuración de proxies y parámetros de red
│   ├── monitoreo.conf                # Definición de sedes, servicios, IPs y puertos
│   ├── mensajes.conf                 # Plantillas de mensajes y reportes
│   ├── commands.json                 # Textos y descripciones de comandos
│   ├── mac_whitelist.txt             # Lista blanca de direcciones MAC autorizadas
│   └── oui.txt                       # Base de datos de fabricantes de red
├── monitor/
│   ├── core_shield.py                # Núcleo de blindaje y validación del sistema
│   ├── system_updater.py             # Actualizador autónomo y sincronización Git (48h)
│   ├── boot_alert.py                 # Detección de arranque limpio vs falla eléctrica
│   ├── ssh_alert.py                  # Alertas de acceso SSH en tiempo real
│   ├── ssh_alert.sh                  # Wrapper de ejecución rápida para PAM
│   ├── network_analyzer.py           # Análisis de red (.pcap, tshark, arp-scan)
│   ├── monitor_servicios.py          # Chequeo de puertos TCP, HTTP, DNS, LDAP, SMB
│   ├── monitor_sedes.py              # Chequeo de conectividad ICMP de sedes
│   ├── monitor_engine.py             # Orquestador unificado con soporte CLI y CRON
│   ├── system_cleaner.py             # Diagnóstico de almacenamiento y limpieza interactiva
│   ├── checker_base.py               # Utilidades de red y formateador HTML
│   ├── config_parser.py              # Parser unificado de configuraciones
│   └── telegram_dispatcher.py        # Despachador con conmutación Directo/Proxies
├── audit/                            # Logs de auditoría, reportes y diagnósticos
├── docs/                             # Documentación técnica interna (.gitignore)
├── requirements.txt                  # Dependencias de Python
└── venv/                             # Entorno virtual aislado de Python
```

---

## 🛠️ Instalación Rápida con el Instalador Maestro

En un servidor base (Debian 12/13 o Ubuntu Server):

```bash
# 1. Clonar el repositorio
sudo mkdir -p /scripts && sudo chown -R $USER:$USER /scripts
cd /scripts
git clone https://github.com/britojq/tgbot-pyt-bashfull.git telegram-admin-bot
cd telegram-admin-bot

# 2. Ejecutar el Instalador Maestro
sudo /bin/bash installer/install.sh

# 3. Iniciar el servicio
sudo systemctl start tg-admin-bot.service
```

---

## 📋 Comandos Disponibles

### 👑 Comandos Exclusivos del Administrador:
| Comando | Descripción |
| :--- | :--- |
| `/emergencia` | Panel de control de emergencia (detener servicio, modo mantenimiento, restaurar config). |
| `/migrar_token` | Asistente interactivo para actualización y migración del token de Telegram. |
| `/botstatus` | Diagnóstico de latencia de conexión directa, proxies y estado de accesos. |
| `/permisos` | Panel interactivo para autorizar o revocar usuarios y grupos con un toque. |
| `/debug_monitor` | Activa o desactiva el Modo Depuración del sistema de monitoreo. |
| `/debug_servicios` | Reporte técnico exhaustivo de servicios corporativos + `servicelog.txt`. |
| `/debug_sedes` | Reporte técnico exhaustivo de sedes y subestaciones + `servicelog.txt`. |
| `/debug_completo` | Reporte técnico integral (Servicios + Sedes) + archivo de log adjunto. |
| `/bloqueo_comandos` | Pausa temporal del uso de comandos para usuarios y grupos. |
| `/limpiador` | Panel interactivo de mantenimiento y limpieza de almacenamiento en disco. |
| `/mensaje` | Difusión de comunicados oficiales (Broadcast) a usuarios y grupos autorizados. |
| `/actualizar` | Comprobación y forzado de sincronización con el repositorio oficial Git. |

### 👥 Comandos Funcionales (Administrador y Grupos Autorizados):
| Comando | Descripción |
| :--- | :--- |
| `/servicios` | Estatus operativo de servicios corporativos (OTRS, Intranet, DNS, LDAP, etc.). |
| `/sedes` | Conectividad de sedes físicas, routers y subestaciones de CORPOELEC. |
| `/monitoreo` | Ejecución concurrente del reporte completo de servicios y sedes. |
| `/analisis_red` | Análisis de tráfico LAN (.pcap), detección de tormentas broadcast y escaneo ARP. |
| `/reset_ia` | Reinicio de la memoria conversacional con el asistente de inteligencia artificial. |
| `/info` | Consulta de términos de uso, aviso legal y confidencialidad. |

---

## 💻 Herramienta CLI Global (`estatus`) & Tareas CRON

El comando global `/usr/local/bin/estatus` permite ejecutar el motor de monitoreo desde la terminal de Linux o mediante CRON:

```bash
# Ejecutar chequeo de Servicios Corporativos y despachar a Telegram
estatus servicios

# Ejecutar chequeo de Sedes y Subestaciones y despachar a Telegram
estatus sedes

# Ejecutar chequeo Completo (Servicios + Sedes en paralelo)
estatus completo

# Diagnóstico profundo de red LAN (.pcap + tshark)
estatus analisis_red

# Ejecutar localmente sin enviar a Telegram
estatus servicios --no-send
```

### ⏰ Configuración en CRON:
```cron
# Reporte de Servicios a las 7:30 AM (Ejecución única)
30 7 * * * /usr/local/bin/estatus servicios >/dev/null 2>&1

# Reporte de Servicios a las 4:00 PM (Ejecución única)
0 16 * * * /usr/local/bin/estatus servicios >/dev/null 2>&1
```

---

## 🔧 Gestión de Servicios Systemd

| Acción | Comando |
| :--- | :--- |
| **Ver estado del bot** | `sudo systemctl status tg-admin-bot.service` |
| **Ver logs en tiempo real** | `sudo journalctl -u tg-admin-bot.service -f` |
| **Reiniciar servicio** | `sudo systemctl restart tg-admin-bot.service` |
| **Ver estado de alertas de arranque** | `sudo systemctl status boot-alert.service` |

---

*Desarrollado para la Gerencia de ATIT Región Central • División de ATIT Carabobo • CORPOELEC.*
