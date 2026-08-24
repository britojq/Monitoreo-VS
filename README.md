# 🤖 Telegram Admin Bot + Asistente Ollama

Bot de administración de servidores Linux/Debian y asistente conversacional potenciado por inteligencia artificial local (**Ollama**). Diseñado con arquitectura modular, control de acceso granular y ejecución desacoplada de comandos.

---

## 🌟 Características Principales

- 🛡️ **Control de Acceso y Seguridad**: Validación estricta por `owner_id`, lista blanca de `allowed_user_ids` y grupos autorizados (`allowed_group_ids`).
- ⚡ **Comandos Dinámicos Desacoplados**: Define, modifica o añade comandos en `commands.json` sin tocar el código fuente.
- 👁️ **Control de Salida de Terminal**: Opción para ejecutar scripts en segundo plano de forma silenciosa (`"show_output": false`) o mostrando la salida en bloque monoespaciado.
- 🧠 **Asistente IA Local (Ollama)**: Consultas conversacionales en lenguaje natural con soporte para modelos locales (ej. `qwen-empresa`), memoria contextual e historial reiniciable (`/reset_ia`).
- 🔄 **Servicio Systemd Resistente**: Servicio automatizado con recuperación ante fallos y arranque coordinado con la red.

---

## 📂 Archivos de Configuración

### 1. `config.json` (Credenciales y Parámetros)
Contiene las claves de conexión y configuración general *(usa `config.example.json` como plantilla)*:
```json
{
  "bot_token": "TU_TOKEN_DE_TELEGRAM",
  "owner_id": 123456789,
  "allowed_user_ids": [123456789],
  "allowed_group_ids": [],
  "commands_enabled": true,

  "notify_unauthorized_to_owner": true,
  "reply_unauthorized_user": true,
  "log_unauthorized_to_file": true,
  "audit_log_file": "intentos_acceso.log",
  "auto_proxy_failover": true,
  "proxies": [],

  "ollama_enabled": true,
  "ollama_allow_all": false,
  "ollama_base_url": "http://localhost:11434",
  "ollama_model": "qwen-empresa",
  "ollama_timeout": 180,
  "ollama_temperature": 0.3,
  "ollama_num_ctx": 4096,
  "ollama_max_history": 12
}
```

### 2. `commands.json` (Definición de Comandos del Sistema)
Permite estructurar los comandos disponibles:
- **`messages`**: Cabeceras y textos globales (`start_header`, `unknown_command`, `help_general`).
- **`commands`**:
  - `description`: Descripción corta para la lista `/start`.
  - `help_text`: Ayuda específica al consultar `/comando help` o `/help comando`.
  - `reply_header`: Mensaje previo enviado antes de ejecutar la tarea.
  - `reply_footer`: Mensaje posterior de confirmación.
  - `show_output`: `true` para mostrar la salida en bloque `<pre>`, o `false` para omitirla (ideal para scripts que envían sus propios reportes).
  - `steps`: Lista de subcomandos con su lista de argumentos `command` y `timeout` en segundos.

---

## 🛠️ Instalación y Puesta en Marcha

### 1. Preparar Entorno Virtual
```bash
cd /scripts/telegram-admin-bot
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 2. Configurar Servicio en Systemd
Crea el archivo `/etc/systemd/system/tg-admin-bot.service`:
```ini
[Unit]
Description=Telegram Admin Bot
Wants=network-online.target
After=network-online.target

[Service]
User=britojab
Group=britojab
WorkingDirectory=/scripts/telegram-admin-bot
ExecStart=/scripts/telegram-admin-bot/venv/bin/python /scripts/telegram-admin-bot/bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Habilita e inicia el servicio:
```bash
sudo systemctl daemon-reload
sudo systemctl enable tg-admin-bot
sudo systemctl start tg-admin-bot
```

---

## 📋 Comandos de Administración Diaria

### Gestión desde Telegram (Exclusivo Creador / Owner):
- `/botstatus` (o `/statusbot`): Diagnóstico en tiempo real de conectividad directa a internet, estado y latencia de cada proxy corporativo, y lista detallada de usuarios permitidos y negados.
- `/debug_monitor` (o `/monitordebug`): Activa/desactiva el **Modo Depuración** del sistema de monitoreo vía comando o panel interactivo de botones.
- `/reporte_servicios` (o `/servicios`): Ejecuta el chequeo asíncrono de los Servicios Corporativos (Web, DNS, Proxies, SMTP, DHCP, CUPS, LDAP, etc.) y genera el reporte formal.
- `/reporte_sedes` (o `/sedes`, `/sitios`): Ejecuta el chequeo asíncrono de las Sedes físicas y sus equipos de comunicación (Routers, Switches, Taquillas).
- `/reporte_completo` (o `/monitoreo`): Ejecuta ambos chequeos en paralelo y despacha los reportes estructurados.
- `/permisos` (o `/autorizados`): Abre el panel interactivo de control de acceso para listar usuarios y grupos autorizados, permitiendo revocar permisos con un solo toque.
- `/reset_ia`: Reinicia el contexto conversacional del asistente IA.

### Gestión desde el Servidor Linux:
| Acción | Comando |
| :--- | :--- |
| **Ver estado en tiempo real** | `sudo systemctl status tg-admin-bot` |
| **Ver logs del bot (stream)** | `sudo journalctl -u tg-admin-bot -f` |
| **Reiniciar servicio** | `sudo systemctl restart tg-admin-bot` |
| **Detener servicio** | `sudo systemctl stop tg-admin-bot` |
