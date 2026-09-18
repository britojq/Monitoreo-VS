# 📘 ESPECIFICACIONES TÉCNICAS DEL SISTEMA DE MONITOREO Y ADMINISTRACIÓN VALLE SECO

---

## 1. 🌟 VISIÓN GENERAL Y PROPÓSITO DEL SISTEMA

La **Plataforma Integral de Monitoreo y Administración Valle Seco** es una solución de ingeniería de software diseñada para la supervisión continua, telemetría en tiempo real, gestión remota y administración operativa de infraestructura crítica de telecomunicaciones, servidores y servicios corporativos en la sede Valle Seco y sedes comerciales/técnicas asociadas.

### Objetivos Principales:
1. **Supervisión de Alta Disponibilidad:** Evaluar ininterrumpidamente la operatividad de servicios corporativos, sedes remotas, proxies de salida a Internet y equipos de red interna.
2. **Paridad de Información y Transparencia:** Proporcionar visibilidad unificada tanto a través del portal web (Tablero de Control estilo Obsidian Cyberpunk) como mediante el bot interactivo de Telegram.
3. **Soporte y Gestión Remota en el Navegador:** Habilitar acceso directo de diagnóstico y soporte hacia estaciones de trabajo, taquillas de recaudación (CIAU), routers y switches mediante clientes integrados de **VNC, SSH y Telnet** sin requerir software cliente adicional.
4. **Resiliencia Operativa y GitOps:** Despliegue seguro con respaldos atómicos de base de datos, Circuit Breaker, auto-rollback y centinela de auto-curación de 5 capas ante cambios en repositorios.
5. **Arquitectura Distribuida en Clúster:** Separación de roles en nodo **Master** (`10.20.23.252`) y nodo **Slave** (`10.20.23.221`) para evitar saturación en firewalls perimetrales (pfSense) y garantizar redundancia y paridad absoluta de datos y telemetría.

---

## 2. 🏛️ ARQUITECTURA TECNOLÓGICA Y STACK DEL SISTEMA

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                   USUARIO / OPERADOR                                   │
│                        (Navegador Web / Cliente Telegram)                              │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
        ┌───────────────────────────────────┴───────────────────────────────────┐
        ▼                                                                       ▼
┌─────────────────────────────────────────────────┐   ┌─────────────────────────────────────────────────┐
│              PORTAL WEB CORPORATIVO             │   │            BOT DE ADMINISTRACIÓN TELEGRAM       │
│  • Framework: Laravel 11.x (PHP 8.2+)           │   │  • Motor: Python 3.11+ Asyncio / python-telegram│
│  • Web Server: Apache 2.4 / mod_php             │   │  • Failover de Proxies (Directo / Squid / pfSense)
│  • UI: TailwindCSS, Alpine.js, Chart.js 4.x     │   │  • Motor Local de IA (Diagnóstico y Soporte)    │
│  • Terminales Web: xterm.js (SSH/Telnet), noVNC │   │  • Comandos de Control (/estatus, /servicios)   │
│  • Clúster API & Replicación Master/Slave       │   │  • Capturas de Pantalla Headless (Playwright)   │
└────────────────────────┬────────────────────────┘   └────────────────────────┬────────────────────────┘
                         │                                                     │
                         └──────────────────────────┬──────────────────────────┘
                                                    ▼
┌───────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   MOTOR DE MONITOREO Y TELEMETRÍA                             │
│  • monitor_web_sync.py: Recolección asíncrona concurrente (< 7s)                              │
│  • monitor_engine.py: Evaluador clásico de fondo para Telegram                                │
│  • cron_runner.py: Planificador de ejecución periódica                                        │
│  • Memoria Compartida (/dev/shm): Caché de proxies ultraligera con candado anti-colisión       │
│  • Sentinel Guard & Thermal Guard: Protección contra sobrecalentamiento y alertas PAM / SSH   │
└───────────────────────────────────────────────┬───────────────────────────────────────────────┘
                                                ▼
