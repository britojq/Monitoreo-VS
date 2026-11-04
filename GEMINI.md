# 🏛️ REGLAS DE ORO Y PROTOCOLO OPERATIVO DEL SISTEMA S.O.N.A.R.
# Directrices Supremas de Ingeniería, Seguridad, Calidad y Continuidad Obligatoria

> [!IMPORTANT]
> **DIRECTRIZ RECTORA: ACTIVACIÓN PERMANENTE E INCONDICIONAL**  
> Este marco de directrices rige de forma automática e ineludible en **cada interacción y mensaje** del usuario. Queda definitivamente erradicada la necesidad de frases disparadoras como *"lee la base y procede"*. El asistente asume estas directrices como su núcleo de operación permanente.
> - Si el usuario instruye **"responde solamente"** (o solicita análisis/explicaciones): queda **estrictamente prohibido** editar código, ejecutar scripts modificadores o alterar la base de datos y configuraciones.

---

## 🧭 SECCIÓN I: PROTOCOLO DE CONTINUIDAD Y CICLO DE TRABAJO (BASE OPERATIVA)

### 1. La Arquitectura Documental de Control (Puntero Vivo + Memoria Forense)
Para erradicar amnesias, pérdidas de contexto y truncados por tamaño:
1. **`docs/ESTADO_VIVO.md` (Puntero Atómico de Carga Rápida):** Mide siempre <20 líneas (<1KB). Contiene la versión oficial, la macro-tarea activa, la última fase completada, el hash del commit oficial y el próximo paso inmediato obligatorio. Es la primera fuente obligatoria que el asistente consulta para conocer el estado exacto en 0.1s sin riesgo de truncado.
2. **`docs/historico_sesiones/HISTORICO_SESIONES.md` (Memoria Forense en Orden Cronológico Inverso):** Toda acción ejecutada se anexa en el **tope del archivo** (Línea 20). Lo más reciente siempre reside en las primeras líneas. (`ESTADO_ACTUAL_Y_CONTINUIDAD.md` se preserva como enlace simbólico de compatibilidad).
3. **`docs/indicaciones.md` (Contrato y Transición de Fases):** Registra la orden activa y el puntero hacia la siguiente fase; nunca queda vacío entre fases intermedias.
4. **`docs/PENDIENTES.md` (Hoja de Ruta Fáctica):** Catálogo macro de fases consolidadas.

### 2. Mandato de Cierre Automatizado y Sello de Continuidad Obligatorio (ERROR #25)
* **Prohibición de Cierre Prematuro o Manual:** Queda terminantemente prohibido dar por concluida una tarea o emitir una respuesta de entrega al usuario sin ejecutar el orquestador atómico:
  ```bash
  python tools/close_phase.py --task "..." --phase "..." --next "..."
  ```
* **El Sello de Continuidad y Certificación Atómica Dual:** Toda respuesta de entrega técnica debe culminar obligatoriamente con el bloque del Sello emitido por `tools/close_phase.py`:
  ```markdown
  ---
  🛡️ SELLO DE CONTINUIDAD Y CERTIFICACIÓN ATÓMICA DUAL
  * Commit Oficial Privado : `[hash]` (tgbot-pyt-bashfull:master)
  * Commit Oficial Público : `[public_hash]` (Monitoreo-VS:master)
  * Versión Certificada    : v[version] (Tags sincronizados en ambos remotos)
  * Estado Vivo            : docs/ESTADO_VIVO.md actualizado (Próximo paso: [next])
  * Histórico              : docs/historico_sesiones/HISTORICO_SESIONES.md actualizado en el tope
  * Certificación          : tools/certify_system_integrity.py (100% Zero-Fail)
  ---
  ```
  Si este sello con datos reales no está presente en la respuesta, **la entrega carece de validez técnica y será rechazada**.

### 3. El Ciclo de 6 Pasos Ineludible
* **Paso 1: Transcripción Textual:** Registrar la orden exacta en `docs/indicaciones.md`.
* **Paso 2: Consulta de Pilares & Auditoría OCP:** Evaluar el Mandato de Extensión Limpia (Open/Closed Principle). Toda nueva funcionalidad se construye por **extensión** (código nuevo sin alterar firmas consolidadas). La modificación de código preexistente queda reservada **única y exclusivamente para reparaciones documentadas (`fix`)**.
* **Paso 3: Ejecución Holística por Fases:** Implementar la solución técnica completa sin parches reactivos ("modo bombero").
* **Paso 4: Verificación Textual Estricta:** Cotejar la solución contra `docs/indicaciones.md`. Si no cumple el 100% de lo solicitado, se replantea y se rehace desde cero (en entorno de desarrollo es obligatorio rehacer hasta alcanzar la perfección).
* **Paso 5: Cierre, Calidad y Traspaso:** Verificar raíz limpia, actualizar la Trilogía Documental, ejecutar `tools/certify_system_integrity.py` y sincronizar (`git push`).
* **Paso 6: Parada Activa:** Presentar evidencias, detenerse por completo y **esperar confirmación explícita del usuario** antes de continuar.

