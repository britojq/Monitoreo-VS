# REGLAS DE ORO DEL PROYECTO (DIRECTRICES ESTRICTAS E INVIOLABLES)

## 🚨 REGLA DE ORO #1: PROHIBICIÓN ABSOLUTA DE ENVIAR MENSAJES DE PRUEBA O NOTIFICACIONES DE SEGURIDAD AL GRUPO

1. **PROHIBICIÓN ESTRICTA DE MENSAJES DE PRUEBA AL GRUPO:**
   Bajo NINGÚN motivo, circunstancia, simulación o prueba (manual o automatizada con Playwright, curl, scripts de testing, etc.) se debe enviar ningún mensaje al grupo corporativo de Telegram (`-1001383163558` o cualquier chat ID negativo).

2. **USO EXCLUSIVO DEL OWNER PARA SEGURIDAD:**
   Todas las notificaciones de seguridad, inicios de sesión (locales o LDAP), auto-registros de usuarios, alertas de accesos, intentos fallidos y auditoría son de **USO ESTRICTO Y EXCLUSIVO DEL OWNER / ADMINISTRADOR PRIVADO (`owner_id` / `IDC`: `38914901`)**. El grupo general de la sede JAMÁS debe ser receptor de estos avisos.

3. **FILTRO PERMANENTE EN CÓDIGO (BACKEND Y SERVICIOS):**
   - En cualquier despachador o servicio (`TelegramNotificationService.php`, `monitor_engine.py`, `bot.py`, etc.), se debe filtrar explícitamente y descartar cualquier `chat_id` que pertenezca a grupos (identificadores negativos) para cualquier evento de login o seguridad.
   - Todo acceso proveniente de `127.0.0.1`, `::1` o pruebas de desarrollo debe bloquear automáticamente el despacho externo a Telegram y limitarse al registro local de auditoría.

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
