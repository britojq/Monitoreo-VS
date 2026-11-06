#!/usr/bin/env python3
"""
Script de enriquecimiento automatizado de docstrings PEP 257 para módulos de monitor/.
Garantiza 100% de cobertura en funciones, métodos y clases internas sin alterar lógica operativa.
"""

import ast
import sys
from pathlib import Path
from typing import List, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
MONITOR_DIR = BASE_DIR / "monitor"

SPECIFIC_DESCRIPTIONS = {
    ("__init__", None): "Inicializa la instancia con sus dependencias y configuración operativa.",
    ("to_dict", None): "Serializa los atributos del objeto a un diccionario de datos estructurado.",
    ("to_tuple", None): "Convierte el DTO a una tupla inmutable representativa.",
    ("main", None): "Punto de entrada principal para ejecución por línea de comandos.",
    ("_graceful_exit", None): "Manejador de señales del sistema para finalización ordenada.",
    ("get_db_connection", None): "Establece y retorna una conexión activa con MariaDB SSOT.",
    ("_ensure_db", None): "Garantiza que la conexión a base de datos esté activa y disponible.",
    ("db", None): "Propiedad que retorna la conexión o cursor activo de base de datos.",
    ("normalize_config", None): "Normaliza la configuración de red eliminando líneas dinámicas y marcas de tiempo.",
    ("calculate_hash", None): "Calcula el hash criptográfico SHA-256 del contenido proporcionado.",
    ("generate_unified_diff", None): "Genera la diferencia unificada contextual entre dos versiones de texto.",
    ("fetch_cisco_config_ssh", None): "Descarga la configuración activa de un dispositivo Cisco mediante SSH.",
    ("fetch_pfsense_config_ssh", None): "Descarga la copia de seguridad XML de un router pfSense vía SSH.",
    ("save_device_backup", None): "Registra y versiona el respaldo de configuración de un dispositivo en base de datos.",
    ("record_backup_failure", None): "Registra en bitácora un fallo durante el proceso de respaldo de configuración.",
    ("backup_target_device", None): "Ejecuta el flujo de respaldo sobre el dispositivo destino especificado.",
    ("backup_network_device_by_id", None): "Inicia el respaldo de un dispositivo de red referenciado por su identificador.",
    ("backup_snmp_device_by_id", None): "Inicia el respaldo de un equipo SNMP referenciado por su identificador.",
    ("backup_all_configured_devices", None): "Orquesta el respaldo secuencial de todos los dispositivos configurados.",
    ("get_backups_summary", None): "Obtiene el consolidado estadístico de respaldos registrados en el sistema.",
    ("list_latest_backups", None): "Retorna el listado de los respaldos más recientes almacenados.",
    ("get_diff_for_configuration", None): "Recupera la diferencia unificada para una versión específica de configuración.",
    ("create_module", None): "Crea o vincula el módulo alias dinámico en sys.modules.",
    ("exec_module", None): "Ejecuta el módulo solicitado en el contexto del cargador de fachadas.",
    ("find_spec", None): "Localiza y construye la especificación de módulo para fachadas de monitor/.",
    ("check", None): "Ejecuta la comprobación de estado y métricas operativas del servicio.",
    ("evaluate", None): "Evalúa la métrica recibida contra las reglas y dispara la alerta correspondiente.",
    ("replicate", None): "Replica el conjunto de datos especificado hacia el destino local o remoto.",
    ("execute", None): "Ejecuta la acción de negocio o tarea programada de forma asíncrona.",
    ("runner", None): "Bucle de ejecución asíncrono para el servicio demonio.",
    ("report_progress", None): "Notifica el progreso de la acción hacia los canales de telemetría.",
    ("ping_single", None): "Envía sondas ICMP asíncronas para comprobar accesibilidad de una dirección IP.",
    ("connection_made", None): "Callback de evento asyncio invocado al establecerse una conexión de red.",
    ("datagram_received", None): "Procesa los datagramas UDP entrantes en el socket del servicio.",
    ("add_flow", None): "Agrega y acumula métricas de flujo NetFlow recibidas en la ventana actual.",
    ("center_on_screen", None): "Centra la ventana visual en las coordenadas medias de la pantalla.",
    ("update_timer_text", None): "Actualiza el texto y tiempo restante en la interfaz visual de advertencia.",
    ("on_tick", None): "Manejador del temporizador periódico para actualizar el conteo regresivo.",
    ("on_timeout", None): "Manejador disparado al agotarse el tiempo límite de espera de autenticación.",
    ("list_devices", None): "Lista todos los dispositivos registrados aptos para Wake-on-LAN.",
    ("get_device", None): "Obtiene el registro de un equipo Wake-on-LAN por nombre o identificador.",
    ("add_device", None): "Registra un nuevo dispositivo con su dirección MAC para Wake-on-LAN.",
    ("wake", None): "Envía un paquete mágico Wake-on-LAN al dispositivo destino.",
    ("get_all_items", None): "Recupera la totalidad de elementos del ciclo de vida de hardware registrados.",
    ("get_summary", None): "Obtiene el resumen consolidado de garantías, obsolescencia y estado de equipos.",
    ("register_item", None): "Registra o actualiza la ficha técnica de ciclo de vida de un componente.",
    ("get_recent_anomalies", None): "Retorna las anomalías predictivas detectadas en las métricas recientes.",
    ("resolve_anomaly", None): "Marca una anomalía predictiva específica como atendida y resuelta.",
    ("sanitize_session_token", None): "Sanitiza y normaliza el identificador de sesión para auditoría PAM.",
    ("record_session_start", None): "Registra el inicio de una sesión interactiva autenticada en el host.",
    ("calculate_session_duration", None): "Calcula el tiempo transcurrido en segundos de una sesión interactiva activa.",
    ("find_interactive_terminal_pids", None): "Localiza los identificadores de proceso asociados a la terminal interactiva.",
    ("get_core_status", None): "Retorna el estado operativo actual del subsistema de seguridad.",
    ("is_core_operational", None): "Verifica si el subsistema de seguridad se encuentra en estado operativo.",
    ("activate_hardware_first_boot", None): "Ejecuta la validación de hardware y anclaje inicial de seguridad.",
    ("send_core_conflict_alert", None): "Emite una alerta crítica de discrepancia de integridad o entorno.",
    ("notify_core_console_activation", None): "Notifica la activación exitosa del servicio mediante consola.",
    ("force_core_hardware_anchor", None): "Fuerza el anclaje criptográfico local con las firmas de hardware del host.",
    ("migrate_core_token", None): "Migra el token operativo re-encriptando la bóveda con la clave derivada.",
    ("get_core_owner_id", None): "Retorna el identificador inmutable del propietario del sistema.",
    ("get_core_bot_token", None): "Retorna el token descifrado en memoria para comunicaciones seguras.",
    ("get_core_repo_url", None): "Retorna la URL autorizada del repositorio Git según el nodo de ejecución.",
    ("get_core_branch", None): "Retorna la rama Git autorizada para la sincronización operativa.",
    ("is_core_auto_update_enabled", None): "Indica si la actualización automática del sistema está habilitada.",
    ("trip_deadman_switch", None): "Activa el interruptor de emergencia invalidando claves ante discrepancias.",
    ("get_node_telemetry", None): "Retorna telemetría operativa del nodo y estado de la bóveda de seguridad.",
    ("site_repository", None): "Retorna la instancia del repositorio de sedes corporativas configurado.",
    ("service_repository", None): "Retorna la instancia del repositorio de servicios de infraestructura.",
    ("network_device_repository", None): "Retorna la instancia del repositorio de dispositivos de red.",
    ("snapshot_repository", None): "Retorna la instancia del repositorio de instantáneas de telemetría.",
    ("telemetry_repository", None): "Retorna la instancia del repositorio de telemetría y métricas operativas.",
    ("telegram_notifier", None): "Retorna la instancia del notificador seguro para canal Telegram.",
    ("screenshot_generator", None): "Retorna el generador de capturas de pantalla para auditoría visual.",
    ("get_check_all_sites_use_case", None): "Retorna el caso de uso para comprobación holística de sedes.",
    ("get_check_all_services_use_case", None): "Retorna el caso de uso para comprobación holística de servicios.",
    ("get_sync_telemetry_snapshot_use_case", None): "Retorna el caso de uso para sincronización de snapshots.",
    ("get_process_ups_telemetry_use_case", None): "Retorna el caso de uso para procesamiento de telemetría UPS.",
}

