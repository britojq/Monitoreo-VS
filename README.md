# Telegram Admin Bot

Bot de administración de sistemas para Debian / Linux desacoplado, seguro y fácil de configurar.

## 📂 Archivos de Configuración Extensión

### 1. `config.json` (Parámetros del Bot)
Contiene las credenciales y permisos de acceso:
- `bot_token`: Token suministrado por @BotFather.
- `owner_id`: Tu ID de usuario en Telegram (numérico).
- `allowed_user_ids`: Lista de IDs de usuarios adicionales con permiso.
- `allowed_group_ids`: Lista de IDs numéricos de grupos autorizados (números negativos como `-100xxxxxxxxxx`).

---

### 2. `commands.json` (Definición de Comandos, Mensajes Informatorios y Ayuda)
Permite agregar, editar o eliminar comandos y mensajes informativos **sin modificar el código fuente `bot.py`**.

Estructura:
- **`messages`**: Mensajes globales del bot (`start_header`, `unknown_command`, `help_general`).
- **`commands`**: Definición de comandos individuales:
  - `description`: Descripción corta para la lista `/start`.
  - `help_text`: Mensaje detallado que se muestra al solicitar ayuda (`/comando help` o `/help comando`).
  - `reply_header`: Mensaje informativo enviado **antes** de ejecutar la tarea.
  - `reply_footer`: Mensaje informativo enviado **después** de ejecutar la tarea.
  - `steps`: Pasos a ejecutar con título y comando del sistema.

Ejemplo:
```json
{
  "messages": {
    "start_header": "🤖 <b>Bot de Administración</b>\n\nComandos disponibles:",
    "unknown_command": "⚠️ Comando no reconocido. Usa /start para ver opciones."
  },
  "commands": {
    "status": {
      "description": "Estado de servicios críticos del sistema",
      "help_text": "ℹ️ <b>Ayuda de /status:</b> Muestra el estado operativo de los servicios SSH.",
      "reply_header": "🔍 <b>Consultando estado de servicios...</b>",
      "reply_footer": "✅ Consulta finalizada.",
      "steps": [
        {
          "title": "Estado SSH:",
          "command": ["systemctl", "status", "ssh", "--no-pager"]
        }
      ]
    }
  }
}
```

---

## 🚀 Reinicio del Bot

```bash
# Para aplicar cambios tras modificar config.json o commands.json
pkill -f "python.*bot.py"
```
`systemd` reiniciará el bot automáticamente en 10 segundos.