---

## 🛠️ SECCIÓN II: LOS 6 PILARES TÉCNICOS DE INGENIERÍA

1. **Laravel 13 / PHP 8.4:**  
   * Declaración estricta `declare(strict_types=1);` obligatoria en todo archivo PHP.
   * Controladores delgados (<15 líneas por método); lógica encapsulada en Actions de responsabilidad única y DTOs inmutables.
   * Validación exclusiva mediante `FormRequest` desacoplados.
   * Autosuficiencia absoluta de assets: **terminantemente prohibido el uso de CDNs o enlaces externos**. Todos los CSS, JS, fuentes e iconos deben residir localmente en `web_portal/public/vendor/`.
   * Preflight de Tailwind desactivado; componentes estilizados mediante aislamiento con clase contenedora `.tw-wrapper`.
2. **Python 3.13 / SOLID:**  
   * Tipado estricto en el 100% de firmas (`typing`).
   * Prohibido intercambio de diccionarios crudos entre capas; uso de DTOs inmutables (`@dataclass(frozen=True)`).
   * Contratos explícitos mediante `typing.Protocol` o `abc.ABC`.
   * Ejecución segura de procesos con `subprocess.run` pasando listas de argumentos (cero `shell=True` o comandos interpolados).
3. **Bash / Shell Linux:**  
   * Cabecera obligatoria `set -euo pipefail`.
   * Comillas estrictas en toda variable `"$var"`, uso de arrays y manejo de señales (`trap`).
4. **Seguridad Web OWASP:**  
   * Validación 100% en backend (nunca confiar en inputs del navegador).
   * Modelos Eloquent protegidos con `$fillable` explícito (prohibido `$guarded = []`).
   * Cero consultas SQL crudas con variables interpoladas (`DB::raw`).
   * Escape XSS con `{{ }}` en Blade (prohibido `{!! !!}` para variables de usuario).
   * Tokens `@csrf` obligatorios y autorización formal con Policies/Gates.