┌───────────────────────────────────────────────────────────────────────────────────────────────┐
│                                       CAPA DE PERSISTENCIA                                    │
│  • Base de Datos: MariaDB 10.11+ (Base de datos: monitoreo_vs)                                │
│  • Snapshot Estático: storage/app/public/monitoring_snapshot.json (Caché desacoplada web)     │
│  • Archivos de Configuración Física: config/monitoreo.conf, config/bot.conf, config/config.json│
└───────────────────────────────────────────────────────────────────────────────────────────────┘
```

### Componentes de Software:
* **Backend Web:** Laravel 11 (PHP 8.2+) bajo Apache 2.4 en entorno Debian GNU/Linux (amd64).
* **Servicios Asíncronos:** Python 3.11+ con `asyncio`, `httpx` (soporte HTTP/2 y TLS legacy), `pymysql`, `urllib.parse`.
* **Frontend Web:** TailwindCSS (paleta Obsidian Dark: `#0B0F19`, `#111827`, acentos cian `#22D3EE`, esmeralda `#34D399`, rojo `#EF4444`), Alpine.js, Chart.js, xterm.js con FitAddon y noVNC.
* **Bridge de Sockets:** `websockify` para enrutamiento seguro WebSocket a sockets TCP (VNC, SSH y Telnet).
* **Motor Headless para Reportes:** Playwright / Chromium headless para renderizado y captura fotográfica del dashboard web hacia Telegram.
* **Base de Datos:** MariaDB 10.11+ con tablas transaccionales InnoDB y pool de conexiones asíncrono.
* **Inteligencia Artificial:** Asistente virtual corporativo integrado en portal web y Telegram impulsado por un **Motor Local de IA**, garantizando procesamiento local de lenguaje natural y telemetría sin enviar información fuera de la red física institucional.

---

## 3. 🔍 MECANISMOS DE CHEQUEO, VERIFICACIÓN Y VALIDACIÓN

El motor de telemetría (`monitor/monitor_web_sync.py` y `monitor/monitor_engine.py`) evalúa 7 categorías de entidades de red mediante protocolos de transporte especializados y optimizados:

### 3.1. Servicios Web y Aplicativos Corporativos (Tipo `WEB`)
* **Protocolo:** HTTP / HTTPS con soporte extendido para infraestructuras legadas.
* **Mecanismo:**
  * Implementado con `httpx.AsyncClient` en modo asíncrono concurrente.
  * **Contexto SSL Permisivo (`SSL_PERMISSIVE_CTX`):** Soporta protocolos TLS heredados (desde TLS 1.0 hasta TLS 1.3), certificados corporativos autofirmados y suites de cifrado antiguas (`DEFAULT@SECLEVEL=0`) sin interrumpir la validación por advertencias de certificados.
  * **Manejo de Redirecciones:** `follow_redirects=True` para seguir automáticamente saltos 301/302 hacia portales SSO o rutas de autenticación.
  * **Criterio de Aceptación (UP):** Códigos HTTP `200`, `301`, `302`, `304`, `307`, `308` y `401` (Unauthorized se considera UP a nivel de servicio web, pues certifica que el servidor web, el proxy inverso y el runtime de backend responden activamente).
  * **Tolerancia WAN:** Timeout dinámico de `7.0 segundos` para compensar enlaces WAN geográficamente distribuidos a nivel nacional.
  * **Medición de Latencia:** Precisión de décimas de milisegundo mediante `time.perf_counter()`.

### 3.2. Directorio Activo y Autenticación Centralizada (Tipo `LDAP`)
* **Protocolo:** TCP Handshake hacia puerto LDAP (`389`) o LDAPS (`636`).
* **Mecanismo:**
  * En telemetría de monitoreo: Apertura de socket TCP no bloqueante con `asyncio.open_connection(host, port, timeout=4.0)`. Si el socket completa el handshake SYN/ACK de tres vías, el servicio se certifica como `ACTIVO`.
  * En autenticación de usuarios del portal: Conexión nativa PHP-LDAP (`ldap_connect`, `ldap_bind`) con autenticación directa de credenciales de usuario, búsqueda en árbol (`ou=Users`) y aprovisionamiento Just-In-Time (JIT) en MariaDB.

### 3.3. Servidores de Correo Electrónico (Tipo `SMTP`)
* **Protocolo:** Handshake TCP a puerto `25` o `587`.
* **Mecanismo:** Verificación de transporte y escucha del socket TCP del Message Transfer Agent (MTA Zimbra / Relay Corporativo).

