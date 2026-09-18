# PROMPT MAESTRO PARA IMPLEMENTACIÓN DE MEJORAS
## Plataforma Integral de Monitoreo y Administración Valle Seco

**Versión:** 2.0
**Fecha:** 2024
**Objetivo:** Guía completa para que una IA implemente todas las mejoras del sistema respetando la arquitectura existente.

---

# 🎯 INSTRUCCIONES GENERALES PARA LA IA

## Rol y Expectativas
Actúas como **Ingeniero de Software Senior especializado en DevOps, Observabilidad y Redes**. Debes generar **código completo, funcional y listo para producción** (NO placeholders, NO `// TODO`, NO funciones vacías).

## Reglas No Negociables

1. **Lee primero `especificaciones-tecnicas.md`** — es la fuente de verdad de la arquitectura actual.
2. **Respeta los 5 Principios Obligatorios:**
   - ✅ **Inmunidad Git:** Toda tabla nueva debe protegerse en `self_heal_environment.py`
   - ✅ **Paridad Master/Slave:** Toda tabla nueva debe replicarse en `/api/cluster/push-snapshot`
   - ✅ **Timeouts WAN:** HTTP 7s, SNMP 5s, SSH 10s, ICMP 2s
   - ✅ **Memoria Compartida:** Usar `/dev/shm` para cachés volátiles
   - ✅ **Idempotencia:** Seeders y migraciones seguros ante re-ejecuciones
3. **Respeta el stack existente:** Laravel 11, PHP 8.2, Python 3.11, MariaDB 10.11, TailwindCSS, Alpine.js, Chart.js 4.x, python-telegram-bot, pysnmp, netmiko, scapy, playwright.
4. **Cifra credenciales** con `encrypt()` de Laravel (SNMP community, SSH passwords, API keys). NUNCA en texto plano, NUNCA en logs.
5. **Auditoría obligatoria:** Toda acción administrativa nueva debe registrarse en `audit_logs` con `old_values` y `new_values` en JSON.
6. **UI:** Paleta Obsidian Dark (`#0B0F19`, `#111827`, acentos cian `#22D3EE`, esmeralda `#34D399`, rojo `#EF4444`).
7. **Comandos del bot:** Todo comando nuevo debe registrarse en la tabla `bot_commands` con nivel de acceso (`operador`, `admin`, `owner`).
8. **NO reinventar** lo que ya existe. Reutiliza `monitor_web_sync.py`, `monitor_engine.py`, `cron_runner.py`, `self_heal_environment.py`, `LdapAuthService`, `AuditService`.

## Orden de Ejecución Obligatorio

Implementa las fases **en orden estricto** porque tienen dependencias:

```
FASE 1 (Discovery) → FASE 2 (SNMP) → FASE 3 (SSL) → FASE 4 (Alertas)
→ FASE 5 (WAN+Configs) → FASE 6 (Traps+NetFlow) → FASE 7 (Topología+IA)
→ FASE 8 (Integración Final)
```

## Criterio de "Done" por Fase

Cada fase se considera terminada SOLO cuando:
- ✅ Migraciones ejecutan sin error (`php artisan migrate`)
- ✅ Seeders ejecutan idempotentemente (`php artisan db:seed --class=X`)
- ✅ Modelos Eloquent con relaciones correctas
- ✅ Scripts Python ejecutan sin excepción con datos reales
- ✅ Comandos del bot responden correctamente
- ✅ Dashboard renderiza sin errores JS en consola
- ✅ Replicación Master→Slave funciona (verificable en ambas BD)
- ✅ `self_heal_environment.py` protege las nuevas tablas
- ✅ Documentación actualizada (`README.md`, `docs/bot_commands.md`)

---

# 📘 FASE 1: AUTO-DISCOVERY DE EQUIPOS EN RED

## Objetivo
Detectar automáticamente dispositivos nuevos en cada subred sin intervención manual, con clasificación asistida y anti-rogue.

## Diseño de Base de Datos

### Tabla `discovery_subnets`

```sql
CREATE TABLE discovery_subnets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subnet VARCHAR(50) UNIQUE NOT NULL COMMENT 'Formato CIDR',
  site_id BIGINT UNSIGNED NULL,
  scan_method ENUM('arp_sweep','nmap','both') DEFAULT 'arp_sweep',
  scan_interval_minutes INT UNSIGNED DEFAULT 15,
  scan_window_start TIME NULL COMMENT 'Ej: 02:00 (ventana nocturna)',
  scan_window_end TIME NULL COMMENT 'Ej: 05:00',
  rate_limit_pps INT UNSIGNED DEFAULT 100 COMMENT 'Paquetes por segundo',
  nmap_options VARCHAR(500) NULL,
  is_active TINYINT(1) DEFAULT 1,
  last_scan_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES monitored_sites(id) ON DELETE SET NULL,
  INDEX idx_active (is_active),
  INDEX idx_site (site_id)
) ENGINE=InnoDB;
```

### Tabla `discovery_scans`

```sql
CREATE TABLE discovery_scans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scan_type ENUM('arp_sweep','nmap_scan','full_scan','passive_snmp') NOT NULL,
  subnet VARCHAR(50) NOT NULL,
  site_id BIGINT UNSIGNED NULL,
  started_at TIMESTAMP NOT NULL,
  finished_at TIMESTAMP NULL,
  duration_seconds DECIMAL(8,2) NULL,
  devices_found INT UNSIGNED DEFAULT 0,
  new_devices INT UNSIGNED DEFAULT 0,
  status ENUM('running','completed','failed','timeout','rate_limited') DEFAULT 'running',
  error_message TEXT NULL,
  executed_by ENUM('cron','manual','api') DEFAULT 'cron',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES monitored_sites(id) ON DELETE SET NULL,
  INDEX idx_type (scan_type),
  INDEX idx_subnet (subnet),
  INDEX idx_started (started_at),
  INDEX idx_status (status)
) ENGINE=InnoDB;
```

### Tabla `discovered_devices`

```sql
CREATE TABLE discovered_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mac_address VARCHAR(17) UNIQUE NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  hostname VARCHAR(255) NULL,
  vendor VARCHAR(255) NULL,
  oui_prefix CHAR(8) NULL,
  device_type ENUM('router','switch','firewall','server','workstation','printer','ap','camera','ups','unknown') DEFAULT 'unknown',
  os_detected VARCHAR(255) NULL,
  open_ports JSON NULL,
  site_id BIGINT UNSIGNED NULL,
  classification_status ENUM('pendiente','clasificado','ignorado','rogue','byod') DEFAULT 'pendiente',
  classified_by BIGINT UNSIGNED NULL,
  classified_at TIMESTAMP NULL,
  linked_network_device_id BIGINT UNSIGNED NULL,
  first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  seen_count INT UNSIGNED DEFAULT 1,
  is_active TINYINT(1) DEFAULT 1,
  discovery_method ENUM('arp_sweep','nmap_scan','snmp_walk','manual','passive') DEFAULT 'arp_sweep',
  is_authorized TINYINT(1) DEFAULT 0 COMMENT 'True si fue aprobado como dispositivo legítimo',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES monitored_sites(id) ON DELETE SET NULL,
  FOREIGN KEY (classified_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (linked_network_device_id) REFERENCES monitored_network_devices(id) ON DELETE SET NULL,
  INDEX idx_ip (ip_address),
  INDEX idx_status (classification_status),
  INDEX idx_last_seen (last_seen),
  INDEX idx_active (is_active),
  INDEX idx_authorized (is_authorized)
) ENGINE=InnoDB;
```

### Tabla `discovered_device_history`

```sql
CREATE TABLE discovered_device_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  discovered_device_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('first_seen','ip_changed','hostname_changed','went_offline','came_online','classified','marked_rogue','approved') NOT NULL,
  previous_value TEXT NULL,
  new_value TEXT NULL,
  occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (discovered_device_id) REFERENCES discovered_devices(id) ON DELETE CASCADE,
  INDEX idx_device (discovered_device_id),
  INDEX idx_event (event_type),
  INDEX idx_occurred (occurred_at)
) ENGINE=InnoDB;
```

### Tabla `oui_vendors` (lookup de fabricante)

