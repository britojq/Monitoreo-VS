#!/usr/bin/env python3
# ==============================================================================
# 🧠 GRAFO DE CONOCIMIENTO INTEGRAL DEL SISTEMA: knowledge_graph.py
# Motor de Grafo Relacional (Graph RAG) sobre MariaDB SSOT para Asistente de IA
# Ubicación: /scripts/telegram-admin-bot/monitor/knowledge_graph.py
# ==============================================================================

from __future__ import annotations

import logging
import re
import threading
import time
from dataclasses import dataclass, field
from enum import Enum
from typing import Any, Dict, List, Optional, Protocol, Set, Tuple

import networkx as nx

from monitor.monitor_db import get_db_connection

logger = logging.getLogger("monitor.knowledge_graph")


class KnowledgeNodeType(str, Enum):
    """Tipos de entidades/nodos en el grafo de infraestructura."""
    SITE = "site"
    NETWORK_DEVICE = "network_device"
    UPS = "ups"
    INVENTORY_ASSET = "inventory_asset"
    POWER_EVENT = "power_event"
    ALERT = "alert"
    TICKET = "ticket"
    SUBNET = "subnet"
    SSL_CERTIFICATE = "ssl_certificate"
    SERVICE = "service"
    PROXY = "proxy"


class KnowledgeEdgeType(str, Enum):
    """Tipos de relaciones dirigidas entre entidades del grafo."""
    LOCATED_AT = "LOCATED_AT"              # Equipo/Activo/UPS ubicado en Sede
    POWERS = "POWERS"                      # UPS suministra energía a Equipo
    RECORDS_EVENT = "RECORDS_EVENT"        # UPS registra Evento de Energía
    GENERATED_ALERT = "GENERATED_ALERT"    # Equipo/Servicio generó Alerta
    HAS_ASSET = "HAS_ASSET"                # Dispositivo de Red vinculado a Ficha ITAM
    ASSIGNED_TO = "ASSIGNED_TO"            # Activo ITAM asignado a Responsable/Dpto
    CONNECTED_TO = "CONNECTED_TO"          # Enlace físico/lógico entre Dispositivos
    MONITORS = "MONITORS"                  # Servicio/Proxy monitorea IP/Host
    IN_SUBNET = "IN_SUBNET"                # Dispositivo pertenece a Subred
    SECURED_BY = "SECURED_BY"              # Servicio/Sede asegurado por SSL
    HAS_TICKET = "HAS_TICKET"              # Activo posee Ticket de Mantenimiento


@dataclass(frozen=True)
class KnowledgeNode:
    """Nodo inmutable del grafo de conocimiento."""
    id: str
    node_type: KnowledgeNodeType
    label: str
    entity_id: int
    attributes: Dict[str, Any] = field(default_factory=dict)

    def get(self, key: str, default: Any = None) -> Any:
        return self.attributes.get(key, default)


@dataclass(frozen=True)
class KnowledgeEdge:
    """Arista dirigida inmutable entre dos nodos del grafo."""
    source_id: str
    target_id: str
    relation: KnowledgeEdgeType
    attributes: Dict[str, Any] = field(default_factory=dict)


class KnowledgeGraphProtocol(Protocol):
    """Contrato formal para el motor de Grafo de Conocimiento."""
    def build_graph(self, force_refresh: bool = False) -> None: ...
    def get_node(self, node_id: str) -> Optional[KnowledgeNode]: ...
    def resolve_query_context(self, query: str, active_node_id: Optional[str] = None) -> Tuple[str, Optional[str]]: ...


