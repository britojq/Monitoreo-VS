# Solicitud de Implementación: Instalador Maestro (`install.sh`), Protocolo de Activación Inicial y Módulo de Migración de Token Dinámica

## Contexto del Proyecto
Tengo el manual técnico actualizado y definitivo de mi bot "Monitor Valle Seco" (`@IA_ValleSeco_bot`). El proyecto está estrictamente estructurado en `/scripts/telegram-admin-bot/` y cuenta con un DRM avanzado en `monitor/core_shield.py` que vincula la ejecución a la huella física del servidor (`audit/.sys_anchor`). 
Además, el sistema incluye un CLI global (`estatus`), servicios systemd (`tg-admin-bot`, `boot-alert`) y hooks PAM para alertas SSH.

## Objetivo
Necesito que actúes como un Ingeniero de Despliegue y Seguridad Senior. Basándote **estrictamente** en las rutas y arquitecturas del manual técnico, genera tres componentes críticos:

---

## PARTE 1: El Instalador Maestro (`/scripts/telegram-admin-bot/installer/install.sh`)
Escribe un script de Bash robusto, con manejo de errores (`set -e`), colores para consola y verificación de privilegios root. El script debe realizar **exactamente** lo siguiente:

1. **Preparación y APT:** Instalar los paquetes de la Sección 2A (`python3`, `tcpdump`, `tshark`, `arp-scan`, `libpam-modules`, etc.).
2. **Permisos de Red:** Ejecutar los `setcap` para `tcpdump` y `arp-scan` (Sección 2B).
3. **Motor de IA (Ollama):** 
   - Instalar Ollama vía script oficial.
   - Descargar el modelo base `qwen2.5:7b`.
   - Compilar el modelo corporativo: `ollama create qwen-empresa -f /scripts/telegram-admin-bot/ai/Modelfile.txt`.
4. **Entorno Python:** Crear el `venv` en `/scripts/telegram-admin-bot/venv` e instalar dependencias (`python-telegram-bot`, `httpx`, `requests`).
5. **CLI Global `estatus`:** Crear un enlace simbólico para la herramienta CLI: `ln -sf /scripts/telegram-admin-bot/estatus /usr/local/bin/estatus` y darle permisos de ejecución.
6. **Despliegue de Servicios Systemd:**
   - Generar e instalar `/etc/systemd/system/tg-admin-bot.service` (basado en la Sección 8A).
   - Generar e instalar `/etc/systemd/system/boot-alert.service` (basado en la Sección 8B, usando los scripts de python en `monitor/`).
   - Hacer `daemon-reload`, `enable` de ambos servicios.
7. **Hook PAM para SSH:** Insertar de forma segura la línea `session optional pam_exec.so seteuid /bin/bash /scripts/telegram-admin-bot/monitor/ssh_alert.sh` al final de `/etc/pam.d/sshd`.
8. **Permisos de Usuario:** Asegurar que el usuario `britojab` sea el propietario de `/scripts/` y tenga los grupos necesarios (ej. `wireshark`).
9. **Arranque Controlado:** **NO iniciar `tg-admin-bot.service` automáticamente al final del script.** El instalador debe dejar el servicio habilitado (`enable`) pero detenido, para que el "Protocolo de Activación" (Parte 2) tome el control en el primer arranque manual.

---

## PARTE 2: Protocolo de Activación Inicial (First-Boot Activation Handshake)
El bot **NO debe ser funcional** hasta que el Owner valide la instalación en su chat de Telegram. Modifica la lógica de `bot.py` y `monitor/core_shield.py` para incluir este flujo en el **primer arranque post-instalación**:

1. **Detección de Primera Ejecución:** Al iniciar, `core_shield.py` debe verificar si `audit/.sys_anchor` existe y está válido. Si **NO existe** (recién instalado), el bot entra en estado `FIRST_BOOT_PENDING`.
2. **Generación y Envío del Serial:** 
   - Genera el Serial Challenge (`AUTH-XXXX-XXXX-XXXX-XXXX`).
   - Usa el "Token Canario" (o un mecanismo de respaldo hardcodeado) para enviar un mensaje directo al `owner_id` (`38914901`):
     *"🔐 [ACTIVACIÓN REQUERIDA] Monitor Valle Seco instalado en [Hostname]. Serial: AUTH-XXXX... Responda con este serial para anclar el hardware y activar el bot."*
3. **Bloqueo de Comandos:** Mientras esté en `FIRST_BOOT_PENDING`, el bot debe ignorar todos los comandos (`/sedes`, `/servicios`, `/estatus`, etc.) y responder solo con: *"⏳ Bot en espera de activación del Owner."*
4. **Validación y Anclaje:** Si el owner responde con el serial exacto:
   - `core_shield.py` calcula la huella física actual (machine-id, MAC, hostname).
   - Deriva la Clave de Hardware (PBKDF2-HMAC).
   - Crea y cifra el archivo `audit/.sys_anchor`.
   - Cambia el estado a `OPERATIONAL`, desofusca el `bot_token` principal y habilita todas las funciones.

---

## PARTE 3: Módulo de Migración de Token en Tiempo Real (`/migrar_token`)
Necesito un nuevo comando de Telegram que permita cambiar la identidad del bot (el Token de BotFather) sin reinstalar el sistema ni perder el anclaje de hardware.

1. **Restricción Estricta:** Solo procesado si lo envía el `owner_id` (`38914901`).
2. **Flujo Interactivo (ConversationHandler):**
   - El owner envía `/migrar_token`.
   - El bot responde: *"🔄 Envíame el nuevo Token de BotFather para migrar la identidad. (Tienes 2 minutos)."*
   - El owner envía el nuevo token (ej. `123456:ABC-DEF...`).
3. **Re-Ofuscación Vinculada al Hardware (CRÍTICO):**
   - El bot NO guarda el nuevo token en texto plano en `config.json` ni en `bot.conf`.
   - Debe tomar el nuevo token, cifrarlo/ofuscarlo utilizando la **Clave de Hardware** actual (la misma que se derivó de `audit/.sys_anchor`).
   - Sobrescribe la variable interna en memoria y actualiza el payload cifrado en el código/configuración.
4. **Reinicio Graceful:**
   - El bot confirma al owner: *"✅ Identidad migrada y re-cifrada con DRM. Reiniciando servicio..."*
   - El bot ejecuta un reinicio limpio de su propio servicio systemd (`os.system('systemctl restart tg-admin-bot.service')`) o hace un `os.execv` para recargarse a sí mismo con la nueva identidad.

---

## Instrucciones de Salida para la IA
1. **Código Bash (`install.sh`):** Entrégame el script completo, comentado y listo para producción, respetando las rutas exactas del manual (`/scripts/telegram-admin-bot/`, `/etc/systemd/system/`, `/etc/pam.d/sshd`).
2. **Código Python (Activación):** Muéstrame cómo modificar `bot.py` (el `Application` y handlers) y `core_shield.py` para interceptar el estado `FIRST_BOOT_PENDING`, validar el serial y generar el `.sys_anchor`.
3. **Código Python (Migración):** Entrégame el handler del comando `/migrar_token` usando `ConversationHandler` de `python-telegram-bot`, asegurándote de que incluya la lógica para re-cifrar el nuevo token usando la clave de hardware existente antes de reiniciar.
4. **Seguridad:** Recuerda mantener la ofuscación de strings y la lógica de DRM intacta en los fragmentos de Python que me entregues.