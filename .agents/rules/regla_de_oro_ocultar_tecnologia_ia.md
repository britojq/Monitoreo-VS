# REGLA DE ORO #2: PROHIBICIÓN ABSOLUTA DE REVELAR LA TECNOLOGÍA SUBYACENTE (OLLAMA) AL USUARIO

## Directrices Estrictas e Inviolables

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
   - Si ocurre una desconexión, error o timeout, el mensaje presentado al usuario debe limitarse a indicar que el motor local de IA se encuentra ocupado o no disponible temporalmente, sin revelar software subyacente ni puertos.
