# 🛡️ Guía de Resiliencia, Puntos de Control y Rollback (@IA_ValleSeco_bot)

Este documento detalla el funcionamiento del **Sistema Integral de Resiliencia y Rollback (SRR)** diseñado para garantizar que el bot nunca quede fuera de servicio ante caídas del suministro eléctrico, modificaciones interrumpidas o errores de sintaxis en caliente.

---

## 📌 1. Visión General de la Arquitectura

El sistema opera en tres capas complementarias:

```
┌────────────────────────────────────────────────────────────────────────┐
│             SISTEMA INTEGRAL DE RESILIENCIA Y ROLLBACK (SRR)           │
├────────────────────────────────────────────────────────────────────────┤
│ 1. PUNTOS DE CONTROL (`checkpoint`):                                   │
│    • Automático: Generado al validar estados operativos limpios.       │
│    • Manual: Ejecutado antes de realizar modificaciones importantes.   │
│    • Almacenamiento: Git tags locales + snapshots en `config/`.        │
│                                                                        │
│ 2. COMANDOS DE RESTAURACIÓN (`rollback`):                              │
│    • `rollback`         -> Restaura al último estado seguro.           │
│    • `rollback --list`  -> Lista historial con IDs y descripciones.   │
│    • `rollback --to ID` -> Restaura a una versión histórica puntual.   │
│                                                                        │
│ 3. AUTORRECUPERACIÓN DESATENDIDA (`ExecStartPre`):                     │
│    • Si la máquina reinicia tras un apagón con archivos corruptos, el  │
│      servicio ejecuta Auto-Rollback en 2 segundos, levanta el bot y    │
│      envía alerta a Telegram al Administrador.                         │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 🚀 2. Comandos Rápidos de Consola (Cheat Sheet)

Todos los comandos están disponibles globalmente en cualquier terminal:

| Comando | Acción |
| :--- | :--- |
| `checkpoint "Mi cambio"` | Guarda un punto de control con comentario antes de editar. |
| `checkpoint --list` | Muestra todos los puntos de restauración disponibles. |
| `rollback` | **Emergencia:** Restaura de inmediato al último estado funcional y reinicia el bot. |
| `rollback --list` | Muestra el inventario de checkpoints históricos. |
| `rollback --to <ID>` | Restaura a un checkpoint específico (ej. `rollback --to 20260828_094701`). |
| `rollback --check` | Verifica la salud física y sintáctica de los archivos sin modificar nada. |
| `rollback --git` | Fuerza el reseteo del árbol Git a `HEAD` respetando `.env` y DRM. |
| `estatus rollback` | Ejecuta el rollback desde el comando institucional `estatus`. |

---

## 🛠️ 3. Protocolo de Intervención Manual (Paso a Paso)

Si estás editando el código o las configuraciones del bot (`mensajes.conf`, `config.json`, `bot.py`) y ocurre un corte eléctrico o un error grave:

### Escenario A: Quieres volver de inmediato al estado anterior (Rápido)
1. Abre tu terminal o sesión SSH.
2. Ejecuta un solo comando:
   ```bash
   rollback
   ```
3. El script automáticamente:
   - Limpia bloqueos de Git huérfanos (`index.lock`).
   - Restaura el código y los archivos de configuración funcionales.
   - Preserva intactos tus credenciales `.env`, llaves DRM y logs de auditoría.
   - Reinicia `tg-admin-bot.service` y verifica que esté `active (running)`.

---

### Escenario B: Quieres volver a un punto específico de ayer o de la mañana
1. Consulta la lista de puntos disponibles:
   ```bash
   rollback --list
   ```
   *Salida de ejemplo:*
   ```text
   ID CHECKPOINT      | FECHA / HORA        | COMMIT GIT | DESCRIPCIÓN
   --------------------------------------------------------------------------------
   20260828_094701    | 2026-08-28 09:47:02 | 16cc709    | Estado estable (LATEST ⭐)
   20260827_153000    | 2026-08-27 15:30:00 | d9b1568    | Antes de cambios en proxies
   ```
2. Restaura al ID deseado:
   ```bash
   rollback --to 20260827_153000
   ```

---

### Escenario C: Vas a realizar cambios grandes y quieres tu propio respaldo
1. Antes de abrir el editor (`nano`, `vim`, etc.), crea tu punto de control:
   ```bash
   checkpoint "Antes de actualizar IPs de los proxies"
   ```
2. Realiza tus modificaciones en los archivos.
3. Si todo sale bien, puedes crear otro checkpoint o dejar que el sistema lo registre.
4. Si algo falla o se va la luz:
   ```bash
   rollback
   ```

---

## 🤖 4. Comportamiento Autónomo ante Cortes Eléctricos

Si la energía eléctrica se corta mientras un archivo se estaba guardando en disco (dejando un archivo de 0 bytes o JSON con sintaxis incompleta):

1. Al regresar la luz, el sistema operativo arranca y activa `tg-admin-bot.service`.
2. La instrucción `ExecStartPre=/scripts/telegram-admin-bot/rollback --pre-start-check` entra en acción:
   - Comprueba `bot.py` (`py_compile`), `config.json` (`jq`) y archivos de plantillas.
   - Si detecta corrupción, ejecuta de inmediato el **Auto-Rollback** al último estado seguro.
   - Registra el incidente en `audit/system_events.log`.
3. El bot arranca limpio y operativo al 100%.
4. En el primer segundo de vida, el bot despacha una notificación de alerta directa al chat privado del Administrador en Telegram:
   ```text
   ⚠️ Alerta de Autorrecuperación (Auto-Rollback)
   ━━━━━━━━━━━━
   El sistema detectó corrupción de archivos o apagón imprevisto durante el arranque.

   🔄 Acción: Rollback Automático Ejecutado
   ✅ Estado Actual: Sistema Restaurado y Operativo
   📁 Punto Restaurado: 20260828_094701
   📝 Detalle: Estado inicial estable
   ━━━━━━━━━━━━
   El bot se encuentra en línea y completamente funcional.
   ```

---

## 🔒 5. Archivos Protegidos e Inmutables

Durante cualquier operación de `rollback` (manual o automática), los siguientes archivos **NUNCA son eliminados ni sobrescritos**:
* `.env` (Credenciales maestras y cifrado local).
* `config/hardware.lock` (Identidad y DRM de hardware).
* `audit/*.log` (Trazabilidad y auditoría forense de seguridad).
* `config/.checkpoints/` (Historial de respaldos).
* `config/.backup_golden/` (Copia dorada inmutable de emergencia).
