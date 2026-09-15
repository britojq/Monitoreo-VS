# REGLAS DE ORO DEL PROYECTO (DIRECTRICES ESTRICTAS E INVIOLABLES)

## 🚨 REGLA DE ORO #1: PROHIBICIÓN ABSOLUTA DE ENVIAR MENSAJES DE PRUEBA O NOTIFICACIONES DE SEGURIDAD AL GRUPO

1. **PROHIBICIÓN ESTRICTA DE MENSAJES DE PRUEBA AL GRUPO:**
   Bajo NINGÚN motivo, circunstancia, simulación o prueba (manual o automatizada con Playwright, curl, scripts de testing, etc.) se debe enviar ningún mensaje al grupo corporativo de Telegram (`-1001383163558` o cualquier chat ID negativo).

2. **USO EXCLUSIVO DEL OWNER PARA SEGURIDAD:**
   Todas las notificaciones de seguridad, inicios de sesión (locales o LDAP), auto-registros de usuarios, alertas de accesos, intentos fallidos y auditoría son de **USO ESTRICTO Y EXCLUSIVO DEL OWNER / ADMINISTRADOR PRIVADO (`owner_id` / `IDC`: `38914901`)**. El grupo general de la sede JAMÁS debe ser receptor de estos avisos.

3. **FILTRO PERMANENTE EN CÓDIGO (BACKEND Y SERVICIOS):**
   - En cualquier despachador o servicio (`TelegramNotificationService.php`, `monitor_engine.py`, `bot.py`, etc.), se debe filtrar explícitamente y descartar cualquier `chat_id` que pertenezca a grupos (identificadores negativos) para cualquier evento de login o seguridad.
   - Las alertas de inicio de sesión web (local y LDAP) deben notificarse siempre al chat privado del Administrador (`owner_id`: `38914901`) tanto en el servidor de desarrollo como en producción, bloqueando única y estrictamente cualquier despacho hacia grupos.

4. **CONFIGURACIÓN RESPETADA:**
   En `config/bot.conf`, la directiva `IDA` debe mantenerse siempre apuntando al ID privado del Administrador (`38914901`) y jamás descomentar la línea del grupo para propósitos de prueba o seguridad.

## 🚨 REGLA DE ORO #2: PROHIBICIÓN ABSOLUTA DE REVELAR LA TECNOLOGÍA SUBYACENTE (OLLAMA) AL USUARIO

1. **PROHIBICIÓN ESTRICTA DE MENCIONAR "OLLAMA" AL USUARIO:**
   Bajo NINGUNA circunstancia, ni por Telegram ni a través del portal Web (mensajes de error, avisos del sistema, interfaces, tooltips, modales, placeholders o respuestas del asistente), se debe mencionar el nombre de la tecnología o motor subyacente ("Ollama").

2. **DENOMINACIÓN CORPORATIVA PERMITIDA:**
   Cualquier mensaje, aviso de indisponibilidad, error o referencia técnica al servicio debe referirse de forma neutral y profesional como:
   - "Motor local de IA"
   - "Servicio local de IA"
   - "Asistente virtual corporativo" / "Monitor Valle Seco"
   - "Servicio de inteligencia artificial"

3. **FILTRO PERMANENTE EN MENSAJES Y CÓDIGO:**
   - En controladores (`AiChatController.php`), vistas (`blade.php`), bot de Telegram (`bot.py`) y cualquier otro módulo de cara al usuario, los textos de error deben mantenerse estrictamente neutrales y sin tecnicismos que expongan las herramientas internas del backend.

## 🚨 REGLA DE ORO #3: INTEGRIDAD DE TELEMETRÍA, HISTÓRICOS Y REPLICACIÓN EN CLUSTER (MODO ESCLAVO Y MASTER)

1. **REPLICACIÓN COMPLETA DE TELEMETRÍA EN NODOS ESCLAVO:**
   - Todo nodo en modo esclavo (`SLAVE`) en el cluster no debe limitarse a recibir el snapshot global en `monitoring_snapshots`. Debe obligatoriamente sincronizar y poblar en tiempo real las 4 tablas de telemetría e histórico en MariaDB:
     - `service_check_histories`
     - `site_check_histories`
     - `proxy_check_histories`
     - `network_device_check_histories`
   - Cualquier nueva métrica, entidad de red o tabla de auditoría/historial que se agregue al ecosistema debe incluirse inmediatamente en la función `sync_from_master()` de `monitor/monitor_web_sync.py` para garantizar paridad absoluta de datos entre Master y Esclavo.