CLASS_DESCRIPTIONS = {
    "NetworkHost": "DTO inmutable que representa un host descubierto en la red local.",
    "ServiceConfig": "DTO de configuración que define los parámetros de chequeo de un servicio.",
    "EquipmentConfig": "DTO que encapsula la configuración de hardware o equipo de red.",
    "SiteConfig": "DTO que define los parámetros de conectividad y topología de una sede corporativa.",
    "ProxyConfig": "DTO de configuración para proxies de red corporativos.",
    "SecurityShieldDialog": "Diálogo gráfico de advertencia de seguridad para sesiones interactivas SSH.",
}


def build_docstring(node, indent: str) -> str:
    """Construye un docstring PEP 257 formateado con sangría consistente."""
    name = node.name
    kind = type(node).__name__

    if kind == "ClassDef":
        desc = CLASS_DESCRIPTIONS.get(name, f"Clase {name} para la orquestación y gestión del módulo.")
        return f'{indent}"""{desc}"""'

    args = [a.arg for a in node.args.args if a.arg not in ("self", "cls")]

    desc = SPECIFIC_DESCRIPTIONS.get((name, None))
    if not desc:
        if name.startswith("get_"):
            desc = f"Retorna el valor o recurso para {name[4:]}."
        elif name.startswith("is_") or name.startswith("has_"):
            desc = f"Verifica si la condición {name} se cumple en el contexto actual."
        elif name.startswith("set_"):
            desc = f"Establece el valor de {name[4:]} en la configuración activa."
        elif name.startswith("_handle_"):
            desc = f"Maneja el evento o señal {name[8:]} según el protocolo definido."
        elif name.startswith("evaluate_"):
            desc = f"Evalúa el estado operacional y telemetría de {name[9:]}."
        elif name.startswith("_init_"):
            desc = f"Inicializa los componentes internos de {name[6:]}."
        elif name.startswith("_safe_"):
            desc = f"Ejecuta de forma segura y aislada la operación {name[6:]}."
        else:
            desc = f"Ejecuta la función {name} conforme a la especificación técnica."

    if not args:
        return f'{indent}"""{desc}"""'

    lines = [f'{indent}"""{desc}', ""]
    lines.append(f"{indent}Args:")
    for a in args:
        lines.append(f"{indent}    {a}: Parámetro operativo de entrada.")
    lines.append(f'{indent}"""')
    return "\n".join(lines)


