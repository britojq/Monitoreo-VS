# MANUAL TÉCNICO: API DE VERIFICACIÓN REMOTA DE CLÚSTER Y PARIDAD (ZERO-SSH)

> **Documentación Oficial del Sistema de Monitoreo Valle Seco (NOC & ITAM)**  
> **Fecha:** Octubre 2026 | **Versión:** 5.33.50  
> **Arquitectura:** SOLID Desacoplada | **Enfoque:** Prevención de Alertas PAM y Auditoría Forense Inmutable

---

## 1. INTRODUCCIÓN Y FILOSOFÍA "ZERO-SSH"

En la infraestructura de alta seguridad del clúster, toda conexión interactiva saliente vía SSH hacia el nodo de Producción dispara alertas inmediatas de seguridad por PAM hacia el canal privado del Administrador (en estricto apego a la **Regla de Oro #5**). 

Para auditar la salud del sistema, verificar la paridad de código, confirmar la integridad de los instaladores y supervisar el despliegue de agentes sin recurrir a sesiones SSH interactivas, se implementó el ecosistema de **Verificación Técnica Remota de Clúster (Zero-SSH)**.

### Beneficios Clave
1. **Auditoría Forense Automática:** Cada consulta queda registrada de forma inmutable en la tabla `audit_logs` con IP, fecha, alcance consultado y resultado.
2. **Camuflaje de Seguridad (Stealth Middleware):** Si un atacante o escáner no autenticado intenta acceder a la API, recibe `404 Not Found` en lugar de `401 Unauthorized` o `403 Forbidden`, ocultando la existencia misma del endpoint.
3. **Inspección Profunda:** Consolida en milisegundos la verificación de binarios instaladores (hashes SHA-256), censo de agentes GLPI, estado de Git, migraciones de base de datos y puertos del sistema.

---

## 2. ARQUITECTURA Y PRINCIPIOS SOLID

El módulo está diseñado bajo el estándar **SOLID** en Laravel:

- **S (Single Responsibility Principle):**
  - `InstallerIntegrityInspector`: Exclusivamente responsable de validar el repositorio físico de instaladores (`/public/instaladores`).
  - `GlpiAgentCensusCollector`: Exclusivamente responsable de tabular el censo, adopción y salud de los agentes de inventario.
  - `RuntimeParityInspector`: Exclusivamente responsable de auditar Git, estado de MariaDB, servicios systemd y sockets de red.
  - `ClusterAuditLoggerService`: Exclusivamente responsable de la persistencia forense en `audit_logs`.
- **O (Open/Closed Principle):** Se pueden añadir nuevos inspectores (ej. inspección de certificados SSL o telemetría de UPS) implementando nuevas rutinas sin alterar el orquestador principal.
- **L (Liskov Substitution Principle):** Cumplimiento riguroso de interfaces.
- **I (Interface Segregation Principle):** Interfaces segregadas `ClusterVerifierInterface` y `ClusterAuditLoggerInterface`.
- **D (Dependency Inversion Principle):** `ClusterVerifierService` depende de abstracciones registradas como *singletons* en `AppServiceProvider`.

---

## 3. ENDPOINTS DISPONIBLES EN LA API

Todos los endpoints residen bajo el prefijo `/api/cluster/` y están protegidos por el middleware `ValidateClusterToken`.

### Cabeceras Obligatorias de Autenticación y Anti-Replay (Fail-Closed F-09)
Toda solicitud hacia los endpoints de clúster debe incluir de forma obligatoria las siguientes cabeceras:
```http
X-Cluster-Token: <CLUSTER_TOKEN_SHA256>
X-Cluster-Timestamp: <TIMESTAMP_UNIX>
X-Cluster-Signature: <HMAC_SHA256_HEX>
```

> [!IMPORTANT]
> **Eliminación de Parámetros en Query String y Blindaje Anti-Replay:**
> 1. Queda estrictamente erradicado el envío de credenciales mediante query params (`?cluster_token=...` o `?token=...`).
> 2. `X-Cluster-Timestamp` debe encontrarse dentro de una ventana de tolerancia máxima de ±300 segundos respecto al reloj del servidor.
> 3. `X-Cluster-Signature` es el HMAC-SHA256 calculado con la clave `X-Cluster-Token` sobre el string `"{timestamp}:{raw_payload}"` (para peticiones `GET` sin cuerpo, el payload es vacío: `"{timestamp}:"`).
> 4. Toda firma vista es almacenada en caché volátil por 300 segundos; cualquier intento de reutilización de la misma firma (ataque de repetición / Replay Attack) es abortado con código HTTP `403 Signature Already Used`.
> 5. Si cualquiera de las cabeceras requeridas no está presente, el middleware responde `404 Not Found` (Stealth Camouflage).

---

### Endpoint 1: Diagnóstico y Verificación Integral
- **Método:** `GET`
- **Ruta:** `/api/cluster/verify`
- **Parámetros Opcionales:**
  - `scope`: Ámbito de inspección (`all`, `installers`, `agents`, `parity`). Por defecto: `all`.

#### Ejemplo de Solicitud (Bash / cURL):
```bash
TIMESTAMP=$(date +%s)
TOKEN="YOUR_CLUSTER_TOKEN"
SIGNATURE=$(printf "%s:" "$TIMESTAMP" | openssl dgst -sha256 -hmac "$TOKEN" | awk '{print $2}')

curl -s \
  -H "X-Cluster-Token: $TOKEN" \
  -H "X-Cluster-Timestamp: $TIMESTAMP" \
  -H "X-Cluster-Signature: $SIGNATURE" \
  "http://192.168.1.250/api/cluster/verify?scope=all"
```

#### Estructura de Respuesta JSON (200 OK):
```json
{
  "success": true,
  "node_role": "MASTER",
  "hostname": "CENCARATIT",
  "scope": "all",
  "status": "healthy",
  "timestamp": "2026-10-09T13:29:34-04:00",
  "summary": {
    "core_services_ok": true,
    "all_installers_present": true,
    "migrations_synced": true,
    "git_is_clean": false,
    "glpi_active_agents": 12
  },
  "installers": {
    "directory_path": "/var/www/monitoreo/public/instaladores",
    "total_tracked": 7,
    "present_count": 7,
    "all_present": true,
    "files": {
      "GLPI-Agent-x86.msi": {
        "present": true,
        "size_bytes": 19388416,
        "size_human": "18.49 MB",
        "sha256": "db492b326a54d6fb35cf1f5869403ecbe7b415e98b0f7721a30c5baaa8d73b57",
        "last_modified": "2026-09-29T11:56:01-04:00"
      },
      "GLPI-Agent-x64.msi": {
        "present": true,
        "size_bytes": 23269376,
        "size_human": "22.19 MB",
        "sha256": "3767a04de426563dbff9315bc2f24d7cb0fca3be4a1d8a43657b985473722a4d",
        "last_modified": "2026-09-29T11:56:00-04:00"
      },
      "glpi-agent_all.deb": { "present": true, "size_human": "4.02 MB" },
      "glpi-linux.sh": { "present": true, "size_human": "4.21 KB" },
      "glpi-win.ps1": { "present": true, "size_human": "6.29 KB" },
      "glpi-switch.sh": { "present": true, "size_human": "1.11 KB" },
      "glpi-switch.ps1": { "present": true, "size_human": "1.64 KB" }
    }
  },
  "glpi_agents": {
    "total_inventory_assets": 37,
    "glpi_installed_total": 12,
    "glpi_reporting_24h": 12,
    "glpi_offline_count": 0,
    "adoption_percentage": 32.4,
    "breakdown_by_os": {
      "windows_32": 1,
      "windows_64": 4,
      "linux": 7
    },
    "recent_agents": [
      {
        "id": 1,
        "device_name": "EQUIPO MONITOREO ATIT",
        "ip": "192.168.1.250",
        "os_family": "linux",
        "last_contact": "2026-10-09T12:52:23-04:00"
      }
    ],
    "receiver_endpoint": "http://192.168.1.250/front/inventory.php",
    "token_tag": "VALLE-SECO-ASSETS"
  },
  "runtime_parity": {
    "platform": {
      "system_version": "v5.52.20",
      "laravel_version": "13.11.2",
      "php_version": "8.4.26",
      "hostname": "CENCARATIT"
    },
    "git": {
      "commit_short": "1dd4871",
      "branch": "master",
      "is_clean": false,
      "uncommitted_files_count": 30
    },
    "database_census": {
      "inventory_assets": 37,
      "monitored_network_devices": 37,
      "discovered_devices": 42,
      "audit_logs": 34125
    },
    "migrations": {
      "applied_count": 48,
      "files_count": 48,
      "is_synchronized": true
    },
    "system_services": {
      "tg-admin-bot": "active",
      "apache2": "active",
      "mariadb": "active",
      "php-fpm": "php8.4-fpm (active)",
      "glpi-agent": "active"
    },
    "port_listeners": {
      "80": true,
      "443": false,
      "3306": true,
      "162": true,
      "514": true,
      "2055": true
    }
  }
}
```

---

### Endpoint 2: Bitácora Forense de Auditoría de Clúster
- **Método:** `GET`
- **Ruta:** `/api/cluster/audit-trail`
- **Parámetros Opcionales:**
  - `limit`: Cantidad máxima de registros a devolver (por defecto: 50, máx: 200).

Devuelve los últimos eventos registrados por sincronizaciones o verificaciones remotas para trazabilidad administrativa.

---

### Endpoint 3: Telemetría Enriquecida de Clúster
- **Método:** `POST`
- **Ruta:** `/api/cluster/telemetry`

Permite al nodo Esclavo recibir no sólo métricas de monitoreo, sino también:
- Dispositivos descubiertos en radar (`discovered_devices`).
- Comandos interactivos del bot (`bot_commands`).
- Plantillas de mensajes corporativos (`bot_message_templates`).
- Auditoría cruzada bidireccional.

---

## 4. HERRAMIENTA CLI: `tools/verify_cluster_parity.py`

Se provee un script CLI en Python que encapsula las consultas HTTP, valida los certificados y presenta un reporte legible por terminal o en JSON para automatizaciones.

### Uso Básico (Verificar el nodo local o esclavo):
```bash
/opt/sonar/venv/bin/python3 tools/verify_cluster_parity.py --url http://127.0.0.1
```

### Verificar el Servidor Master / Producción (Zero-SSH):
```bash
/opt/sonar/venv/bin/python3 tools/verify_cluster_parity.py --url http://192.168.1.250
```

### Opciones Disponibles:
| Parámetro | Descripción |
|---|---|
| `--url <URL>` | URL base del servidor a inspeccionar (ej: `http://192.168.1.250`). Por defecto: `http://127.0.0.1`. |
| `--token <TOKEN>` | Token SHA-256 del clúster. Si se omite, se lee automáticamente de `config/config.json`. |
| `--scope <all\|installers\|agents\|parity>` | Limita el análisis a instaladores, agentes o runtime. |
| `--json` | Muestra la salida en formato JSON puro (útil para pipelines CI/CD o scripts de monitoreo). |

---

## 5. TRAZA FORENSE EN BASE DE DATOS (`audit_logs`)

Cada invocación genera un registro inmutable en MariaDB:

| Campo | Valor Típico |
|---|---|
| `user_id` | `null` (Consulta API de Sistema) |
| `ip_address` | IP del nodo o cliente que invoca la petición |
| `module` | `Cluster API` |
| `event` | `cluster_api_verification` |
| `description` | `Inspección remota de salud y paridad del clúster (scope: all, status: healthy)` |
| `user_agent` | Identificador del cliente HTTP / Python / cURL |
| `created_at` | Timestamp con zona horaria oficial |

---

## 6. SUITE DE PRUEBAS AUTOMATIZADAS

La funcionalidad cuenta con cobertura 100% en:
- `web_portal/tests/Feature/ClusterVerifyApiTest.php`:
  1. `test_verify_endpoint_without_token_returns_404`: Certifica camuflaje sin token.
  2. `test_verify_endpoint_with_invalid_token_returns_404`: Certifica camuflaje ante token erróneo.
  3. `test_verify_endpoint_with_valid_token_returns_full_health_structure`: Valida respuesta 200 y JSON completo.
  4. `test_verify_endpoint_scoped_to_installers`: Valida filtrado por scope.
  5. `test_verify_endpoint_automatically_records_immutable_audit_log`: Certifica inserción en `audit_logs`.
  6. `test_audit_trail_endpoint_returns_cluster_logs`: Valida consulta de logs.

Para ejecutar los tests:
```bash
cd /var/www/monitoreo && ./vendor/bin/phpunit --filter=ClusterVerifyApiTest
```
