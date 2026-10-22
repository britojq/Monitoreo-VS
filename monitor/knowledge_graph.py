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
    ASSIGNMENT_ACT = "assignment_act"
    COMPONENT = "component"
    PERIPHERAL = "peripheral"
    NETWORK_INTERFACE = "network_interface"
    PERSON = "person"


class KnowledgeEdgeType(str, Enum):
    """Tipos de relaciones dirigidas entre entidades del grafo."""
    LOCATED_AT = "LOCATED_AT"                  # Equipo/Activo/UPS ubicado en Sede
    POWERS = "POWERS"                          # UPS suministra energía a Equipo
    RECORDS_EVENT = "RECORDS_EVENT"            # UPS registra Evento de Energía
    GENERATED_ALERT = "GENERATED_ALERT"        # Equipo/Servicio generó Alerta
    HAS_ASSET = "HAS_ASSET"                    # Dispositivo de Red vinculado a Ficha ITAM
    ASSIGNED_TO = "ASSIGNED_TO"                # Activo ITAM asignado a Responsable/Dpto
    CONNECTED_TO = "CONNECTED_TO"              # Enlace físico/lógico entre Dispositivos
    MONITORS = "MONITORS"                      # Servicio/Proxy monitorea IP/Host
    IN_SUBNET = "IN_SUBNET"                    # Dispositivo pertenece a Subred
    SECURED_BY = "SECURED_BY"                  # Servicio/Sede asegurado por SSL
    HAS_TICKET = "HAS_TICKET"                  # Activo posee Ticket de Mantenimiento
    HAS_ASSIGNMENT_ACT = "HAS_ASSIGNMENT_ACT"  # Activo posee Acta de Asignación UINF-4.05
    HAS_COMPONENT = "HAS_COMPONENT"            # Activo posee Componente de Hardware (CPU/RAM/Disco)
    HAS_PERIPHERAL = "HAS_PERIPHERAL"          # Activo posee Periférico Asociado (Monitor/Teclado/Mouse)
    HAS_INTERFACE = "HAS_INTERFACE"            # Activo posee Interfaz de Red (IP/MAC)
    HOLDS_ASSET = "HOLDS_ASSET"                # Persona / Responsable tiene a su cargo Activo
    ASSIGNED_TO_PERSON = "ASSIGNED_TO_PERSON"  # Acta formal asignada a Persona


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

                        notes_text = d.get("notes") or ""
                        port_mappings: List[str] = []
                        for line in notes_text.splitlines():
                            clean_l = line.strip()
                            if clean_l and (clean_l.lower().startswith("puerto") or "cascada" in clean_l.lower() or "enlace" in clean_l.lower()):
                                port_mappings.append(clean_l)

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
                                "notes": notes_text,
                                "port_mappings": port_mappings,
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
                                "model": u.get("model") or "Genérico",
                                "serial_number": u.get("serial_number") or "",
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

                        if new_graph.has_node("site_1"):
                            new_graph.add_edge(node_id, "site_1", relation=KnowledgeEdgeType.LOCATED_AT)

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
                               monitored_network_device_id, custodian_name, custodian_id_number,
                               custodian_department, operating_system, notes, is_active
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
                                "custodian_id_number": a.get("custodian_id_number") or "",
                                "custodian_department": a.get("custodian_department") or "",
                                "operating_system": a.get("operating_system") or "",
                                "notes": a.get("notes") or "",
                                "is_active": bool(a.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=node)

                        site_id = a.get("monitored_site_id")
                        if site_id and new_graph.has_node(f"site_{site_id}"):
                            new_graph.add_edge(node_id, f"site_{site_id}", relation=KnowledgeEdgeType.LOCATED_AT)

                        net_dev_id = a.get("monitored_network_device_id")
                        if net_dev_id and new_graph.has_node(f"device_{net_dev_id}"):
                            new_graph.add_edge(f"device_{net_dev_id}", node_id, relation=KnowledgeEdgeType.HAS_ASSET)

                        for u in ups_devices:
                            if u.get("inventory_asset_id") == a["id"]:
                                ups_node_id = f"ups_{u['id']}"
                                if new_graph.has_node(ups_node_id):
                                    new_graph.add_edge(ups_node_id, node_id, relation=KnowledgeEdgeType.HAS_ASSET)

                    # 6. COMPONENTES INTERNOS DE HARDWARE (inventory_components)
                    cur.execute(
                        """
                        SELECT id, inventory_asset_id, type, name, brand, model,
                               capacity, serial_number, slot_or_bus, status
                        FROM inventory_components
                        """
                    )
                    components = cur.fetchall()
                    for comp in components:
                        c_node_id = f"component_{comp['id']}"
                        comp_name = comp.get("name") or comp.get("type") or "Componente"
                        node = KnowledgeNode(
                            id=c_node_id,
                            node_type=KnowledgeNodeType.COMPONENT,
                            label=f"{comp_name} ({comp.get('capacity') or comp.get('type') or ''})".strip(),
                            entity_id=comp["id"],
                            attributes={
                                "type": comp.get("type"),
                                "name": comp.get("name"),
                                "brand": comp.get("brand"),
                                "model": comp.get("model"),
                                "capacity": comp.get("capacity"),
                                "serial_number": comp.get("serial_number"),
                                "slot_or_bus": comp.get("slot_or_bus"),
                                "status": comp.get("status"),
                                "inventory_asset_id": comp.get("inventory_asset_id"),
                            }
                        )
                        new_graph.add_node(c_node_id, data=node)
                        a_id = comp.get("inventory_asset_id")
                        if a_id and new_graph.has_node(f"asset_{a_id}"):
                            new_graph.add_edge(f"asset_{a_id}", c_node_id, relation=KnowledgeEdgeType.HAS_COMPONENT)

                    # 7. PERIFÉRICOS ASOCIADOS (inventory_peripherals)
                    cur.execute(
                        """
                        SELECT id, asset_tag, type, brand, model, serial_number,
                               status, monitored_site_id, current_asset_id, notes
                        FROM inventory_peripherals
                        """
                    )
                    peripherals = cur.fetchall()
                    for per in peripherals:
                        p_node_id = f"peripheral_{per['id']}"
                        brand_model = f"{per.get('brand') or ''} {per.get('model') or ''}".strip()
                        ptype = per.get("type") or "Periférico"
                        label = f"{ptype.capitalize()}: {brand_model}" if brand_model else ptype.capitalize()
                        node = KnowledgeNode(
                            id=p_node_id,
                            node_type=KnowledgeNodeType.PERIPHERAL,
                            label=label,
                            entity_id=per["id"],
                            attributes={
                                "type": per.get("type"),
                                "brand": per.get("brand"),
                                "model": per.get("model"),
                                "serial_number": per.get("serial_number"),
                                "asset_tag": per.get("asset_tag"),
                                "status": per.get("status"),
                                "current_asset_id": per.get("current_asset_id"),
                                "monitored_site_id": per.get("monitored_site_id"),
                                "notes": per.get("notes") or "",
                            }
                        )
                        new_graph.add_node(p_node_id, data=node)
                        curr_a = per.get("current_asset_id")
                        if curr_a and new_graph.has_node(f"asset_{curr_a}"):
                            new_graph.add_edge(f"asset_{curr_a}", p_node_id, relation=KnowledgeEdgeType.HAS_PERIPHERAL)

                    # 8. INTERFACES DE RED DE ACTIVOS (inventory_asset_network_interfaces)
                    cur.execute(
                        """
                        SELECT id, inventory_asset_id, interface_name, mac_address,
                               ip_address, speed, is_primary
                        FROM inventory_asset_network_interfaces
                        """
                    )
                    ifaces = cur.fetchall()
                    for iface in ifaces:
                        i_node_id = f"iface_{iface['id']}"
                        lbl = f"{iface.get('interface_name')} ({iface.get('ip_address') or iface.get('mac_address') or 'iface'})"
                        node = KnowledgeNode(
                            id=i_node_id,
                            node_type=KnowledgeNodeType.NETWORK_INTERFACE,
                            label=lbl,
                            entity_id=iface["id"],
                            attributes={
                                "interface_name": iface.get("interface_name"),
                                "mac_address": iface.get("mac_address"),
                                "ip_address": iface.get("ip_address"),
                                "speed": iface.get("speed"),
                                "is_primary": bool(iface.get("is_primary")),
                                "inventory_asset_id": iface.get("inventory_asset_id"),
                            }
                        )
                        new_graph.add_node(i_node_id, data=node)
                        a_id = iface.get("inventory_asset_id")
                        if a_id and new_graph.has_node(f"asset_{a_id}"):
                            new_graph.add_edge(f"asset_{a_id}", i_node_id, relation=KnowledgeEdgeType.HAS_INTERFACE)

                    # 9. ACTAS DE ASIGNACIÓN ITAM (inventory_assignments)
                    cur.execute(
                        """
                        SELECT id, assignment_code, inventory_asset_id, custodian_name,
                               custodian_id_number, custodian_department, custodian_role,
                               monitored_site_id, document_pdf_path, status, assigned_at
                        FROM inventory_assignments
                        ORDER BY id ASC
                        """
                    )
                    assignments = cur.fetchall()
                    for asg in assignments:
                        asg_node_id = f"assignment_{asg['id']}"
                        has_pdf = bool(asg.get("document_pdf_path"))
                        custodian = asg.get("custodian_name") or "Sin Asignar"
                        asg_node = KnowledgeNode(
                            id=asg_node_id,
                            node_type=KnowledgeNodeType.ASSIGNMENT_ACT,
                            label=f"Acta {asg['assignment_code']} ({custodian})",
                            entity_id=asg["id"],
                            attributes={
                                "assignment_code": asg.get("assignment_code"),
                                "inventory_asset_id": asg.get("inventory_asset_id"),
                                "custodian_name": custodian,
                                "custodian_id_number": asg.get("custodian_id_number") or "",
                                "custodian_department": asg.get("custodian_department") or "",
                                "custodian_role": asg.get("custodian_role") or "",
                                "monitored_site_id": asg.get("monitored_site_id"),
                                "document_pdf_path": asg.get("document_pdf_path") or "",
                                "has_scanned_pdf": has_pdf,
                                "status": asg.get("status") or "active",
                                "assigned_at": str(asg.get("assigned_at") or ""),
                            }
                        )
                        new_graph.add_node(asg_node_id, data=asg_node)
                        asset_id = asg.get("inventory_asset_id")
                        if asset_id and new_graph.has_node(f"asset_{asset_id}"):
                            new_graph.add_edge(f"asset_{asset_id}", asg_node_id, relation=KnowledgeEdgeType.HAS_ASSIGNMENT_ACT)

                    # 10. PERSONAS / RESPONSABLES INSTITUCIONALES (Unificados)
                    # Deduplicar personas de assignments y assets
                    persons_map: Dict[str, Dict[str, Any]] = {}
                    for asg in assignments:
                        c_name = (asg.get("custodian_name") or "").strip()
                        if c_name and c_name.lower() not in ("sin asignar", "n/a", "none"):
                            clean_key = re.sub(r"[^a-zA-Z0-9]+", "_", c_name.lower()).strip("_")
                            if clean_key not in persons_map:
                                persons_map[clean_key] = {
                                    "name": c_name,
                                    "id_number": asg.get("custodian_id_number") or "",
                                    "department": asg.get("custodian_department") or "",
                                    "role": asg.get("custodian_role") or "",
                                    "assignment_ids": [],
                                    "asset_ids": [],
                                }
                            persons_map[clean_key]["assignment_ids"].append(asg["id"])
                            if asg.get("inventory_asset_id"):
                                persons_map[clean_key]["asset_ids"].append(asg["inventory_asset_id"])

                    for a in assets:
                        c_name = (a.get("custodian_name") or "").strip()
                        if c_name and c_name.lower() not in ("sin asignar", "n/a", "none"):
                            clean_key = re.sub(r"[^a-zA-Z0-9]+", "_", c_name.lower()).strip("_")
                            if clean_key not in persons_map:
                                persons_map[clean_key] = {
                                    "name": c_name,
                                    "id_number": a.get("custodian_id_number") or "",
                                    "department": a.get("custodian_department") or "",
                                    "role": "",
                                    "assignment_ids": [],
                                    "asset_ids": [],
                                }
                            if a["id"] not in persons_map[clean_key]["asset_ids"]:
                                persons_map[clean_key]["asset_ids"].append(a["id"])

                    for clean_key, pdata in persons_map.items():
                        p_node_id = f"person_{clean_key}"
                        p_node = KnowledgeNode(
                            id=p_node_id,
                            node_type=KnowledgeNodeType.PERSON,
                            label=pdata["name"],
                            entity_id=0,
                            attributes=pdata,
                        )
                        new_graph.add_node(p_node_id, data=p_node)

                        for asg_id in pdata["assignment_ids"]:
                            asg_node_id = f"assignment_{asg_id}"
                            if new_graph.has_node(asg_node_id):
                                new_graph.add_edge(asg_node_id, p_node_id, relation=KnowledgeEdgeType.ASSIGNED_TO_PERSON)

                        for a_id in pdata["asset_ids"]:
                            asset_node_id = f"asset_{a_id}"
                            if new_graph.has_node(asset_node_id):
                                new_graph.add_edge(p_node_id, asset_node_id, relation=KnowledgeEdgeType.HOLDS_ASSET)
                                new_graph.add_edge(asset_node_id, p_node_id, relation=KnowledgeEdgeType.ASSIGNED_TO)

                    # 11. ENLACES DE TOPOLOGÍA (network_topology_links)
                    cur.execute(
                        """
                        SELECT id, source_device_id, target_device_id, link_type, link_status
                        FROM network_topology_links
                        """
                    )
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

                    # 12. TICKETS DE MANTENIMIENTO TÉCNICO ITAM (maintenance_tickets)
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

                    # 13. ALERTAS Y NOTIFICACIONES NOC (alerts)
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

                        target_node = None
                        if etype in ("network_device", "device"):
                            target_node = f"device_{eid}"
                        elif etype == "service":
                            target_node = f"service_{eid}"
                        elif etype == "site":
                            target_node = f"site_{eid}"
                        elif etype == "ssl_certificate":
                            target_node = f"ssl_{eid}"

                        if target_node and new_graph.has_node(target_node):
                            new_graph.add_edge(target_node, alert_node_id, relation=KnowledgeEdgeType.GENERATED_ALERT)

                    # 14. SERVICIOS Y PROXIES (monitored_services, monitored_proxies)
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

                    # 15. SUBREDES IPAM (discovery_subnets)
                    cur.execute(
                        """
                        SELECT ds.id, ds.subnet, ds.site_id, ds.scan_method, ds.is_active, ms.name AS site_name
                        FROM discovery_subnets ds
                        LEFT JOIN monitored_sites ms ON ds.site_id = ms.id
                        ORDER BY ds.id ASC
                        """
                    )
                    subnets = cur.fetchall()
                    for sub in subnets:
                        node_id = f"subnet_{sub['id']}"
                        s_name = sub.get("site_name") or "Sin Sede Asignada"
                        sub_node = KnowledgeNode(
                            id=node_id,
                            node_type=KnowledgeNodeType.SUBNET,
                            label=f"Subred {sub['subnet']} ({s_name})",
                            entity_id=sub["id"],
                            attributes={
                                "subnet": sub["subnet"],
                                "site_id": sub.get("site_id"),
                                "site_name": s_name,
                                "scan_method": sub.get("scan_method", "arp_sweep"),
                                "is_active": bool(sub.get("is_active", 1)),
                            }
                        )
                        new_graph.add_node(node_id, data=sub_node)
                        if sub.get("site_id") and new_graph.has_node(f"site_{sub['site_id']}"):
                            new_graph.add_edge(node_id, f"site_{sub['site_id']}", relation=KnowledgeEdgeType.LOCATED_AT)

                    # 16. CERTIFICADOS SSL (ssl_certificates)
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
        Distingue inequívocamente Personas, UPS, Switches, Routers, Servidores y PCs.
        """
        self.ensure_fresh_graph()
        q = query.lower().strip()

        # Prioridad 1: Consulta explícita sobre UPS / Respaldo Eléctrico
        ups_keywords = (
            "ups", "bateria", "batería", "respaldo electrico", "respaldo eléctrico",
            "voltaje", "corte de luz", "fluctuacion", "fluctuación", "falla electrica", "falla eléctrica"
        )
        if any(re.search(rf"\b{re.escape(kw)}\b", q) for kw in ups_keywords):
            ups_nodes = self.find_nodes_by_type(KnowledgeNodeType.UPS)
            if ups_nodes:
                return ups_nodes[0]

        # Prioridad 2: Consulta por Persona / Responsable (nombre, apellido o cédula)
        ci_match = re.search(r"\b(\d{7,8})\b", q)
        clean_ci = ci_match.group(1) if ci_match else None

        for p in self.find_nodes_by_type(KnowledgeNodeType.PERSON):
            lbl_parts = p.label.lower().split()
            # Si coincide cédula
            if clean_ci and clean_ci in re.sub(r"\D", "", p.get("id_number", "")):
                return p
            # Si contiene nombres o apellidos distintivos (longitud >= 4)
            for part in lbl_parts:
                if len(part) >= 4 and re.search(rf"\b{re.escape(part)}\b", q):
                    return p

        # Prioridad 3: Consulta por IP exacta
        ip_match = re.search(r"\b(?:\d{1,3}\.){3}\d{1,3}\b", q)
        if ip_match:
            target_ip = ip_match.group(0)
            for _, attrs in self.graph.nodes(data=True):
                data: Optional[KnowledgeNode] = attrs.get("data")
                if data and (data.get("ip") == target_ip or data.get("ip_address") == target_ip):
                    return data

        # Prioridad 4: Consulta por Switch específico (ej. SW01, SW-03, SW 03)
        sw_match = re.search(r"\bsw(?:itch)?[-_\s]?0?(\d+)\b", q)
        if sw_match:
            num = sw_match.group(1).lstrip("0") or "0"
            for n in self.find_nodes_by_type(KnowledgeNodeType.NETWORK_DEVICE):
                lbl = n.label.lower()
                if f"sw{num}" in lbl or f"sw0{num}" in lbl or f"switch {num}" in lbl or f"switch 0{num}" in lbl:
                    return n

        # Prioridad 5: Consulta por Serial específico
        serial_match = re.search(r"\b[A-Za-z0-9]{7,15}\b", query)
        if serial_match:
            s_cand = serial_match.group(0).upper()
            for node_type in (KnowledgeNodeType.INVENTORY_ASSET, KnowledgeNodeType.COMPONENT, KnowledgeNodeType.PERIPHERAL):
                for n in self.find_nodes_by_type(node_type):
                    sn = (n.get("serial_number") or n.get("serial") or "").upper()
                    if sn and s_cand == sn:
                        return n

        # Prioridad 6: Consulta por Nombre Etiqueta o Hostname (filtrando stop words genéricos)
        stop_words = {
            "equipo", "switch", "router", "servidor", "servidores", "estacion",
            "estación", "dispositivo", "dispositivos", "sede", "interfaz", "red", "monitoreo",
            "operaciones", "centro", "alertas", "incidente", "incidentes", "ticket", "tickets",
            "subred", "subredes", "servicio", "servicios", "sistema", "general", "resumen"
        }

        # 6a: Coincidencia exacta de frase o etiqueta limpia (ej. "router principal", "cencaratit", "equipo 01")
        for node_type in (KnowledgeNodeType.INVENTORY_ASSET, KnowledgeNodeType.NETWORK_DEVICE, KnowledgeNodeType.SITE):
            for n in self.find_nodes_by_type(node_type):
                lbl = n.label.lower()
                clean_lbl = re.sub(r"\s*\(.*?\)\s*", " ", lbl).strip()
                if clean_lbl and len(clean_lbl) >= 4:
                    if re.search(rf"\b{re.escape(clean_lbl)}\b", q):
                        return n

        # 6b: Coincidencia por palabra distintiva (ej. "cencaratit", "c3750", "9060") ignorando stop words
        for node_type in (KnowledgeNodeType.INVENTORY_ASSET, KnowledgeNodeType.NETWORK_DEVICE):
            for n in self.find_nodes_by_type(node_type):
                lbl = n.label.lower()
                clean_lbl = re.sub(r"\s*\(.*?\)\s*", " ", lbl).strip()
                for part in clean_lbl.split():
                    if len(part) >= 5 and part not in stop_words and re.search(rf"\b{re.escape(part)}\b", q):
                        return n

        return None

    def _get_asset_full_subgraph_details(self, asset_node: KnowledgeNode) -> Dict[str, Any]:
        """Extrae el perfil relacional 100% completo de un activo ITAM y sus satélites."""
        asset_id = asset_node.entity_id

        # 1. Componentes internos
        components: List[Dict[str, Any]] = []
        for succ in self.graph.successors(asset_node.id):
            n = self.graph.nodes[succ].get("data")
            if n and n.node_type == KnowledgeNodeType.COMPONENT:
                components.append(n.attributes)

        # 2. Periféricos asociados
        peripherals: List[Dict[str, Any]] = []
        for succ in self.graph.successors(asset_node.id):
            n = self.graph.nodes[succ].get("data")
            if n and n.node_type == KnowledgeNodeType.PERIPHERAL:
                peripherals.append(n.attributes)

        # 3. Interfaces de red
        interfaces: List[Dict[str, Any]] = []
        for succ in self.graph.successors(asset_node.id):
            n = self.graph.nodes[succ].get("data")
            if n and n.node_type == KnowledgeNodeType.NETWORK_INTERFACE:
                interfaces.append(n.attributes)

        # 4. Actas de asignación
        assignments: List[Dict[str, Any]] = []
        for succ in self.graph.successors(asset_node.id):
            n = self.graph.nodes[succ].get("data")
            if n and n.node_type == KnowledgeNodeType.ASSIGNMENT_ACT:
                assignments.append(n.attributes)

        # 5. Responsable / Persona asociada
        person_info: Optional[Dict[str, Any]] = None
        for succ in self.graph.successors(asset_node.id):
            n = self.graph.nodes[succ].get("data")
            if n and n.node_type == KnowledgeNodeType.PERSON:
                person_info = n.attributes
                break

        # 6. Switch o Dispositivo de Red conectado
        connected_switch: Optional[str] = None
        for pred in self.graph.predecessors(asset_node.id):
            n = self.graph.nodes[pred].get("data")
            if n and n.node_type == KnowledgeNodeType.NETWORK_DEVICE:
                connected_switch = f"{n.label} (IP: {n.get('ip')})"
                break

        return {
            "asset": asset_node,
            "components": components,
            "peripherals": peripherals,
            "interfaces": interfaces,
            "assignments": assignments,
            "person": person_info,
            "connected_switch": connected_switch,
        }

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

        # =====================================================================
        # DOMINIO 1: ENERGÍA Y AVERÍAS DEL UPS
        # =====================================================================
        ups_keywords = (
            "ups", "bateria", "batería", "averia", "avería", "fluctuacion", "fluctuación",
            "corte", "falla electrica", "falla eléctrica", "histórico de fallas", "historico de fallas",
            "respaldo electrico", "respaldo eléctrico", "voltaje", "cuantas averias", "cuántas averías"
        )
        is_ups_query = any(k in q for k in ups_keywords) and (
            not target_node or target_node.node_type == KnowledgeNodeType.UPS or "ups" in q
        )

        if is_ups_query:
            ups_nodes = self.find_nodes_by_type(KnowledgeNodeType.UPS)
            if ups_nodes:
                ups = ups_nodes[0]
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
                    f"  • Modelo Oficial: {ups.get('model') or 'No especificado'}",
                    f"  • Serial de Chasis / Fábrica: {ups.get('serial_number') or 'No registrado'}",
                    f"  • Ubicación Física: {ups.get('location') or 'Sede Principal Valle Seco (Cuarto de Telecomunicaciones)'}",
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

        # =====================================================================
        # DOMINIO 2: PERSONA / RESPONSABLE INSTITUCIONAL (RELACIONAL 100%)
        # =====================================================================
        if target_node and target_node.node_type == KnowledgeNodeType.PERSON:
            person = target_node
            # Obtener activos asociados
            held_asset_nodes: List[KnowledgeNode] = []
            for succ in self.graph.successors(person.id):
                n = self.graph.nodes[succ].get("data")
                if n and n.node_type == KnowledgeNodeType.INVENTORY_ASSET:
                    held_asset_nodes.append(n)

            lines = [
                f"[CONTEXTO_DEL_SISTEMA - FICHA INTEGRAL DE FUNCIONARIO Y EQUIPOS (KNOWLEDGE GRAPH)]",
                f"👤 FUNCIONARIO / RESPONSABLE: {person.label}",
                f"  • Cédula de Identidad: {person.get('id_number') or 'No registrada'}",
                f"  • Departamento: {person.get('department') or 'No asignado'}",
                f"  • Cargo / Puesto: {person.get('role') or 'Personal Corporativo'}",
                f"  • Cantidad de Equipos Asignados: {len(held_asset_nodes)} activo(s) en MariaDB (SSOT).",
            ]

            for idx, a_node in enumerate(held_asset_nodes, 1):
                det = self._get_asset_full_subgraph_details(a_node)
                lines.append(f"\n💻 EQUIPO #{idx}: {a_node.label} ({a_node.get('brand')} {a_node.get('model')})")
                lines.append(f"  • Serial de Chasis / Máquina: {a_node.get('serial_number') or 'Pendiente por catalogación'}")
                lines.append(f"  • Dirección IP: {a_node.get('ip_address') or 'N/A'} | MAC: {a_node.get('mac_address') or 'N/A'}")
                lines.append(f"  • Sistema Operativo: {a_node.get('operating_system') or 'N/A'}")

                # Actas oficiales escaneadas
                if det["assignments"]:
                    for asg in det["assignments"]:
                        pdf_txt = f"PDF Escaneado Oficial: {asg.get('document_pdf_path')}" if asg.get('has_scanned_pdf') else "Sin acta física escaneada adjunta"
                        lines.append(f"  • Acta Asignación: Código {asg.get('assignment_code')} | Estado: {asg.get('status')} | {pdf_txt}")

                # Componentes de hardware
                if det["components"]:
                    lines.append("  • Componentes de Hardware Internos Registrados:")
                    for c in det["components"]:
                        lines.append(f"    - {c.get('type', 'Comp').upper()}: {c.get('name')} | Capacidad: {c.get('capacity') or 'N/A'} | Serial: {c.get('serial_number') or 'N/A'}")
                else:
                    lines.append("  • Componentes Internos (CPU/RAM/Discos): Actualmente este activo no posee componentes desglosados individualmente en MariaDB (pendiente por catalogación física).")

                # Periféricos asociados
                if det["peripherals"]:
                    lines.append("  • Periféricos Asociados al Puesto de Trabajo:")
                    for p in det["peripherals"]:
                        lines.append(f"    - {p.get('type', 'Periférico').capitalize()}: {p.get('brand')} {p.get('model')} | Serial: {p.get('serial_number') or 'N/A'}")
                else:
                    lines.append("  • Periféricos Asociados: Sin periféricos complementarios registrados en el sistema.")

                if det["connected_switch"]:
                    lines.append(f"  • Conexión de Red / Topología: Conectado a {det['connected_switch']}")

            return "\n".join(lines), person.id

        # =====================================================================
        # DOMINIO 3: ACTAS DE ASIGNACIÓN ITAM (CENSO Y OFICIALES ESCANEADAS)
        # =====================================================================
        acta_keywords = (
            "acta", "actas", "asignacion", "asignación", "asignaciones",
            "uinf", "uinf-4.05", "pase de movimiento", "pases de movimiento",
            "pase-mov", "documento escaneado", "documentos escaneados",
            "acta escaneada", "actas escaneadas", "quien tiene asignado", "quién tiene asignado",
            "equipos con acta", "equipos que tienen acta"
        )
        is_acta_query = any(k in q for k in acta_keywords)

        if is_acta_query:
            all_assets = self.find_nodes_by_type(KnowledgeNodeType.INVENTORY_ASSET)
            total_assets = len(all_assets)
            all_acts = self.find_nodes_by_type(KnowledgeNodeType.ASSIGNMENT_ACT)
            scanned_acts = [a for a in all_acts if a.get("has_scanned_pdf")]
            scanned_count = len(scanned_acts)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE ACTAS DE ASIGNACIÓN ITAM (KNOWLEDGE GRAPH)]",
                f"📊 CENSO Y AUDITORÍA DE ACTAS DE ASIGNACIÓN EN MARIADB (SSOT):",
                f"  • Total de Equipos / Activos Físicos en el Sistema: {total_assets} equipos.",
                f"  • Equipos con Acta Oficial Física Escaneada en PDF (Formato UINF-4.05): {scanned_count} equipos (todos pertenecientes a la Coordinación de Transmisión).",
                f"  • Registros Totales de Movimiento / Asignación en Base de Datos: {len(all_acts)} registros (los restantes corresponden a pases de movimiento o asignaciones preliminares sin acta física escaneada adjunta).",
                "",
                f"📋 DETALLE EXHAUSTIVO DE LOS {scanned_count} EQUIPOS CON ACTA FÍSICA ESCANEADA:",
            ]

            for idx, act in enumerate(scanned_acts, 1):
                asset_id = act.get("inventory_asset_id")
                asset_node = self.get_node(f"asset_{asset_id}") if asset_id else None
                asset_name = asset_node.label if asset_node else f"Activo ID {asset_id}"
                serial_num = asset_node.get("serial_number") if asset_node else "N/A"
                brand_model = f"{asset_node.get('brand', '')} {asset_node.get('model', '')}".strip() if asset_node else "Desktop"
                custodian = act.get("custodian_name") or "Funcionario"
                ci = act.get("custodian_id_number") or "N/A"
                dept = act.get("custodian_department") or "COORDINACIÓN DE TRANSMISIÓN"
                code = act.get("assignment_code") or "UINF-4.05"
                pdf_path = act.get("document_pdf_path") or ""

                lines.append(
                    f"  {idx}. {asset_name} ({brand_model}) | Serial: {serial_num}\n"
                    f"     • Responsable: {custodian} (C.I. {ci}) | Departamento: {dept}\n"
                    f"     • Código de Acta Oficial: {code}\n"
                    f"     • Archivo Escaneado: {pdf_path}"
                )

            lines.append(
                f"\n📌 INSTRUCCIÓN OBLIGATORIA: En el sistema SÓLO existen {scanned_count} equipos con acta de asignación física firmada y escaneada en PDF (EQUIPO 01, EQUIPO 02 y EQUIPO 03 de la Coordinación de Transmisión). "
                f"Queda TERMINANTEMENTE PROHIBIDO afirmar que hay 18 actas. El número 18 corresponde únicamente a los equipos que tienen serial de chasis/CPU registrado, NO a actas de asignación. "
                f"Bajo NINGUNA circunstancia generes código SQL (como 'SELECT * FROM equipos;') ni pidas al usuario consultar la base de datos. Responde con los datos anteriores en lenguaje natural claro y estructurado."
            )

            first_node_id = scanned_acts[0].id if scanned_acts else (all_acts[0].id if all_acts else None)
            return "\n".join(lines), first_node_id

        # =====================================================================
        # DOMINIO 4: PERIFÉRICOS GLOBALES Y CONSULTAS CUANTITATIVAS
        # =====================================================================
        peripheral_keywords = (
            "monitor", "monitores", "pantalla", "pantallas", "teclado", "teclados",
            "mouse", "raton", "ratón", "ratones", "impresora", "impresoras", "scanner", "escaner", "regulador"
        )
        if any(k in q for k in peripheral_keywords):
            all_peripherals = self.find_nodes_by_type(KnowledgeNodeType.PERIPHERAL)
            selected_type = None
            if any(k in q for k in ("monitor", "monitores", "pantalla", "pantallas")):
                selected_type = "monitor"
            elif any(k in q for k in ("teclado", "teclados")):
                selected_type = "keyboard"
            elif any(k in q for k in ("mouse", "raton", "ratón", "ratones")):
                selected_type = "mouse"
            elif any(k in q for k in ("impresora", "impresoras")):
                selected_type = "printer"

            brand_filter = None
            for b in ("hp", "hewlett", "lenovo", "cdp", "dell", "samsung", "lg", "ite"):
                if re.search(rf"\b{b}\b", q):
                    brand_filter = b
                    break

            matched_peripherals = []
            for p in all_peripherals:
                pt = (p.get("type") or "").lower()
                pb = f"{p.get('brand') or ''} {p.get('model') or ''}".lower()
                if selected_type and selected_type != pt:
                    continue
                if brand_filter:
                    if brand_filter in ("hp", "hewlett"):
                        if not ("hp" in pb or "hewlett" in pb):
                            continue
                    elif brand_filter not in pb:
                        continue
                matched_peripherals.append(p)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE PERIFÉRICOS DE HARDWARE ITAM (KNOWLEDGE GRAPH)]",
                f"🖥️ CENSO DE PERIFÉRICOS REGISTRADOS EN MARIADB (SSOT):",
                f"  • Total de Periféricos en Inventario: {len(all_peripherals)} unidades.",
                f"  • Periféricos Coincidentes con la Consulta: {len(matched_peripherals)} unidades.",
            ]

            if not matched_peripherals:
                lines.append(f"  • Actualmente no se registran periféricos de ese tipo o marca en la base de datos.")
            else:
                lines.append(f"📋 Listado Detallado:")
                for idx, p in enumerate(matched_peripherals, 1):
                    asset_id = p.get("current_asset_id")
                    asset_node = self.get_node(f"asset_{asset_id}") if asset_id else None
                    asset_str = f"Asignado a: {asset_node.label}" if asset_node else "Sin equipo asignado"
                    lines.append(
                        f"  {idx}. {p.label} | Marca: {p.get('brand')} | Modelo: {p.get('model')} | "
                        f"Serial: {p.get('serial_number') or 'S/N'} | {asset_str}"
                    )

            return "\n".join(lines), matched_peripherals[0].id if matched_peripherals else None

        # =====================================================================
        # DOMINIO 4B: COMPONENTES DE HARDWARE GLOBALES (RAM, CPU, DISCOS)
        # =====================================================================
        comp_keywords = (
            "ram", "memoria ram", "memorias", "procesador", "procesadores", "cpu", "cpus",
            "disco", "discos", "disco duro", "discos duros", "almacenamiento"
        )
        if any(k in q for k in comp_keywords) and not target_node:
            all_comps = self.find_nodes_by_type(KnowledgeNodeType.COMPONENT)
            target_type = None
            if any(k in q for k in ("ram", "memoria")):
                target_type = "ram"
            elif any(k in q for k in ("procesador", "cpu")):
                target_type = "cpu"
            elif any(k in q for k in ("disco", "almacenamiento")):
                target_type = "disk"

            matched_comps = []
            for c in all_comps:
                ct = (c.get("type") or "").lower()
                if target_type and target_type != ct:
                    continue
                matched_comps.append(c)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE COMPONENTES DE HARDWARE ITAM (KNOWLEDGE GRAPH)]",
                f"🧩 CENSO DE COMPONENTES DE HARDWARE REGISTRADOS EN MARIADB (SSOT):",
                f"  • Total de Componentes Internos Catalogados: {len(all_comps)} unidades.",
                f"  • Coincidentes con la Consulta ({target_type or 'General'}): {len(matched_comps)} componentes.",
            ]
            for idx, c in enumerate(matched_comps[:15], 1):
                asset_id = c.get("inventory_asset_id")
                asset_node = self.get_node(f"asset_{asset_id}") if asset_id else None
                asset_str = f"En equipo: {asset_node.label}" if asset_node else "Sin equipo asociado"
                lines.append(
                    f"  {idx}. {c.label} | Serial: {c.get('serial_number') or 'S/N'} | Capacidad: {c.get('capacity') or 'N/A'} | {asset_str}"
                )

            return "\n".join(lines), matched_comps[0].id if matched_comps else None

        # =====================================================================
        # DOMINIO 5: ACTIVO ITAM ESPECÍFICO (EQUIPO 01, CENCARATIT, ETC.)
        # =====================================================================
        if target_node and target_node.node_type == KnowledgeNodeType.INVENTORY_ASSET:
            det = self._get_asset_full_subgraph_details(target_node)
            a = target_node
            lines = [
                f"[CONTEXTO_DEL_SISTEMA - FICHA INTEGRAL DE ACTIVO ITAM: {a.label.upper()} (KNOWLEDGE GRAPH)]",
                f"🏷️ Nombre Oficial: {a.label}",
                f"  • Tipo de Activo: {a.get('type') or 'desktop'}",
                f"  • Marca / Modelo: {a.get('brand')} {a.get('model')}",
                f"  • Serial de Chasis: {a.get('serial_number') or 'Pendiente por catalogación física'}",
                f"  • Dirección IP: {a.get('ip_address') or 'N/A'} | MAC: {a.get('mac_address') or 'N/A'}",
                f"  • Sistema Operativo: {a.get('operating_system') or 'N/A'}",
                f"  • Notas Técnicas: {a.get('notes') or 'N/A'}",
            ]

            if det["person"]:
                lines.append(f"👤 RESPONSABLE: {det['person'].get('name')} (C.I. {det['person'].get('id_number') or 'N/A'}) | Depto: {det['person'].get('department')}")
            elif a.get("custodian_name"):
                lines.append(f"👤 RESPONSABLE: {a.get('custodian_name')} | Depto: {a.get('custodian_department')}")
            else:
                lines.append("👤 RESPONSABLE: Sin responsable formal asignado en MariaDB.")

            if det["assignments"]:
                for asg in det["assignments"]:
                    pdf_txt = f"PDF Escaneado Oficial: {asg.get('document_pdf_path')}" if asg.get('has_scanned_pdf') else "Sin acta física escaneada adjunta"
                    lines.append(f"📋 ACTA ASIGNACIÓN: Código {asg.get('assignment_code')} | Estado: {asg.get('status')} | {pdf_txt}")

            if det["components"]:
                lines.append("🧩 COMPONENTES INTERNOS DE HARDWARE REGISTRADOS:")
                for c in det["components"]:
                    lines.append(f"  • {c.get('type', 'Componente').upper()}: {c.get('name')} | Capacidad: {c.get('capacity') or 'N/A'} | Serial: {c.get('serial_number') or 'N/A'}")
            else:
                lines.append("🧩 COMPONENTES INTERNOS: Actualmente este activo no posee componentes (CPU/RAM/Disco) desglosados individualmente en MariaDB (pendiente por catalogación física).")

            if det["peripherals"]:
                lines.append("🖥️ PERIFÉRICOS ASOCIADOS:")
                for p in det["peripherals"]:
                    lines.append(f"  • {p.get('type', 'Periférico').capitalize()}: {p.get('brand')} {p.get('model')} | Serial: {p.get('serial_number') or 'N/A'}")
            else:
                lines.append("🖥️ PERIFÉRICOS ASOCIADOS: Sin periféricos registrados.")

            if det["connected_switch"]:
                lines.append(f"🔌 CONEXIÓN DE RED / TOPOLOGÍA: Conectado a {det['connected_switch']}")

            return "\n".join(lines), a.id

        # =====================================================================
        # DOMINIO 6: DISPOSITIVO DE RED / SWITCH (SW01, ROUTER PRINCIPAL, ETC.)
        # =====================================================================
        if target_node and target_node.node_type == KnowledgeNodeType.NETWORK_DEVICE:
            d = target_node
            lines = [
                f"[CONTEXTO_DEL_SISTEMA - FICHA TOPOLÓGICA DE {d.label.upper()} (KNOWLEDGE GRAPH)]",
                f"🏷️ Nombre Oficial: {d.label}",
                f"  • Tipo de Entidad: Dispositivo de Red (Switch / Router / Servidor)",
                f"  • Dirección IP: {d.get('ip') or 'N/A'}",
                f"  • Dirección MAC: {d.get('mac') or 'N/A'}",
                f"  • Modelo: {d.get('model') or 'N/A'}",
                f"  • Serial: {d.get('serial') or 'N/A'}",
                f"  • Estado: {'Operativo / En línea' if d.get('is_active') else 'Inactivo'}",
                f"  • Notas Técnicas & Mapeo de Puertos Físicos:\n{d.get('notes') or 'N/A'}",
            ]

            # Enlaces de topología conectados
            topo_edges = []
            for succ in self.graph.successors(d.id):
                succ_node = self.graph.nodes[succ].get("data")
                if succ_node and succ_node.node_type == KnowledgeNodeType.NETWORK_DEVICE:
                    edata = self.graph.get_edge_data(d.id, succ) or {}
                    topo_edges.append(f"Hacia {succ_node.label} ({edata.get('link_type', 'ethernet')})")
            for pred in self.graph.predecessors(d.id):
                pred_node = self.graph.nodes[pred].get("data")
                if pred_node and pred_node.node_type == KnowledgeNodeType.NETWORK_DEVICE:
                    edata = self.graph.get_edge_data(pred, d.id) or {}
                    topo_edges.append(f"Desde {pred_node.label} ({edata.get('link_type', 'ethernet')})")

            if topo_edges:
                lines.append("🔌 ENLACES DE TOPOLOGÍA REGISTRADOS:")
                for te in topo_edges:
                    lines.append(f"  • {te}")

            return "\n".join(lines), d.id

        # =====================================================================
        # DOMINIO 7: CONSULTAS CUANTITATIVAS DE SERIALES DE ACTIVOS
        # =====================================================================
        inventory_keywords = ("serial", "seriales", "inventario", "censo", "cuantos equipos", "cuántos equipos")
        is_general_serial_query = any(k in q for k in (
            "cuanto", "cuánto", "total", "listado", "lista", "censo", "todos", "resumen", "cuales", "cuáles"
        ))
        if any(k in q for k in inventory_keywords) and is_general_serial_query:
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
                    f"  • Estado fáctico: Actualmente NO existe ningún equipo con serial de chasis o CPU asociado/registrado en el inventario del sistema (0 de {total_assets} equipos).",
                    "  • Observación técnica: Todos los equipos registrados en el inventario figuran actualmente sin serial cargado o pendientes por catalogación física.",
                ])
            else:
                lines.append(f"  • Censo Exhaustivo de los {with_serial_count} Equipos con Serial Registrado en el Sistema:")
                for idx, a in enumerate(with_serial, 1):
                    brand_model = f"{a.get('brand', '')} {a.get('model', '')}".strip() or a.get('type', 'Equipo')
                    ip_str = f" | IP: {a.get('ip_address')}" if a.get('ip_address') else ""
                    resp_str = f" | Responsable: {a.get('custodian_name')}" if a.get('custodian_name') else ""
                    lines.append(f"    {idx}. {a.label} ({brand_model}): Serial: {a.get('serial_number')}{ip_str}{resp_str}")

                lines.append(
                    f"\n📌 INSTRUCCIÓN OBLIGATORIA: Si el usuario solicita el listado o censo de seriales, responde presentando de forma exhaustiva y numerada los {with_serial_count} equipos anteriores con su respectivo serial, sin omitir ninguno. "
                    f"BAJO NINGUNA CIRCUNSTANCIA generes código SQL (como 'SELECT * FROM equipos;') ni pidas al usuario consultar la base de datos. Responde directamente en texto claro con los datos anteriores."
                )

            return "\n".join(lines), None

        # =====================================================================
        # DOMINIO 8: TICKETS DE MANTENIMIENTO TÉCNICO ITAM
        # =====================================================================
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

        # =====================================================================
        # DOMINIO 9: ALERTAS E INCIDENTES DEL CENTRO DE OPERACIONES (NOC)
        # =====================================================================
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

        # =====================================================================
        # DOMINIO 10: SUBREDES Y GESTIÓN IPAM
        # =====================================================================
        subnet_keywords = (
            "subred", "subredes", "ipam", "bloque ip", "bloques ip", "segmento",
            "segmentos", "segmentos de red", "rango ip", "rangos ip", "redes configuradas", "plan de ip"
        )
        if any(k in q for k in subnet_keywords):
            subnet_nodes = self.find_nodes_by_type(KnowledgeNodeType.SUBNET)
            total_subnets = len(subnet_nodes)

            lines = [
                "[CONTEXTO_DEL_SISTEMA - DOMINIO DE SUBREDES Y PLAN IP / IPAM (KNOWLEDGE GRAPH)]",
                "🌐 PLAN DE SUBREDES Y DIRECCIONAMIENTO IP EN MARIADB (SSOT):",
                f"📍 Subredes y Segmentos Operativos Registrados ({total_subnets} bloques en discovery_subnets):",
            ]
            if subnet_nodes:
                for idx, sn in enumerate(subnet_nodes, 1):
                    site_lbl = sn.get("site_name") or "Infraestructura General"
                    method_lbl = sn.get("scan_method", "arp_sweep")
                    status_lbl = "Activa" if sn.get("is_active") else "Inactiva"
                    lines.append(f"    {idx}. Segmento: {sn.get('subnet')} | Sede: {site_lbl} | Método: {method_lbl} ({status_lbl})")
            else:
                lines.append("    (No hay bloques de subred configurados en discovery_subnets)")

            return "\n".join(lines), subnet_nodes[0].id if subnet_nodes else None

        # =====================================================================
        # DOMINIO 11: CERTIFICADOS SSL / TLS
        # =====================================================================
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

        # =====================================================================
        # DOMINIO 12: SERVICIOS CORPORATIVOS, PROXIES Y TELEMETRÍA DE CAÍDAS (NOC)
        # =====================================================================
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

        # =====================================================================
        # DOMINIO 13: FALLBACK FÁCTICO GENERAL (CERO CEGUERA / UNIVERSAL TIER 1)
        # =====================================================================
        all_assets = self.find_nodes_by_type(KnowledgeNodeType.INVENTORY_ASSET)
        all_devs = self.find_nodes_by_type(KnowledgeNodeType.NETWORK_DEVICE)
        all_sites = self.find_nodes_by_type(KnowledgeNodeType.SITE)
        all_subnets = self.find_nodes_by_type(KnowledgeNodeType.SUBNET)
        all_assignments = self.find_nodes_by_type(KnowledgeNodeType.ASSIGNMENT_ACT)
        all_ups = self.find_nodes_by_type(KnowledgeNodeType.UPS)

        assets_with_serial = sum(1 for a in all_assets if a.get("serial_number"))
        assignments_with_pdf = sum(1 for asg in all_assignments if asg.get("has_scanned_pdf"))

        if all_ups:
            u_first = all_ups[0]
            ups_status = "en línea" if u_first.get("is_online") else "en modo bypass / red"
            ups_summary = f"{u_first.label} ({ups_status})"
        else:
            ups_summary = "Monitoreo eléctrico centralizado en MariaDB"

        if all_subnets:
            subnets_summary = ", ".join(f"{sn.get('subnet')} ({sn.get('site_name', 'General')})" for sn in all_subnets)
        else:
            subnets_summary = "Consultado dinámicamente desde discovery_subnets"

        lines = [
            "[CONTEXTO_DEL_SISTEMA - CENSO GLOBAL DE INFRAESTRUCTURA (KNOWLEDGE GRAPH)]",
            f"📊 CENSO TOTAL DE LA RED Y DISPOSITIVOS EN MARIADB (SSOT):",
            f"  • Sedes Físicas Conectadas: {len(all_sites)} sedes.",
            f"  • Dispositivos de Red Monitoreados (Switches, Routers, Servidores): {len(all_devs)} equipos.",
            f"  • Activos de Hardware ITAM: {len(all_assets)} equipos.",
            f"  • Equipos con Serial de Chasis / CPU Asociado: {assets_with_serial} de {len(all_assets)}.",
            f"  • Actas de Asignación con PDF Escaneado Oficial (UINF-4.05): {assignments_with_pdf} de {len(all_assignments)}.",
            f"  • Respaldo Eléctrico: {ups_summary}.",
            f"  • Segmentos Principales: {subnets_summary}.",
        ]
        return "\n".join(lines), None
