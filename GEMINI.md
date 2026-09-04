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