class SystemKnowledgeGraph:
    """
    Motor del Grafo de Conocimiento de Infraestructura (Graph RAG).
    Modela determinísticamente todas las entidades de MariaDB (SSOT) y sus relaciones topológicas,
    eliminando ambigüedades, colisiones de memoria y respuestas evasivas.
    """

    _instance: Optional[SystemKnowledgeGraph] = None
    _lock: threading.Lock = threading.Lock()

    def __init__(self, cache_ttl_seconds: int = 30) -> None:
        self.graph: nx.DiGraph = nx.DiGraph()
        self.cache_ttl_seconds: int = cache_ttl_seconds
        self.last_built_at: float = 0.0
        self._build_lock: threading.Lock = threading.Lock()

    @classmethod
    def get_instance(cls, cache_ttl_seconds: int = 30) -> SystemKnowledgeGraph:
        """Patrón Singleton con Double-Checked Locking."""
        if cls._instance is None:
            with cls._lock:
                if cls._instance is None:
                    cls._instance = cls(cache_ttl_seconds=cache_ttl_seconds)
        return cls._instance

    def is_stale(self) -> bool:
        """Indica si el grafo en memoria requiere reconstrucción por TTL."""
        return (time.time() - self.last_built_at) > self.cache_ttl_seconds

    def ensure_fresh_graph(self) -> None:
        """Garantiza que el grafo esté cargado y vigente."""
        if self.graph.number_of_nodes() == 0 or self.is_stale():
            self.build_graph()

    def build_graph(self, force_refresh: bool = False) -> None:
        """
        Construye el grafo completo en memoria extrayendo el 100% de entidades y relaciones
        desde MariaDB (SSOT).
        """
        with self._build_lock:
            if not force_refresh and self.graph.number_of_nodes() > 0 and not self.is_stale():
                return

            new_graph: nx.DiGraph = nx.DiGraph()
            conn = get_db_connection()

            try:
                with conn.cursor() as cur:
                    # 1. SEDES (monitored_sites)
                    cur.execute("SELECT id, letter, name, ip, address, is_active FROM monitored_sites")
                    sites = cur.fetchall()
                    for s in sites:
                        node_id = f"site_{s['id']}"
                        node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.SITE,
                            label=s["name"],
                            entity_id=s["id"],
                            attributes={
                                "letter": s.get("letter"),
                                "ip": s.get("ip"),
                                "address": s.get("address"),
                                "is_active": bool(s.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=node)

                    # 2. DISPOSITIVOS DE RED (monitored_network_devices)
                    cur.execute(
                        """
                        SELECT id, monitored_site_id, device_number, name, ip, mac,
                               model, serial, access_type, access_port, is_active, down_since, notes
                        FROM monitored_network_devices
                        """
                    )
                    net_devices = cur.fetchall()
                    ip_to_device_node: Dict[str, str] = {}
                    for d in net_devices:
                        node_id = f"device_{d['id']}"
                        label = d["name"] or f"Equipo {d['device_number']}"
                        ip = d.get("ip") or ""
                        if ip:
                            ip_to_device_node[ip] = node_id

                        node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.NETWORK_DEVICE,
                            label=label,
                            entity_id=d["id"],
                            attributes={
                                "ip": ip,
                                "mac": d.get("mac") or "",
                                "model": d.get("model") or "",
                                "serial": d.get("serial") or "",
                                "access_type": d.get("access_type") or "",
                                "access_port": d.get("access_port"),
                                "is_active": bool(d.get("is_active", 1)),
                                "down_since": str(d.get("down_since")) if d.get("down_since") else None,
                                "notes": d.get("notes") or "",
                            }
                        )
                        new_graph.add_node(node_id, data=node)

                        # Arista: Dispositivo -> Sede
                        site_id = d.get("monitored_site_id")
                        if site_id:
                            site_node_id = f"site_{site_id}"
                            if new_graph.has_node(site_node_id):
                                new_graph.add_edge(node_id, site_node_id, relation=KnowledgeEdgeType.LOCATED_AT)

                    # 3. SISTEMAS UPS (ups_devices)
                    cur.execute(
                        """
                        SELECT id, name, model, serial_number, inventory_asset_id,
                               input_voltage, output_voltage, load_percent, frequency,
                               battery_voltage, battery_percent, temperature_c,
                               is_online, is_on_battery, is_battery_low, is_ups_failed,
                               outage_since, last_seen_at
                        FROM ups_devices
                        """
                    )
                    ups_devices = cur.fetchall()
                    for u in ups_devices:
                        node_id = f"ups_{u['id']}"
                        label = u["name"] or "UPS Principal Valle Seco"
                        node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.UPS,
                            label=label,
                            entity_id=u["id"],
                            attributes={
                                "model": u.get("model") or "ZTG LV6KL 6kVA",
                                "serial_number": u.get("serial_number") or "LV6KL-VS-01",
                                "inventory_asset_id": u.get("inventory_asset_id"),
                                "input_voltage": float(u["input_voltage"]) if u.get("input_voltage") is not None else 0.0,
                                "output_voltage": float(u["output_voltage"]) if u.get("output_voltage") is not None else 0.0,
                                "load_percent": float(u["load_percent"]) if u.get("load_percent") is not None else 0.0,
                                "battery_percent": float(u["battery_percent"]) if u.get("battery_percent") is not None else 100.0,
                                "battery_voltage": float(u["battery_voltage"]) if u.get("battery_voltage") is not None else 0.0,
                                "temperature_c": float(u["temperature_c"]) if u.get("temperature_c") is not None else 0.0,
                                "is_online": bool(u.get("is_online", 0)),
                                "is_on_battery": bool(u.get("is_on_battery", 0)),
                                "is_battery_low": bool(u.get("is_battery_low", 0)),
                                "is_ups_failed": bool(u.get("is_ups_failed", 0)),
                                "outage_since": str(u.get("outage_since")) if u.get("outage_since") else None,
                            }
                        )
                        new_graph.add_node(node_id, data=node)

                        # Enlazar UPS con Sede Principal por defecto (Sede 1)
                        if new_graph.has_node("site_1"):
                            new_graph.add_edge(node_id, "site_1", relation=KnowledgeEdgeType.LOCATED_AT)

                        # Enlazar con equipos que alimenta (todos los dispositivos de Valle Seco Sede 1)
                        for d in net_devices:
                            if d.get("monitored_site_id") == 1:
                                dev_node = f"device_{d['id']}"
                                if new_graph.has_node(dev_node):
                                    new_graph.add_edge(node_id, dev_node, relation=KnowledgeEdgeType.POWERS)

                    # 4. EVENTOS DE ENERGÍA DE UPS (ups_power_events)
                    cur.execute(
                        """
                        SELECT id, ups_device_id, event_type, started_at, ended_at,
                               duration_seconds, input_voltage, status, description
                        FROM ups_power_events
                        ORDER BY started_at DESC
                        """
                    )
                    power_events = cur.fetchall()
                    events_by_ups: Dict[int, List[Dict[str, Any]]] = {}
                    events_summary_by_ups: Dict[int, Dict[str, Any]] = {}

                    for pe in power_events:
                        u_id = pe["ups_device_id"]
                        if u_id not in events_by_ups:
                            events_by_ups[u_id] = []
                            events_summary_by_ups[u_id] = {
                                "total": 0,
                                "by_type": {},
                                "total_duration_seconds": 0,
                                "recent": [],
                            }

                        events_by_ups[u_id].append(pe)
                        summary = events_summary_by_ups[u_id]
                        summary["total"] += 1
                        etype = pe.get("event_type") or "desconocido"
                        summary["by_type"][etype] = summary["by_type"].get(etype, 0) + 1
                        dur = pe.get("duration_seconds") or 0
                        summary["total_duration_seconds"] += dur

                        if len(summary["recent"]) < 10:
                            summary["recent"].append({
                                "id": pe["id"],
                                "event_type": etype,
                                "started_at": str(pe.get("started_at")),
                                "ended_at": str(pe.get("ended_at")),
                                "duration_seconds": dur,
                                "input_voltage": float(pe["input_voltage"]) if pe.get("input_voltage") is not None else None,
                                "description": pe.get("description") or "",
                            })

                    # Registrar nodo resumen de eventos de energía y vincular al UPS
                    for u_id, summary in events_summary_by_ups.items():
                        pe_node_id = f"power_events_ups_{u_id}"
                        pe_node = KnowledgeNode(
                            id=pe_node_id,
                            node_type=KnowledgeNodeType.POWER_EVENT,
                            label=f"Histórico de Averías y Eventos Eléctricos (UPS {u_id})",
                            entity_id=u_id,
                            attributes=summary,
                        )
                        new_graph.add_node(pe_node_id, data=pe_node)

                        ups_node_id = f"ups_{u_id}"
                        if new_graph.has_node(ups_node_id):
                            new_graph.add_edge(ups_node_id, pe_node_id, relation=KnowledgeEdgeType.RECORDS_EVENT)

                    # 5. INVENTARIO ITAM (inventory_assets)
                    cur.execute(
                        """
                        SELECT id, asset_tag, name, type, status, brand, model,
                               serial_number, mac_address, ip_address, monitored_site_id,
                               monitored_network_device_id, custodian_name, custodian_department,
                               operating_system, notes, is_active
                        FROM inventory_assets
                        """
                    )
                    assets = cur.fetchall()
                    for a in assets:
                        node_id = f"asset_{a['id']}"
                        label = a["name"] or a["asset_tag"] or f"Activo {a['id']}"
                        serial = (a.get("serial_number") or "").strip()
                        has_serial = bool(serial and serial.upper() not in ("S/N", "SIN SERIAL", "N/A", "PENDIENTE", "NONE", "0"))

                        node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.INVENTORY_ASSET,
                            label=label,
                            entity_id=a["id"],
                            attributes={
                                "asset_tag": a.get("asset_tag") or "",
                                "type": a.get("type") or "desktop",
                                "status": a.get("status") or "active",
                                "brand": a.get("brand") or "",
                                "model": a.get("model") or "",
                                "serial_number": serial if has_serial else None,
                                "has_serial": has_serial,
                                "mac_address": a.get("mac_address") or "",
                                "ip_address": a.get("ip_address") or "",
                                "custodian_name": a.get("custodian_name") or "",
                                "custodian_department": a.get("custodian_department") or "",
                                "operating_system": a.get("operating_system") or "",
                                "is_active": bool(a.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=node)

                        # Arista: Activo -> Sede
                        site_id = a.get("monitored_site_id")
                        if site_id and new_graph.has_node(f"site_{site_id}"):
                            new_graph.add_edge(node_id, f"site_{site_id}", relation=KnowledgeEdgeType.LOCATED_AT)

                        # Arista: Dispositivo de Red <-> Activo ITAM
                        net_dev_id = a.get("monitored_network_device_id")
                        if net_dev_id and new_graph.has_node(f"device_{net_dev_id}"):
                            new_graph.add_edge(f"device_{net_dev_id}", node_id, relation=KnowledgeEdgeType.HAS_ASSET)

                        # Si el activo es el propio UPS
                        for u in ups_devices:
                            if u.get("inventory_asset_id") == a["id"]:
                                ups_node_id = f"ups_{u['id']}"
                                if new_graph.has_node(ups_node_id):
                                    new_graph.add_edge(ups_node_id, node_id, relation=KnowledgeEdgeType.HAS_ASSET)

                    # 6. ALERTAS Y NOTIFICACIONES NOC (alerts)
                    cur.execute(
                        """
                        SELECT id, entity_type, entity_id, entity_name, severity,
                               status, message, fired_at, resolved_at, duration_seconds
                        FROM alerts
                        ORDER BY fired_at DESC
                        LIMIT 500
                        """
                    )
                    alerts = cur.fetchall()
                    alerts_by_entity: Dict[str, List[Dict[str, Any]]] = {}
                    for al in alerts:
                        key = f"{al['entity_type']}_{al['entity_id']}"
                        if key not in alerts_by_entity:
                            alerts_by_entity[key] = []
                        alerts_by_entity[key].append(al)

                    # Registrar alertas activas y resumen por entidad
                    for key, al_list in alerts_by_entity.items():
                        etype, eid = key.split("_", 1)
                        alert_node_id = f"alert_group_{key}"
                        active_count = sum(1 for x in al_list if x.get("status") == "active")
                        al_node = KnowledgeNode(
                            id=alert_node_id,
                            node_type=KnowledgeNodeType.ALERT,
                            label=f"Alertas de {etype} {eid}",
                            entity_id=int(eid) if eid.isdigit() else 0,
                            attributes={
                                "total_alerts": len(al_list),
                                "active_alerts": active_count,
                                "latest_severity": al_list[0].get("severity") if al_list else "info",
                                "latest_message": al_list[0].get("message") if al_list else "",
                                "fired_at": str(al_list[0].get("fired_at")) if al_list else "",
                            }
                        )
                        new_graph.add_node(alert_node_id, data=al_node)

                        # Enlazar con el nodo de origen correspondiente
                        target_node = None
                        if etype == "network_device" or etype == "device":
                            target_node = f"device_{eid}"
                        elif etype == "service":
                            target_node = f"service_{eid}"
                        elif etype == "site":
                            target_node = f"site_{eid}"
                        elif etype == "ssl_certificate":
                            target_node = f"ssl_{eid}"

                        if target_node and new_graph.has_node(target_node):
                            new_graph.add_edge(target_node, alert_node_id, relation=KnowledgeEdgeType.GENERATED_ALERT)

                    # 7. SERVICIOS Y PROXIES (monitored_services, monitored_proxies)
                    cur.execute("SELECT id, letter, name, type, host_ip, port, is_active FROM monitored_services")
                    services = cur.fetchall()
                    for s in services:
                        node_id = f"service_{s['id']}"
                        s_node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.SERVICE,
                            label=s["name"],
                            entity_id=s["id"],
                            attributes={
                                "letter": s.get("letter"),
                                "type": s.get("type"),
                                "host_ip": s.get("host_ip"),
                                "port": s.get("port"),
                                "is_active": bool(s.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=s_node)

                    cur.execute("SELECT id, letter, name, ip_port, is_active FROM monitored_proxies")
                    proxies = cur.fetchall()
                    for p in proxies:
                        node_id = f"proxy_{p['id']}"
                        p_node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.PROXY,
                            label=p["name"],
                            entity_id=p["id"],
                            attributes={
                                "letter": p.get("letter"),
                                "ip_port": p.get("ip_port"),
                                "is_active": bool(p.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=p_node)

                    # 8. SUBREDES IPAM (discovery_subnets)
                    cur.execute("SELECT id, subnet, site_id, scan_method, is_active FROM discovery_subnets")
                    subnets = cur.fetchall()
                    for sub in subnets:
                        node_id = f"subnet_{sub['id']}"
                        sub_node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.SUBNET,
                            label=f"Subred {sub['subnet']}",
                            entity_id=sub["id"],
                            attributes={
                                "subnet": sub["subnet"],
                                "site_id": sub.get("site_id"),
                                "is_active": bool(sub.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=sub_node)
                        if sub.get("site_id") and new_graph.has_node(f"site_{sub['site_id']}"):
                            new_graph.add_edge(node_id, f"site_{sub['site_id']}", relation=KnowledgeEdgeType.LOCATED_AT)

                    # 9. CERTIFICADOS SSL (ssl_certificates)
                    cur.execute("SELECT id, service_id, domain, port, issuer_cn, days_remaining, is_active FROM ssl_certificates")
                    certs = cur.fetchall()
                    for c in certs:
                        node_id = f"ssl_{c['id']}"
                        c_node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.SSL_CERTIFICATE,
                            label=f"Certificado SSL {c['domain']}",
                            entity_id=c["id"],
                            attributes={
                                "domain": c["domain"],
                                "port": c.get("port"),
                                "issuer_cn": c.get("issuer_cn"),
                                "days_remaining": c.get("days_remaining"),
                                "is_active": bool(c.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=c_node)
                        if c.get("service_id") and new_graph.has_node(f"service_{c['service_id']}"):
                            new_graph.add_edge(f"service_{c['service_id']}", node_id, relation=KnowledgeEdgeType.SECURED_BY)

                    # 10. ENLACES DE TOPOLOGÍA (network_topology_links)
                    cur.execute("SELECT source_device_id, target_device_id, link_type, link_status FROM network_topology_links")
                    links = cur.fetchall()
                    for link in links:
                        s_id = f"device_{link['source_device_id']}"
                        t_id = f"device_{link['target_device_id']}"
                        if new_graph.has_node(s_id) and new_graph.has_node(t_id):
                            new_graph.add_edge(
                                s_id,
                                t_id,
                                relation=KnowledgeEdgeType.CONNECTED_TO,
                                link_type=link.get("link_type", "ethernet"),
                                link_status=link.get("link_status", "up"),
                            )

                    # 11. TICKETS DE MANTENIMIENTO TÉCNICO ITAM (maintenance_tickets)
                    cur.execute(
                        """
                        SELECT id, ticket_code, inventory_asset_id, type, status, priority,
                               title, description, scheduled_at, started_at, completed_at, cost
                        FROM maintenance_tickets
                        ORDER BY id DESC
                        LIMIT 200
                        """
                    )
                    tickets = cur.fetchall()
                    for t in tickets:
                        t_node_id = f"ticket_{t['id']}"
                        t_node = KnowledgeNode(
                            id=t_node_id,
                            node_type=KnowledgeNodeType.TICKET,
                            label=f"Ticket {t['ticket_code']} ({t['title']})",
                            entity_id=t["id"],
                            attributes={
                                "ticket_code": t.get("ticket_code"),
                                "inventory_asset_id": t.get("inventory_asset_id"),
                                "type": t.get("type"),
                                "status": t.get("status"),
                                "priority": t.get("priority"),
                                "title": t.get("title"),
                                "description": t.get("description"),
                                "cost": float(t.get("cost") or 0.0),
                            }
                        )
                        new_graph.add_node(t_node_id, data=t_node)
                        asset_id = t.get("inventory_asset_id")
                        if asset_id and new_graph.has_node(f"asset_{asset_id}"):
                            new_graph.add_edge(f"asset_{asset_id}", t_node_id, relation=KnowledgeEdgeType.HAS_TICKET)

            finally:
                conn.close()

            self.graph = new_graph
            self.last_built_at = time.time()
            logger.info(
                f"Grafo de Conocimiento del Sistema construido exitosamente: "
                f"{self.graph.number_of_nodes()} nodos, {self.graph.number_of_edges()} aristas relacionales."
            )

    def get_node(self, node_id: str) -> Optional[KnowledgeNode]:
        """Obtiene un nodo por su ID único."""
        self.ensure_fresh_graph()
        if self.graph.has_node(node_id):
            return self.graph.nodes[node_id].get("data")
        return None

    def find_nodes_by_type(self, node_type: KnowledgeNodeType) -> List[KnowledgeNode]:
        """Retorna todos los nodos de un tipo específico."""
        self.ensure_fresh_graph()
        result: List[KnowledgeNode] = []
        for _, attrs in self.graph.nodes(data=True):
            data: Optional[KnowledgeNode] = attrs.get("data")
            if data and data.node_type == node_type:
                result.append(data)
        return result

    def search_entity(self, query: str) -> Optional[KnowledgeNode]:
        """
        Búsqueda determinista de la entidad en el grafo.
        Distingue inequívocamente UPS, Switches, Routers, Servidores y PCs.
        """
        self.ensure_fresh_graph()
        q = query.lower().strip()

        # Prioridad 1: Consulta explícita sobre UPS / Respaldo Eléctrico
        ups_keywords = ("ups", "bateria", "batería", "respaldo electrico", "respaldo eléctrico", "voltaje", "corte de luz", "fluctuacion", "fluctuación", "falla electrica", "falla eléctrica")
        if any(re.search(rf"\b{re.escape(kw)}\b", q) for kw in ups_keywords):
            ups_nodes = self.find_nodes_by_type(KnowledgeNodeType.UPS)
            if ups_nodes:
                return ups_nodes[0]

        # Prioridad 2: Consulta por IP exacta
        ip_match = re.search(r"\b10\.20\.\d{1,3}\.\d{1,3}\b", q)
        if ip_match:
            target_ip = ip_match.group(0)
            for _, attrs in self.graph.nodes(data=True):
                data: Optional[KnowledgeNode] = attrs.get("data")
                if data and data.get("ip") == target_ip or data.get("ip_address") == target_ip:
                    return data

        # Prioridad 3: Consulta por Switch específico (ej. SW03, SW-03, SW 03)
        sw_match = re.search(r"\bsw(?:itch)?[-_\s]?0?(\d+)\b", q)
        if sw_match:
            num = sw_match.group(1).lstrip("0") or "0"
            for n in self.find_nodes_by_type(KnowledgeNodeType.NETWORK_DEVICE):
                lbl = n.label.lower()
                if f"sw{num}" in lbl or f"sw0{num}" in lbl or f"switch {num}" in lbl or f"switch 0{num}" in lbl:
                    return n

        # Prioridad 4: Consulta por Nombre Etiqueta o Hostname
        for node_type in (KnowledgeNodeType.NETWORK_DEVICE, KnowledgeNodeType.INVENTORY_ASSET, KnowledgeNodeType.SITE):
            for n in self.find_nodes_by_type(node_type):
                lbl = n.label.lower()
                if lbl and (lbl in q or q in lbl):
                    return n

        return None

    def resolve_query_context(
        self,
        query: str,
        active_node_id: Optional[str] = None
    ) -> Tuple[str, Optional[str]]:
        """
        Navega el Grafo de Conocimiento y genera un bloque de contexto determinista libre de ambigüedades.
        Retorna (bloque_contexto_rag, nuevo_active_node_id).
        """
        self.ensure_fresh_graph()
        q = query.lower().strip()

        # 1. Identificar si la consulta introduce una nueva entidad objetivo
        target_node = self.search_entity(query)

        # Si no hubo coincidencia explícita, verificar si se mantiene el nodo activo de la memoria
        if not target_node and active_node_id:
            target_node = self.get_node(active_node_id)

        # ---------------------------------------------------------------------
        # CASO A: CONSULTAS DE ENERGÍA Y AVERÍAS DEL UPS
        # ---------------------------------------------------------------------
        ups_keywords = ("ups", "bateria", "batería", "averia", "avería", "fluctuacion", "fluctuación", "corte", "falla electrica", "falla eléctrica", "histórico de fallas", "historico de fallas")
        is_ups_query = any(k in q for k in ups_keywords) and (
            not target_node or target_node.node_type == KnowledgeNodeType.UPS or "ups" in q
        )

        if is_ups_query:
            ups_nodes = self.find_nodes_by_type(KnowledgeNodeType.UPS)
            if ups_nodes:
                ups = ups_nodes[0]
                # Buscar el nodo de eventos de energía enlazado
                events_node: Optional[KnowledgeNode] = None
                for succ in self.graph.successors(ups.id):
                    n = self.graph.nodes[succ].get("data")
                    if n and n.node_type == KnowledgeNodeType.POWER_EVENT:
                        events_node = n
                        break

                total_events = events_node.get("total", 0) if events_node else 0
                by_type = events_node.get("by_type", {}) if events_node else {}
                recent = events_node.get("recent", []) if events_node else []

                lines = [
                    "[CONTEXTO_DEL_SISTEMA - DOMINIO DE ENERGÍA Y RESPALDO ELÉCTRICO (KNOWLEDGE GRAPH)]",
                    f"⚡ IDENTIDAD DEL EQUIPO: {ups.label}",
                    f"  • Tipo de Entidad: Sistema de Alimentación Ininterrumpida (UPS)",
                    f"  • Modelo Oficial: {ups.get('model', 'ZTG LV6KL 6kVA')}",
                    f"  • Serial de Chasis / Fábrica: {ups.get('serial_number', 'LV6KL-VS-01')}",
                    f"  • Ubicación Física: Sede Principal Valle Seco (Cuarto de Telecomunicaciones)",
                    f"  • Estado Operativo: {'EN LÍNEA (Normal)' if ups.get('is_online') else 'Bypass / Modo Red Comercial'}",
                    f"  • Nivel de Batería: {ups.get('battery_percent', 100.0)}%",
                    f"  • Voltaje de Entrada: {ups.get('input_voltage', 0.0)} VAC | Voltaje de Salida: {ups.get('output_voltage', 0.0)} VAC",
                    f"  • Carga Suministrada: {ups.get('load_percent', 0.0)}%",
                    "",
                    f"📊 HISTORIAL DE AVERÍAS Y EVENTOS ELÉCTRICOS DETECTADOS POR EL UPS:",
                    f"  • Total de Eventos Registrados en Histórico: {total_events} incidencias registradas en MariaDB (SSOT).",
                ]

                if by_type:
                    breakdown_str = ", ".join(f"{cnt} de tipo '{t}'" for t, cnt in by_type.items())
                    lines.append(f"  • Desglose por Tipo de Avería: {breakdown_str}.")

                if recent:
                    lines.append("  • Muestra de Últimos Eventos Registrados en el Histórico:")
                    for idx, ev in enumerate(recent[:5], 1):
                        lines.append(
                            f"    {idx}. Tipo: {ev.get('event_type', 'evento')} | Inicio: {ev.get('started_at')} | "
                            f"Duración: {ev.get('duration_seconds', 0)}s | Voltaje In: {ev.get('input_voltage')}V | "
                            f"Detalle: {ev.get('description', 'Evento registrado')}"
                        )
                else:
                    lines.append("  • Actualmente no se registran eventos de corte o fluctuación pendientes.")

                return "\n".join(lines), ups.id

        # ---------------------------------------------------------------------
        # CASO B: CONSULTAS CUANTITATIVAS DE INVENTARIO Y SERIALES
        # ---------------------------------------------------------------------
        inventory_keywords = ("serial", "seriales", "inventario", "censo", "cuantos equipos", "cuántos equipos")
        if any(k in q for k in inventory_keywords) and (not target_node or target_node.node_type == KnowledgeNodeType.INVENTORY_ASSET):
            all_assets = self.find_nodes_by_type(KnowledgeNodeType.INVENTORY_ASSET)
            total_assets = len(all_assets)
            with_serial = [a for a in all_assets if a.get("has_serial")]
            with_serial_count = len(with_serial)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE INVENTARIO Y ACTIVOS ITAM (KNOWLEDGE GRAPH)]",
                f"📊 RESUMEN CUANTITATIVO DE EQUIPOS Y SERIALES EN MARIADB (SSOT):",
                f"  • Total de Equipos / Activos Físicos Registrados: {total_assets}",
                f"  • Equipos con Serial de Chasis / CPU Asociado: {with_serial_count} de {total_assets}",
            ]

            if with_serial_count == 0:
                lines.extend([
                    "  • Estado fáctico: Actualmente NO existe ningún equipo con serial de chasis o CPU asociado/registrado en el inventario del sistema (0 de 24 equipos).",
                    "  • Observación técnica: Todos los equipos registrados en el inventario figuran actualmente sin serial cargado o pendientes por catalogación física.",
                ])
            else:
                lines.append("  • Equipos con Serial Registrado en el Sistema:")
                for a in with_serial[:10]:
                    lines.append(f"    - {a.label} ({a.get('type', 'equipo')} {a.get('brand', '')} {a.get('model', '')}): Serial {a.get('serial_number')}")

            return "\n".join(lines), target_node.id if target_node else None

        # ---------------------------------------------------------------------
        # CASO C: TICKETS DE MANTENIMIENTO TÉCNICO ITAM
        # ---------------------------------------------------------------------
        ticket_keywords = (
            "ticket", "tickets", "mantenimiento", "mantenimientos", "reparacion", "reparación",
            "reparaciones", "orden de trabajo", "ordenes de trabajo", "órdenes de trabajo",
            "en reparacion", "en reparación", "en soporte", "en mantenimiento", "preventivo", "correctivo"
        )
        if any(k in q for k in ticket_keywords):
            all_tickets = self.find_nodes_by_type(KnowledgeNodeType.TICKET)
            total_tickets = len(all_tickets)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE TICKETS Y MANTENIMIENTO TÉCNICO ITAM (KNOWLEDGE GRAPH)]",
                "📊 ESTADO GENERAL DE TICKETS DE MANTENIMIENTO EN MARIADB (SSOT):",
                f"  • Total de Tickets Registrados: {total_tickets}",
            ]

            if total_tickets == 0:
                lines.extend([
                    "  • Estado fáctico: Actualmente NO existen tickets de soporte o mantenimiento registrados en el sistema.",
                    "  • Observación técnica: Todos los equipos de la infraestructura se encuentran operativos y al día sin órdenes de trabajo pendientes ni mantenimientos preventivos/correctivos en curso.",
                ])
            else:
                by_status: Dict[str, int] = {}
                by_priority: Dict[str, int] = {}
                for tk in all_tickets:
                    st = str(tk.get("status") or "scheduled")
                    pr = str(tk.get("priority") or "medium")
                    by_status[st] = by_status.get(st, 0) + 1
                    by_priority[pr] = by_priority.get(pr, 0) + 1

                status_str = ", ".join(f"{cnt} en '{st}'" for st, cnt in by_status.items())
                lines.append(f"  • Desglose por Estado: {status_str}.")
                priority_str = ", ".join(f"{cnt} prioridad '{pr}'" for pr, cnt in by_priority.items())
                lines.append(f"  • Desglose por Prioridad: {priority_str}.")

                lines.append("  • Muestra de Tickets Registrados en el Sistema:")
                for tk in all_tickets[:5]:
                    lines.append(
                        f"    - Código: {tk.get('ticket_code')} | Título: {tk.get('title')} | Tipo: {tk.get('type')} | "
                        f"Estado: {tk.get('status')} | Prioridad: {tk.get('priority')} | Costo: ${tk.get('cost', 0):.2f}"
                    )

            return "\n".join(lines), all_tickets[0].id if all_tickets else None

        # ---------------------------------------------------------------------
        # CASO D: ALERTAS E INCIDENTES DEL CENTRO DE OPERACIONES (NOC)
        # ---------------------------------------------------------------------
        alert_keywords = (
            "alerta", "alertas", "incidente", "incidentes", "falla noc", "fallas noc",
            "alertas activas", "alertas criticas", "alertas críticas", "averias del noc",
            "averías del noc", "eventos noc", "disparada", "disparadas"
        )
        if any(k in q for k in alert_keywords):
            active_alerts_list = []
            total_alerts = 0
            firing_count = 0
            ack_count = 0
            critical_count = 0
            warning_count = 0

            try:
                conn = get_db_connection()
                try:
                    with conn.cursor() as cur:
                        cur.execute("SELECT COUNT(*) as total FROM alerts")
                        row = cur.fetchone()
                        total_alerts = row["total"] if row else 0

                        cur.execute(
                            """
                            SELECT id, entity_type, entity_name, severity, status, message, fired_at
                            FROM alerts
                            WHERE status IN ('firing', 'active', 'acknowledged')
                            ORDER BY id DESC
                            LIMIT 10
                            """
                        )
                        active_alerts_list = cur.fetchall()

                        cur.execute("SELECT status, COUNT(*) as cnt FROM alerts GROUP BY status")
                        for r in cur.fetchall():
                            if r["status"] == "firing":
                                firing_count = r["cnt"]
                            elif r["status"] == "acknowledged":
                                ack_count = r["cnt"]

                        cur.execute("SELECT severity, COUNT(*) as cnt FROM alerts WHERE status IN ('firing', 'active', 'acknowledged') GROUP BY severity")
                        for r in cur.fetchall():
                            if r["severity"] == "critical":
                                critical_count = r["cnt"]
                            elif r["severity"] == "warning":
                                warning_count = r["cnt"]
                finally:
                    conn.close()
            except Exception as e:
                logger.warning(f"Error consultando alertas en vivo desde MariaDB: {e}")

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE ALERTAS E INCIDENTES NOC (KNOWLEDGE GRAPH)]",
                "🚨 SUPERVISIÓN DE ALERTAS E INCIDENTES DEL NOC EN MARIADB (SSOT):",
                f"  • Total de Alertas en Histórico: {total_alerts}",
                f"  • Alertas Activas en Curso: {len(active_alerts_list)} ({firing_count} disparadas en vivo, {ack_count} reconocidas)",
                f"  • Desglose por Severidad Activa: {critical_count} críticas, {warning_count} advertencias",
            ]

            if not active_alerts_list:
                lines.append("  • Estado fáctico: Actualmente NO existen alertas activas ni incidentes abiertos en el NOC. Toda la infraestructura monitoreada se encuentra estable y operativa.")
            else:
                lines.append("  • Listado de Alertas Activas en el Centro de Operaciones:")
                for idx, al in enumerate(active_alerts_list[:5], 1):
                    lines.append(
                        f"    {idx}. [{al.get('severity', 'info').upper()}] {al.get('entity_name')} ({al.get('entity_type')}): "
                        f"{al.get('message')} | Estado: {al.get('status')} | Disparada: {al.get('fired_at')}"
                    )

            return "\n".join(lines), None

        # ---------------------------------------------------------------------
        # CASO E: SUBREDES Y GESTIÓN DE DIRECCIONAMIENTO IP (IPAM)
        # ---------------------------------------------------------------------
        subnet_keywords = (
            "subred", "subredes", "ipam", "bloque ip", "bloques ip", "segmento",
            "segmentos", "segmentos de red", "rango ip", "rangos ip", "redes configuradas", "plan de ip"
        )
        if any(k in q for k in subnet_keywords):
            subnet_nodes = self.find_nodes_by_type(KnowledgeNodeType.SUBNET)
            total_subnets = len(subnet_nodes)

            # Extraer segmentos operativos reales desde sedes y dispositivos
            operational_segments = []
            try:
                conn = get_db_connection()
                try:
                    with conn.cursor() as cur:
                        cur.execute(
                            """
                            SELECT 
                                s.name as site_name, 
                                s.ip as site_gateway, 
                                CONCAT(SUBSTRING_INDEX(d.ip, '.', 3), '.0/24') as subnet_calc,
                                COUNT(d.id) as device_count
                            FROM monitored_sites s
                            JOIN monitored_network_devices d ON d.monitored_site_id = s.id
                            WHERE d.ip IS NOT NULL AND d.ip != ''
                            GROUP BY s.id, s.name, s.ip, subnet_calc
                            ORDER BY s.id
                            """
                        )
                        operational_segments = cur.fetchall()
                finally:
                    conn.close()
            except Exception as e:
                logger.warning(f"Error extrayendo segmentos operativos: {e}")

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE SUBREDES Y PLAN IP / IPAM (KNOWLEDGE GRAPH)]",
                "🌐 PLAN DE SUBREDES Y DIRECCIONAMIENTO IP EN MARIADB (SSOT):",
            ]

            if operational_segments:
                lines.append("📍 1. Segmentos de Red Operativos por Sede (Infraestructura Real):")
                for idx, seg in enumerate(operational_segments, 1):
                    lines.append(
                        f"    {idx}. Segmento: {seg['subnet_calc']} | Sede: {seg['site_name']} "
                        f"(Enlace: {seg['site_gateway']}) | Equipos Asociados: {seg['device_count']}"
                    )

            lines.append(f"\n🔍 2. Subredes Registradas en Motor de Descubrimiento (Discovery Scanner):")
            lines.append(f"  • Total de Bloques de Escaneo: {total_subnets}")
            if total_subnets == 0:
                lines.append("  • Actualmente no hay subredes de descubrimiento adicionales configuradas.")
            else:
                for idx, sn in enumerate(subnet_nodes, 1):
                    lines.append(
                        f"    - Subred: {sn.get('subnet')} | Método de Escaneo: {sn.get('scan_method', 'arp_sweep')} | "
                        f"Estado: {'Activa' if sn.get('is_active') else 'Inactiva'}"
                    )

            return "\n".join(lines), subnet_nodes[0].id if subnet_nodes else None

        # ---------------------------------------------------------------------
        # CASO F: CERTIFICADOS SSL / TLS Y VENCIMIENTOS
        # ---------------------------------------------------------------------
        ssl_keywords = (
            "ssl", "tls", "certificado", "certificados", "vencimiento ssl", "vencimiento de certificado",
            "caducidad", "por vencer", "por caducar", "https"
        )
        if any(k in q for k in ssl_keywords):
            ssl_nodes = self.find_nodes_by_type(KnowledgeNodeType.SSL_CERTIFICATE)
            total_certs = len(ssl_nodes)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE CERTIFICADOS SSL / TLS (KNOWLEDGE GRAPH)]",
                "🔒 SUPERVISIÓN DE CERTIFICADOS SSL / TLS EN MARIADB (SSOT):",
                f"  • Total de Certificados Monitoreados: {total_certs}",
            ]

            if total_certs == 0:
                lines.append("  • Actualmente no hay certificados SSL registrados en el sistema.")
            else:
                lines.append("  • Listado de Certificados y Días de Vigencia Restante:")
                for idx, cert in enumerate(ssl_nodes, 1):
                    days = cert.get("days_remaining", 0)
                    status_lbl = "Válido" if days > 30 else ("Por Vencer" if days > 0 else "Expirado / Error")
                    lines.append(
                        f"    {idx}. Dominio: {cert.get('domain')}:{cert.get('port', 443)} | Emisor: {cert.get('issuer_cn') or 'Desconocido'} | "
                        f"Días Restantes: {days} días | Estado: {status_lbl}"
                    )

            return "\n".join(lines), ssl_nodes[0].id if ssl_nodes else None

        # ---------------------------------------------------------------------
        # CASO H: SERVICIOS CORPORATIVOS, PROXIES Y TELEMETRÍA DE CAÍDAS (NOC)
        # ---------------------------------------------------------------------
        service_keywords = (
            "servicio", "servicios", "servicio corporativo", "servicios corporativos",
            "servicios caidos", "servicios caídos", "servicios activos", "servicios apagados",
            "cuantos servicios", "cuántos servicios", "que servicios", "qué servicios",
            "caido", "caído", "caidos", "caídos", "caida", "caída", "caidas", "caídas",
            "otrs", "intranet", "factura digital", "claves", "sao", "surge",
            "te equivocaste", "estas equivocada", "estás equivocada", "mentira",
            "reporte de servicios", "/servicios", "fallas activas", "servicios en linea", "servicios en línea"
        )
        if any(k in q for k in service_keywords):
            try:
                from monitor.monitor_engine import get_latest_snapshot
                payload, snapshot_dt = get_latest_snapshot()
            except Exception:
                payload, snapshot_dt = None, None

            services_list = []
            proxies_list = []
            if payload:
                services_list = payload.get("services", [])
                proxies_list = payload.get("proxies", [])
            else:
                service_nodes = self.find_nodes_by_type(KnowledgeNodeType.SERVICE)
                for sn in service_nodes:
                    services_list.append({
                        "id": sn.entity_id,
                        "letter": sn.get("letter", "?"),
                        "name": sn.label,
                        "is_up": sn.get("is_up", True)
                    })

            total_services = len(services_list)
            down_services = [s for s in services_list if not s.get("is_up")]
            up_services = [s for s in services_list if s.get("is_up")]
            sites_list = payload.get("sites", []) if payload else []
            down_sites = [st for st in sites_list if not st.get("is_up")]

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE SERVICIOS CORPORATIVOS Y ESTADO NOC (KNOWLEDGE GRAPH)]",
                f"🕒 Telemetría Oficial Consolidada (Snapshot MariaDB): {snapshot_dt or 'En tiempo real'}",
                f"📊 RESUMEN DE SERVICIOS: {total_services} total | {len(up_services)} Operativos / En Línea | {len(down_services)} CAÍDOS / FUERA DE LÍNEA.",
            ]

            if down_services:
                lines.append(f"\n❌ SERVICIOS CAÍDOS DETECTADOS ({len(down_services)} fuera de línea):")
                for s in down_services:
                    lines.append(f"  • [{s.get('letter', '?')}] {s.get('name')} | Estado: ❌ CAÍDO / APAGADO | Tipo: {s.get('type', 'Servicio')}")
            else:
                lines.append("\n✅ Estado: Todos los servicios corporativos se encuentran 100% operativos y en línea.")

            if down_sites:
                lines.append(f"\n🏢 SEDES O ENLACES CAÍDOS ({len(down_sites)} detectados):")
                for st in down_sites:
                    lines.append(f"  • {st.get('name')} (IP: {st.get('ip', '')}) | Estado: ❌ FUERA DE LÍNEA / INACCESIBLE")

            if up_services:
                lines.append(f"\n✅ SERVICIOS EN LÍNEA ({len(up_services)} operativos):")
                for s in up_services:
                    lines.append(f"  • [{s.get('letter', '?')}] {s.get('name')} | Estado: ✅ ACTIVO")

            if proxies_list:
                lines.append(f"\n🌐 PROXIES DE NAVEGACIÓN ({len(proxies_list)} monitoreados):")
                for p in proxies_list:
                    st = "✅ ACTIVO" if p.get("is_up") else "❌ CAÍDO"
                    lines.append(f"  • [{p.get('letter', '?')}] {p.get('name')} ({p.get('ip_port', '')}) | {st}")

            lines.append(
                f"\n📌 INSTRUCCIÓN OBLIGATORIA: Responde directamente indicando que hay exactamente {len(down_services)} "
                f"servicios caídos ({', '.join(s.get('name') for s in down_services)}) y {len(up_services)} servicios operativos de un total de {total_services}. "
                f"Bajo ninguna circunstancia niegues las caídas ni afirmes que no tienes acceso a la información."
            )

            return "\n".join(lines), "services_summary"

        # ---------------------------------------------------------------------
        # CASO G: CONSULTA SOBRE ENTIDAD ESPECÍFICA (DISPOSITIVO / SWITCH / SEDE)
        # ---------------------------------------------------------------------
        if target_node:
            lines = [
                f"[CONTEXTO_DEL_SISTEMA - FICHA TOPOLÓGICA DE {target_node.label.upper()} (KNOWLEDGE GRAPH)]",
                f"🏷️ Nombre Oficial: {target_node.label}",
                f"  • Tipo de Entidad: {target_node.node_type.value}",
            ]
            for k, v in target_node.attributes.items():
                if v is not None and v != "":
                    lines.append(f"  • {k.replace('_', ' ').capitalize()}: {v}")

            # Subgrafo relacional: Vecinos directos
            neighbors = list(self.graph.neighbors(target_node.id))
            predecessors = list(self.graph.predecessors(target_node.id))

            if neighbors or predecessors:
                lines.append("🔗 RELACIONES TOPOLÓGICAS EN EL GRAFO:")
                for n_id in neighbors:
                    edge_data = self.graph.get_edge_data(target_node.id, n_id) or {}
                    rel = edge_data.get("relation", "CONECTADO_A")
                    neighbor_node = self.graph.nodes[n_id].get("data")
                    if neighbor_node:
                        lines.append(f"  • Hacia [{neighbor_node.label}] ({neighbor_node.node_type.value}) mediante relación '{rel}'")

                for p_id in predecessors:
                    edge_data = self.graph.get_edge_data(p_id, target_node.id) or {}
                    rel = edge_data.get("relation", "CONECTADO_A")
                    pred_node = self.graph.nodes[p_id].get("data")
                    if pred_node:
                        lines.append(f"  • Desde [{pred_node.label}] ({pred_node.node_type.value}) mediante relación '{rel}'")

            return "\n".join(lines), target_node.id

        # Fallback general del sistema
        return "", None