2. **REGLA DE VISIBILIDAD DE GRÁFICOS (BACKEND Y FRONTEND):**
   - La bandera `has_data` jamás debe supeditarse a que el estado sea activo/UP (`$upChecks > 0`). Los eventos de caída (DOWN) o degradación forman parte fundamental del histórico y deben graficarse como líneas/curvas de indisponibilidad (en color rojo `#ef4444`). Ocultar el lienzo por estar caído es un error crítico de monitoreo.
   - En cualquier consulta de histórico (`MonitoringDataService.php`, APIs o vistas), si la ventana de tiempo (ej. 24h) cuenta con menos de 2 puntos por pausas de servicios o mantenimientos, debe implementarse un mecanismo de *fallback* automático que recupere los últimos N registros existentes para evitar canvas vacíos.

3. **VERIFICACIÓN MULTI-NODO ANTES DE CONFIRMAR CAMBIOS:**
   - Todo cambio en el esquema de base de datos, migraciones o lógica de recolección de métricas debe verificarse y contrastarse tanto en el entorno de Desarrollo/Esclavo (`10.20.23.221`) como en el servidor Master/Producción (`10.20.23.252`).

## 🚨 REGLA DE ORO #4: CONTROL ESTRICTO DE REPOSITORIO PÚBLICO Y DESPLIEGUE A PRODUCCIÓN

1. **PROHIBICIÓN ABSOLUTA DE ACTUALIZAR EL REPOSITORIO PÚBLICO SIN PRUEBAS COMPLETAS EN DESARROLLO:**
   Bajo NINGUNA circunstancia se debe actualizar o hacer `git push` al repositorio público (`public` / `Monitoreo-VS`) sin que antes todas las pruebas en el servidor de desarrollo (`10.20.23.221`) hayan concluido exitosamente y certifiquen que la solución es 100% correcta, funcional y libre de errores.

2. **PROHIBICIÓN ESTRICTA DE ACTUALIZAR PRODUCCIÓN SIN AUTORIZACIÓN PREVIA:**
   Está estrictamente prohibido aplicar cambios, sincronizar archivos, ejecutar migraciones o desplegar al servidor de producción (`10.20.23.252`) sin haber solicitado y recibido autorización explícita previa del usuario o sin que este lo indique directamente.

## 🚨 REGLA DE ORO #5: TRANSPARENCIA TOTAL, VERACIDAD ABSOLUTA Y REGISTRO OBLIGATORIO DE CONEXIONES

1. **OBLIGACIÓN DE VERACIDAD Y RESPUESTA DIRECTA:**
   Cuando el usuario pregunte sobre cualquier conexión remota, comando ejecutado o acción realizada en cualquier servidor (especialmente en Producción `10.20.23.252` o Desarrollo `10.20.23.221`), el asistente debe responder con absoluta veracidad, claridad y sin rodeos, detallando exactamente qué comando se ejecutó, por qué medio (SSH, API, CLI, navegador), a qué hora exacta y qué resultado produjo.

2. **PROHIBICIÓN ESTRICTA DE NEGAR, FALSEAR U OCULTAR ACCIONES/CONEXIONES:**
   Bajo NINGUNA circunstancia se debe negar, minimizar u omitir una conexión o comando ejecutado. Queda estrictamente prohibido responder afirmando que "no hubo conexión" o que "no se realizó ninguna acción" sin antes haber auditado minuciosamente el historial completo de subprocesos y herramientas ejecutadas en la sesión.

3. **CONCIENCIA DE SEGURIDAD Y ALERTAS PAM EN TIEMPO REAL:**
   Toda conexión saliente vía SSH hacia el servidor de Producción (`10.20.23.252`) dispara alertas inmediatas de seguridad por PAM hacia el Telegram privado del Administrador. Toda interacción hacia cualquier nodo remoto es un evento crítico de auditoría y alta visibilidad que jamás debe ejecutarse de forma inadvertida ni encubierta.

4. **PROHIBICIÓN DE CONEXIONES A PRODUCCIÓN SIN AUTORIZACIÓN EXPRESA:**
   En estricto cumplimiento de la Regla de Oro #4, no se debe realizar ningún tipo de conexión SSH (ni siquiera comandos de sólo lectura como `cat`, `diff` o consultas de diagnóstico) hacia el servidor de producción (`10.20.23.252`) sin la autorización previa, explícita y directa del usuario.