```sql
CREATE TABLE oui_vendors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  oui_prefix CHAR(8) UNIQUE NOT NULL COMMENT 'Ej: 00:1A:2B',
  vendor_name VARCHAR(255) NOT NULL,
  country VARCHAR(3) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

## Entregables de Código

### 1. Migración Laravel
`database/migrations/2024_XX_XX_000001_create_discovery_tables.php`

### 2. Modelos Eloquent
- `app/Models/DiscoverySubnet.php` (con `scopeActive`, relación `site`)
- `app/Models/DiscoveryScan.php` (con `scopeRunning`, `scopeRecent`)
- `app/Models/DiscoveredDevice.php` (con `scopePending`, `scopeRogue`, `scopeAuthorized`, `markAsRogue()`)
- `app/Models/DiscoveredDeviceHistory.php`
- `app/Models/OuiVendor.php` (con `lookup($mac)` static)

### 3. Seeders Idempotentes
- `DiscoverySubnetsSeeder.php` — inserta `10.20.23.0/24`, `10.20.0.0/24`, `10.100.94.0/24` con `updateOrCreate`
- `OuiVendorsSeeder.php` — descarga el archivo OUI público de IEEE y puebla masivamente (o seed estático con los 20 fabricantes más comunes: Cisco, HP, Dell, Apple, TP-Link, Mikrotik, APC, etc.)

### 4. Script Python: `monitor/network_discovery.py`

```python
async def arp_sweep(subnet: str, timeout: float = 2.0) -> list[dict]:
    """
    Escaneo ARP usando scapy. Retorna lista de dicts con:
    {'ip': ..., 'mac': ..., 'vendor': ..., 'hostname': ...}
    """

async def nmap_scan(subnet: str, options: str = "-sn -PE -PS22,80,443") -> list[dict]:
    """
    Escaneo con python-nmap. Solo si nmap está instalado.
    """

async def passive_snmp_discovery(seed_device_ip: str, community: str) -> list[dict]:
    """
    Lee ipNetToMediaTable del dispositivo semilla para descubrir
    vecinos SIN escanear activamente.
    OID: 1.3.6.1.2.1.4.22.1.2
    """

async def resolve_hostname(ip: str) -> str | None:
    """DNS inverso con timeout de 2s."""

def lookup_vendor(mac: str) -> str | None:
    """Lookup en tabla oui_vendors o cache en /dev/shm."""

async def run_discovery(subnet_id: int, trigger: str = 'cron'):
    """
    Orquestador principal:
    1. Verifica ventana horaria
    2. Aplica rate limiting
    3. Ejecuta ARP sweep + Nmap (según config)
    4. Enriquece con hostname + vendor
    5. Registra en discovery_scans
    6. Upsert en discovered_devices
    7. Detecta IP changes y registra en discovered_device_history
    8. Retorna estadísticas
    """
```

**Consideraciones críticas:**
- **Rate limiting estricto** con `asyncio.Semaphore` (no más de `rate_limit_pps` paquetes/seg)
- **Ventana horaria:** si `scan_window_start` y `scan_window_end` están definidos, solo escanear dentro
- **Exclusión de IPs del clúster** (`10.20.23.252`, `10.20.23.221`) y gateways
- **Cache de vendors en `/dev/shm/monitoreo_oui_cache`** con TTL de 24h
- **Anti-rogue:** si un dispositivo aparece en puerto de acceso no autorizado (detectable vía SNMP FDB en Fase 2) → marcar como `rogue` automáticamente
- **NO escanear** si ya hay un scan `running` para la misma subred (candado en `/dev/shm`)

### 5. Integración con `cron_runner.py`

```python
# Ejecutar cada 15 minutos, respetando ventanas horarias
scheduler.add_job(run_discovery_all_subnets, 'interval', minutes=15)
```

### 6. Comandos del Bot
Registrar en `bot_commands` con nivel `operador`:
- `/discovery` — Resumen: total descubiertos, pendientes, rogue
- `/discovery scan <subnet>` — Escaneo manual (nivel `admin`)
- `/discovery classify <id> <tipo>` — Clasificar equipo
- `/discovery list [pendiente|clasificado|rogue|all]` — Listar
- `/discovery approve <id>` — Aprobar como autorizado (nivel `admin`)
- `/discovery mark-rogue <id>` — Marcar como rogue (nivel `admin`)

### 7. Dashboard
- **Columna 2:** Widget "Dispositivos Descubiertos" con badge naranja si hay pendientes
- **Modal de clasificación** desde el dashboard (mismo patrón que modales existentes)
- **Alerta visual** si hay rogue detectado (badge rojo parpadeante)

### 8. CRUD en `/admin`
- `Admin/DiscoverySubnetController` (resource)
- `Admin/DiscoveredDeviceController` (index, show, classify, approve, markRogue)
- Vista Blade con tabla filtrable + acciones masivas

### 9. Inmunidad Git

Añadir a `self_heal_environment.py`:

```python
PROTECTED_TABLES += [
    'discovery_subnets', 'discovery_scans',
    'discovered_devices', 'discovered_device_history',
    'oui_vendors'
]
```

### 10. Replicación Master/Slave

En `ClusterController::pushSnapshot()`, incluir:
- `discovered_devices` (solo `is_active=1`)
- `discovery_subnets` (solo `is_active=1`)
- `oui_vendors` (solo si se actualizó en las últimas 24h)

### 11. Tests de Integración
`tests/integration/test_discovery.py`:
- Escanear `10.20.23.0/24` → verificar que encuentra al menos 1 dispositivo
- Verificar upsert idempotente (ejecutar 2 veces → 0 duplicados)
- Verificar detección de cambio de IP

---

# 📡 FASE 2: ACTIVACIÓN Y MONITOREO SNMP

## Objetivo
Extraer métricas ricas de routers, switches Cisco, firewalls pfSense y equipos con SNMP. Incluye activación remota con fallback Telnet para equipos legacy.

## Diseño de Base de Datos

### Tabla `snmp_oids`

```sql
CREATE TABLE snmp_oids (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) UNIQUE NOT NULL,
  oid VARCHAR(255) UNIQUE NOT NULL,
  mib VARCHAR(255) NULL,
  vendor VARCHAR(255) NULL COMMENT 'Cisco, pfSense, APC, standard',
  data_type ENUM('counter','gauge','string','timeticks','ipaddress','octets','integer') NOT NULL,
  unit VARCHAR(50) NULL COMMENT '%, ms, bytes, °C',
  is_standard TINYINT(1) DEFAULT 0,
  is_counter_wrap TINYINT(1) DEFAULT 0 COMMENT 'True si es counter32 que se reinicia',
  description TEXT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vendor (vendor),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;