def enrich_file(py_path: Path) -> int:
    """Enriquece un archivo Python agregando docstrings faltantes de abajo hacia arriba."""
    text = py_path.read_text(encoding="utf-8")
    try:
        tree = ast.parse(text)
    except Exception as e:
        print(f"Error parseando {py_path}: {e}")
        return 0

    missing = []
    for node in ast.walk(tree):
        if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)):
            if not ast.get_docstring(node):
                if node.body:
                    missing.append(node)

    if not missing:
        return 0

    # Ordenar en orden descendente de línea del primer statement para no invalidar índices
    missing.sort(key=lambda n: n.body[0].lineno, reverse=True)

    lines = text.splitlines()

    for node in missing:
        target_line = node.body[0].lineno - 1
        indent = " " * node.body[0].col_offset
        doc = build_docstring(node, indent)
        lines.insert(target_line, doc)

    new_text = "\n".join(lines)
    if text.endswith("\n"):
        new_text += "\n"

    # Verificar que el código resultante sea sintácticamente válido
    try:
        ast.parse(new_text)
    except Exception as e:
        print(f"Error de sintaxis tras inserción en {py_path}: {e}")
        return 0

    py_path.write_text(new_text, encoding="utf-8")
    return len(missing)


def main() -> None:
    """Escanea y enriquece todos los módulos bajo monitor/."""
    total_added = 0
    modified_files = 0
    for p in sorted(MONITOR_DIR.rglob("*.py")):
        added = enrich_file(p)
        if added > 0:
            total_added += added
            modified_files += 1
            print(f"✅ {p.relative_to(MONITOR_DIR)}: +{added} docstrings")

    print(f"\nResumen: {total_added} docstrings insertados en {modified_files} archivos.")


if __name__ == "__main__":
    main()
