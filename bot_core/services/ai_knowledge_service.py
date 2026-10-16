"""
# ==============================================================================
# 🧠 MOTOR DE CONOCIMIENTO DE INFRAESTRUCTURA Y GUARDRAILS: ai_knowledge_service.py
# Inyección contextual en tiempo real desde MariaDB (SSOT) para el Asistente de IA de ATIT.
# Delimitación estricta de alcance técnico y redirección a Google para temas no informáticos.
# ==============================================================================
"""

from __future__ import annotations

import logging
import re
from typing import Any, Dict, List, Optional

logger = logging.getLogger("bot.services.ai_knowledge")


class AiKnowledgeService:
    """Servicio de contexto de conocimiento y delimitación de dominio para el asistente de IA."""

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
            "   - Datos y supervisión de la infraestructura de este sistema (proxies, sedes, enlaces WAN, inventario de equipos y telemetría).\n\n"
            "2. DELIMITACIÓN DE DOMINIO ESTRICTA (GUARDRAIL INVIOLABLE):\n"
            "   - Si el usuario te hace preguntas técnicas de telecomunicaciones o TI (ej. '¿Cómo configurar una VLAN en un switch Cisco?', '¿Cómo reiniciar un servicio en Linux?'), responde con máxima solvencia técnica, explicando los pasos y entregando los comandos formateados en bloques de código (```cisco o ```bash).\n"
            "   - Si el usuario te hace preguntas ajenas a la tecnología, informática o telecomunicaciones (tales como historia, política, deportes, entretenimiento, recetas de cocina, personajes históricos como Simón Bolívar, geografía general, etc.):\n"
            "     TIENES LA PROHIBICIÓN ESTRICTA DE RESPONDER A ESOS TEMAS.\n"
            "     En su lugar, debes declinar educadamente con el siguiente mensaje formal y exacto:\n"
            "     \"Como Asistente Técnico corporativo de ATIT, mi función está orientada exclusivamente a la infraestructura de red, telecomunicaciones y soporte técnico de TI. Para temas históricos, de cultura general o ajenos a la tecnología, por favor realiza tu consulta en Google (www.google.com).\"\n\n"
            "3. DATOS DE INFRAESTRUCTURA REAL (MARIADB SSOT):\n"
            "   - Cuando se te provean datos en el bloque [CONTEXTO_DEL_SISTEMA], utilízalos con absoluta fidelidad y exactitud. Nunca inventes IPs, seriales, nombres de equipos ni sedes.\n"
            "   - Si no encuentras un dato específico en el contexto provisto, indícalo con franqueza sin alucinar.\n\n"
            "4. IDIOMA Y ESTILO:\n"
            "   - Responde siempre en español profesional, sobrio, claro y estructurado con viñetas y negritas.\n"
        )

    @classmethod
    def resolve_system_context(cls, user_message: str) -> Optional[str]:
        """
        Analiza la intención del usuario y extrae datos en vivo de MariaDB (SSOT)
        para proveer respuestas exactas sobre proxies, inventario, sedes o seriales.
        """
        normalized = user_message.lower().strip()
        context_blocks: List[str] = []

        try:
            from monitor.monitor_db import get_db_connection
            conn = get_db_connection()
        except Exception as e:
            logger.warning(f"No se pudo conectar a MariaDB para extraer contexto de IA: {e}")
            return None

        try:
            with conn.cursor() as cur:
                # 1. Consulta de Proxies corporativos
                if any(w in normalized for w in ("proxy", "proxies", "squid", "navegacion", "navegación")):
                    cur.execute("SELECT letter, name, ip_port, is_active FROM monitored_proxies ORDER BY letter")
                    proxies = cur.fetchall()
                    if proxies:
                        lines = ["LISTA OFICIAL DE PROXIES CORPORATIVOS (MariaDB):"]
                        for p in proxies:
                            status = "ACTIVO" if p.get("is_active") else "INACTIVO"
                            lines.append(f"- Proxy {p.get('letter')}: {p.get('name')} | IP y Puerto: {p.get('ip_port')} | Estado: {status}")
                        context_blocks.append("\n".join(lines))

                # 2. Consulta de Inventario o conteo por Sede
                cur.execute("SELECT id, name, ip FROM monitored_sites WHERE is_active = 1")
                sites = cur.fetchall()

                for site in sites:
                    site_id = site["id"]
                    site_name = site["name"]
                    site_norm = site_name.lower()
                    matches = False

                    if site_norm in normalized:
                        matches = True
                    else:
                        if "mariño" in site_norm and any(w in normalized for w in ("mariño", "marino", "paseo")):
                            matches = True
                        elif "consolidado" in site_norm and "consolidado" in normalized:
                            matches = True
                        elif "moron" in site_norm and any(w in normalized for w in ("moron", "morón")):
                            matches = True
                        elif "trasmision" in site_norm and any(w in normalized for w in ("transmision", "trasmision", "transmisión", "planta centro")):
                            matches = True
                        elif "valle seco" in site_norm and "valle seco" in normalized:
                            if "ciau" in site_norm and any(w in normalized for w in ("ciau", "comercial", "taquilla", "oficina")):
                                matches = True
                            elif "ciau" not in site_norm and not any(w in normalized for w in ("ciau", "comercial")):
                                matches = True

                    if matches and any(w in normalized for w in ("equipo", "equipos", "cuantos", "cuántos", "inventario", "sede", "dispositivos", "activos", "hay", "tienen", "computadoras", "pc")):
                        cur.execute("SELECT count(*) as total FROM inventory_assets WHERE monitored_site_id = %s", (site_id,))
                        asset_row = cur.fetchone()
                        asset_count = asset_row["total"] if asset_row else 0

                        cur.execute("SELECT count(*) as total FROM monitored_network_devices WHERE monitored_site_id = %s", (site_id,))
                        net_row = cur.fetchone()
                        net_count = net_row["total"] if net_row else 0

                        cur.execute("SELECT type, count(*) as count FROM inventory_assets WHERE monitored_site_id = %s GROUP BY type", (site_id,))
                        type_rows = cur.fetchall()
                        breakdown_parts = [f"{tr['count']} {tr['type']}(s)" for tr in type_rows]
                        breakdown_text = ", ".join(breakdown_parts) if breakdown_parts else "Sin desglose de tipos"

                        cur.execute("SELECT name, ip_address, serial_number, type FROM inventory_assets WHERE monitored_site_id = %s LIMIT 15", (site_id,))
                        device_rows = cur.fetchall()
                        dev_lines = [f"- {d['name']} ({d['type']}) | IP: {d['ip_address']} | Serial: {d['serial_number']}" for d in device_rows]
                        devices_list = "\n".join(dev_lines) if dev_lines else "Sin equipos registrados"

                        site_block = (
                            f"DATOS DE LA SEDE: {site_name} (ID: {site_id}, Subred: {site.get('ip', 'N/A')})\n"
                            f"- Total Equipos Registrados en Inventario ITAM: {asset_count}\n"
                            f"- Dispositivos de Red Monitoreados en NOC: {net_count}\n"
                            f"- Desglose por Tipos: {breakdown_text}\n"
                            f"- Equipos registrados en esta sede:\n{devices_list}"
                        )
                        context_blocks.append(site_block)

                # 3. Búsqueda por Serial, IP o Código de Activo
                potential_tokens = re.findall(r"\b([A-Za-z0-9\-_]{6,25})\b", user_message)
                ip_tokens = re.findall(r"\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b", user_message)
                all_tokens = list(set(potential_tokens + ip_tokens))

                ignored = {"cisco", "switch", "router", "debian", "valle", "moron", "puerto", "config", "serial", "servidor", "direccion", "ip"}
                for token in all_tokens:
                    token_clean = token.strip()
                    if token_clean.lower() in ignored:
                        continue

                    cur.execute(
                        """
                        SELECT ia.name, ia.type, ia.brand, ia.model, ia.serial_number, ia.asset_tag,
                               ia.ip_address, ia.mac_address, ia.custodian_name, ia.status, ms.name as site_name
                        FROM inventory_assets ia
                        LEFT JOIN monitored_sites ms ON ia.monitored_site_id = ms.id
                        WHERE ia.serial_number LIKE %s OR ia.asset_tag LIKE %s OR ia.ip_address = %s
                        LIMIT 1
                        """,
                        (f"%{token_clean}%", f"%{token_clean}%", token_clean),
                    )
                    asset = cur.fetchone()
                    if asset:
                        site_name = asset.get("site_name") or "Sede no asignada"
                        responsable = asset.get("custodian_name") or "Sin responsable asignado"
                        asset_block = (
                            f"FICHA TÉCNICA DEL ACTIVO DETECTADO (MariaDB):\n"
                            f"- Nombre Etiqueta: {asset.get('name')}\n"
                            f"- Tipo: {asset.get('type')} | Marca: {asset.get('brand')} | Modelo: {asset.get('model')}\n"
                            f"- Número de Serie (Serial): {asset.get('serial_number')}\n"
                            f"- Código de Activo (Asset Tag): {asset.get('asset_tag')}\n"
                            f"- Dirección IP: {asset.get('ip_address')} | Dirección MAC: {asset.get('mac_address')}\n"
                            f"- Sede: {site_name}\n"
                            f"- Responsable Asignado: {responsable}\n"
                            f"- Estado Operativo: {asset.get('status')}"
                        )
                        context_blocks.append(asset_block)
                        break

                # 4. Resumen Global o Censo Total
                if any(w in normalized for w in ("resumen general", "estatus global", "censo total", "cuantas sedes", "cuántas sedes")):
                    cur.execute("SELECT count(*) as total FROM monitored_sites WHERE is_active = 1")
                    total_sites = cur.fetchone()["total"]
                    cur.execute("SELECT count(*) as total FROM inventory_assets")
                    total_assets = cur.fetchone()["total"]
                    cur.execute("SELECT count(*) as total FROM monitored_proxies WHERE is_active = 1")
                    total_proxies = cur.fetchone()["total"]
                    cur.execute("SELECT count(*) as total FROM monitored_network_devices WHERE is_active = 1")
                    total_net_devs = cur.fetchone()["total"]

                    census_block = (
                        f"CENSO GLOBAL DE INFRAESTRUCTURA (MariaDB):\n"
                        f"- Sedes Activas: {total_sites}\n"
                        f"- Total Activos en Inventario ITAM: {total_assets}\n"
                        f"- Dispositivos de Red Monitoreados: {total_net_devs}\n"
                        f"- Proxies Oficiales Activos: {total_proxies}"
                    )
                    context_blocks.append(census_block)

        finally:
            conn.close()

        if not context_blocks:
            return None

        return "[CONTEXTO_DEL_SISTEMA - DATOS FÁCTICOS DE MARIADB]:\n" + "\n\n".join(context_blocks)