```

### Tabla `snmp_devices`

```sql
CREATE TABLE snmp_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) UNIQUE NOT NULL,
  snmp_version ENUM('v2c','v3') NOT NULL DEFAULT 'v2c',
  snmp_community_encrypted TEXT NULL COMMENT 'Cifrado con encrypt()',
  snmp_v3_username VARCHAR(255) NULL,
  snmp_v3_auth_protocol ENUM('MD5','SHA','SHA-256','SHA-512') NULL,
  snmp_v3_auth_password_encrypted TEXT NULL,
  snmp_v3_priv_protocol ENUM('DES','AES','AES-256') NULL,
  snmp_v3_priv_password_encrypted TEXT NULL,
  snmp_port INT UNSIGNED DEFAULT 161,
  snmp_timeout_seconds INT UNSIGNED DEFAULT 5,
  snmp_retries INT UNSIGNED DEFAULT 2,
  device_type ENUM('router','switch','firewall','server','ups','ap','unknown') DEFAULT 'unknown',
  vendor VARCHAR(100) NULL,
  model VARCHAR(255) NULL,
  firmware_version VARCHAR(255) NULL,
  serial_number VARCHAR(255) NULL,
  sys_name VARCHAR(255) NULL,
  sys_description TEXT NULL,
  sys_object_id VARCHAR(255) NULL,
  sys_uptime BIGINT UNSIGNED NULL,
  sys_location VARCHAR(255) NULL,
  sys_contact VARCHAR(255) NULL,
  site_id BIGINT UNSIGNED NULL,
  discovered_device_id BIGINT UNSIGNED NULL,
  network_device_id BIGINT UNSIGNED NULL,
  poll_interval_seconds INT UNSIGNED DEFAULT 60,
  is_active TINYINT(1) DEFAULT 1,
  last_poll_at TIMESTAMP NULL,
  last_poll_status ENUM('success','timeout','auth_error','unknown_oid','error') NULL,
  consecutive_failures INT UNSIGNED DEFAULT 0,
  ssh_enabled TINYINT(1) DEFAULT 0,
  ssh_username VARCHAR(255) NULL,
  ssh_password_encrypted TEXT NULL,
  ssh_port INT UNSIGNED DEFAULT 22,
  custom_oids JSON NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES monitored_sites(id) ON DELETE SET NULL,
  FOREIGN KEY (discovered_device_id) REFERENCES discovered_devices(id) ON DELETE SET NULL,
  FOREIGN KEY (network_device_id) REFERENCES monitored_network_devices(id) ON DELETE SET NULL,
  INDEX idx_active (is_active),
  INDEX idx_type (device_type),
  INDEX idx_poll (last_poll_at)
) ENGINE=InnoDB;
```

### Tabla `snmp_device_oids`

```sql
CREATE TABLE snmp_device_oids (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snmp_device_id BIGINT UNSIGNED NOT NULL,
  snmp_oid_id BIGINT UNSIGNED NOT NULL,
  custom_oid VARCHAR(255) NULL COMMENT 'Override del OID base',
  is_active TINYINT(1) DEFAULT 1,
  alert_threshold_warning DECIMAL(15,4) NULL,
  alert_threshold_critical DECIMAL(15,4) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (snmp_device_id) REFERENCES snmp_devices(id) ON DELETE CASCADE,
  FOREIGN KEY (snmp_oid_id) REFERENCES snmp_oids(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_device_oid (snmp_device_id, snmp_oid_id)
) ENGINE=InnoDB;
```

### Tabla `snmp_metrics_history`

```sql
CREATE TABLE snmp_metrics_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snmp_device_id BIGINT UNSIGNED NOT NULL,
  snmp_oid_id BIGINT UNSIGNED NOT NULL,
  metric_value DECIMAL(20,6) NULL,
  metric_value_raw TEXT NULL COMMENT 'Para strings o timeticks',
  collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (snmp_device_id) REFERENCES snmp_devices(id) ON DELETE CASCADE,
  FOREIGN KEY (snmp_oid_id) REFERENCES snmp_oids(id) ON DELETE CASCADE,
  INDEX idx_device_time (snmp_device_id, collected_at),
  INDEX idx_oid_time (snmp_oid_id, collected_at)
) ENGINE=InnoDB;
```

### Tabla `snmp_interfaces`

```sql
CREATE TABLE snmp_interfaces (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snmp_device_id BIGINT UNSIGNED NOT NULL,
  if_index INT UNSIGNED NOT NULL,
  if_name VARCHAR(255) NULL,
  if_description VARCHAR(255) NULL,
  if_alias VARCHAR(255) NULL,
  if_type INT UNSIGNED NULL,
  if_speed BIGINT UNSIGNED NULL,
  if_high_speed BIGINT UNSIGNED NULL,
  if_physical_address VARCHAR(17) NULL,
  if_admin_status ENUM('up','down','testing') NULL,
  if_oper_status ENUM('up','down','testing','unknown','dormant','notPresent','lowerLayerDown') NULL,
  is_monitored TINYINT(1) DEFAULT 0,
  last_in_octets BIGINT UNSIGNED NULL,
  last_out_octets BIGINT UNSIGNED NULL,
  last_polled_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (snmp_device_id) REFERENCES snmp_devices(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_device_if (snmp_device_id, if_index)
) ENGINE=InnoDB;
```

### Tabla `snmp_interface_metrics`

```sql
CREATE TABLE snmp_interface_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snmp_interface_id BIGINT UNSIGNED NOT NULL,
  in_octets BIGINT UNSIGNED NULL,
  out_octets BIGINT UNSIGNED NULL,
  in_unicast_pkts BIGINT UNSIGNED NULL,
  out_unicast_pkts BIGINT UNSIGNED NULL,
  in_discards INT UNSIGNED NULL,
  out_discards INT UNSIGNED NULL,
  in_errors INT UNSIGNED NULL,
  out_errors INT UNSIGNED NULL,
  in_bps DECIMAL(15,4) NULL COMMENT 'Calculado por delta',
  out_bps DECIMAL(15,4) NULL,
  in_utilization_pct DECIMAL(5,2) NULL,
  out_utilization_pct DECIMAL(5,2) NULL,
  collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (snmp_interface_id) REFERENCES snmp_interfaces(id) ON DELETE CASCADE,
  INDEX idx_if_time (snmp_interface_id, collected_at)
) ENGINE=InnoDB;
```

### Tabla `snmp_activation_log`

```sql
CREATE TABLE snmp_activation_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snmp_device_id BIGINT UNSIGNED NULL,
  discovered_device_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NOT NULL,
  activation_method ENUM('ssh_cisco','telnet_cisco','api_pfsense','manual','auto') NOT NULL,
  community_set VARCHAR(255) NULL,
  commands_executed TEXT NULL,
  status ENUM('success','failed','timeout','auth_error') NOT NULL,
  response_output TEXT NULL,
  error_message TEXT NULL,
  executed_by BIGINT UNSIGNED NULL,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (snmp_device_id) REFERENCES snmp_devices(id) ON DELETE SET NULL,
  FOREIGN KEY (discovered_device_id) REFERENCES discovered_devices(id) ON DELETE SET NULL,
  FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ip (ip_address),
  INDEX idx_status (status),
  INDEX idx_date (executed_at)
) ENGINE=InnoDB;
```

### Tabla `snmp_traps_received` (usada en Fase 6)

```sql
CREATE TABLE snmp_traps_received (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  snmp_device_id BIGINT UNSIGNED NULL,
  source_ip VARCHAR(45) NOT NULL,
  trap_oid VARCHAR(255) NOT NULL,
  trap_type VARCHAR(100) NULL COMMENT 'linkDown, linkUp, coldStart, authenticationFailure, etc',
  varbinds JSON NULL,
  severity ENUM('info','warning','critical','emergency') DEFAULT 'info',
  processed TINYINT(1) DEFAULT 0,
  alert_id BIGINT UNSIGNED NULL COMMENT 'FK a alerts (Fase 4)',
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (snmp_device_id) REFERENCES snmp_devices(id) ON DELETE SET NULL,
  INDEX idx_source (source_ip),
  INDEX idx_type (trap_type),
  INDEX idx_received (received_at)
) ENGINE=InnoDB;
```

## OIDs Prioritarios a Sembrar

**MIB-II Estándar (RFC 1213):**

| Nombre | OID | Tipo |
|---|---|---|
| sysDescr | 1.3.6.1.2.1.1.1.0 | string |
| sysObjectID | 1.3.6.1.2.1.1.2.0 | string |
| sysUpTime | 1.3.6.1.2.1.1.3.0 | timeticks |
| sysContact | 1.3.6.1.2.1.1.4.0 | string |
| sysName | 1.3.6.1.2.1.1.5.0 | string |
| sysLocation | 1.3.6.1.2.1.1.6.0 | string |
| ifNumber | 1.3.6.1.2.1.2.1.0 | integer |
| ifIndex | 1.3.6.1.2.1.2.2.1.1 | integer |
| ifDescr | 1.3.6.1.2.1.2.2.1.2 | string |
| ifType | 1.3.6.1.2.1.2.2.1.3 | integer |
| ifSpeed | 1.3.6.1.2.1.2.2.1.5 | gauge |
| ifPhysAddress | 1.3.6.1.2.1.2.2.1.6 | octets |
| ifAdminStatus | 1.3.6.1.2.1.2.2.1.7 | integer |
| ifOperStatus | 1.3.6.1.2.1.2.2.1.8 | integer |
| ifInOctets | 1.3.6.1.2.1.2.2.1.10 | counter |
| ifOutOctets | 1.3.6.1.2.1.2.2.1.16 | counter |
| ifInErrors | 1.3.6.1.2.1.2.2.1.14 | counter |
| ifOutErrors | 1.3.6.1.2.1.2.2.1.20 | counter |
| ipNetToMediaTable | 1.3.6.1.2.1.4.22.1.2 | string |

**Cisco Específicos:**

| Nombre | OID |
|---|---|
| ciscoCPU_5sec | 1.3.6.1.4.1.9.2.1.56.0 |
| ciscoCPU_1min | 1.3.6.1.4.1.9.2.1.57.0 |
| ciscoCPU_5min | 1.3.6.1.4.1.9.2.1.58.0 |
| ciscoMemPoolUsed | 1.3.6.1.4.1.9.9.48.1.1.1.5 |
| ciscoMemPoolFree | 1.3.6.1.4.1.9.9.48.1.1.1.6 |
| ciscoTempValue | 1.3.6.1.4.1.9.9.13.1.3.1.3 |
| ciscoEnvMonState | 1.3.6.1.4.1.9.9.13.1.2.1.3 |
| ciscoCDPNeighbors | 1.3.6.1.4.1.9.9.23.1.2.1.1.6 |
| ciscoLLDPRemSysName | 1.0.8802.1.1.2.1.4.1.1.9 |

**Genéricos (HOST-RESOURCES-MIB):**

| Nombre | OID |
|---|---|
| hrProcessorLoad | 1.3.6.1.2.1.25.3.3.1.2 |
| hrMemorySize | 1.3.6.1.2.1.25.2.2.0 |
| hrStorageUsed | 1.3.6.1.2.1.25.2.3.1.6 |
| hrStorageSize | 1.3.6.1.2.1.25.2.3.1.5 |

**UPS (UPS-MIB):**

| Nombre | OID |
|---|---|
| upsBatteryStatus | 1.3.6.1.2.1.33.1.2.1.0 |
| upsEstimatedMinutesRemaining | 1.3.6.1.2.1.33.1.2.3.0 |
| upsBatteryCharge | 1.3.6.1.2.1.33.1.2.4.0 |
| upsAdvBatteryCapacity | 1.3.6.1.4.1.318.1.1.1.2.2.1.0 |

**pfSense (Net-SNMP):**

| Nombre | OID |
|---|---|
| pfStateCount | 1.3.6.1.4.1.12325.1.200.1.2.0 |
| pfStateLimit | 1.3.6.1.4.1.12325.1.200.1.3.0 |

## Entregables de Código

### 1. Migración Laravel
`database/migrations/2024_XX_XX_000002_create_snmp_tables.php`

### 2. Modelos Eloquent
- `SnmpOid.php` (con `scopeStandard`, `scopeVendor`)
- `SnmpDevice.php` (con casts de cifrado: `snmp_community_encrypted`, `ssh_password_encrypted`; métodos `getCommunity()`, `setCommunity($value)`; `scopeActive`; `shouldPoll()`)
- `SnmpDeviceOid.php`
- `SnmpMetricHistory.php`
- `SnmpInterface.php` (con `scopeMonitored`, `calculateBandwidth($prevMetrics)`)
- `SnmpInterfaceMetric.php`
- `SnmpActivationLog.php`
- `SnmpTrapReceived.php`

### 3. Seeder
`SnmpOidsSeeder.php` — inserta TODOS los OIDs listados arriba con `updateOrCreate`

### 4. Script Python: `monitor/snmp_poller.py`

```python
async def poll_device(device: dict, oids: list[dict]) -> dict:
    """
    Polling SNMP con pysnmp asyncio.
    - Timeout: snmp_timeout_seconds (default 5s)
    - Retries: snmp_retries (default 2)
    - Usar GETBULK para tablas (ifTable)
    - Retorna {'metrics': [...], 'interfaces': [...], 'status': 'success'|...}
    """