### 3.4. Servidores DNS Corporativos (Tipo `DNS`)
* **Protocolo:** Socket TCP/UDP a puerto `53` en servidores primario (`100.1.1.16`) y secundario (`10.100.94.230`).
* **Mecanismo:** Comprobación de escucha activa en el puerto de resolución de nombres BIND9.

### 3.5. Servidores de Impresión (Tipo `CUPS`)
* **Protocolo:** Socket TCP a puerto `631` (Internet Printing Protocol / CUPS).
* **Mecanismo:** Validación de disponibilidad de colas de impresión centralizadas.

### 3.6. Proxies Corporativos de Salida a Internet (Tipo `PROXY`)
* **Infraestructura:** Proxies Squid / Dansguardian y firewalls perimetrales pfSense (`10.20.0.89:8080`, `10.20.23.65:8080`, `10.20.0.119:8080`).
* **Mecanismo:**
  * Conexión HTTP vía proxy configurando URL de transporte con credenciales codificadas en formato RFC: `http://{user_encoded}:{pwd_encoded}@{proxy_host}:{port}` (utilizando `urllib.parse.quote` para caracteres especiales).
  * **Objetivo de Prueba:** Petición HTTP GET hacia `https://core.telegram.org/bots`.
  * **Resolución Automática de Credenciales:** Si la entidad no tiene credenciales en la base de datos o contiene valores por defecto (`USUARIO:CLAVE`), el motor resuelve automáticamente las claves legítimas desde la tabla `monitored_proxies` o el archivo protegido `config/bot.conf`.
  * **Candado In-Flight y Memoria Compartida (`/dev/shm`):**
    * Utiliza `ProxyInflightGuard` para evitar que múltiples procesos escaneen concurrentemente el mismo proxy contra el firewall perimetral.
    * Los resultados exitosos se almacenan en memoria RAM compartida (`/dev/shm/monitoreo_proxy_cache`) con TTL de 60 segundos, protegiendo las credenciales contra bloqueos por fuerza bruta en pfSense.

### 3.7. Sedes Remotas y Dispositivos de Red Local (Tipo `PING` / ICMP)
* **Protocolo:** ICMP Echo Request / Reply.
* **Mecanismo:**
  * Subproceso asíncrono no bloqueante: `ping -c 1 -W 2 <ip_destino>`.
  * Extracción regex de latencia RTT: `r"min/avg/max/(?:mdev|stddev)\s*=\s*[\d.]+/([\d.]+)/"`.
  * Si el paquete retorna antes de 2 segundos, se computa la latencia y se valida como `ACTIVO`. Si hay 100% packet loss o timeout, se clasifica como `APAGADO`.

---

## 4. 📊 PRESENTACIÓN Y FUNCIONALIDADES DEL DASHBOARD