5. **Prevención Activa de Errores:**  
   * Consulta obligatoria de [`docs/historico_sesiones/errores-cometidos.md`](file:///opt/sonar/docs/historico_sesiones/errores-cometidos.md) y ejecución de la auto-auditoría previa a responder.
6. **Contexto Vivo de Red e Infraestructura:**  
   * Respeto a la topología corporativa, direccionamiento IPAM y roles de nodo.

---

## 🚨 SECCIÓN III: LAS 14 REGLAS DE ORO INVIOLABLES

### Regla #0: Protocolo Supremo de Control y Pilares Técnicos
Ejecución permanente del ciclo de calidad, la Trilogía Documental y los 6 pilares de ingeniería en cada requerimiento.

### Regla #1: Prohibición Absoluta de Despachos a Grupos de Telegram
* Bajo **ninguna circunstancia**, prueba, simulación o evento se enviarán mensajes al grupo corporativo (`-1001383163558` o cualquier ID negativo).
* Las alertas de seguridad, auditoría, inicios de sesión y pruebas son de **uso exclusivo y estricto del chat privado del Administrador (`owner_id`: `38914901`)**.
* Todo servicio despachador debe filtrar y anular en código cualquier intento de envío grupal para eventos de seguridad.

### Regla #2: Prohibición Absoluta de Mencionar la Tecnología de IA Subyacente
Terminantemente prohibido mencionar el nombre del motor local ("Ollama") en interfaces web, Telegram, logs visibles o respuestas al usuario. Referirse siempre como "Asistente Corporativo" o "Motor de Inteligencia".

### Regla #3: Cumplimiento Estricto del Modo "Responde Solamente"
Ante frases como "responde solamente", "solo explica" o "dame un análisis", el asistente debe limitarse a responder textualmente, quedando **estrictamente inhabilitado para modificar código, base de datos o configuraciones**.

### Regla #4: Aislamiento Absoluto de Servidores y Entornos
* **Servidor de Producción (`192.168.1.250`):** Es **intocable**. Prohibido ejecutar comandos, alterar su base de datos o desplegar sin orden expresa y modo mantenimiento (`php artisan down` / `up`).
* **Servidor de Desarrollo (`192.168.1.220`, `CENCARATIT`):** Entorno de trabajo autorizado (`/opt/sonar`). Se permite rehacer de raíz bajo condición de cero regresiones.

### Regla #5: Veracidad Fáctica y Auditoría Real
* Prohibición absoluta de inventar datos técnicos, sistemas operativos, IPs o estados.
* Toda afirmación debe sustentarse en consultas fácticas a MariaDB SSOT o hardware real.
* En la empresa **no existen equipos con Windows 10**; el parque es 100% Windows 7 (Ultimate) y Ubuntu/Xubuntu Linux.

### Reglas #6 y #7: MariaDB (`monitoreo_vs`) como Única Fuente de Verdad (SSOT) y Cero Datos Ficticios
* **Cero Datos Dummy:** Prohibidos seriales sintéticos (`00000000`, `SN-NET-%`), IPs de prueba (`10.20.99.X`) o puertos genéricos (`1x GE`).
* **PROHIBICIÓN TOTAL DE ARCHIVOS PLANOS:** Queda terminantemente prohibido el uso de archivos planos (`.conf`, `.json`, `.log` en disco) para gobernar o persistir configuración, estados o auditoría.
* Todo parámetro operativo del bot y de la plataforma debe residir en la tabla `bot_settings`.
* Toda bitácora de seguridad o auditoría debe residir en la tabla `audit_logs`.
* Los demonios del sistema deben canalizar sus registros operativos hacia **`systemd-journald`**.

### Regla #8: Consistencia e Integridad de Activos Troncales
* **Router Principal (ID 1):** Obligatoriamente `TELNET:23`, IP `192.168.1.1`, tipo `router`.
* **Servidor de Desarrollo (ID 11519):** IP `192.168.1.220`, hostname `CENCARATIT`.

### Regla #9: Prohibición de Términos Vetados
Prohibido el uso de términos obsoletos o no autorizados:
* Prohibido `"360°"` o `"360 grados"`.
* Prohibido el término `"custodio"`; utilizar siempre **"Responsable"** o **"Funcionario"**.

### Regla #10: Política de Raíz 100% Limpia y Ubicación Canónica
* **Toda documentación técnica reside exclusivamente en `docs/`:** Prohibido crear archivos `.md`, guías o notas sueltas en la raíz del proyecto (`/opt/sonar`).
* Los únicos archivos de documentación autorizados en raíz son `README.md` y `GEMINI.md`.

### Regla #11: Prohibición de Badges con Texto en Títulos
En cabeceras `<h1>` y `<h2>`, queda prohibido colocar píldoras con texto visible. Únicamente se permiten iconos compactos con tooltip informativo (`title="..."`).

### Regla #12: Control de Versión Atómico y Paridad Aritmética Obligatoria
* Todo cambio debe incrementar la versión mediante `tools/bump_version.py` (ej. `v5.37.40`).
* Paridad bit a bit obligatoria entre `admin.blade.php`, `RuntimeParityInspector.php` y `HelpController.php`.

### Regla #13: Bóveda Criptográfica Anclada al Hardware
* Los tokens de bot jamás se guardan en texto plano en la base de datos ni en archivos (`[ENCRYPTED_HARDWARE_VAULT]`).
* La clave se desenrolla en RAM volátil mediante la bóveda criptográfica del nodo físico (`system/vault/.sys_anchor`).
* Repositorio público estrictamente sanitizado de IPs privadas, seriales corporativos o scripts internos.

### Regla #14: Prohibición de Desarrollo Reactivo ("Modo Bombero")
* Prohibido aplicar parches aislados sin comprender el modelo holístico de MariaDB y el impacto en los 4 pilares (Telegram, SSH/PAM, MariaDB SSOT y Web).
* Todo trabajo debe dividirse en fases cerradas con verificación integral previa al cierre.

---

## 🔍 SECCIÓN IV: LISTA DE CHEQUEO PREVIA A RESPONDER (AUTO-AUDITORÍA)

Antes de entregar cualquier código o respuesta al usuario, el asistente debe validar mentalmente:
1. **¿El usuario ordenó "responde solamente"?** Si es afirmativo, verificar que ningún archivo haya sido modificado.
2. **¿Se intentó crear o leer archivos de configuración planos en disco?** Si es afirmativo, abortar y redirigir a MariaDB `bot_settings`.
3. **¿Se asumió algún dato técnico sin consultar la base de datos?** (Ej. sistemas operativos, IPs).
4. **¿Se enviaron mensajes al grupo de Telegram (`-1001383163558`)?** Garantizar cero despachos a grupos.
5. **¿Se mantuvo la raíz del proyecto limpia sin archivos `.md` sueltos?**
6. **¿Se ejecutó la certificación `tools/certify_system_integrity.py` con 100% Zero-Fail (incluyendo Fase 6 de Estado Vivo)?**
7. **¿Se ejecutó `tools/close_phase.py` y se estampó el Sello de Continuidad y Certificación Atómica con su hash real?**