async def discover_interfaces(device: dict) -> list[dict]:
    """
    Descubre interfaces vía ifTable walk.
    Actualiza snmp_interfaces con upsert.
    """

async def poll_interface_metrics(interface: dict, prev_metrics: dict | None) -> dict:
    """
    Calcula in_bps/out_bps por delta con métrica previa.
    Calcula utilization_pct = (bps / if_speed) * 100.
    Maneja counter wrap de 32 bits (si valor nuevo < valor previo → wrap).
    """

async def run_snmp_poll(device_ids: list[int] | None = None) -> dict:
    """
    Orquestador:
    1. Lee dispositivos activos (o los especificados)
    2. Poll concurrente con Semaphore(10)
    3. Persiste métricas
    4. Actualiza last_poll_at y consecutive_failures
    5. Si consecutive_failures >= 3 → notificar (Fase 4)
    """

def snmp_walk_arp_table(device: dict) -> list[dict]:
    """
    Extrae ipNetToMediaTable para alimentar passive discovery.
    Retorna [{'ip': ..., 'mac': ...}]
    """
```

**Consideraciones críticas:**
- **Cache de credenciales** en `/dev/shm/monitoreo_snmp_cache` con TTL de 5 min
- **No loguear credenciales** — sanitizar antes de escribir logs
- **Timeout agresivo:** 5s por dispositivo, no bloquear el ciclo
- **Concurrencia limitada:** `Semaphore(10)` para no saturar la red
- **Counter wrap:** si nuevo < previo y `is_counter_wrap=True` → `(2^32 - previo) + nuevo`
- **GETBULK obligatorio** para tablas (no GET en bucle)

### 5. Script Python: `monitor/snmp_activator.py`

```python
async def activate_cisco_snmp(ip, ssh_user, ssh_pass, community, version='2c') -> dict:
    """
    Usa netmiko para conectar por SSH y ejecutar:
    enable
    configure terminal
    snmp-server community <community> RO
    snmp-server location <location>
    snmp-server contact <contact>
    snmp-server enable traps
    end
    write memory
    """

async def activate_cisco_snmp_telnet(ip, telnet_user, telnet_pass, community) -> dict:
    """
    Fallback para equipos legacy (Catalyst 2960 con solo Telnet).
    Usa netmiko con device_type='cisco_ios_telnet'.
    """

async def activate_pfsense_snmp(ip, api_key, api_secret, community) -> dict:
    """
    Usa API REST de pfSense:
    POST /api/v1/services/snmp
    """

async def log_activation(ip, method, status, output, error, user_id):
    """Registra en snmp_activation_log."""
```

**Reglas de seguridad:**
- **Doble confirmación** en el bot antes de ejecutar (`/snmp activate <ip>` → bot pide `confirmar <código>`)
- **Auditoría obligatoria** en `audit_logs`
- **Comunidad por defecto NO usar `public`** — generar aleatoria de 16 chars si no se especifica
- **Timeout 10s** por operación SSH
- **NO ejecutar `write memory`** sin backup previo (coordinar con Fase 5)

### 6. Integración con `cron_runner.py`

```python
# Poll cada 60 segundos
scheduler.add_job(run_snmp_poll, 'interval', seconds=60, max_instances=1)
# Discovery de interfaces cada 6 horas
scheduler.add_job(discover_all_interfaces, 'interval', hours=6)
```

### 7. Comandos del Bot

Nivel `operador`:
- `/snmp` — Resumen: total devices, activos, fallando
- `/snmp detail <ip_o_name>` — Detalles
- `/snmp interfaces <ip_o_name>` — Interfaces con estado
- `/snmp metrics <ip_o_name> <oid_name>` — Últimas 24h de métrica

Nivel `admin`:
- `/snmp activate <ip>` — Activar SNMP remotamente (con confirmación)
- `/snmp add <ip> <community> [v2c|v3]` — Añadir dispositivo
- `/snmp poll <ip>` — Forzar polling manual
- `/snmp test <ip>` — Test de conectividad SNMP

### 8. Dashboard
- **Columna 3:** Gráficos Chart.js:
  - CPU % (línea)
  - Memoria usada/libre (área apilada)
  - Tráfico por interfaz (in/out bps)
  - Temperatura (línea con umbral)
- **Widget "Dispositivos SNMP"** con semáforo por dispositivo
- **Modal de interfaces** con estado up/down y utilización

### 9. CRUD en `/admin`
- `Admin/SnmpDeviceController` (resource)
- `Admin/SnmpOidController` (resource)
- `Admin/SnmpInterfaceController` (index, show, toggleMonitored)
- Vista de activación remota con confirmación

### 10. Inmunidad Git

```python
PROTECTED_TABLES += [
    'snmp_oids', 'snmp_devices', 'snmp_device_oids',
    'snmp_metrics_history', 'snmp_interfaces',
    'snmp_interface_metrics', 'snmp_activation_log',
    'snmp_traps_received'
]
```

### 11. Replicación Master/Slave

Incluir en payload:
- `snmp_devices` (SIN campos `_encrypted`)
- `snmp_oids`
- `snmp_device_oids`
- `snmp_interfaces`
- `snmp_metrics_history` (últimas 24h)
- `snmp_interface_metrics` (últimas 24h)

### 12. Tests de Integración
`tests/integration/test_snmp.py`:
- Poll exitoso a dispositivo de prueba
- Manejo de timeout
- Cálculo correcto de bps por delta
- Detección de counter wrap

---

# 🔒 FASE 3: MONITOREO DE CERTIFICADOS SSL/TLS

## Objetivo
Monitorear vigencia, cadena y renovaciones de certificados SSL/TLS de todos los servicios HTTPS.

## Diseño de Base de Datos

### Tabla `ssl_certificates`

```sql
CREATE TABLE ssl_certificates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id BIGINT UNSIGNED NULL,
  domain VARCHAR(255) UNIQUE NOT NULL,
  port INT UNSIGNED DEFAULT 443,
  subject_cn VARCHAR(255) NULL,
  subject_org VARCHAR(255) NULL,
  subject_ou VARCHAR(255) NULL,
  subject_country VARCHAR(10) NULL,
  subject_state VARCHAR(100) NULL,
  subject_locality VARCHAR(100) NULL,
  issuer_cn VARCHAR(255) NULL,
  issuer_org VARCHAR(255) NULL,
  issuer_country VARCHAR(10) NULL,
  serial_number VARCHAR(255) NULL,
  signature_algorithm VARCHAR(100) NULL,
  public_key_algorithm VARCHAR(100) NULL,
  public_key_bits INT UNSIGNED NULL,
  version INT UNSIGNED NULL,
  valid_from TIMESTAMP NOT NULL,
  valid_to TIMESTAMP NOT NULL,
  days_remaining INT GENERATED ALWAYS AS (TIMESTAMPDIFF(DAY, NOW(), valid_to)) STORED,
  is_self_signed TINYINT(1) DEFAULT 0,
  is_wildcard TINYINT(1) DEFAULT 0,
  is_ev TINYINT(1) DEFAULT 0 COMMENT 'Extended Validation',
  san_entries JSON NULL,
  fingerprint_sha256 VARCHAR(64) NULL,
  fingerprint_sha1 VARCHAR(40) NULL,
  pem_certificate MEDIUMTEXT NULL,
  alert_threshold_warning INT UNSIGNED DEFAULT 30,
  alert_threshold_critical INT UNSIGNED DEFAULT 7,
  last_checked_at TIMESTAMP NULL,
  last_check_status ENUM('success','error','expired','expiring_soon','hostname_mismatch') NULL,
  consecutive_errors INT UNSIGNED DEFAULT 0,
  renewal_count INT UNSIGNED DEFAULT 0,
  notes TEXT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES monitored_services(id) ON DELETE SET NULL,
  INDEX idx_service (service_id),
  INDEX idx_valid_to (valid_to),
  INDEX idx_days_remaining (days_remaining),
  INDEX idx_status (last_check_status)
) ENGINE=InnoDB;
```

### Tabla `ssl_certificate_history`

```sql
CREATE TABLE ssl_certificate_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ssl_certificate_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('initial_discovery','renewal','expiration_warning','expired','issuer_changed','hostname_mismatch','error','recovered') NOT NULL,
  previous_fingerprint VARCHAR(64) NULL,
  new_fingerprint VARCHAR(64) NULL,
  previous_valid_to TIMESTAMP NULL,
  new_valid_to TIMESTAMP NULL,
  days_remaining_at_event INT NULL,
  error_message TEXT NULL,
  occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ssl_certificate_id) REFERENCES ssl_certificates(id) ON DELETE CASCADE,
  INDEX idx_cert (ssl_certificate_id),
  INDEX idx_event (event_type),
  INDEX idx_occurred (occurred_at)
) ENGINE=InnoDB;
```

## Entregables de Código

### 1. Migración Laravel
`database/migrations/2024_XX_XX_000003_create_ssl_certificate_tables.php`

### 2. Modelos Eloquent
- `SslCertificate.php` (con `scopeExpiring`, `scopeExpired`, `scopeCritical`, `calculateDaysRemaining()`)
- `SslCertificateHistory.php`

### 3. Modificación de `monitor_web_sync.py`

Tras cada conexión HTTPS exitosa, extraer cert con `cryptography.x509`:

```python
from cryptography import x509
from cryptography.hazmat.backends import default_backend

