# Solicitud de Implementación: Protocolo de Alerta al Owner y Re-validación Remota (Human-in-the-Loop)

## Contexto Continuo
En la solicitud anterior, implementamos un sistema DRM donde el `bot_token` y `owner_id` se desofuscan usando un "Hash de Entorno" (hardware). Si el hardware cambia o hay tamper, el bot no puede desofuscar el token y se bloquea.

## Nuevo Objetivo
Necesito agregar un **Protocolo de Alerta y Re-validación** que se active justo antes del bloqueo total. Si el bot detecta un cambio de entorno o tamper, debe intentar contactar al `owner_id` para permitir una re-validación manual remota.

## Reto Técnico a Resolver (El Token Canario)
Como el `bot_token` principal está bloqueado por el cambio de hardware, el bot no puede enviar el mensaje de alerta. 
* **Solución requerida:** Implementa un "Token Canario" (un token de bot secundario, hardcodeado y ofuscado de forma independiente, o una llave de desencriptación de respaldo "Fallback" que solo funcione en modo alerta). Este token solo debe tener permisos para enviar mensajes al `owner_id` y escuchar *una sola respuesta*.

## Flujo Lógico del Protocolo de Alerta

### 1. Detección y Generación del Serial (Challenge)
Cuando el módulo de seguridad (`SecureCore`) detecta una anomalía (cambio de MAC, CPU, o archivo modificado):
* No se bloquea inmediatamente. Entra en estado `PENDING_VALIDATION`.
* Genera un **Serial de Validación dinámico y antifalsificación**. Fórmula sugerida: `Serial = SHA256(Nuevo_Hash_Entorno + Timestamp_Actual + Salto_Secreto)`.
* Pausa todas las funciones normales del bot (IA, comandos), pero mantiene el listener de Telegram activo *exclusivamente* para mensajes provenientes del `owner_id`.

### 2. Envío de la Notificación de Emergencia
Usando el "Token Canario", envía un mensaje directo al `owner_id` con el siguiente formato (ofusca los strings de este mensaje en el código):
* **Encabezado:** ⚠️ [ALERTA DE SEGURIDAD] Bot [Nombre] - Entorno Comprometido.
* **Cuerpo:** Detallar qué falló (ej. "Hash de entorno no coincide", "Archivo config modificado").
* **Acción sugerida:** "Si esta es una migración legítima, revalide la información para ajustar el bot al nuevo equipo."
* **El Serial:** Mostrar el Serial de Validación generado.
* **Instrucción:** "Responda a este mensaje con el Serial exacto en los próximos [X] minutos. Si no responde o el serial es incorrecto, el bot ejecutará el protocolo de destrucción de estado."

### 3. Fase de Espera y Resolución
El bot inicia un temporizador (timeout) de, por ejemplo, 10 minutos.
* **Escenario A (Éxito - Serial Correcto):** 
  * El owner responde con el serial exacto.
  * El bot verifica que el serial coincide con el generado para el *Nuevo Hash de Entorno*.
  * **Acción:** El bot actualiza su almacenamiento local seguro con el *Nuevo Hash de Entorno* como la nueva llave válida. Desofusca el `bot_token` principal correctamente, cierra el "Token Canario" y reanuda sus operaciones normales.
* **Escenario B (Fallo - Serial Incorrecto o Timeout):**
  * El owner envía un serial wrong, o pasan los 10 minutos sin respuesta.
  * **Acción:** El bot ejecuta inmediatamente las acciones de "Scorched Earth" acordadas en la fase anterior (corromper la semilla local, invalidar variables de sesión, entrar en bucle infinito de sleep o `sys.exit`).

## Requisitos de Ofuscación y Seguridad para este Módulo
1. **Strings Ofuscados:** No uses texto plano para "PENDING_VALIDATION", "Token Canario", "Serial", o los textos del mensaje de alerta. Usa las mismas técnicas de ofuscación a nivel de bits (XOR, etc.) que en el módulo anterior.
2. **Lógica de Listener Aislado:** El listener que espera el serial no debe interferir con la lógica normal. Si el bot recibe comandos de otros usuarios durante el estado `PENDING_VALIDATION`, debe ignorarlos por completo o responder con un error genérico de "Servicio en mantenimiento".
3. **Anti-Replay:** Asegúrate de que el Serial expire. Si el atacante intercepta el serial y lo usa 5 minutos después de que el timeout haya expirado, debe ser rechazado.

## Instrucciones para la IA
1. Integra este nuevo flujo en el módulo `SecureCore` que diseñamos anteriormente.
2. Implementa la lógica del "Token Canario" o "Fallback Key" para poder enviar el mensaje cuando el token principal está bloqueado.
3. Escribe la función de generación del Serial y la función de espera (con timeout) para la respuesta del owner.
4. Asegúrate de que, si la validación es exitosa, el bot reescriba su propia "huella digital autorizada" en su almacenamiento local para que, al reiniciar, ya reconozca el nuevo hardware.
5. Mantén el código altamente ofuscado y camuflado.
