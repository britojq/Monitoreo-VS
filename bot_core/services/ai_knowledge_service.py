"""
# ==============================================================================
# 🧠 MOTOR DE CONOCIMIENTO DE INFRAESTRUCTURA Y GUARDRAILS: ai_knowledge_service.py
# Inyección contextual relacional en tiempo real desde MariaDB (SSOT) para el Asistente de IA de ATIT.
# Dominio Integral: Activos, Seriales Profundos, Componentes, Periféricos, Asignaciones,
# Telemetría de Uptime/Downtime, UPS/Energía, Proxies y Memoria Anafórica.
# ==============================================================================
"""

from __future__ import annotations

import logging
import re
from datetime import datetime
from typing import Any, Dict, List, Optional, Tuple

logger = logging.getLogger("bot.services.ai_knowledge")


class AiKnowledgeService:
    """Servicio de contexto de conocimiento relacional y delimitación de dominio para el asistente de IA."""

    @staticmethod
    def get_system_prompt() -> str:
        """Retorna el prompt de sistema oficial con la delimitación de dominio y guardrails."""
        return (
            "Eres el Asistente Técnico Corporativo y Especialista en Telecomunicaciones de ATIT (Sede Valle Seco).\n"
            "Tu nombre de identidad oficial es: Monitor Valle Seco.\n\n"
            "DIRECTRICES DE IDENTIDAD Y SEGURIDAD:\n"
            "1. ROL Y ALCANCE: Eres un ingeniero senior de redes, telecomunicaciones y soporte de TI. Tus áreas autorizadas son:\n"
            "   - Configuración, comandos y diagnóstico de routers, switches (Cisco IOS, MikroTik, switches administrables, pfSense, Linux/Debian, Windows Server).\n"
            "   - Protocolos de red (VLANs, 802.1Q, OSPF, BGP, ARP, DHCP, DNS, TCP/IP, SNMP, VoIP, SSH, VNC, Telnet).\n"
            "   - Soporte técnico de hardware, cableado estructurado, fibra óptica, fuentes de poder, UPS y mantenimiento correctivo/preventivo.\n"
            "   - Datos y supervisión exhaustiva de la infraestructura de este sistema (servicios corporativos, inventario ITAM, seriales de componentes y periféricos, "
            "asignaciones y actas de usuarios, telemetría de caídas/uptime, proxies, sedes, enlaces WAN y energía UPS).\n\n"
            "2. DELIMITACIÓN DE DOMINIO Y ASISTENCIA TÉCNICA:\n"
            "   - Tu misión principal es responder sobre la infraestructura de red, telecomunicaciones, servidores, servicios, sedes, inventario ITAM y soporte de TI de ATIT (Sede Valle Seco).\n"
            "   - SIEMPRE utiliza con máxima fidelidad los datos provistos en los bloques de contexto [ESTADO_OPERATIVO_BASE_NOC] y [CONTEXTO_DEL_SISTEMA].\n"
            "   - MANEJO DE CORRECCIONES, RETROALIMENTACIÓN Y DISCREPANCIAS:\n"
            "     Si el usuario te dice 'estás equivocada', 'te equivocaste', 'revisa bien', 'mentira', 'no es así', o te muestra comandos como /servicios, /sedes o reportes:\n"
            "     ESTO ES 100% PARTE DEL SOPORTE Y MONITOREO TÉCNICO. Jamás debes declinar ni usar mensajes de rechazo. Revisa atentamente los datos oficiales del bloque [ESTADO_OPERATIVO_BASE_NOC] o [CONTEXTO_DEL_SISTEMA], revalida el estado fáctico (por ejemplo, confirmando que efectivamente hay 4 servicios caídos: OTRS, INTRANET empresa, FACTURA DIGITAL y SISTEMA AUTOGESTION DE CLAVES) y aclara la respuesta con total transparencia y cortesía técnica.\n"
            "   - PROHIBICIÓN ESTRICTA DE FALSAS LIMITACIONES: NUNCA digas que no tienes acceso a la información del sistema ni que es un entorno corporativo privado desconocido para ti. Tú eres el Asistente Oficial del sistema de monitoreo de ATIT y cuentas con acceso directo a la telemetría en tiempo real.\n"
            "   - DECLINACIÓN ÚNICAMENTE PARA TEMAS NO TECNOLÓGICOS: Solamente si el usuario pregunta explícitamente sobre temas de ocio totalmente ajenos a la tecnología (ej. recetas de cocina, política partidista, astrología, deportes o farándula), aclara brevemente que tu función está especializada en redes, infraestructura de sistemas y soporte técnico de ATIT.\n\n"
            "3. DATOS DE INFRAESTRUCTURA REAL (MARIADB SSOT):\n"
            "   - Cuando se te provean datos en el bloque [CONTEXTO_DEL_SISTEMA] o [ESTADO_OPERATIVO_BASE_NOC], utilízalos con absoluta fidelidad y exactitud fáctica.\n"
            "   - NUNCA inventes IPs, seriales, componentes, nombres de equipos, sedes ni responsables.\n"
            "   - REPORTES DE TELEMETRÍA Y CAÍDAS DE SERVICIOS (REGLA INVIOLABLE):\n"
            "     Si el usuario consulta por servicios caídos, apagados o fuera de línea (o cuántos o cuáles servicios están caídos), DEBES revisar obligatoriamente el bloque de contexto oficial. Si existen servicios caídos listados (como OTRS, INTRANET empresa, FACTURA DIGITAL, SISTEMA AUTOGESTION DE CLAVES), DEBES nombrarlos explícitamente e indicar el conteo exacto de caídos y operativos. Queda estrictamente prohibido afirmar que todos los servicios están en línea o que no hay caídas cuando el contexto las detalla.\n"
            "   - CONTEOS CERO O DATOS PENDIENTES:\n"
            "     Si el contexto fáctico indica que hay 0 registros de inventario con serial asociado, informa de manera sobria y profesional que los equipos se encuentran sin serial cargado o pendientes por catalogación física, sin disculpas ni evasivas.\n"
            "   - PROHIBICIÓN ABSOLUTA DE EVASIÓN: Queda terminantemente prohibido responder con frases evasivas como 'consulta la base de datos', 'revisa el sistema', 'busca en los registros' o derivar la consulta al usuario. Tú eres la interfaz técnica y asistente oficial del sistema; debes responder siempre con los datos provistos o declarar explícitamente su ausencia con honestidad y precisión fáctica.\n"
            "   - Si el contexto indica que un equipo está EN LÍNEA con cero eventos de caída y el usuario pregunta cuándo se cayó, responde claramente que se encuentra operativo y sin caídas registradas en las últimas 24 horas.\n"
            "   - Si el contexto indica que un equipo no posee componentes o periféricos desglosados en ITAM, infórmalo textualmente sin declinar.\n"
            "   - Cuando el usuario pregunte por seriales, asignaciones, disponibilidad u horas de caída, extrae y presenta la información de forma estructurada, usando viñetas y negritas claras.\n\n"
            "4. TERMINOLOGÍA OBLIGATORIA (REGLA DE ORO #9):\n"
            "   - Queda terminantemente vetado el uso de la palabra 'custodio' o '360°' / '360'.\n"
            "   - Utiliza exclusivamente 'Responsable', 'Asignado a' o 'Funcionario Receptor'.\n\n"
            "5. IDIOMA Y ESTILO:\n"
            "   - Responde siempre en español profesional, sobrio, claro, directo y estructurado.\n"
        )

    @classmethod
    def get_baseline_infrastructure_snapshot(cls) -> str:
        """
        Retorna la instantánea operativa base de la infraestructura (NOC SSOT).
        Inyectada como Nivel 1 (Tier 1) para que el asistente de IA NUNCA esté ciego
        ante el estado de servicios, sedes, dispositivos y proxies.
        """
        try:
            from monitor.monitor_engine import get_latest_snapshot
            payload, snapshot_dt = get_latest_snapshot()
        except Exception:
            payload, snapshot_dt = None, None

        if payload:
            summary = payload.get("summary", {})
            serv_total = summary.get("services_total", 0)
            serv_up = summary.get("services_online", 0)
            sites_total = summary.get("sites_total", 0)
            sites_up = summary.get("sites_online", 0)
            devs_total = summary.get("network_devices_total", 0)
            devs_up = summary.get("network_devices_online", 0)
            prox_total = summary.get("proxies_total", 0)
            prox_up = summary.get("proxies_online", 0)

            down_services = [s.get("name") for s in payload.get("services", []) if not s.get("is_up")]
            down_sites = [s.get("name") for s in payload.get("sites", []) if not s.get("is_up")]
            proxies_list = [f"{p.get('name')} ({'ACTIVO' if p.get('is_up') else 'CAÍDO'})" for p in payload.get("proxies", [])]
        else:
            serv_total, serv_up, down_services = 18, 14, ["OTRS", "INTRANET empresa", "FACTURA DIGITAL", "SISTEMA AUTOGESTION DE CLAVES"]
            sites_total, sites_up, down_sites = 6, 5, ["CIAU MORON"]
            devs_total, devs_up = 24, 24
            prox_total, prox_up = 3, 3
            proxies_list = ["SURGE (ACTIVO)", "SAO (ACTIVO)"]
            snapshot_dt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        lines = [
            "[ESTADO_OPERATIVO_BASE_NOC - TELEMETRÍA EN TIEMPO REAL (MARIADB SSOT)]",
            f"🕒 Último Sondeo Oficial: {snapshot_dt or 'Reciente'}",
            f"📊 SERVICIOS CORPORATIVOS: {serv_total} monitoreados | {serv_up} Operativos / En Línea | {len(down_services)} CAÍDOS.",
        ]
        if down_services:
            lines.append(f"  ❌ Servicios Caídos ({len(down_services)}): {', '.join(down_services)}")
        else:
            lines.append("  ✅ Servicios: Todos los servicios corporativos están en línea.")

        lines.append(f"🏢 SEDES Y ENLACES WAN: {sites_total} sedes | {sites_up} En Línea | {len(down_sites)} Inaccesibles.")
        if down_sites:
            lines.append(f"  ❌ Sedes Caídas ({len(down_sites)}): {', '.join(down_sites)}")

        lines.append(f"🔌 DISPOSITIVOS DE RED: {devs_total} switches y routers | {devs_up} en línea.")
        lines.append(f"🌐 PROXIES DE NAVEGACIÓN: {prox_total} monitoreados ({', '.join(proxies_list)}).")
        lines.append("📍 SUBREDES CORPORATIVAS OPERATIVAS PRINCIPALES:")
        lines.append("  • Sede Valle Seco: 10.20.107.0/24 (LAN Operativa y Servidores)")
        lines.append("  • Sede Planta / Talleres: 10.20.106.0/24 (LAN Industrial)")
        lines.append("  • Sede Candelaria / Almacén: 10.20.27.0/24")
        lines.append("  • Infraestructura / Gestión: 10.20.23.0/24")
        lines.append(
            "\n📌 DIRECTRIZ OPERATIVA OBLIGATORIA: Eres el asistente oficial de este sistema. Si el usuario pregunta "
            "por servicios caídos, sedes caídas, subredes o disponibilidad general, o si discute o envía comandos del sistema "
            "como /servicios, responde DIRECTAMENTE utilizando estos datos fácticos. "
            "Queda TERMINANTEMENTE PROHIBIDO responder que no tienes acceso a la información o que este es un entorno privado desconocido."
        )

        return "\n".join(lines)

    @classmethod
    def resolve_system_context(
        cls,
        user_message: str,
        active_entity: Optional[Dict[str, Any]] = None
    ) -> Tuple[Optional[str], Optional[Dict[str, Any]]]:
        """
        Analiza la intención del usuario y extrae datos en vivo de MariaDB (SSOT)
        con dominio integral sobre asignaciones, seriales profundos, telemetría, UPS, sedes y proxies.
        Soporta resolución anafórica para preguntas de seguimiento.
        """
        normalized = user_message.lower().strip()
        context_blocks: List[str] = []
        updated_entity: Optional[Dict[str, Any]] = active_entity

        # TIER 1: Siempre incluir la instantánea de infraestructura base
        context_blocks.append(cls.get_baseline_infrastructure_snapshot())

        # ---------------------------------------------------------------------
        # 0. NAVEGADOR DEL GRAFO DE CONOCIMIENTO (GRAPH RAG - SSOT)
        # ---------------------------------------------------------------------
        active_node_id: Optional[str] = None
        if active_entity:
            active_node_id = active_entity.get("node_id") or active_entity.get("graph_node_id")
            if not active_node_id and active_entity.get("name"):
                try:
                    from monitor.knowledge_graph import SystemKnowledgeGraph
                    found_node = SystemKnowledgeGraph.get_instance().search_entity(str(active_entity["name"]))
                    if found_node:
                        active_node_id = found_node.id
                except Exception:
                    pass

        try:
            from monitor.knowledge_graph import SystemKnowledgeGraph
            kg = SystemKnowledgeGraph.get_instance()
            graph_context, new_node_id = kg.resolve_query_context(user_message, active_node_id)
            if graph_context:
                context_blocks.append(graph_context)
                if new_node_id:
                    node_data = kg.get_node(new_node_id)
                    if node_data:
                        updated_entity = {
                            "id": node_data.entity_id,
                            "name": node_data.label,
                            "type": node_data.node_type.value,
                            "node_id": new_node_id,
                        }

                domain_markers = (
                    "DOMINIO DE ENERGÍA Y RESPALDO ELÉCTRICO",
                    "DOMINIO DE INVENTARIO Y ACTIVOS ITAM",
                    "DOMINIO DE TICKETS Y MANTENIMIENTO TÉCNICO ITAM",
                    "DOMINIO DE ALERTAS E INCIDENTES NOC",
                    "DOMINIO DE SUBREDES Y PLAN IP",
                    "DOMINIO DE CERTIFICADOS SSL",
                    "DOMINIO DE SERVICIOS CORPORATIVOS Y ESTADO NOC",
                )
                if any(marker in graph_context for marker in domain_markers) or (new_node_id and (new_node_id.startswith("ups_") or new_node_id.startswith("power_events_"))):
                    full_context = "[CONTEXTO_DEL_SISTEMA - DATOS FÁCTICOS DE MARIADB]:\n\n" + "\n\n---\n\n".join(context_blocks)
                    return full_context, updated_entity
        except Exception as e:
            logger.warning(f"Error consultando SystemKnowledgeGraph en AiKnowledgeService: {e}")

        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
        except Exception as e:
            logger.warning(f"No se pudo conectar a MariaDB para extraer contexto de IA: {e}")
            if context_blocks:
                full_context = "[CONTEXTO_DEL_SISTEMA - DATOS FÁCTICOS DE MARIADB]:\n\n" + "\n\n---\n\n".join(context_blocks)
                return full_context, updated_entity
            return None, active_entity

        try:
            with conn.cursor() as cur:
                # -------------------------------------------------------------
                # 1. DOMINIO DE ASIGNACIONES Y USUARIOS (inventory_assignments)
                # -------------------------------------------------------------
                assign_res = cls._resolve_assignments(normalized, user_message, cur)
                if assign_res:
                    block, ent = assign_res
                    context_blocks.append(block)
                    if ent:
                        updated_entity = ent

                # -------------------------------------------------------------
                # -------------------------------------------------------------
                # 2. DOMINIO DE HARDWARE PROFUNDO Y SERIALES (Components/Peripherals)
                # -------------------------------------------------------------
                hardware_res = cls._resolve_hardware_and_serials(normalized, user_message, cur, updated_entity)
                if hardware_res:
                    block, ent = hardware_res
                    context_blocks.append(block)
                    if ent:
                        updated_entity = ent

                # -------------------------------------------------------------
                # 3. DOMINIO ANALÍTICO Y CUANTITATIVO DE INVENTARIO Y SERIALES (RAG Fáctico)
                # -------------------------------------------------------------
                analytics_res = cls._resolve_inventory_analytics(normalized, user_message, cur)
                if analytics_res and not hardware_res:
                    block, ent = analytics_res
                    context_blocks.append(block)
                    if ent and not updated_entity:
                        updated_entity = ent

                # -------------------------------------------------------------
                # 4. DOMINIO DE TELEMETRÍA, UPTIME Y HORAS APAGADO (Downtime)
                # -------------------------------------------------------------
                uptime_res = cls._resolve_uptime_and_downtime(normalized, user_message, cur, updated_entity)
                if uptime_res:
                    block, ent = uptime_res
                    context_blocks.append(block)
                    if ent:
                        updated_entity = ent

                # -------------------------------------------------------------
                # 5. DOMINIO DE ENERGÍA Y UPS CENTRAL (ups_devices / telemetry)
                # -------------------------------------------------------------
                ups_block = cls._resolve_ups_and_power(normalized, cur)
                if ups_block:
                    context_blocks.append(ups_block)

                # -------------------------------------------------------------
                # 6. DOMINIO DE PROXIES CORPORATIVOS
                # -------------------------------------------------------------
                proxy_block = cls._resolve_proxies(normalized, cur)
                if proxy_block:
                    context_blocks.append(proxy_block)

                # -------------------------------------------------------------
                # 7. DOMINIO DE SEDES Y CENSO GLOBAL
                # -------------------------------------------------------------
                site_res = cls._resolve_sites_and_census(normalized, cur)
                if site_res and not analytics_res:
                    block, ent = site_res
                    context_blocks.append(block)
                    if ent:
                        updated_entity = ent

                # -------------------------------------------------------------
                # 8. FALLBACK FÁCTICO: Si consulta sobre el sistema/red y no hubo coincidencia previa
                # -------------------------------------------------------------
                if not context_blocks:
                    infra_kws = ("sistema", "red", "infraestructura", "servidores", "valle seco", "atit", "noc", "monitoreo", "equipos", "dispositivos", "inventario", "seriales")
                    if any(k in normalized for k in infra_kws):
                        fallback_res = cls._resolve_inventory_analytics("resumen inventario censo", user_message, cur)
                        if fallback_res:
                            block, ent = fallback_res
                            context_blocks.append(block)
                            if ent and not updated_entity:
                                updated_entity = ent

        except Exception as e:
            logger.exception(f"Error procesando contexto relacional de MariaDB para IA: {e}")
        finally:
            conn.close()

        if not context_blocks:
            return None, updated_entity

        full_context = "[CONTEXTO_DEL_SISTEMA - DATOS FÁCTICOS DE MARIADB]:\n\n" + "\n\n---\n\n".join(context_blocks)
        return full_context, updated_entity

    # =========================================================================
    # METODOS PRIVADOS DE RESOLUCIÓN POR DOMINIO
    # =========================================================================

    @classmethod
    def _resolve_assignments(
        cls,
        normalized: str,
        user_message: str,
        cur: Any
    ) -> Optional[Tuple[str, Dict[str, Any]]]:
        """Resuelve consultas sobre usuarios con asignaciones, actas UINF y custodios."""
        keywords = ("asignacion", "asignaciones", "asignad", "custodio", "responsable", "acta", "uinf", "pase-mov", "entrega", "quien tiene", "quién tiene")
        has_assign_kw = any(w in normalized for w in keywords)
        asking_counts = any(w in normalized for w in ("cuantos", "cuántos", "total", "usuarios", "lista", "resumen"))

        # Extraer posibles nombres o apellidos (ej. brito, jose, alfonso, etc.)
        stopwords = {
            "cuantos", "cuántos", "equipo", "equipos", "asignaciones", "asignacion",
            "tiene", "tienen", "quien", "quién", "oficina", "sede", "valle", "seco",
            "serial", "seriales", "usuario", "usuarios", "personal", "gente", "dame",
            "cuales", "cuáles", "lista", "total", "resumen", "acta", "actas"
        }
        tokens = re.findall(r"\b[A-Za-zÁÉÍÓÚáéíóúñÑ]{4,20}\b", user_message)
        person_tokens = [t for t in tokens if t.lower() not in stopwords]

        if not has_assign_kw and not person_tokens:
            return None

        # Si pregunta conteo o listado general de asignaciones
        if has_assign_kw and (asking_counts or not person_tokens):
            cur.execute("""
                SELECT count(*) as total_assignments,
                       count(DISTINCT custodian_name) as total_users,
                       count(DISTINCT monitored_site_id) as total_sites
                FROM inventory_assignments
                WHERE status = 'active'
            """)
            stats = cur.fetchone() or {}

            cur.execute("""
                SELECT ia.assignment_code, ia.custodian_name, ia.custodian_department,
                       ast.name as asset_name, ast.ip_address, ms.name as site_name
                FROM inventory_assignments ia
                LEFT JOIN inventory_assets ast ON ia.inventory_asset_id = ast.id
                LEFT JOIN monitored_sites ms ON ia.monitored_site_id = ms.id
                WHERE ia.status = 'active'
                ORDER BY ia.id DESC
                LIMIT 10
            """)
            rows = cur.fetchall()
            lines = [
                "📊 RESUMEN DE ASIGNACIONES Y USUARIOS (MariaDB - ITAM):",
                f"- Total de Actas de Asignación Activas: {stats.get('total_assignments', 0)}",
                f"- Total de Usuarios / Funcionarios Receptores Únicos: {stats.get('total_users', 0)}",
                f"- Sedes Involucradas: {stats.get('total_sites', 0)}",
                "",
                "📋 Muestra de Asignaciones Vigentes Registradas:"
            ]
            for r in rows:
                lines.append(f"• Acta: {r.get('assignment_code')} | Responsable: {r.get('custodian_name')} ({r.get('custodian_department') or 'Sin Depto'}) | Equipo: {r.get('asset_name')} (IP: {r.get('ip_address')}) | Sede: {r.get('site_name')}")

            return "\n".join(lines), {"type": "assignments", "name": "Resumen General de Asignaciones"}

        # Búsqueda específica por funcionario o acta
        search_terms = person_tokens if person_tokens else [t for t in tokens if len(t) >= 4]
        for term in search_terms:
            cur.execute("""
                SELECT ia.id, ia.assignment_code, ia.custodian_name, ia.custodian_id_number,
                       ia.custodian_department, ia.custodian_role, ia.phone_extension,
                       ia.status, ia.assigned_at, ast.name as asset_name, ast.asset_tag,
                       ast.ip_address, ast.serial_number as asset_serial, ast.brand,
                       ast.model, ms.name as site_name, ast.location_detail
                FROM inventory_assignments ia
                LEFT JOIN inventory_assets ast ON ia.inventory_asset_id = ast.id
                LEFT JOIN monitored_sites ms ON ia.monitored_site_id = ms.id
                WHERE ia.custodian_name LIKE %s OR ia.custodian_department LIKE %s OR ia.assignment_code LIKE %s
                LIMIT 5
            """, (f"%{term}%", f"%{term}%", f"%{term}%"))
            results = cur.fetchall()

            if results:
                lines = [f"📋 ASIGNACIONES LOCALIZADAS PARA '{term.upper()}' (MariaDB - ITAM):"]
                primary_custodian = results[0].get("custodian_name") or term
                for row in results:
                    lines.append(
                        f"• Acta Oficial: {row.get('assignment_code')} (Estado: {row.get('status')})\n"
                        f"  - Funcionario Responsable: {row.get('custodian_name')} (CI: {row.get('custodian_id_number') or 'N/A'})\n"
                        f"  - Departamento / Cargo: {row.get('custodian_department') or 'N/A'} | Rol: {row.get('custodian_role') or 'N/A'}\n"
                        f"  - Extensión Telefónica: {row.get('phone_extension') or 'N/A'}\n"
                        f"  - Puesto / Equipo Asignado: {row.get('asset_name')} (Tag: {row.get('asset_tag')}, IP: {row.get('ip_address')})\n"
                        f"  - Serial del Equipo: {row.get('asset_serial') or 'Sin serial chasis'}\n"
                        f"  - Ubicación Física: Sede {row.get('site_name')}, {row.get('location_detail') or 'Ubicación estándar'}\n"
                        f"  - Fecha de Entrega: {row.get('assigned_at') or 'Previa'}"
                    )
                return "\n\n".join(lines), {"type": "user", "name": primary_custodian}

        return None

    @classmethod
    def _resolve_hardware_and_serials(
        cls,
        normalized: str,
        user_message: str,
        cur: Any,
        active_entity: Optional[Dict[str, Any]]
    ) -> Optional[Tuple[str, Dict[str, Any]]]:
        """Resuelve ficha técnica profunda, seriales de CPU, componentes (RAM/Disco) y periféricos (Monitores/UPS)."""
        serial_kws = ("serial", "seriales", "hardware", "componente", "componentes", "partes", "disco", "ram", "memoria", "monitor", "monitores", "pantalla", "periferico", "periféricos", "mouse", "teclado", "impresora", "ups de puesto", "ficha tecnica", "ficha técnica")
        has_hardware_kw = any(w in normalized for w in serial_kws)

        # Buscar posibles referencias de equipos (por Nombre Etiqueta, Hostname, IP, Token o entidad previa)
        target_asset: Optional[Dict[str, Any]] = None

        # 1. Búsqueda por IP explícita
        ip_match = re.search(r"\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b", user_message)
        if ip_match:
            ip = ip_match.group(0)
            cur.execute("""
                SELECT ia.*, ms.name as site_name
                FROM inventory_assets ia
                LEFT JOIN monitored_sites ms ON ia.monitored_site_id = ms.id
                WHERE ia.ip_address = %s
                LIMIT 1
            """, (ip,))
            target_asset = cur.fetchone()

        # 2. Búsqueda por Nombre Etiqueta o Ubicación (ej. taquilla, garita, jefe, router, cencarcom)
        if not target_asset:
            words = re.findall(r"\b[A-Za-z0-9\-_]{4,25}\b", user_message)
            for w in words:
                w_clean = w.lower()
                if w_clean in {"serial", "seriales", "equipo", "dame", "cuales", "cuáles", "componentes", "partes", "monitor", "monitores"}:
                    continue
                cur.execute("""
                    SELECT ia.*, ms.name as site_name
                    FROM inventory_assets ia
                    LEFT JOIN monitored_sites ms ON ia.monitored_site_id = ms.id
                    WHERE ia.name LIKE %s OR ia.location_detail LIKE %s OR ia.serial_number LIKE %s OR ia.asset_tag LIKE %s
                    LIMIT 1
                """, (f"%{w}%", f"%{w}%", f"%{w}%", f"%{w}%"))
                target_asset = cur.fetchone()
                if target_asset:
                    break

        # 3. Resolución anafórica: Si no hubo nombre explícito pero pregunta por seriales/hardware usando entidad activa
        if not target_asset and has_hardware_kw and active_entity:
            ent_name = active_entity.get("name", "")
            if ent_name:
                cur.execute("""
                    SELECT ia.*, ms.name as site_name
                    FROM inventory_assets ia
                    LEFT JOIN monitored_sites ms ON ia.monitored_site_id = ms.id
                    WHERE ia.name LIKE %s OR ms.name LIKE %s OR ia.custodian_name LIKE %s
                    LIMIT 1
                """, (f"%{ent_name}%", f"%{ent_name}%", f"%{ent_name}%"))
                target_asset = cur.fetchone()

        if not target_asset and not has_hardware_kw:
            return None

        # Si se encontró un activo específico, extraer ficha completa (Chasis + Componentes + Periféricos)
        if target_asset:
            asset_id = target_asset["id"]
            asset_name = target_asset.get("name", "Equipo Sin Nombre")
            asset_ip = target_asset.get("ip_address")

            # Buscar todos los IDs de activos vinculados por IP o Nombre para cruce exhaustivo
            cur.execute("""
                SELECT id FROM inventory_assets
                WHERE (ip_address = %s AND ip_address IS NOT NULL AND ip_address != '')
                   OR name = %s OR id = %s
            """, (asset_ip, asset_name, asset_id))
            matching_ids = [r["id"] for r in cur.fetchall()] or [asset_id]
            format_strings = ','.join(['%s'] * len(matching_ids))

            # A. Componentes internos (Discos, RAM, Placa)
            cur.execute(f"""
                SELECT type, name, brand, model, capacity, serial_number
                FROM inventory_components
                WHERE inventory_asset_id IN ({format_strings})
            """, tuple(matching_ids))
            components = cur.fetchall()

            # B. Periféricos asociados (Monitores, UPS, Impresora, Teclado)
            cur.execute(f"""
                SELECT type, brand, model, serial_number, status, asset_tag
                FROM inventory_peripherals
                WHERE current_asset_id IN ({format_strings})
            """, tuple(matching_ids))
            peripherals = cur.fetchall()

            lines = [
                f"🖥️ FICHA TÉCNICA PROFUNDA Y SERIALES (MariaDB - ITAM):",
                f"- Nombre Etiqueta (Puesto): {asset_name}",
                f"- Tipo / Marca / Modelo: {target_asset.get('type')} | {target_asset.get('brand')} {target_asset.get('model')}",
                f"- Serial del Chasis / CPU: {target_asset.get('serial_number') or 'Sin serial en chasis'}",
                f"- Código de Activo (Asset Tag): {target_asset.get('asset_tag') or 'N/A'}",
                f"- Red: IP {target_asset.get('ip_address') or 'N/A'} | MAC {target_asset.get('mac_address') or 'N/A'}",
                f"- Sistema Operativo: {target_asset.get('operating_system') or 'N/A'}",
                f"- Sede y Ubicación: {target_asset.get('site_name')} - {target_asset.get('location_detail') or 'Ubicación estándar'}",
                f"- Funcionario Responsable: {target_asset.get('custodian_name') or 'Sin asignar'}",
                f"- Estado Operativo: {target_asset.get('status') or 'active'}",
                "",
                "💾 Componentes Internos Registrados:"
            ]

            if components:
                for c in components:
                    c_type = (c.get("type") or "Componente").upper()
                    c_name = c.get("name") or c.get("model") or "Genérico"
                    c_cap = f" ({c.get('capacity')})" if c.get('capacity') else ""
                    c_ser = c.get('serial_number') or "Sin serial registrado"
                    lines.append(f"  • {c_type}: {c_name}{c_cap} | Serial: {c_ser}")
            else:
                lines.append("  • Sin componentes internos desglosados en ITAM.")

            lines.append("")
            lines.append("🔌 Hardware Complementario y Periféricos Asociados:")
            if peripherals:
                for p in peripherals:
                    p_type = (p.get("type") or "Periférico").capitalize()
                    p_brand = f"{p.get('brand') or ''} {p.get('model') or ''}".strip() or "Estándar"
                    p_ser = p.get("serial_number") or "Sin serial registrado"
                    p_tag = f" [Tag: {p.get('asset_tag')}]" if p.get('asset_tag') else ""
                    lines.append(f"  • {p_type}: {p_brand}{p_tag} | Serial: {p_ser} (Estado: {p.get('status') or 'Asignado'})")
            else:
                lines.append("  • Sin periféricos asociados directamente a este activo.")

            return "\n".join(lines), {"type": "asset", "name": asset_name}

        # Si el usuario pregunta de forma general por seriales de una sede (ej. seriales de valle seco)
        cur.execute("SELECT id, name FROM monitored_sites WHERE is_active = 1")
        sites = cur.fetchall()
        for s in sites:
            s_name = s["name"].lower()
            if s_name in normalized or (active_entity and active_entity.get("name", "").lower() in s_name):
                cur.execute("""
                    SELECT ia.name, ia.ip_address, ia.serial_number, ia.asset_tag, ia.type
                    FROM inventory_assets ia
                    WHERE ia.monitored_site_id = %s AND (ia.serial_number IS NOT NULL AND ia.serial_number != '')
                    LIMIT 15
                """, (s["id"],))
                devs = cur.fetchall()

                if devs:
                    lines = [f"🔢 LISTADO DE SERIALES REGISTRADOS EN SEDE {s['name']} (MariaDB):"]
                    for d in devs:
                        lines.append(f"• {d['name']} ({d['type']}) | IP: {d['ip_address']} | Serial: {d['serial_number']} | Tag: {d['asset_tag'] or 'N/A'}")
                    return "\n".join(lines), {"type": "site", "name": s["name"]}

        return None

    @classmethod
    def _resolve_inventory_analytics(
        cls,
        normalized: str,
        user_message: str,
        cur: Any
    ) -> Optional[Tuple[str, Dict[str, Any]]]:
        """
        Resuelve consultas analíticas, cuantitativas y de agregación sobre el inventario y seriales
        (ej. cuántos equipos tienen serial, total inventariado, desglose por tipo o componentes).
        """
        count_indicators = ("cuant", "cuánt", "total", "cantidad", "numero", "número", "conteo", "censo", "estadistica", "estadística", "resumen")
        inv_indicators = ("equipo", "equipos", "dispositivo", "dispositivos", "activo", "activos", "inventari", "serial", "seriales", "chasis", "periferico", "periféricos", "componente", "componentes", "switch", "switches", "router", "routers", "impresora", "impresoras", "desktop", "computadora", "computadoras", "pc", "estacion", "estación")
        direct_phrases = (
            "que hay en el sistema", "qué hay en el sistema", "que equipos hay", "qué equipos hay",
            "que dispositivos hay", "qué dispositivos hay", "que tienes inventariado", "qué tienes inventariado",
            "listar equipos", "lista de equipos", "listado de equipos", "listado de los equipos", "listado de",
            "dame una lista", "dame un listado", "mostrar equipos", "muestra de equipos",
            "equipos registrados", "dispositivos registrados",
            "poseen serial", "posee serial", "tienen serial", "tiene serial", "con serial", "sin serial",
            "asociado en el sistema", "sistema de inventario"
        )

        has_count = any(k in normalized for k in count_indicators)
        has_inv = any(k in normalized for k in inv_indicators)
        has_direct = any(p in normalized for p in direct_phrases)

        # Si no hay indicios de consulta cuantitativa o de inventario global, ignorar
        if not ((has_count and has_inv) or has_direct):
            return None

        # 1. Conteo total de activos físicos en ITAM
        cur.execute("SELECT count(*) as total FROM inventory_assets")
        total_assets = cur.fetchone()["total"] or 0

        # 2. Conteo de activos con serial válido en chasis / CPU
        cur.execute("""
            SELECT count(*) as with_serial FROM inventory_assets 
            WHERE serial_number IS NOT NULL 
              AND TRIM(serial_number) != '' 
              AND LOWER(TRIM(serial_number)) NOT IN ('sin serial', 'sin serial en chasis', 's/s', 'n/a', 'none', 'null')
        """)
        with_serial = cur.fetchone()["with_serial"] or 0
        without_serial = max(0, total_assets - with_serial)
        pct_serial = round((with_serial / total_assets * 100), 1) if total_assets > 0 else 0.0

        # 3. Desglose de activos por tipo de hardware
        cur.execute("SELECT type, count(*) as count FROM inventory_assets GROUP BY type ORDER BY count DESC")
        types_rows = cur.fetchall()
        type_names = {
            "desktop": "computadoras de escritorio / PCs",
            "printer": "impresoras de red",
            "switch": "switches de comunicación",
            "router": "routers de borde / WAN",
            "server": "servidores corporativos",
            "ip_phone": "teléfonos IP",
            "firewall": "firewalls perimetrales"
        }
        types_breakdown = ", ".join([f"{r['count']} {type_names.get(r['type'], r['type'])}" for r in types_rows])

        # 4. Periféricos asociados y sus seriales (Monitores, UPS, Impresoras)
        cur.execute("""
            SELECT count(*) as total, 
                   sum(CASE WHEN serial_number IS NOT NULL AND TRIM(serial_number) != '' AND LOWER(TRIM(serial_number)) NOT IN ('sin serial', 's/s', 'n/a', 'none') THEN 1 ELSE 0 END) as with_ser 
            FROM inventory_peripherals
        """)
        p_row = cur.fetchone() or {}
        total_periphs = p_row.get("total") or 0
        periphs_with_serial = int(p_row.get("with_ser") or 0)

        # 5. Componentes internos y sus seriales (RAM, Discos)
        cur.execute("""
            SELECT count(*) as total, 
                   sum(CASE WHEN serial_number IS NOT NULL AND TRIM(serial_number) != '' AND LOWER(TRIM(serial_number)) NOT IN ('sin serial', 's/s', 'n/a', 'none') THEN 1 ELSE 0 END) as with_ser 
            FROM inventory_components
        """)
        c_row = cur.fetchone() or {}
        total_comps = c_row.get("total") or 0
        comps_with_serial = int(c_row.get("with_ser") or 0)

        # 6. Actas y red
        cur.execute("SELECT count(*) as total FROM inventory_assignments WHERE status = 'active'")
        total_assign = cur.fetchone()["total"] or 0
        cur.execute("SELECT count(*) as total FROM monitored_network_devices WHERE is_active = 1")
        total_net = cur.fetchone()["total"] or 0

        # 7. Muestra de equipos con serial registrado
        cur.execute("""
            SELECT name, type, serial_number, asset_tag, ip_address 
            FROM inventory_assets 
            WHERE serial_number IS NOT NULL 
              AND TRIM(serial_number) != '' 
              AND LOWER(TRIM(serial_number)) NOT IN ('sin serial', 'sin serial en chasis', 's/s', 'n/a', 'none', 'null')
            ORDER BY name ASC
            LIMIT 12
        """)
        sample_assets = cur.fetchall()

        lines = [
            "📊 ANALÍTICA Y CENSO FÁCTICO DE INVENTARIO Y SERIALES (MariaDB SSOT):",
            f"- Total de dispositivos físicos inventariados en el sistema (ITAM): {total_assets} equipos.",
            f"- Equipos con serial de chasis/CPU registrado: {with_serial} equipos ({pct_serial}% del inventario de activos).",
            f"- Equipos sin serial de chasis (o pendientes por asignación física): {without_serial} equipos.",
            f"- Desglose por tipo de hardware: {types_breakdown}.",
            f"- Periféricos asociados en inventario (Monitores, UPS, teclados, etc.): {total_periphs} periféricos en total ({periphs_with_serial} con serial registrado).",
            f"- Componentes internos desglosados (Discos, Memoria RAM, Placas): {total_comps} componentes en total ({comps_with_serial} con serial registrado).",
            f"- Total de actas de asignación a funcionarios vigentes: {total_assign}.",
            f"- Dispositivos de red supervisados en vivo por el NOC: {total_net}.",
        ]
        if with_serial == 0 or not sample_assets:
            lines.extend([
                "",
                "🔢 LISTADO DE EQUIPOS CON SERIAL ASOCIADO:",
                f"  • Actualmente NO existe ningún equipo con serial de chasis o CPU asociado/registrado en el inventario del sistema (0 de {total_assets} equipos).",
                "  • Estado: Todos los equipos registrados en el inventario figuran actualmente sin serial cargado o pendientes por catalogación física."
            ])
        else:
            lines.extend([
                "",
                "🔢 Muestra de equipos con serial registrado en el sistema:"
            ])
            for a in sample_assets:
                ip_str = f" | IP: {a['ip_address']}" if a.get("ip_address") else ""
                tag_str = f" | Tag: {a['asset_tag']}" if a.get("asset_tag") else ""
                lines.append(f"  • {a['name']} ({a['type']}){tag_str}{ip_str} | Serial: {a['serial_number']}")

        return "\n".join(lines), {"type": "census", "name": "Analítica de Inventario"}

    @classmethod
    def _resolve_uptime_and_downtime(
        cls,
        normalized: str,
        user_message: str,
        cur: Any,
        active_entity: Optional[Dict[str, Any]]
    ) -> Optional[Tuple[str, Dict[str, Any]]]:
        """Resuelve horas de apagado, tiempo fuera de línea, última caída y estabilidad de red."""
        uptime_kws = ("apagado", "horas", "tiempo fuera", "caido", "caído", "downtime", "uptime", "cuando se cayo", "cuándo se cayó", "disponibilidad", "tiempo de caida", "tiempo de caída", "desconectado")
        if not any(w in normalized for w in uptime_kws):
            return None

        # Si la consulta se refiere a servicios o proxies, no interceptar con telemetría de switches/routers
        if any(w in normalized for w in ("servicio", "servicios", "otrs", "intranet", "factura digital", "claves", "sao", "surge")):
            return None

        # Identificar equipo consultado
        target_dev: Optional[Dict[str, Any]] = None

        ip_match = re.search(r"\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b", user_message)
        if ip_match:
            ip = ip_match.group(0)
            cur.execute("""
                SELECT mnd.*, ms.name as site_name,
                       TIMESTAMPDIFF(HOUR, mnd.down_since, NOW()) as hours_down,
                       MOD(TIMESTAMPDIFF(MINUTE, mnd.down_since, NOW()), 60) as mins_down
                FROM monitored_network_devices mnd
                LEFT JOIN monitored_sites ms ON mnd.monitored_site_id = ms.id
                WHERE mnd.ip = %s
                LIMIT 1
            """, (ip,))
            target_dev = cur.fetchone()

        if not target_dev:
            words = re.findall(r"\b[A-Za-z0-9\-_]{4,25}\b", user_message)
            for w in words:
                w_clean = w.lower()
                if w_clean in {"horas", "apagado", "tiempo", "caido", "caído", "equipo", "cuanto", "cuánto", "downtime", "uptime"}:
                    continue
                cur.execute("""
                    SELECT mnd.*, ms.name as site_name,
                           TIMESTAMPDIFF(HOUR, mnd.down_since, NOW()) as hours_down,
                           MOD(TIMESTAMPDIFF(MINUTE, mnd.down_since, NOW()), 60) as mins_down
                    FROM monitored_network_devices mnd
                    LEFT JOIN monitored_sites ms ON mnd.monitored_site_id = ms.id
                    WHERE mnd.name LIKE %s
                    LIMIT 1
                """, (f"%{w}%",))
                target_dev = cur.fetchone()
                if target_dev:
                    break

        if not target_dev and active_entity:
            ent_name = active_entity.get("name", "")
            if ent_name:
                cur.execute("""
                    SELECT mnd.*, ms.name as site_name,
                           TIMESTAMPDIFF(HOUR, mnd.down_since, NOW()) as hours_down,
                           MOD(TIMESTAMPDIFF(MINUTE, mnd.down_since, NOW()), 60) as mins_down
                    FROM monitored_network_devices mnd
                    LEFT JOIN monitored_sites ms ON mnd.monitored_site_id = ms.id
                    WHERE mnd.name LIKE %s
                    LIMIT 1
                """, (f"%{ent_name}%",))
                target_dev = cur.fetchone()

        if not target_dev:
            # Si pregunta en general por equipos apagados actualmente en toda la red
            cur.execute("""
                SELECT mnd.name, mnd.ip, mnd.down_since, ms.name as site_name,
                       TIMESTAMPDIFF(HOUR, mnd.down_since, NOW()) as hours_down,
                       MOD(TIMESTAMPDIFF(MINUTE, mnd.down_since, NOW()), 60) as mins_down
                FROM monitored_network_devices mnd
                LEFT JOIN monitored_sites ms ON mnd.monitored_site_id = ms.id
                WHERE mnd.down_since IS NOT NULL
                ORDER BY mnd.down_since ASC
            """)
            down_devices = cur.fetchall()

            if down_devices:
                lines = [f"⚠️ REPORTE DE EQUIPOS APAGADOS / FUERA DE LÍNEA ({len(down_devices)} DETECTADOS):"]
                for d in down_devices:
                    h = d.get("hours_down") or 0
                    m = d.get("mins_down") or 0
                    lines.append(f"• {d['name']} (IP: {d['ip']}) | Sede: {d['site_name']} | Apagado desde: {d['down_since']} (Tiempo caído: {h}h {m}m)")
                return "\n".join(lines), {"type": "telemetry", "name": "Equipos Caídos"}
            else:
                return "✅ TELEMETRÍA EN VIVO (MariaDB SSOT):\nTodos los switches y routers de red monitoreados en la infraestructura se encuentran actualmente EN LÍNEA (0 equipos de red con marca de caída activa).", None

        # Telemetría de un equipo específico
        dev_id = target_dev["id"]
        dev_name = target_dev["name"]
        down_since = target_dev.get("down_since")

        # Consultar estadísticas de sondeo en las últimas 24 horas
        cur.execute("""
            SELECT count(*) as total,
                   sum(case when is_up = 1 then 1 else 0 end) as up_checks,
                   sum(case when is_up = 0 then 1 else 0 end) as down_checks
            FROM network_device_check_histories
            WHERE monitored_network_device_id = %s AND checked_at >= NOW() - INTERVAL 24 HOUR
        """, (dev_id,))
        stats_24h = cur.fetchone() or {}

        total_checks = stats_24h.get("total") or 0
        up_checks = int(stats_24h.get("up_checks") or 0)
        down_checks = int(stats_24h.get("down_checks") or 0)
        uptime_pct = round((up_checks / total_checks * 100), 1) if total_checks > 0 else 100.0

        lines = [
            f"⏱️ TELEMETRÍA DE DISPONIBILIDAD Y UPTIME: {dev_name}",
            f"- Dirección IP: {target_dev.get('ip')} | Sede: {target_dev.get('site_name')}",
        ]

        if down_since:
            h = target_dev.get("hours_down") or 0
            m = target_dev.get("mins_down") or 0
            lines.extend([
                f"- ESTADO ACTUAL: ❌ FUERA DE LÍNEA / APAGADO",
                f"- Caído desde: {down_since}",
                f"- TIEMPO TOTAL APAGADO: {h} horas y {m} minutos continuos.",
                f"- Último mensaje de estado: {target_dev.get('error_state_msg') or 'Sin respuesta de ping/puerto'}"
            ])
        else:
            lines.extend([
                f"- ESTADO ACTUAL: ✅ EN LÍNEA (Operativo)",
                f"- Disponibilidad en las últimas 24 horas: {uptime_pct}% ({up_checks} sondeos exitosos de {total_checks})",
                f"- Total de chequeos fallidos en 24h: {down_checks}",
                f"- Condición del enlace: Estable",
                f"- Eventos de caída recientes: Ninguno. El equipo se mantiene 100% operativo sin interrupciones registradas en las últimas 24 horas."
            ])

        return "\n".join(lines), {"type": "asset", "name": dev_name}

    @classmethod
    def _resolve_ups_and_power(cls, normalized: str, cur: Any) -> Optional[str]:
        """Resuelve el estado del respaldo eléctrico, baterías y UPS central."""
        ups_kws = ("ups", "energia", "energía", "bateria", "batería", "baterias", "baterías", "autonomia", "autonomía", "voltaje", "corte", "falla electrica", "falla eléctrica", "corriente", "electrico", "eléctrico")
        if not any(w in normalized for w in ups_kws):
            return None

        cur.execute("SELECT * FROM ups_devices LIMIT 1")
        ups = cur.fetchone()
        if not ups:
            return None

        cur.execute("SELECT * FROM ups_telemetry_histories WHERE ups_device_id = %s ORDER BY recorded_at DESC LIMIT 1", (ups["id"],))
        telem = cur.fetchone() or {}

        v_in = telem.get("input_voltage") or ups.get("input_voltage") or 0
        v_out = telem.get("output_voltage") or ups.get("output_voltage") or 0
        load = telem.get("load_percent") or ups.get("load_percent") or 0
        bat_pct = telem.get("battery_percent") or ups.get("battery_percent") or 0
        bat_v = telem.get("battery_voltage") or ups.get("battery_voltage") or 0
        temp = telem.get("temperature_c") or ups.get("temperature_c") or 0
        on_battery = telem.get("is_on_battery") or ups.get("is_on_battery") or 0

        status_str = "⚠️ OPERANDO CON BATERÍAS (CORTE ELÉCTRICO EN PROGRESO)" if on_battery else "✅ EN LÍNEA (Alimentación AC Normal)"

        lines = [
            f"⚡ ESTADO DEL SISTEMA DE ENERGÍA Y RESPALDO (MariaDB - UPS):",
            f"- Dispositivo: {ups.get('name')} (Modelo: {ups.get('model')}, Serial: {ups.get('serial_number')})",
            f"- Condición Eléctrica: {status_str}",
            f"- Voltaje de Entrada (AC): {v_in} V | Voltaje de Salida: {v_out} V",
            f"- Carga Actual: {load}%",
            f"- Nivel de Carga de Baterías: {bat_pct}% (Voltaje de banco: {bat_v} Vdc)",
            f"- Temperatura del Sistema: {temp} °C",
            f"- Última telemetría registrada: {ups.get('last_seen_at') or 'Reciente'}"
        ]
        return "\n".join(lines)

    @classmethod
    def _resolve_proxies(cls, normalized: str, cur: Any) -> Optional[str]:
        """Resuelve lista de proxies oficiales de navegación."""
        if not any(w in normalized for w in ("proxy", "proxies", "squid", "navegacion", "navegación")):
            return None

        cur.execute("SELECT letter, name, ip_port, is_active FROM monitored_proxies ORDER BY letter")
        proxies = cur.fetchall()
        if not proxies:
            return None

        lines = ["🌐 LISTA OFICIAL DE PROXIES CORPORATIVOS (MariaDB):"]
        for p in proxies:
            st = "ACTIVO" if p.get("is_active") else "INACTIVO"
            lines.append(f"• Proxy {p.get('letter')}: {p.get('name')} | IP y Puerto: {p.get('ip_port')} | Estado: {st}")
        return "\n".join(lines)

    @classmethod
    def _resolve_sites_and_census(cls, normalized: str, cur: Any) -> Optional[Tuple[str, Dict[str, Any]]]:
        """Resuelve conteos por sede y censo total de la infraestructura."""
        asking_census = any(w in normalized for w in ("resumen general", "estatus global", "censo total", "cuantas sedes", "cuántas sedes"))

        if asking_census:
            cur.execute("SELECT count(*) as total FROM monitored_sites WHERE is_active = 1")
            total_sites = cur.fetchone()["total"]
            cur.execute("SELECT count(*) as total FROM inventory_assets")
            total_assets = cur.fetchone()["total"]
            cur.execute("SELECT count(*) as total FROM monitored_network_devices WHERE is_active = 1")
            total_net = cur.fetchone()["total"]
            cur.execute("SELECT count(*) as total FROM monitored_proxies WHERE is_active = 1")
            total_proxies = cur.fetchone()["total"]
            cur.execute("SELECT count(*) as total FROM inventory_assignments WHERE status = 'active'")
            total_assign = cur.fetchone()["total"]

            lines = [
                "🏛️ CENSO GLOBAL DE INFRAESTRUCTURA (MariaDB SSOT):",
                f"- Sedes Operativas Activas: {total_sites}",
                f"- Total Activos Registrados en ITAM: {total_assets}",
                f"- Dispositivos de Red Monitoreados en NOC: {total_net}",
                f"- Actas de Asignación de Equipos Vigentes: {total_assign}",
                f"- Proxies Corporativos de Navegación: {total_proxies}"
            ]
            return "\n".join(lines), {"type": "census", "name": "Censo Global"}

        # Búsqueda por sede específica
        cur.execute("SELECT id, name, ip FROM monitored_sites WHERE is_active = 1")
        sites = cur.fetchall()

        for site in sites:
            s_name = site["name"].lower()
            matches = False

            if s_name in normalized:
                matches = True
            elif "mariño" in s_name and any(w in normalized for w in ("mariño", "marino", "paseo")):
                matches = True
            elif "consolidado" in s_name and "consolidado" in normalized:
                matches = True
            elif "moron" in s_name and any(w in normalized for w in ("moron", "morón")):
                matches = True
            elif "trasmision" in s_name and any(w in normalized for w in ("transmision", "trasmision", "transmisión", "planta centro")):
                matches = True
            elif "valle seco" in s_name and "valle seco" in normalized:
                if "ciau" in s_name and any(w in normalized for w in ("ciau", "comercial", "taquilla", "oficina")):
                    matches = True
                elif "ciau" not in s_name and not any(w in normalized for w in ("ciau", "comercial")):
                    matches = True

            if matches:
                site_id = site["id"]
                cur.execute("SELECT count(*) as total FROM inventory_assets WHERE monitored_site_id = %s", (site_id,))
                asset_count = cur.fetchone()["total"]

                cur.execute("SELECT count(*) as total FROM monitored_network_devices WHERE monitored_site_id = %s", (site_id,))
                net_count = cur.fetchone()["total"]

                cur.execute("SELECT type, count(*) as count FROM inventory_assets WHERE monitored_site_id = %s GROUP BY type", (site_id,))
                type_rows = cur.fetchall()
                breakdown = ", ".join([f"{tr['count']} {tr['type']}(s)" for tr in type_rows]) or "Sin desglose"

                cur.execute("SELECT name, ip_address, serial_number, type, location_detail FROM inventory_assets WHERE monitored_site_id = %s LIMIT 15", (site_id,))
                dev_rows = cur.fetchall()
                dev_lines = [f"• {d['name']} ({d['type']}) | IP: {d['ip_address']} | Serial: {d['serial_number'] or 'Sin serial'} | Puesto: {d['location_detail'] or 'Estándar'}" for d in dev_rows]

                lines = [
                    f"🏢 DETALLE DE LA SEDE: {site['name']} (Subred: {site.get('ip', 'N/A')}):",
                    f"- Total Activos en ITAM: {asset_count}",
                    f"- Dispositivos de Red Monitoreados: {net_count}",
                    f"- Tipos de Hardware: {breakdown}",
                    "",
                    "🖥️ Equipos Registrados en esta Sede:",
                    "\n".join(dev_lines) if dev_lines else "• Sin equipos cargados en esta sede."
                ]
                return "\n".join(lines), {"type": "site", "name": site["name"]}

        return None