def extract_cert_info(hostname: str, port: int = 443) -> dict:
    """
    Conecta con ssl.get_server_certificate() y extrae:
    - subject, issuer, valid_from, valid_to, serial, fingerprint
    - SAN entries
    - self-signed (subject == issuer)
    - wildcard (* en CN)
    """
```

### 4. Job en `cron_runner.py`

```python
# Verificación profunda cada 6 horas
scheduler.add_job(deep_ssl_check, 'interval', hours=6)
```

Alertas a 30, 15, 7, 3, 1 días (según umbrales configurables).

### 5. Comandos del Bot
- `/certificados` — Lista todos con días restantes
- `/certificado <dominio>` — Detalles completos
- `/certificados expirando [días]` — Filtro por días

### 6. Dashboard
- **Columna 1:** Badge de candado junto a cada servicio HTTPS (verde/amarillo/rojo)
- **Columna 3:** Widget "Certificados por Expirar" con semáforo y días restantes
- **Tooltip:** Issuer + valid_to

### 7. Inmunidad Git y Replicación
- Proteger tablas: `ssl_certificates`, `ssl_certificate_history`
- Incluir `ssl_certificates` (sin PEM completo, solo fingerprint) en payload

---

# 🚨 FASE 4: SISTEMA DE ALERTAS CON ESCALACIÓN Y CORRELACIÓN

## Objetivo
Sistema inteligente de alertas con correlación padre-hijo, escalación por niveles, ack manual, ventanas de mantenimiento y supresión de tormentas.

## Diseño de Base de Datos

### Tabla `alert_rules`

```sql
CREATE TABLE alert_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  entity_type ENUM('service','site','site_device','network_device','proxy','ssl_certificate','snmp_device','discovered_device','interface') NOT NULL,
  entity_id BIGINT UNSIGNED NULL COMMENT 'NULL = aplica a todos del tipo',
  condition_type ENUM('is_down','latency_high','packet_loss_high','jitter_high','cpu_high','memory_high','interface_down','interface_utilization_high','cert_expiring','cert_expired','new_device','device_disappeared','config_changed','temperature_high','battery_low','snmp_unreachable') NOT NULL,
  threshold_value DECIMAL(15,4) NULL,
  comparison ENUM('gt','lt','eq','gte','lte') NULL,
  duration_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Tiempo sostenido para disparar',
  severity ENUM('info','warning','critical','emergency') NOT NULL,
  cooldown_minutes INT UNSIGNED DEFAULT 30,
  max_alerts_per_hour INT UNSIGNED DEFAULT 5,
  auto_resolve TINYINT(1) DEFAULT 1,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;
```

### Tabla `alert_escalation_levels`

```sql
CREATE TABLE alert_escalation_levels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_rule_id BIGINT UNSIGNED NOT NULL,
  level INT UNSIGNED NOT NULL,
  delay_minutes INT UNSIGNED DEFAULT 0,
  channel ENUM('telegram','email','sms','phone_call','webhook') NOT NULL,
  target_type ENUM('user','group','external') NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  target_group VARCHAR(255) NULL,
  target_external VARCHAR(255) NULL,
  message_template_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (alert_rule_id) REFERENCES alert_rules(id) ON DELETE CASCADE,
  FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (message_template_id) REFERENCES bot_message_templates(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_rule_level (alert_rule_id, level)
) ENGINE=InnoDB;
```

### Tabla `alert_correlation_groups`

```sql
CREATE TABLE alert_correlation_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  parent_entity_type ENUM('site','network_device','proxy','snmp_device') NOT NULL,
  parent_entity_id BIGINT UNSIGNED NOT NULL,
  suppression_strategy ENUM('suppress_all','suppress_if_parent_down','reduce_severity') NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_parent (parent_entity_type, parent_entity_id),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;
```

### Tabla `alert_correlation_members`

```sql
CREATE TABLE alert_correlation_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  correlation_group_id BIGINT UNSIGNED NOT NULL,
  child_entity_type ENUM('service','site_device','network_device','interface','snmp_device') NOT NULL,
  child_entity_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (correlation_group_id) REFERENCES alert_correlation_groups(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_group_child (correlation_group_id, child_entity_type, child_entity_id),
  INDEX idx_child (child_entity_type, child_entity_id)
) ENGINE=InnoDB;
```

### Tabla `alerts`

```sql
CREATE TABLE alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_rule_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  entity_name VARCHAR(255) NULL,
  condition_type VARCHAR(50) NOT NULL,
  severity ENUM('info','warning','critical','emergency') NOT NULL,
  status ENUM('firing','acknowledged','resolved','suppressed','auto_resolved') DEFAULT 'firing',
  current_escalation_level INT UNSIGNED DEFAULT 1,
  value_at_trigger DECIMAL(15,4) NULL,
  threshold_value DECIMAL(15,4) NULL,
  message TEXT NULL,
  correlation_group_id BIGINT UNSIGNED NULL,
  is_correlated_suppressed TINYINT(1) DEFAULT 0,
  parent_alert_id BIGINT UNSIGNED NULL,
  fired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at TIMESTAMP NULL,
  resolved_at TIMESTAMP NULL,
  acknowledged_by BIGINT UNSIGNED NULL,
  resolved_by ENUM('auto','manual') NULL,
  last_notified_at TIMESTAMP NULL,
  notification_count INT UNSIGNED DEFAULT 0,
  duration_seconds INT GENERATED ALWAYS AS (IFNULL(TIMESTAMPDIFF(SECOND, fired_at, IFNULL(resolved_at, NOW())), 0)) STORED,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (alert_rule_id) REFERENCES alert_rules(id) ON DELETE SET NULL,
  FOREIGN KEY (correlation_group_id) REFERENCES alert_correlation_groups(id) ON DELETE SET NULL,
  FOREIGN KEY (parent_alert_id) REFERENCES alerts(id) ON DELETE SET NULL,
  FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_severity (severity),
  INDEX idx_fired (fired_at)
) ENGINE=InnoDB;
```

### Tabla `alert_notifications`

```sql
CREATE TABLE alert_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_id BIGINT UNSIGNED NOT NULL,
  escalation_level_id BIGINT UNSIGNED NULL,
  channel VARCHAR(50) NOT NULL,
  target VARCHAR(255) NULL,
  message_sent TEXT NULL,
  status ENUM('sent','failed','pending','suppressed') DEFAULT 'pending',
  error_message TEXT NULL,
  external_message_id VARCHAR(255) NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
  FOREIGN KEY (escalation_level_id) REFERENCES alert_escalation_levels(id) ON DELETE SET NULL,
  INDEX idx_alert (alert_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;
```

### Tabla `maintenance_windows`

```sql
CREATE TABLE maintenance_windows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  entity_type ENUM('service','site','site_device','network_device','proxy','snmp_device','all') NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  suppress_severities JSON NULL COMMENT 'Array: ["warning","critical"]',
  starts_at TIMESTAMP NOT NULL,
  ends_at TIMESTAMP NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  is_active TINYINT(1) GENERATED ALWAYS AS (NOW() BETWEEN starts_at AND ends_at) STORED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_dates (starts_at, ends_at)
) ENGINE=InnoDB;
```

### Tabla `alert_storm_suppression`

```sql
CREATE TABLE alert_storm_suppression (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fingerprint VARCHAR(64) UNIQUE NOT NULL COMMENT 'hash de entity_type+entity_id+condition_type',
  alert_count INT UNSIGNED DEFAULT 1,
  first_alert_at TIMESTAMP NOT NULL,
  last_alert_at TIMESTAMP NOT NULL,
  suppressed_count INT UNSIGNED DEFAULT 0,
  next_allowed_at TIMESTAMP NULL,
  INDEX idx_last (last_alert_at)
) ENGINE=InnoDB;
```

## Entregables de Código

### 1. Migración Laravel
`database/migrations/2024_XX_XX_000004_create_alert_tables.php`

### 2. Modelos Eloquent
Los 8 modelos con relaciones completas. `Alert::acknowledge($userId)`, `Alert::resolve($mode)`, `Alert::isSuppressed()`.

### 3. Seeder de Reglas por Defecto
`AlertRulesSeeder.php`:
- Servicio caído → `critical`, cooldown 30min
- Latencia > 500ms sostenida 60s → `warning`
- Packet loss > 10% → `critical`
- CPU > 80% sostenido 5min → `warning`
- Memoria libre < 15% → `warning`
- Certificado expira en 7 días → `critical`
- Certificado expirado → `emergency`
- Nuevo dispositivo descubierto → `info`
- Dispositivo autorizado desaparecido > 1h → `warning`
- Interface down → `warning`
- Config cambiada → `warning`
- Temperatura > 65°C → `critical`

### 4. Motor de Evaluación en `monitor_engine.py`

```python
async def evaluate_alert_rules(snapshot: dict):
    """
    1. Cargar reglas activas
    2. Para cada regla, evaluar condición contra snapshot
    3. Verificar storm suppression (fingerprint en alert_storm_suppression)
    4. Verificar maintenance_windows activas
    5. Verificar correlación padre-hijo
    6. Crear/actualizar alerta en tabla alerts
    7. Auto-resolver si condición ya no se cumple y auto_resolve=1
    """

