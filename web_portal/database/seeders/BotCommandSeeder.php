<?php

namespace Database\Seeders;

use App\Models\BotCommand;
use App\Models\BotSetting;
use Illuminate\Database\Seeder;

class BotCommandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Semillas de Mensajes Generales del Bot
        $settings = [
            [
                'setting_key' => 'start_header',
                'setting_value' => "<b>═════════════════════════════════════</b>\n🤖 <b>BOT DE MONITOREO DE SEDE VALLE SECO</b>\n📍 <i>¡Bienvenido!</i>\n<b>═════════════════════════════════════</b>\n\nUtiliza los comandos listados a continuación para la gestión. Para ver detalles de un comando específico, envía:\n <code>/comando help</code> o <code>/ayuda comando</code>.\n\nEn caso contrario, siéntete libre de preguntarme sobre cualquier tema técnico ejemplo:\n <i>Cómo configurar un servidor web con apache2 en Debian</i>\nTambien puedes hacerme preguntas sobre cualquier tema que necesites información.",
                'setting_group' => 'messages',
                'title' => 'Encabezado de Bienvenida (/start)',
                'description' => 'Texto de bienvenida y presentación institucional desplegado con /start',
                'type' => 'textarea',
            ],
            [
                'setting_key' => 'unknown_command',
                'setting_value' => '⚠️ Comando no reconocido. Utiliza <code>/start</code> o <code>/help</code> para ver los comandos disponibles.',
                'setting_group' => 'messages',
                'title' => 'Aviso de Comando Desconocido',
                'description' => 'Mensaje de respuesta cuando un usuario ingresa un comando no existente o no registrado',
                'type' => 'textarea',
            ],
            [
                'setting_key' => 'help_general',
                'setting_value' => "ℹ️ <b>Centro de Ayuda</b>\n\nEnvía <code>/start</code> para ver la lista rápida de comandos, o escribe <code>/help [comando]</code> (ejemplo: <code>/help recursos</code>) para ver información detallada de cada función.",
                'setting_group' => 'messages',
                'title' => 'Encabezado General de Ayuda (/help)',
                'description' => 'Instrucciones generales de navegación y ayuda interactiva',
                'type' => 'textarea',
            ],
            [
                'setting_key' => 'commands_enabled',
                'setting_value' => '1',
                'setting_group' => 'access',
                'title' => 'Ejecución Global de Comandos',
                'description' => 'Interruptor maestro que habilita o desactiva la recepción de comandos en el bot',
                'type' => 'boolean',
            ],
            [
                'setting_key' => 'commands_locked_for_users',
                'setting_value' => '0',
                'setting_group' => 'access',
                'title' => 'Bloqueo para No Administradores',
                'description' => 'Restringe la ejecución de comandos exclusivamente a Administradores y Owner',
                'type' => 'boolean',
            ],
        ];

        foreach ($settings as $s) {
            BotSetting::updateOrCreate(
                ['setting_key' => $s['setting_key']],
                $s
            );
        }

        // 2. Semillas de Comandos Oficiales del Bot
        $commands = [
            // MONITOREO
            [
                'command' => 'servicios',
                'title' => 'Servicios Corporativos',
                'description' => 'Verificar el estatus de los servicios corporativos',
                'help_text' => "ℹ️ <b>Ayuda del comando /servicios:</b>\n\n• <code>/servicios</code>: Genera el reporte textual con latencias y estado de todos los servicios corporativos.\n• <code>/servicios web</code>: Genera además la captura panorámica en alta definición del dashboard web.\n• <code>/servicios grupo</code>: (Exclusivo Owner) Ejecuta el chequeo con captura web y lo despacha directamente al grupo corporativo autorizado, confirmando la entrega al Administrador.",
                'category' => 'Monitoreo',
                'access_level' => 'all',
                'sort_order' => 1,
            ],
            [
                'command' => 'sedes',
                'title' => 'Sedes y Enlaces',
                'description' => 'Verificar el estatus de las sedes y enlaces de comunicación',
                'help_text' => "ℹ️ <b>Ayuda del comando /sedes:</b>\n\n• <code>/sedes</code>: Reporte textual de conectividad en sedes regionales y equipos remotos.\n• <code>/sedes web</code>: Genera además la captura gráfica en alta definición con los acordeones de equipos desplegados.\n• <code>/sedes grupo</code>: (Exclusivo Owner) Ejecuta el chequeo con captura web y lo despacha directamente al grupo corporativo autorizado.",
                'category' => 'Monitoreo',
                'access_level' => 'all',
                'sort_order' => 2,
            ],
            [
                'command' => 'caidas',
                'title' => 'Fallas e Incidentes',
                'description' => 'Reporte enfocado en fallas, servicios caídos y sedes desconectadas',
                'help_text' => "ℹ️ <b>Ayuda del comando /caidas:</b>\n\n• <code>/caidas</code>: Filtra y muestra únicamente los incidentes activos, servicios en timeout o caídos y sedes sin conexión.\n• <code>/caidas web</code>: Adjunta la captura visual en alta definición de la columna de incidentes.\n• <code>/caidas grupo</code>: (Exclusivo Owner) Despacha los incidentes detectados con captura web directamente al grupo corporativo autorizado.",
                'category' => 'Monitoreo',
                'access_level' => 'all',
                'sort_order' => 3,
            ],
            [
                'command' => 'web',
                'title' => 'Captura Panorámica Web',
                'description' => 'Captura panorámica en alta definición del dashboard web en vivo',
                'help_text' => "ℹ️ <b>Ayuda del comando /web:</b>\n\n• <code>/web</code> o <code>/web full</code>: Captura panorámica HD (1920x1080 @2x) del tablero completo de monitoreo.\n• <code>/web servicios</code>: Captura aislada de la columna de servicios activos.\n• <code>/web sedes</code>: Captura aislada de la columna de sedes regionales con equipos expandidos.\n• <code>/web caidas</code>: Captura aislada de incidentes y servicios fuera de línea.",
                'category' => 'Monitoreo',
                'access_level' => 'all',
                'sort_order' => 4,
            ],
            [
                'command' => 'monitoreo',
                'title' => 'Monitoreo Integral',
                'description' => 'Reporte unificado integral (Servicios + Sedes)',
                'help_text' => "ℹ️ <b>Ayuda del comando /monitoreo:</b>\n\n• <code>/monitoreo</code>: Ejecuta una ronda integral de verificación combinada (Servicios Corporativos + Sedes Regionales + Dispositivos Locales).\n• <code>/monitoreo grupo</code>: (Exclusivo Owner) Ejecuta el reporte completo con captura web y lo envía directamente al grupo corporativo autorizado.",
                'category' => 'Monitoreo',
                'access_level' => 'all',
                'sort_order' => 5,
            ],

            // DIAGNÓSTICO Y TELEMETRÍA
            [
                'command' => 'recursos',
                'title' => 'Recursos del Sistema',
                'description' => 'Diagnóstico de RAM, CPU y panel interactivo para liberar memoria',
                'help_text' => "ℹ️ <b>Ayuda del comando /recursos:</b>\n\n• Muestra el consumo detallado de memoria RAM, memoria Swap, uso de CPU y los procesos que más memoria consumen en el host.\n• Despliega un panel con botones interactivos para:\n  - 🧹 <b>Liberar Caché de RAM</b> (drop_caches).\n  - 🔄 <b>Reiniciar Plasma Shell</b> (recuperar memoria gráfica acumulada de KDE Plasma).\n  - ⚡ <b>Optimización Completa</b> (ambas acciones combinadas).",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 10,
            ],
            [
                'command' => 'temperatura',
                'title' => 'Temperatura de CPU',
                'description' => 'Diagnóstico térmico en vivo de CPU y estado del guardián de hardware',
                'help_text' => "ℹ️ <b>Ayuda del comando /temperatura:</b>\n\n• Muestra la temperatura en tiempo real del procesador (Package y por cada núcleo físico individual).\n• Audita el estado del servicio local de IA y los límites físicos de hardware (RAM 8GB, CPU 280%).\n• Dispone de botón interactivo para refrescar la telemetría térmica al instante.",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 11,
            ],
            [
                'command' => 'disk',
                'title' => 'Espacio en Disco',
                'description' => 'Uso de almacenamiento y particiones en disco',
                'help_text' => "ℹ️ <b>Ayuda del comando /disk:</b>\n\nConsulta el espacio utilizado y disponible en todas las particiones del sistema (df -h).",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 12,
            ],
            [
                'command' => 'network',
                'title' => 'Puertos y Conexiones',
                'description' => 'Análisis rápido de interfaces y puertos escuchando',
                'help_text' => "ℹ️ <b>Ayuda del comando /network:</b>\n\nMuestra interfaces de red y puertos TCP/UDP escuchando en el servidor.",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 13,
            ],
            [
                'command' => 'ip',
                'title' => 'Parámetros IP',
                'description' => 'Consultar la dirección IP y parámetros de red del servidor',
                'help_text' => "ℹ️ <b>Ayuda del comando /ip:</b>\n\n• <code>/ip</code>: Muestra el nombre de host (hostname), las direcciones IPv4 asignadas a las interfaces de red físicas y lógicas, la puerta de enlace (Gateway), servidores DNS y los enlaces de acceso directo al portal web.",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 14,
            ],
            [
                'command' => 'internet',
                'title' => 'Salidas a Internet',
                'description' => 'Diagnóstico de salidas a internet y proxies corporativos',
                'help_text' => "ℹ️ <b>Ayuda del comando /internet:</b>\n\nVerifica la conectividad y latencia hacia internet a través de cada proxy corporativo configurado (Squid, pfSense, Dansguardian).",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 15,
            ],
            [
                'command' => 'status',
                'title' => 'Servicios Críticos Host',
                'description' => 'Estado de servicios críticos del sistema (SSH, etc.)',
                'help_text' => "ℹ️ <b>Ayuda del comando /status:</b>\n\nVerifica el estado del servicio SSH y procesos básicos en el host local.",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 16,
            ],
            [
                'command' => 'botstatus',
                'title' => 'Estado del Bot y Proxies',
                'description' => 'Diagnóstico de conectividad del bot, proxies corporativos y accesos denegados',
                'help_text' => "ℹ️ <b>Ayuda del comando /botstatus:</b>\n\nAuditoría en tiempo real del estado de conexión del bot, proxy en uso, latencias de transporte hacia Telegram y registro de intentos de acceso denegados.",
                'category' => 'Diagnóstico',
                'access_level' => 'all',
                'sort_order' => 17,
            ],
            [
                'command' => 'analisis_red',
                'title' => 'Análisis Profundo de Red',
                'description' => 'Captura de tráfico en vivo (tcpdump) y análisis profundo (tshark)',
                'help_text' => "ℹ️ <b>Ayuda del comando /analisis_red [segundos]:</b>\n\nCaptura paquetes en la interfaz de red local durante el intervalo especificado (por defecto 120s) y genera reportes detallados en formato Markdown y HTML con gráficos de protocolos y flujos sospechosos.",
                'category' => 'Diagnóstico',
                'access_level' => 'admin',
                'sort_order' => 18,
            ],

            // GESTIÓN OPERATIVA
            [
                'command' => 'cron',
                'title' => 'Horarios CRON',
                'description' => 'Panel interactivo de programación y horarios de reportes automáticos',
                'help_text' => "ℹ️ <b>Ayuda del comando /cron:</b>\n\nPermite pausar o reanudar los reportes automáticos, así como configurar los horarios y la frecuencia de los despachos programados a los canales autorizados.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 20,
            ],
            [
                'command' => 'permisos',
                'title' => 'Permisos y Accesos',
                'description' => 'Gestión interactiva de usuarios y grupos autorizados',
                'help_text' => "ℹ️ <b>Ayuda del comando /permisos:</b>\n\nPanel de administración de accesos para consultar, autorizar o revocar permisos a usuarios individuales (IDs de Telegram) y grupos de chat de operaciones.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 21,
            ],
            [
                'command' => 'grupos',
                'title' => 'Auditoría de Grupos',
                'description' => 'Auditoría en tiempo real de grupos activos y forzar salida',
                'help_text' => "ℹ️ <b>Ayuda del comando /grupos:</b>\n\n• Consulta a Telegram en tiempo real para listar únicamente los grupos donde el bot está activo.\n• Muestra el estado de autorización oficial y el rol asignado al bot.\n• Dispone de botones directos para forzar la salida del bot de cualquier grupo seleccionado y actualizar la lista en caliente.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 22,
            ],
            [
                'command' => 'salir_grupo',
                'title' => 'Salir de Grupo',
                'description' => 'Forzar salida inmediata del bot de un grupo por ID',
                'help_text' => "ℹ️ <b>Ayuda del comando /salir_grupo [ID]:</b>\n\n• Obliga al bot a abandonar inmediatamente el grupo con el ID numérico proporcionado (ejemplo: <code>/salir_grupo -1001383163558</code>).\n• Si el grupo estaba autorizado, se elimina automáticamente de la configuración.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 23,
            ],
            [
                'command' => 'bloqueo_comandos',
                'title' => 'Interruptor de Comandos',
                'description' => 'Bloquear o reactivar el uso de comandos para usuarios y grupos',
                'help_text' => "ℹ️ <b>Ayuda del comando /bloqueo_comandos:</b>\n\nInterruptor de seguridad para restringir el uso de comandos a usuarios no administradores durante tareas de mantenimiento o situaciones de contingencia.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 24,
            ],
            [
                'command' => 'mensaje',
                'title' => 'Comunicados Masivos',
                'description' => 'Envío de comunicados y avisos masivos a usuarios autorizados',
                'help_text' => "ℹ️ <b>Ayuda del comando /mensaje [texto]:</b>\n\nDifunde un aviso administrativo oficial a todos los usuarios y canales autorizados registrados en la whitelist del bot.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 25,
            ],
            [
                'command' => 'limpiador',
                'title' => 'Mantenimiento y Purga',
                'description' => 'Diagnóstico de almacenamiento, inodos y panel interactivo de limpieza',
                'help_text' => "ℹ️ <b>Ayuda del comando /limpiador:</b>\n\nHerramienta de mantenimiento para evaluar espacio libre en disco, inodos y purgar logs antiguos, temporales de capturas web y cachés del sistema.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 26,
            ],
            [
                'command' => 'actualizar',
                'title' => 'Actualización Git',
                'description' => 'Comprobar y aplicar actualizaciones desde GitHub',
                'help_text' => "ℹ️ <b>Ayuda del comando /actualizar:</b>\n\nCompara la versión local contra el repositorio remoto en GitHub, mostrando los commits pendientes y permitiendo aplicar la actualización en caliente.",
                'category' => 'Gestión',
                'access_level' => 'admin',
                'sort_order' => 27,
            ],

            // ASISTENTE VIRTUAL E IA
            [
                'command' => 'ia',
                'title' => 'Motor Local de IA',
                'description' => 'Panel interactivo de control y encendido/apagado del motor local de IA',
                'help_text' => "ℹ️ <b>Ayuda del comando /ia:</b>\n\n• <code>/ia</code> o <code>/ia status</code>: Despliega el panel interactivo de control con botones táctiles.\n• <code>/ia on</code>: Activa y levanta el servicio del motor local de IA en caliente.\n• <code>/ia off</code>: Detiene de inmediato el motor local de IA y libera la memoria RAM y CPU al instante.",
                'category' => 'Asistente IA',
                'access_level' => 'all',
                'sort_order' => 30,
            ],
            [
                'command' => 'reset_ia',
                'title' => 'Resetear Contexto IA',
                'description' => 'Reiniciar la memoria y contexto de conversación con el asistente',
                'help_text' => "ℹ️ <b>Ayuda del comando /reset_ia:</b>\n\nLimpia el hilo histórico de la conversación con el asistente virtual corporativo para comenzar una nueva consulta técnica desde cero.",
                'category' => 'Asistente IA',
                'access_level' => 'all',
                'sort_order' => 31,
            ],
            [
                'command' => 'info',
                'title' => 'Marco Legal e Info',
                'description' => 'Información legal, privacidad y políticas de seguridad',
                'help_text' => "ℹ️ <b>Ayuda del comando /info:</b>\n\nMuestra el marco legal de uso, directivas de confidencialidad y responsabilidades operativas del sistema de monitoreo institucional.",
                'category' => 'General',
                'access_level' => 'all',
                'sort_order' => 32,
            ],

            // ADMINISTRACIÓN CRÍTICA
            [
                'command' => 'emergencia',
                'title' => 'Panel de Contingencia',
                'description' => 'Panel de contingencia y modo de mantenimiento',
                'help_text' => "ℹ️ <b>Ayuda del comando /emergencia:</b>\n\nPermite detener temporalmente servicios, activar el modo de mantenimiento institucional o restaurar archivos de configuración de respaldo.",
                'category' => 'Administración',
                'access_level' => 'owner',
                'sort_order' => 40,
            ],
            [
                'command' => 'reinicia',
                'title' => 'Reinicio del Host',
                'description' => 'Reiniciar el servidor host (Exclusivo Owner)',
                'help_text' => "ℹ️ <b>Ayuda del comando /reinicia:</b>\n\nEjecuta el reinicio completo del sistema operativo del servidor (<code>sudo reboot</code>). Exclusivo para el Administrador Propietario (Owner).",
                'category' => 'Administración',
                'access_level' => 'owner',
                'sort_order' => 41,
            ],
        ];

        foreach ($commands as $cmd) {
            BotCommand::updateOrCreate(
                ['command' => $cmd['command']],
                $cmd
            );
        }
    }
}