El portal web expone la información mediante un diseño **Obsidian Dark Cyberpunk**, optimizado ergonómicamente para pantallas de monitoreo continuo 24/7 en centros de operaciones (NOC).

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│  [TOPBAR] HUD Estado General: OPERACIONAL/DEGRADADO/CRÍTICO | Nodo: MASTER/SLAVE | Auth│
├──────────────────────────┬──────────────────────────┬──────────────────────────────────┤
│   COLUMNA 1 (32%)        │   COLUMNA 2 (34%)        │   COLUMNA 3 (34%)                │
│   SERVICIOS ACTIVOS      │   SEDES Y DISPOSITIVOS   │   INCIDENCIAS E HISTÓRICO 24H    │
│                          │                          │                                  │
│ • Buscador en vivo       │ • Sedes Regionales (5)   │ • Tarjetas de Servicios Caídos   │
│ • LEDs Verde Esmeralda   │   - Valle Seco           │ • Gráficas Chart.js 24h          │
│ • Badges de Protocolo    │   - Consolidado          │   - Curva de latencia continua   │
│ • Latencias en ms        │   - Paseo Mariño         │   - Área de caída roja (#EF4444) │
│ • Modal de detalles      │   - Morón                │ • % Disponibilidad (Uptime)      │
│                          │   - Transmisión          │ • Fallback de últimos 50 puntos  │
│                          │ • Dispositivos Locales   │ • Terminal de Diagnóstico        │
│                          │   (Routers, SW Cisco)    │                                  │
├──────────────────────────┴──────────────────────────┴──────────────────────────────────┤
│  [WIDGETS INFERIORES] Proxies Online (4/4) | Dispositivos Valle Seco (15/24)           │
│  [ASISTENTE IA FLOTANTE] Chat interactivo de soporte y diagnóstico en tiempo real      │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### 4.1. Características de la Interfaz Web:
* **Arquitectura de 3 Columnas Proporcionales (32% / 34% / 34%):**
  * **Columna 1 - Servicios Operativos:** Vista compacta de alta densidad con actualización visual en tiempo real.
  * **Columna 2 - Topología Regional y LAN:** Jerarquía colapsable con estado del gateway principal de cada sede y dispositivos en cascada.
  * **Columna 3 - Gestión de Caídas y Diagnóstico:** Enfoque prioritario en los incidentes activos, métricas de tiempo fuera de servicio y visualización del histórico.
* **Mapeo de Datos Desacoplado:** El portal web lee los datos del archivo estático `monitoring_snapshot.json` y los complementa con las 4 tablas históricas de MariaDB (`service_check_histories`, `site_check_histories`, `proxy_check_histories`, `network_device_check_histories`).
* **Gráficos de Uptime y Línea de Indisponibilidad:**
  * Graficado continuo de 24 horas con degradados dinámicos en Chart.js.
  * Si un servicio está caído (`is_up = 0`), la curva refleja la indisponibilidad en rojo espeso (`#EF4444`).
  * **Fallback Automático de Datos:** Si una ventana de tiempo cuenta con menos de 2 registros por mantenimientos o reinicios del servidor, la consulta rescata automáticamente los últimos 50 registros existentes en MariaDB para evitar lienzos vacíos.
* **Temporizador de Redirección Automática en `/login`:**
  * Cuenta regresiva de **15 segundos** con barra de progreso animada al cerrar sesión o permanecer inactivo en la vista de acceso.
  * **Detección de Actividad:** Si el operador escribe en el formulario (`#login` o `#password`), el contador se pausa de inmediato. Incluye botón manual de pausa y opción de retorno directo al monitoreo público.

---

## 5. 🛠️ FUNCIONES AVANZADAS DE SOPORTE Y GESTIÓN REMOTA

El sistema incorpora herramientas integradas en el navegador para asistencia técnica directa sin depender de clientes VPN pesados ni herramientas de terceros.

### 5.1. Soporte VNC Web Viewer (Visualizador Remoto en Navegador)
* **Objetivo:** Tomar control gráfico de estaciones de trabajo, taquillas de recaudación CIAU (`HP ProDesk 400 G4`) y servidores con entorno gráfico.
* **Componentes:**
  * **Frontend:** Cliente HTML5 `noVNC` embebido en vista Blade (`viewer.blade.php`).
  * **Bridge WebSocket:** Demonio `websockify` ejecutándose en el servidor que convierte tráfico WebSocket proveniente del navegador en paquetes TCP estándar para el servidor VNC (puerto `5900`).
  * **Mapeo Seguro de Tokens (`/etc/websockify/tokens.cfg`):**
    * Cada equipo tiene asignado un token alfanumérico o identificador único asociado a su dirección IP y puerto:
      ```
      device_10_20_106_231: 10.20.106.231:5900
      device_10_20_106_242: 10.20.106.242:5900
      ```
    * El navegador nunca interactúa directamente con la IP interna del equipo, preservando el aislamiento de red.

### 5.2. Soporte SSH Web Terminal (Terminal de Línea de Comandos)
* **Objetivo:** Administración de routers Cisco, switches y servidores Linux (ej. Dell PowerEdge R340).
* **Componentes:**
  * **Frontend:** Emulador de terminal `xterm.js` con soporte para secuencias de escape ANSI/VT100, redimensionamiento dinámico (`FitAddon`) y paleta Cyberpunk Obsidian.
  * **Backend:** Puente de WebSocket bidireccional conectado a una pseudo-terminal (PTY) SSH en el puerto `22`.
  * **Auditoría de Sesión:** Apertura, cierre y comandos ejecutados se auditan en la base de datos para trazabilidad de seguridad.

### 5.3. Soporte Telnet Web Terminal
* **Objetivo:** Acceso a la consola de administración de switches Catalyst 2960 y equipos de red legados que no soportan SSHv2.
* **Componentes:**
  * Puente WebSocket a socket TCP Telnet (puerto `23`) con negociación de opciones RFC 854 y emulación de terminal en `xterm.js`.

### 5.4. Módulo de Términos y Condiciones Legales (`/admin/terms`)
* **Mecanismo:** Modal bloqueante desplegado a todo usuario tras el primer inicio de sesión.
* **Trazabilidad:** Persistencia en MariaDB (`terms_accepted_at`, `terms_accepted_ip`) con registro en la tabla de auditoría (`audit_logs`).

### 5.5. Seguridad Perimetral, Baneo de IPs y Throttling (`CheckBannedIp`)
* **Mecanismo:** Middleware perimetral que inspecciona las IPs entrantes contra la tabla `banned_ips`.
* **Protección Anti Fuerza Bruta:** Bloqueo automático de IPs tras múltiples intentos fallidos de autenticación local o LDAP, con registro de razón, fecha y expiración configurable.

### 5.6. Autenticación Híbrida y Aprovisionamiento JIT (`LdapAuthService`)
* **Mecanismo:** Flujo dual de validación:
  1. Primero intenta autenticación contra cuentas locales administradas en MariaDB (`users`).
  2. Si no es local, autentica vía protocolo LDAP contra el Directorio Activo corporativo.
  3. Tras validar credenciales LDAP, aprovisiona automáticamente la cuenta en MariaDB (Just-In-Time) asignando el rol correspondiente (`admin`, `operador`, `observador`).

### 5.7. Registro Integral de Auditoría y Trazabilidad de Cambios (`AuditService`)
* **Mecanismo:** Cada acción administrativa (creación, edición, borrado de servicios, proxies o dispositivos, inicios de sesión, cambios de contraseñas, apertura de terminales VNC/SSH) genera un evento inmutable en `audit_logs`.
* **Seguimiento de Cambios (Diffs):** Guarda instantáneas en formato JSON del estado anterior (`old_values`) y nuevo (`new_values`), permitiendo comparar visualmente las modificaciones desde el panel de administración.

---

## 6. 🤖 BOT DE TELEGRAM: ASISTENTE INTERACTIVO Y FAILOVER

El demonio principal del bot (`bot.py`) provee un canal seguro de alertas, diagnóstico y ejecución de comandos remotos.

### 6.1. Failover Inteligente de Conexión a Telegram
El bot evalúa continuamente la salida hacia `api.telegram.org` y conmuta dinámicamente entre rutas sin interrupción de servicio:
1. **Ruta 1 (Prioritaria):** Conexión Directa a Internet.
2. **Ruta 2:** Proxy Squid / Dansguardian Carabobo (`10.20.0.89`).
3. **Ruta 3:** Proxy Squid / Dansguardian Valle Seco (`10.20.23.65`).
4. **Ruta 4:** Proxy pfSense Carabobo (`10.20.0.119`).

### 6.2. Motor Local de IA (Asistente Virtual Corporativo)
* Procesa consultas en lenguaje natural formuladas por los administradores autorizados.
* Consume métricas en tiempo real de la base de datos local para responder dudas operativas de infraestructura ("¿Cómo está la sede Morón?", "¿Qué servicios están caídos?") sin enviar datos confidenciales fuera de la red física local.

### 6.3. Despacho Gráfico con Navegador Headless (`web_screenshot.py`)
* Motor de captura automatizada basado en Playwright / Chromium headless.
* Permite renderizar el dashboard web exacto en segundo plano y enviarlo como imagen de alta resolución directamente al Telegram privado del Administrador en despachos programados o bajo demanda.

### 6.4. Alertas de Seguridad en Tiempo Real (Módulos PAM / SSH)
* **`monitor/ssh_alert.py`:** Integrado con el subsistema PAM de Linux. Cada inicio o cierre de sesión SSH en el servidor despacha una alerta inmediata con IP de origen, usuario y timestamp al Telegram privado del Administrador.
* **`monitor/boot_alert.py`:** Detecta reinicios del host físico y notifica la recuperación del sistema.

### 6.5. Catálogo de Comandos del Bot:
| Comando | Nivel | Descripción |
| :--- | :---: | :--- |
| `/estatus` | Operador/Admin | Informe completo de diagnóstico de red, proxies y control de accesos. |
| `/servicios` | Operador/Admin | Reporte de operatividad y latencias de los 18 servicios monitoreados. |
| `/sedes` | Operador/Admin | Estado de conectividad de las 5 sedes regionales y routers de borde. |
| `/dispositivos`| Operador/Admin | Estado de los 24 dispositivos de la sede local Valle Seco. |
| `/actualizar` | Owner | Dispara el ciclo de despliegue atómico con GitOps y Circuit Breaker. |
| `/rollback` | Owner | Revierte la versión de software y base de datos al snapshot previo. |
| `/desbloquear_update`| Owner | Desactiva el Circuit Breaker (`.update_lock`) tras resolver una contingencia. |
| `/recursos` | Admin | Diagnóstico de memoria RAM, CPU, disco y procesos del host físico. |
| `/temperatura` | Admin | Métricas de sensores térmicos del procesador y placa base. |

---

## 7. 🛡️ SISTEMA DE INMUNIDAD, GITOPS Y RESILIENCIA

Para asegurar que las actualizaciones de software desde Git no rompan la configuración operativa ni introduzcan datos ficticios de repositorios públicos, el ecosistema opera con una defensa de 5 capas:

```
[GIT PULL / MERGE]
       │
       ▼
[CAPA 1: Git Hook Nativo (.git/hooks/post-merge y post-checkout)]
       │ (Dispara automáticamente el centinela tras cualquier operación git)
       ▼
[CAPA 2: Centinela de Auto-Curación (monitor/self_heal_environment.py)]
       ├─▶ Ejecuta auto-curación atómica en MariaDB (restaura URLs legítimas)
       ├─▶ Sincroniza credenciales de proxies legítimos desde monitored_proxies
       ├─▶ Purga memoria compartida (/dev/shm/monitoreo_proxy_cache)
       ├─▶ Sincroniza web_portal hacia /var/www/monitoreo y limpia cachés Laravel
       └─▶ Lanza escaneo web forzado en segundo plano
       │
       ▼
[CAPA 3: Pipeline de Despliegue con Circuit Breaker (deploy_pipeline.sh)]
       ├─▶ Respaldo atómico de MariaDB (mysqldump comprimido .sql.gz)
       ├─▶ Smoke Test de salud web y base de datos
       └─▶ Auto-Rollback atómico si el Smoke Test detecta anomalías
       │
       ▼
[CAPA 4: Auto-Curación Preventiva Pre-Escaneo (monitor_web_sync.py)]
       │ (En cada ciclo de escaneo corrige la base de datos en 1ms antes de evaluar)
       ▼
[CAPA 5: Seeders Idempotentes Auto-Defensivos (CleanMonitoringSeeder.php)]
       ├─▶ Lee y prioriza config/monitoreo.conf
       ├─▶ Des-sanitiza dinámicamente en memoria cualquier string 'empresa'
       ├─▶ Preserva URLs activas existentes en MariaDB
       └─▶ Hereda automáticamente credenciales de proxies legítimos
```

### 7.1. Componentes del Pipeline de Resiliencia:
* **`monitor/database_backup.py`:** Genera snapshots comprimidos `.sql.gz` de MariaDB con rotación automática (conservando los últimos 15 respaldos).
* **`monitor/system_updater.py`:** Orquestador de actualizaciones con evaluación de salida a GitHub (directa vs proxies corporativos), ejecución de migraciones y Circuit Breaker.
* **`deploy_pipeline.sh`:** Interfaz CLI unificada para administración de ciclo de vida:
  * `update`: Actualización atómica con respaldo previo y smoke tests.
  * `rollback`: Restauración instantánea de base de datos y código fuente.
  * `smoke-test`: Verificación integral de integridad de servicios y base de datos.
  * `backup-db`: Respaldo manual en frío de MariaDB.
  * `status`: Estado del servicio y bloqueo del Circuit Breaker.

---

## 8. 🌐 ARQUITECTURA EN CLÚSTER Y REPLICACIÓN MASTER / SLAVE

Para evitar saturación de conexiones concurrentes en los firewalls perimetrales (pfSense) y asegurar redundancia de visualización, la plataforma opera en topología de dos nodos:

* **Nodo Master (`10.20.23.252`):**
  * Servidor de Producción principal.
  * Ejecuta el escaneo activo de red física, proxies y servicios corporativos.
  * Escribe los resultados en MariaDB y genera el snapshot `monitoring_snapshots`.
  * Expone el endpoint API seguro de sincronización `/api/cluster/push-snapshot`.
* **Nodo Slave (`10.20.23.221`):**
  * Servidor de Desarrollo y Contingencia.
  * No bombardea la red externa; consume la telemetría enviada por el Master mediante un token de autenticación cifrado (`ValidateClusterToken`).
  * **Replicación Completa de Telemetría:** El esclavo puebla en tiempo real no sólo el snapshot global, sino las 4 tablas de telemetría e histórico en su base de datos local MariaDB:
    1. `service_check_histories`
    2. `site_check_histories`
    3. `proxy_check_histories`
    4. `network_device_check_histories`
  * Garantiza que si el Master requiere mantenimiento, el Slave cuenta con el histórico idéntico para continuar operando sin interrupción.

---

## 9. 🌡️ MONITOREO TÉRMICO Y PROTECCIÓN DE HARDWARE

* **Servicio:** `tg-thermal-guard.service` (`monitor/thermal_guard.py`).
* **Funcionamiento:**
  * Lee continuamente los sensores térmicos del procesador a través de interfaces del kernel Linux (`/sys/class/thermal/` y módulos `coretemp` / `sensors`).
  * **Umbral de Advertencia (65°C):** Notifica al administrador vía Telegram para alertar sobre posible fallo de climatización en el cuarto de servidores.
  * **Umbral Crítico de Emergencia (75°C):** Ejecuta una parada ordenada de servicios de alto consumo para salvaguardar la integridad física del procesador y la placa base.

---

## 10. 🗄️ ESTRUCTURA Y MODELO DE DATOS EN MARIADB

La base de datos `monitoreo_vs` estructura la información en las siguientes tablas clave:

| Tabla | Propósito |
| :--- | :--- |
| `monitored_services` | Catálogo de los 18 servicios corporativos y regionales (URLs, IPs, puertos, protocolos). |
| `monitored_sites` | Las 5 sedes corporativas monitoreadas con sus gateways de comunicación. |
| `monitored_site_devices` | Dispositivos secundarios agrupados por sede geográfica. |
| `monitored_network_devices` | Los 24 dispositivos de infraestructura de red de la sede principal Valle Seco. |
| `monitored_proxies` | Los 4 proxies corporativos con credenciales y URLs de prueba. |
| `monitoring_snapshots` | Instantáneas globales del clúster con payload JSON de cada escaneo. |
| `service_check_histories` | Telemetría e histórico de disponibilidad, latencia y código HTTP por servicio. |
| `site_check_histories` | Histórico de latencia y estado por sede regional. |
| `proxy_check_histories` | Histórico de latencia y respuesta de cada proxy corporativo. |
| `network_device_check_histories`| Histórico de latencia y disponibilidad de dispositivos locales. |
| `audit_logs` | Auditoría de accesos (locales y LDAP), intentos fallidos, cambios y soporte remoto. |
| `banned_ips` | Registro de direcciones IP bloqueadas por actividad maliciosa o fuerza bruta. |
| `users` | Cuentas locales y operadores auto-registrados por LDAP corporativo. |
| `bot_commands` | Catálogo configurable de comandos disponibles en el bot de Telegram. |
| `bot_message_templates`| Plantillas de mensajes y notificaciones enviadas por Telegram. |

---

## 11. 📋 CONCLUSIÓN Y GUÍA PARA FUTURAS MEJORAS

Este documento recopila la totalidad del funcionamiento operativo de la plataforma. Para cualquier futura mejora o refactorización, se debe considerar:
1. **Preservación de la Inmunidad Git:** Toda adición al código debe respetar los mecanismos de auto-curación y no depender de cadenas fijas propensas a sanitización en repositorios públicos.
2. **Compatibilidad con Clúster:** Los cambios en recolección de métricas deben mantener paridad absoluta entre el nodo Master (`10.20.23.252`) y el nodo Slave (`10.20.23.221`).
3. **Optimización de Timeouts WAN:** Mantener los tiempos de espera ajustados a la realidad de los enlaces WAN para evitar falsos positivos de caídas.
4. **Respeto a las Reglas de Oro:** Ningún despacho de prueba o seguridad al grupo corporativo, estricta neutralidad sin exponer el motor de IA subyacente, y confirmación previa del usuario para cualquier acción en producción.