async def apply_correlation(alert: Alert):
    """
    Si existe correlation_group para la entidad:
    - Si padre está down y suppression=suppress_if_parent_down → marcar is_correlated_suppressed
    - Reducir severidad si strategy=reduce_severity
    """
```

### 5. Job de Escalación en `cron_runner.py`

```python
# Cada 60 segundos
scheduler.add_job(process_escalations, 'interval', seconds=60)

async def process_escalations():
    """
    Para cada alerta firing/acknowledged:
    1. Calcular tiempo desde fired_at o last_notified_at
    2. Verificar si corresponde subir de nivel según alert_escalation_levels
    3. Despachar notificación por canal configurado
    4. Registrar en alert_notifications
    5. Actualizar current_escalation_level
    """
```

### 6. Comandos del Bot
- `/alertas` — Lista alertas activas (firing + acknowledged)
- `/alertas <id>` — Detalle
- `/ack <id>` — Reconocer (nivel `operador`)
- `/resolver <id>` — Resolver manual (nivel `admin`)
- `/mantenimiento <entidad> <minutos> [título]` — Crear ventana (nivel `admin`)
- `/reglas` — Lista reglas configuradas
- `/silenciar <id> <minutos>` — Silenciar alerta específica (nivel `operador`)

### 7. Dashboard
- **Columna 3:** Widget "Alertas Activas" con:
  - Orden por severidad (emergency > critical > warning > info)
  - Botones inline de ack/resolver
  - Contador de duración
  - Indicador de escalación
- **Widget "Tormenta Suprimida"** si hay storm suppression activa
- **Topbar:** Badge de alertas críticas activas con parpadeo

### 8. CRUD en `/admin`
- `Admin/AlertRuleController` (resource)
- `Admin/AlertEscalationLevelController`
- `Admin/AlertCorrelationGroupController`
- `Admin/MaintenanceWindowController`
- `Admin/AlertController` (index, show, ack, resolve)

### 9. Inmunidad Git y Replicación
- Proteger las 8 tablas
- Incluir `alerts` (firing/acknowledged) y `maintenance_windows` (activos) en payload

---

# 🌐 FASE 5: CALIDAD WAN + BACKUP DE CONFIGURACIONES

## Objetivo
Medir jitter/packet loss real y respaldar configuraciones de routers/switches con detección de cambios.

## Diseño de Base de Datos

### Modificación de `site_check_histories`

```sql
ALTER TABLE site_check_histories
  ADD COLUMN packet_loss_pct DECIMAL(5,2) NULL AFTER latency_ms,
  ADD COLUMN jitter_ms DECIMAL(10,4) NULL AFTER packet_loss_pct,
  ADD COLUMN min_rtt_ms DECIMAL(10,4) NULL AFTER jitter_ms,
  ADD COLUMN max_rtt_ms DECIMAL(10,4) NULL AFTER min_rtt_ms,
  ADD COLUMN mdev_ms DECIMAL(10,4) NULL AFTER max_rtt_ms;
```

### Tabla `device_configurations`

```sql
CREATE TABLE device_configurations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  network_device_id BIGINT UNSIGNED NULL,
  snmp_device_id BIGINT UNSIGNED NULL,
  device_type ENUM('cisco_router','cisco_switch','pfsense','other') NOT NULL,
  config_text LONGTEXT NOT NULL,
  config_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256',
  config_size_bytes INT UNSIGNED NULL,
  captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  captured_by ENUM('cron','manual') DEFAULT 'cron',
  notes TEXT NULL,
  FOREIGN KEY (network_device_id) REFERENCES monitored_network_devices(id) ON DELETE SET NULL,
  FOREIGN KEY (snmp_device_id) REFERENCES snmp_devices(id) ON DELETE SET NULL,
  INDEX idx_network (network_device_id),
  INDEX idx_snmp (snmp_device_id),
  INDEX idx_hash (config_hash),
  INDEX idx_captured (captured_at)
) ENGINE=InnoDB;
```

### Tabla `config_change_logs`

```sql
CREATE TABLE config_change_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_configuration_id BIGINT UNSIGNED NOT NULL,
  previous_config_id BIGINT UNSIGNED NULL,
  change_type ENUM('initial','modified','reverted') NOT NULL,
  diff_summary TEXT NULL,
  diff_unified TEXT NULL COMMENT 'Diff unificado estilo git',
  lines_added INT UNSIGNED DEFAULT 0,
  lines_removed INT UNSIGNED DEFAULT 0,
  detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  alerted TINYINT(1) DEFAULT 0,
  FOREIGN KEY (device_configuration_id) REFERENCES device_configurations(id) ON DELETE CASCADE,
  FOREIGN KEY (previous_config_id) REFERENCES device_configurations(id) ON DELETE SET NULL,
  INDEX idx_config (device_configuration_id),
  INDEX idx_detected (detected_at)
) ENGINE=InnoDB;
```

## Entregables de Código

### 1. Migración Laravel
`database/migrations/2024_XX_XX_000005_create_wan_config_tables.php`

### 2. Modificación de `monitor_web_sync.py`

```python
# Cambiar de ping -c 1 a ping -c 10
cmd = f"ping -c 10 -W 2 {ip}"
# Parsear:
# - min/avg/max/mdev (mdev = jitter)
# - packet loss %: "10 packets transmitted, 8 received, 20% packet loss"
```

### 3. Script Python: `monitor/config_backup.py`

```python
async def backup_cisco_config(ip, ssh_user, ssh_pass, device_type='cisco_ios') -> dict:
    """
    Usa netmiko.send_command('show running-config')
    Retorna {'config': ..., 'hash': sha256, 'success': bool}
    """

async def backup_pfsense_config(ip, api_key, api_secret) -> dict:
    """
    GET /api/v1/diagnostics/config
    """

async def detect_config_change(device_id, new_config_hash) -> dict | None:
    """
    Compara hash con último backup.
    Si difiere:
    - Guarda nuevo backup
    - Genera diff unificado con difflib.unified_diff
    - Registra en config_change_logs
    - Retorna info del cambio para alertar
    """

async def run_config_backup(device_ids=None):
    """Orquestador nocturno."""
```

### 4. Job en `cron_runner.py`

```python
# Backup nocturno a las 2:00 AM
scheduler.add_job(run_config_backup, 'cron', hour=2, minute=0)
```

### 5. Comandos del Bot
- `/configs` — Lista últimos backups por dispositivo
- `/configs diff <equipo>` — Muestra diff del último cambio
- `/configs backup <equipo>` — Fuerza backup manual (nivel `admin`)

### 6. Dashboard
- **Columna 3:** Gráficos de jitter y packet loss por sede
- **Widget "Cambios de Configuración Recientes"** con diff inline

### 7. Inmunidad Git y Replicación
- Proteger tablas: `device_configurations`, `config_change_logs`
- Incluir `device_configurations` (solo último por dispositivo, sin `config_text` completo por tamaño) en payload

---

# 📡 FASE 6: SNMP TRAPS + NETFLOW/SFLOW + SYSLOG

## Objetivo
Complementar el modelo pull con modelo push para detección en segundos, y análisis de flujos de tráfico.

## Diseño de Base de Datos

### Tabla `netflow_records` (agregada por minuto)

```sql
CREATE TABLE netflow_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exporter_ip VARCHAR(45) NOT NULL,
  src_ip VARCHAR(45) NOT NULL,
  dst_ip VARCHAR(45) NOT NULL,
  src_port INT UNSIGNED NULL,
  dst_port INT UNSIGNED NULL,
  protocol TINYINT UNSIGNED NULL COMMENT '6=TCP, 17=UDP, 1=ICMP',
  bytes BIGINT UNSIGNED NOT NULL,
  packets BIGINT UNSIGNED NOT NULL,
  direction ENUM('ingress','egress') NULL,
  window_start TIMESTAMP NOT NULL,
  window_end TIMESTAMP NOT NULL,
  INDEX idx_exporter (exporter_ip, window_start),
  INDEX idx_src (src_ip, window_start),
  INDEX idx_dst (dst_ip, window_start),
  INDEX idx_ports (dst_port, window_start)
) ENGINE=InnoDB;
```

### Tabla `syslog_events`

```sql
CREATE TABLE syslog_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_ip VARCHAR(45) NOT NULL,
  hostname VARCHAR(255) NULL,
  facility INT UNSIGNED NULL,
  severity INT UNSIGNED NULL COMMENT '0=emergency ... 7=debug',
  program VARCHAR(255) NULL,
  message TEXT NOT NULL,
  raw_message TEXT NULL,
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_source (source_ip, received_at),
  INDEX idx_severity (severity),
  INDEX idx_program (program),
  INDEX idx_received (received_at)
) ENGINE=InnoDB;
```

### Tabla `netflow_top_talkers` (materializada cada 5 min)

```sql
CREATE TABLE netflow_top_talkers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  window_start TIMESTAMP NOT NULL,
  window_end TIMESTAMP NOT NULL,
  rank_type ENUM('src_ip','dst_ip','src_as','dst_as','protocol') NOT NULL,
  rank_value VARCHAR(255) NOT NULL,
  bytes BIGINT UNSIGNED NOT NULL,
  packets BIGINT UNSIGNED NOT NULL,
  percentage DECIMAL(5,2) NULL,
  INDEX idx_window (window_start, rank_type),
  INDEX idx_value (rank_value)
) ENGINE=InnoDB;
```

## Entregables de Código

### 1. Migración Laravel
`database/migrations/2024_XX_XX_000006_create_netflow_syslog_tables.php`

### 2. Script Python: `monitor/snmp_trap_receiver.py`

```python
# Demonio snmptrapd en el Master (UDP 162)
# Alternativa Python: pysnmp.carrier.asyncio.dgram.udp
async def start_trap_receiver():
    """
    Escucha traps SNMP.
    Traduce trap_oid a trap_type:
    - 1.3.6.1.6.3.1.1.5.3 → linkDown
    - 1.3.6.1.6.3.1.1.5.4 → linkUp
    - 1.3.6.1.6.3.1.1.5.1 → coldStart
    - 1.3.6.1.6.3.1.1.5.5 → authenticationFailure
    Persiste en snmp_traps_received.
    """
```

### 3. Script Python: `monitor/netflow_collector.py`

```python
# nfcapd escuchando UDP 2055
# Alternativa: python-nfqueue o py-netflow
async def collect_netflow():
    """
    Escucha flows, agrega por ventana de 1 minuto.
    Persiste en netflow_records.
    Cada 5 min calcula top talkers.
    """
```

### 4. Script Python: `monitor/syslog_receiver.py`

```python
# Escucha UDP 514
async def start_syslog_receiver():
    """
    Parsea RFC 3164 y RFC 5424.
    Persiste en syslog_events.
    Correlaciona con alerts si severity <= 3.
    """
```

### 5. Comandos del Bot
- `/traps` — Últimos traps recibidos
- `/traps <ip>` — Traps de un dispositivo
- `/netflow top [src|dst|proto]` — Top talkers
- `/syslog search <patrón>` — Búsqueda
- `/syslog device <ip>` — Eventos de un dispositivo

### 6. Dashboard
- **Columna 3:** Widget "Traps Recientes"
- **Widget "Top Talkers"** con tabla
- **Widget "Syslog Live"** con autoscroll

### 7. Inmunidad Git y Replicación
- Proteger tablas: `netflow_records`, `syslog_events`, `netflow_top_talkers`
- Incluir `netflow_top_talkers` (última ventana) y `snmp_traps_received` (últimas 24h) en payload

---

# 🗺️ FASE 7: TOPOLOGÍA VISUAL + WAKE-ON-LAN + IA PREDICTIVA + INVENTARIO

## Objetivo
Aprovechar datos ya recopilados para agregar valor: mapa visual, WoL, análisis predictivo y ciclo de vida.

## Diseño de Base de Datos

### Tabla `network_topology_links`

```sql
CREATE TABLE network_topology_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_device_id BIGINT UNSIGNED NOT NULL COMMENT 'snmp_device_id',
  source_interface_id BIGINT UNSIGNED NULL,
  target_device_id BIGINT UNSIGNED NULL,
  target_mac VARCHAR(17) NULL,
  target_hostname VARCHAR(255) NULL,
  link_type ENUM('lldp','cdp','manual','fdb_inferred') NOT NULL,
  link_status ENUM('up','down','unknown') DEFAULT 'unknown',
  last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_source (source_device_id),
  INDEX idx_target (target_device_id)
) ENGINE=InnoDB;
```

### Tabla `wol_devices`

```sql
CREATE TABLE wol_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  mac_address VARCHAR(17) UNIQUE NOT NULL,
  ip_address VARCHAR(45) NULL,
  broadcast_address VARCHAR(45) NULL COMMENT 'Ej: 10.20.23.255',
  site_id BIGINT UNSIGNED NULL,
  discovered_device_id BIGINT UNSIGNED NULL,
  is_enabled TINYINT(1) DEFAULT 1,
  last_woken_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

### Tabla `predictive_anomalies`

```sql
CREATE TABLE predictive_anomalies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  anomaly_type ENUM('trend_upward','seasonal_pattern','correlation','outlier') NOT NULL,
  metric_name VARCHAR(100) NULL,
  confidence DECIMAL(5,2) NULL COMMENT '0-100',
  description TEXT NULL,
  predicted_impact VARCHAR(255) NULL,
  detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  acknowledged TINYINT(1) DEFAULT 0,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_detected (detected_at)
) ENGINE=InnoDB;
```

### Tabla `hardware_lifecycle`

```sql
CREATE TABLE hardware_lifecycle (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  serial_number VARCHAR(255) NULL,
  purchase_date DATE NULL,
  warranty_end_date DATE NULL,
  eol_date DATE NULL COMMENT 'End of Life del fabricante',
  eos_date DATE NULL COMMENT 'End of Support',
  battery_last_replaced DATE NULL COMMENT 'Para UPS',
  disk_health_status ENUM('ok','warning','failing','unknown') DEFAULT 'unknown',
  firmware_version VARCHAR(255) NULL,
  firmware_latest VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_eol (eol_date),
  INDEX idx_warranty (warranty_end_date)
) ENGINE=InnoDB;
```

## Entregables de Código

### 1. Script Python: `monitor/topology_builder.py`

```python
async def build_topology_from_lldp(device_id):
    """Walk de LLDP-MIB y CDP-MIB."""

async def build_topology_from_fdb(device_id):
    """Inferir enlaces vía FDB (MACs por puerto)."""

async def render_topology_graph():
    """Genera JSON para Cytoscape.js."""
```

### 2. Script Python: `monitor/wol_sender.py`

```python
def send_magic_packet(mac, broadcast):
    """UDP magic packet."""
```

### 3. Script Python: `monitor/predictive_analyzer.py`

```python
async def analyze_trends():
    """
    Para cada métrica clave (CPU, memoria, latencia):
    - Calcular tendencia lineal (regresión simple)
    - Detectar outlier si valor > μ + 3σ
    - Registrar en predictive_anomalies
    """
```

### 4. Comandos del Bot
- `/topologia` — Enlace al mapa visual
- `/wol <mac_o_nombre>` — Encender equipo
- `/predicciones` — Anomalías detectadas
- `/inventario [equipo]` — Ciclo de vida

### 5. Dashboard
- **Página `/topologia`:** Cytoscape.js con mapa interactivo
- **Botón "Encender"** en cada dispositivo WoL habilitado
- **Widget "Predicciones"** en Columna 3
- **Widget "Hardware EOL"** con alertas de vencimiento

### 6. Inmunidad Git y Replicación
- Proteger tablas: `network_topology_links`, `wol_devices`, `predictive_anomalies`, `hardware_lifecycle`
- Incluir en payload (excepto `predictive_anomalies` que es local al Master)

---

# 🔗 FASE 8: INTEGRACIÓN FINAL Y CONSIDERACIONES

## Entregables

### 1. Actualización de `/api/cluster/push-snapshot`

`app/Http/Controllers/Api/ClusterController.php`

Incluir en payload (con paginación para tablas grandes):

```php
$payload = [
    'discovered_devices' => DiscoveredDevice::active()->get(),
    'discovery_subnets' => DiscoverySubnet::active()->get(),
    'snmp_devices' => SnmpDevice::active()->get()->map->withoutSecrets(),
    'snmp_oids' => SnmpOid::active()->get(),
    'snmp_device_oids' => SnmpDeviceOid::active()->get(),
    'snmp_interfaces' => SnmpInterface::all(),
    'snmp_metrics_history' => SnmpMetricHistory::recent(24)->get(),
    'snmp_interface_metrics' => SnmpInterfaceMetric::recent(24)->get(),
    'ssl_certificates' => SslCertificate::active()->get()->map->withoutPem(),
    'alerts' => Alert::active()->get(),
    'maintenance_windows' => MaintenanceWindow::active()->get(),
    'device_configurations' => DeviceConfiguration::latestPerDevice()->get()->map->withoutFullConfig(),
    'netflow_top_talkers' => NetflowTopTalker::latest()->get(),
    'snmp_traps_received' => SnmpTrapReceived::recent(24)->get(),
];
```

**Consideración crítica:** El payload puede crecer mucho. Implementar **compresión gzip** y **chunking** si supera 10MB.

### 2. Actualización de `self_heal_environment.py`

Añadir TODAS las tablas nuevas a `PROTECTED_TABLES`:

```python
PROTECTED_TABLES = [
    # Existentes
    'monitored_services', 'monitored_sites', 'monitored_site_devices',
    'monitored_network_devices', 'monitored_proxies', 'monitoring_snapshots',
    # Fase 1
    'discovery_subnets', 'discovery_scans', 'discovered_devices',
    'discovered_device_history', 'oui_vendors',
    # Fase 2
    'snmp_oids', 'snmp_devices', 'snmp_device_oids',
    'snmp_metrics_history', 'snmp_interfaces', 'snmp_interface_metrics',
    'snmp_activation_log', 'snmp_traps_received',
    # Fase 3
    'ssl_certificates', 'ssl_certificate_history',
    # Fase 4
    'alert_rules', 'alert_escalation_levels', 'alert_correlation_groups',
    'alert_correlation_members', 'alerts', 'alert_notifications',
    'maintenance_windows', 'alert_storm_suppression',
    # Fase 5
    'device_configurations', 'config_change_logs',
    # Fase 6
    'netflow_records', 'syslog_events', 'netflow_top_talkers',
    # Fase 7
    'network_topology_links', 'wol_devices', 'predictive_anomalies',
    'hardware_lifecycle',
]
```

### 3. Actualización de `CleanMonitoringSeeder.php`
- Todos los seeders con `updateOrCreate`
- NO insertar datos ficticios en tablas de producción
- Preservar configuraciones existentes

### 4. Actualización de `deploy_pipeline.sh`

```bash
# Verificar dependencias Python
REQUIRED_PYTHON_PACKAGES=(
  "pysnmp-lextudio"
  "netmiko"
  "scapy"
  "cryptography"
  "python-nmap"
  "playwright"
  "pymysql"
  "httpx"
)
for pkg in "${REQUIRED_PYTHON_PACKAGES[@]}"; do
  pip show "$pkg" >/dev/null 2>&1 || pip install "$pkg"
done

# Verificar dependencias del sistema
command -v nmap >/dev/null || apt-get install -y nmap
command -v snmptrapd >/dev/null || apt-get install -y snmptrapd snmp-mibs-downloader
command -v nfcapd >/dev/null || apt-get install -y nfdump
```

### 5. Documentación `docs/bot_commands.md`
Documentar TODOS los comandos nuevos organizados por categoría y nivel.

### 6. README Actualizado
Secciones nuevas: funcionalidades, requisitos, configuración, comandos.

### 7. Script de Pruebas de Integración `tests/integration_test.py`

Probar:
- Discovery en subred local
- SNMP poll a dispositivo de prueba
- Extracción de certificado HTTPS
- Disparo y escalación de alerta
- Cálculo de jitter/packet loss
- Backup de config de dispositivo de prueba
- Recepción de trap SNMP simulado
- Recepción de syslog simulado
- Envío de WoL

## Consideraciones Finales

### Rendimiento
- **Purga automática** de tablas de histórico:
  - `snmp_metrics_history`: retener 30 días
  - `snmp_interface_metrics`: retener 30 días
  - `netflow_records`: retener 7 días
  - `syslog_events`: retener 30 días
  - `snmp_traps_received`: retener 90 días
- **Tablas de resumen horario** para retención de 1 año
- **Particionado por mes** en tablas grandes (`snmp_metrics_history`, `netflow_records`)

### Seguridad
- **NUNCA** credenciales en texto plano ni en logs
- **Cifrar** con `encrypt()` de Laravel
- **Validar** todos los inputs del bot (regex para IPs, MACs, etc.)
- **Rate limiting** en comandos del bot para evitar abuso
- **Auditoría** de TODA acción administrativa

### Escalabilidad
- Sistema actual escala hasta ~1000 dispositivos con polling secuencial cada 60s
- Para más dispositivos:
  - Distribuir polling entre workers con `multiprocessing`
  - Migrar a **TimescaleDB** para métricas
  - Usar **Redis** como cola de tareas
  - Separar NetFlow/Syslog a servidor dedicado

### Criterios de Aceptación Globales
1. ✅ Las 8 fases implementadas y probadas
2. ✅ Migraciones ejecutan sin error
3. ✅ Seeders idempotentes
4. ✅ Scripts Python funcionales con datos reales
5. ✅ Comandos del bot responden correctamente
6. ✅ Dashboard renderiza sin errores JS
7. ✅ Replicación Master/Slave funciona para TODAS las tablas
8. ✅ Inmunidad Git protege TODAS las tablas
9. ✅ Documentación completa
10. ✅ Tests de integración pasan

### Anti-patrones a Evitar
- ❌ NO usar `SELECT *` en tablas grandes
- ❌ NO cargar todo el histórico en memoria
- ❌ NO bloquear el event loop con operaciones sincrónicas
- ❌ NO hardcodear IPs, comunidades o credenciales
- ❌ NO usar `public` como comunidad SNMP por defecto
- ❌ NO ejecutar discovery sin rate limiting
- ❌ NO saltarse la auditoría
- ❌ NO romper la inmunidad Git con strings sanitizables

---

# 🎬 ORDEN DE EJECUCIÓN FINAL

```
1. FASE 1 (Discovery)        → 2 semanas
2. FASE 2 (SNMP)             → 2 semanas
3. FASE 3 (SSL)              → 1 semana
4. FASE 4 (Alertas)          → 2 semanas
5. FASE 5 (WAN + Configs)    → 1.5 semanas
6. FASE 6 (Traps + NetFlow)  → 2 semanas
7. FASE 7 (Topología + IA)   → 2.5 semanas
8. FASE 8 (Integración)      → 1 semana
─────────────────────────────────────────
TOTAL:                       ~14 semanas
```

**Instrucción final para la IA:** Comienza por la **FASE 1**. NO avances a la siguiente fase hasta que la actual cumpla TODOS los criterios de "Done". Si encuentras ambigüedad, consulta `especificaciones-tecnicas.md` y prioriza la coherencia arquitectónica sobre la velocidad de implementación.

---

**¡COMIENZA CON LA FASE 1: AUTO-DISCOVERY!**

---

# 📌 RESUMEN DE MEJORAS RESPECTO AL PROMPT ORIGINAL

| # | Mejora nueva | Fase | Justificación |
|---|---|---|---|
| 1 | **SNMP Traps** (modelo push) | 6 | Detección en segundos vs. próximo ciclo |
| 2 | **NetFlow/sFlow** | 6 | Ver *quién* consume ancho de banda |
| 3 | **Syslog centralizado** | 6 | Correlación de eventos |
| 4 | **Mapa topológico LLDP/CDP** | 7 | Visualización real de enlaces |
| 5 | **Wake-on-LAN** | 7 | Complemento natural a VNC existente |
| 6 | **Análisis predictivo con IA** | 7 | Aprovecha el Motor Local de IA actual |
| 7 | **Inventario de ciclo de vida** | 7 | UPS, discos, firmware EOL |
| 8 | **Alert storm suppression** | 4 | Evita spam de alertas |
| 9 | **Discovery pasivo vía SNMP** | 1 | Descubre vecinos sin escanear |
| 10 | **Anti-rogue automático** | 1 | Detección BYOD |
| 11 | **OUI vendor lookup** | 1 | Identificación de fabricantes |
| 12 | **Diff visual de configs** | 5 | Auditoría de cambios |

---


