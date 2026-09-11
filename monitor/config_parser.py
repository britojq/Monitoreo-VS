"""
# ==============================================================================
# ⚙️ PARSER Y GESTIÓN DE CONFIGURACIONES: config_parser.py (@IA_ValleSeco_bot)
# Procesamiento de archivos de configuración (.conf), variables y renderizado
# Ubicación: /scripts/telegram-admin-bot/monitor/config_parser.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server
# License: GNU Affero General Public License v3.0 
# Author: Jose A. Brito H. (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Jose A. Brito H.
# ==============================================================================
"""

from __future__ import annotations

import os
import re
import urllib.parse
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple, Union

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"


def get_config_path(filename: str) -> Path:
    """Busca el archivo exclusivamente en el directorio config/ del proyecto."""
    return CONFIG_DIR / filename


def parse_bash_config(filepath: Union[Path, str]) -> Dict[str, str]:
    """Parsea archivos de configuración de Bash con formato CLAVE=VALOR, soportando valores multilínea."""
    data: Dict[str, str] = {}
    filepath = Path(filepath)
    if not filepath.exists():
        return data

    content = filepath.read_text(encoding="utf-8", errors="ignore")
    pattern = re.compile(r"^[ \t]*([A-Za-z0-9_]+)[ \t]*=[ \t]*(?:\"([^\"]*)\"|'([^']*)'|([^#\r\n]*))", re.MULTILINE)
    for m in pattern.finditer(content):
        k = m.group(1).strip()
        v = m.group(2) if m.group(2) is not None else (m.group(3) if m.group(3) is not None else m.group(4))
        data[k] = (v or "").strip()
    return data


def parse_bash_templates(filepath: Path) -> Dict[str, str]:
    """Parsea plantillas multilínea en archivos tipo Bash (como mensajes.conf)."""
    if not filepath.exists():
        return {}

    content = filepath.read_text(encoding="utf-8", errors="ignore")
    data: Dict[str, str] = {}
    pattern = re.compile(r'^([A-Za-z0-9_]+)=([\"\'])(.*?)\2', re.MULTILINE | re.DOTALL)
    for m in pattern.finditer(content):
        data[m.group(1)] = m.group(3)
    return data


def load_templates_from_mariadb() -> Dict[str, str]:
    """Carga las plantillas de mensajes oficiales directamente desde MariaDB (SSOT)."""
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT template_key, header_text, sub_header, legend_text, "
                    "impact_statement, default_signature, slogan FROM bot_message_templates WHERE is_active = 1"
                )
                rows = cursor.fetchall()
                if not rows:
                    return {}

                def to_md(text: str) -> str:
                    if not text:
                        return ""
                    return text.replace("<b>", "**").replace("</b>", "**")

                templates: Dict[str, str] = {}
                for r in rows:
                    key = r.get("template_key")
                    hdr = to_md(r.get("header_text") or "")
                    sub = to_md(r.get("sub_header") or "")
                    leg = to_md(r.get("legend_text") or "")
                    imp = r.get("impact_statement") or ""
                    sig = to_md(r.get("default_signature") or "")
                    slo = to_md(r.get("slogan") or "")

                    if key == "servicios":
                        body = (
                            f"\n{hdr}\n"
                            f"**Fecha:** $fecha\n"
                            f"**Hora:** $hora.\n\n"
                            f"{sub}\n\n"
                            f"{leg}\n\n"
                            f"━━━━━━━━━━━━\n"
                            f"**Servicios Corporativos Verificados:**\n"
                            f"━━━━━━━━━━━━\n"
                            f"$STHOSTA\n$STHOSTB\n$STHOSTC\n$STHOSTD\n$STHOSTE\n"
                            f"$STHOSTF\n$STHOSTG\n$STHOSTI\n$STHOSTJ\n$STHOSTK\n\n"
                            f"━━━━━━━━━━━━\n"
                            f"**Servicios Regionales – Carabobo Verificados:**\n"
                            f"━━━━━━━━━━━━\n"
                            f"$STHOSTL\n$STHOSTM\n$STHOSTN\n$STHOSTO\n$STHOSTT\n"
                            f"$STHOSTS\n$STHOSTR\n$STHOSTQ\n\n"
                            f"{imp}\n\n"
                            f"{sig}\n\n"
                            f"{slo}\n"
                        )
                        templates["MENSAJEA"] = body
                    elif key == "sedes":
                        body = (
                            f"\n{hdr}\n"
                            f"**Fecha:** $fecha\n"
                            f"**Hora:** $hora.\n\n"
                            f"{sub}\n\n"
                            f"{leg}\n\n"
                            f"━━━━━━━━━━━━\n"
                            f"**$NAMESITEA**\n"
                            f"━━━━━━━━━━━━\n"
                            f"$STSITEA\n\n"
                            f"━━━━━━━━━━━━\n"
                            f"**$NAMESITEC**\n"
                            f"━━━━━━━━━━━━\n"
                            f"$STSITEC\n"
                            f"$STSITECEQUIPO1\n$STSITECEQUIPO2\n$STSITECEQUIPO3\n$STSITECEQUIPO5\n$STSITECEQUIPO8\n\n"
                            f"━━━━━━━━━━━━\n"
                            f"**$NAMESITED**\n"
                            f"━━━━━━━━━━━━\n"
                            f"$STSITED\n"
                            f"$STSITEDEQUIPO1\n$STSITEDEQUIPO2\n$STSITEDEQUIPO3\n\n"
                            f"━━━━━━━━━━━━\n"
                            f"**$NAMESITEE**\n"
                            f"━━━━━━━━━━━━\n"
                            f"$STSITEE\n"
                            f"$STSITEEEQUIPO1\n$STSITEEEQUIPO2\n$STSITEEEQUIPO3\n$STSITEEEQUIPO4\n$STSITEEEQUIPO5\n$STSITEEEQUIPO8\n\n"
                            f"{imp}\n\n"
                            f"{sig}\n\n"
                            f"{slo}\n"
                        )
                        templates["MENSAJEC"] = body
                return templates
        finally:
            conn.close()
    except Exception:
        return {}


@dataclass
class ServiceConfig:
    letter: str
    service_type: str  # WEB, DNS, PROXY, SMTP, DHCP, CUPS, LDAP, PING, OTRO
    name: str
    web_url: str = ""
    ip_host: str = ""
    cups_port_ip: str = ""
    ldap_port_ip: str = ""
    smtp_port: str = ""
    net_interface: str = ""
    dns_test_host: str = ""
    proxy_user_pass: str = ""
    proxy_ip_port: str = ""
    url_test_site: str = ""
    msg_normal: str = ""
    msg_error: str = ""


@dataclass
class EquipmentConfig:
    site_letter: str
    num: int
    name: str
    ip_host: str
    msg_normal: str = ""
    msg_error: str = ""


@dataclass
class SiteConfig:
    letter: str
    name: str
    ip_host: str
    msg_normal: str = ""
    msg_error: str = ""
    equipment: List[EquipmentConfig] = field(default_factory=list)


@dataclass
class ProxyConfig:
    letter: str
    name: str
    ip_port: str
    user_pass: str
    url: str


def load_raw_configs_from_mariadb() -> Tuple[Dict[str, str], Dict[str, str]]:
    """Carga variables de configuración de servicios, sedes y proxies directamente desde MariaDB (SSOT)."""
    raw_monitoreo: Dict[str, str] = {}
    raw_bot: Dict[str, str] = {}
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                # 1. Servicios
                cur.execute("SELECT * FROM monitored_services")
                for r in cur.fetchall():
                    L = r["letter"].upper()
                    raw_monitoreo[f"NAMESERVICE{L}"] = r["name"] or ""
                    raw_monitoreo[f"TYPESERVICE{L}"] = (r["type"] or "WEB").upper() if r.get("is_active") else "DESACTIVADO"
                    raw_monitoreo[f"WEBSERVICE{L}"] = r["web_url"] or ""
                    raw_ip = (r["host_ip"] or "").strip()
                    clean_ip = raw_ip.split(":")[0] if raw_ip else ""
                    port = r.get("port")
                    raw_monitoreo[f"IPSERVICE{L}"] = clean_ip
                    raw_monitoreo[f"CUPSPORTIP{L}"] = f"{clean_ip}:{port or 631}"
                    raw_monitoreo[f"LDAPPORTIP{L}"] = str(port or 389)
                    raw_monitoreo[f"SMTPPORT{L}"] = str(port or 25)
                    raw_monitoreo[f"NETINTERFACE{L}"] = r.get("check_interface") or "eno1"
                    raw_monitoreo[f"TESTHOSTDNS{L}"] = r.get("dns_test_domain") or ""
                    raw_monitoreo[f"PROXYUSERPASSW{L}"] = r.get("credentials") or "USUARIO:CLAVE"
                    raw_monitoreo[f"PROXYIPPORT{L}"] = f"{clean_ip}:{port or 8080}"
                    raw_monitoreo[f"URLTESTSITE{L}"] = r.get("web_url") or ""
                    raw_monitoreo[f"NORMALESTATEMSG{L}"] = r.get("normal_state_msg") or f"✅ - $NAMESERVICE{L}"
                    raw_monitoreo[f"ERRORESTATEMSG{L}"] = r.get("error_state_msg") or f"❌ - $NAMESERVICE{L}"

                # 2. Sedes y Equipos
                cur.execute("SELECT * FROM monitored_sites")
                for s in cur.fetchall():
                    L = s["letter"].upper()
                    raw_monitoreo[f"NAMESITE{L}"] = s["name"] or ""
                    raw_monitoreo[f"IPSITE{L}"] = s["ip"] or "0.0.0.0"
                    raw_monitoreo[f"NORMALSITE{L}"] = s.get("normal_state_msg") or f"✅ - $NAMESITE{L}"
                    raw_monitoreo[f"ERRORSITE{L}"] = s.get("error_state_msg") or f"❌ - $NAMESITE{L}"
                    raw_monitoreo[f"SITE{L}DIRECCION"] = s.get("address") or ""
                    for n in range(1, 9):
                        raw_monitoreo[f"SITE{L}TELEFONO{n}"] = s.get(f"phone_{n}") or ""

                cur.execute(
                    "SELECT d.*, s.letter as site_letter FROM monitored_site_devices d "
                    "JOIN monitored_sites s ON d.monitored_site_id = s.id"
                )
                for d in cur.fetchall():
                    L = d["site_letter"].upper()
                    N = d["device_number"]
                    raw_monitoreo[f"NAMESITE{L}EQUIPO{N}"] = d["name"] or ""
                    raw_monitoreo[f"IPSITE{L}EQUIPO{N}"] = d["ip"] if d.get("is_active") else "0.0.0.0"
                    raw_monitoreo[f"NORMALSITE{L}EQUIPO{N}"] = d.get("normal_state_msg") or f"✅ - $NAMESITE{L}EQUIPO{N}"
                    raw_monitoreo[f"ERRORSITE{L}EQUIPO{N}"] = d.get("error_state_msg") or f"❌ - $NAMESITE{L}EQUIPO{N}"

                # 3. Proxies
                cur.execute("SELECT * FROM monitored_proxies")
                for p in cur.fetchall():
                    L = p["letter"].upper()
                    raw_bot[f"NAMEPROXY{L}"] = p["name"] or f"Proxy {L}"
                    raw_bot[f"IPADDRPORTPROXY{L}"] = p["ip_port"] or ""
                    raw_bot[f"USERPASSWDPROXY{L}"] = p.get("auth_userpass") or ""
                    raw_monitoreo[f"DEFAULTPROXY{L}"] = p["ip_port"] or ""
                    raw_monitoreo[f"USUARIOCLAVEDEFAULT{L}"] = p.get("auth_userpass") or ""
        finally:
            conn.close()
    except Exception:
        pass
    return raw_monitoreo, raw_bot


def load_services_from_mariadb() -> List[ServiceConfig]:
    """Extrae la lista de servicios activos directamente desde MariaDB (SSOT)."""
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT * FROM monitored_services WHERE is_active = 1 "
                    "AND name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY sort_order, letter"
                )
                rows = cur.fetchall()
                if not rows:
                    return []

                services: List[ServiceConfig] = []
                for r in rows:
                    letter = r["letter"].upper()
                    name = (r["name"] or "").strip()
                    stype = (r["type"] or "WEB").strip().upper()
                    raw_ip = (r["host_ip"] or "").strip()
                    clean_ip = raw_ip.split(":")[0] if raw_ip else ""
                    port = r.get("port")
                    web_url = (r["web_url"] or "").strip()
                    credentials = (r.get("credentials") or "").strip()
                    check_iface = (r.get("check_interface") or "eno1").strip()
                    dns_domain = (r.get("dns_test_domain") or "intranet.corpoelec.com.ve").strip()
                    normal_msg = (r.get("normal_state_msg") or f"✅ - {name}").strip()
                    error_msg = (r.get("error_state_msg") or f"❌ - {name}").strip()

                    cups_port = f"{clean_ip}:{port or 631}"
                    ldap_port = str(port or 389)
                    smtp_port = str(port or 25)
                    proxy_ip_port = f"{clean_ip}:{port or 8080}"
                    url_test = web_url or (f"http://{clean_ip}" if clean_ip else "")

                    svc = ServiceConfig(
                        letter=letter,
                        service_type=stype,
                        name=name,
                        web_url=web_url,
                        ip_host=clean_ip,
                        cups_port_ip=cups_port,
                        ldap_port_ip=ldap_port,
                        smtp_port=smtp_port,
                        net_interface=check_iface,
                        dns_test_host=dns_domain,
                        proxy_user_pass=credentials,
                        proxy_ip_port=proxy_ip_port,
                        url_test_site=url_test,
                        msg_normal=normal_msg,
                        msg_error=error_msg
                    )
                    services.append(svc)
                return services
        finally:
            conn.close()
    except Exception:
        return []


def load_sites_from_mariadb() -> List[SiteConfig]:
    """Extrae la lista de sedes y sus equipos de comunicación activos desde MariaDB (SSOT)."""
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT * FROM monitored_sites WHERE is_active = 1 "
                    "AND name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY sort_order, letter"
                )
                site_rows = cur.fetchall()
                if not site_rows:
                    return []

                cur.execute(
                    "SELECT d.*, s.letter as site_letter FROM monitored_site_devices d "
                    "JOIN monitored_sites s ON d.monitored_site_id = s.id "
                    "WHERE d.is_active = 1 AND d.name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY s.letter, d.device_number"
                )
                dev_rows = cur.fetchall()

                dev_by_site: Dict[str, List[Dict]] = {}
                for d in dev_rows:
                    dev_by_site.setdefault(d["site_letter"].upper(), []).append(d)

                sites: List[SiteConfig] = []
                for s in site_rows:
                    letter = s["letter"].upper()
                    name = (s["name"] or "").strip()
                    ip = (s["ip"] or "").strip()
                    normal_msg = (s.get("normal_state_msg") or f"✅ - {name}").strip()
                    error_msg = (s.get("error_state_msg") or f"❌ - {name}").strip()

                    equipment: List[EquipmentConfig] = []
                    for d in dev_by_site.get(letter, []):
                        dnum = int(d["device_number"])
                        dname = (d["name"] or "").strip()
                        dip = (d["ip"] or "").strip()
                        dnorm = (d.get("normal_state_msg") or f"✅ - {dname}").strip()
                        derr = (d.get("error_state_msg") or f"❌ - {dname}").strip()
                        eq = EquipmentConfig(
                            site_letter=letter,
                            num=dnum,
                            name=dname,
                            ip_host=dip,
                            msg_normal=dnorm,
                            msg_error=derr
                        )
                        equipment.append(eq)

                    st = SiteConfig(
                        letter=letter,
                        name=name,
                        ip_host=ip,
                        msg_normal=normal_msg,
                        msg_error=error_msg,
                        equipment=equipment
                    )
                    sites.append(st)
                return sites
        finally:
            conn.close()
    except Exception:
        return []


def load_proxies_from_mariadb() -> List[ProxyConfig]:
    """Extrae la lista de proxies configurados directamente desde MariaDB (SSOT)."""
    try:
        from monitor.monitor_web_sync import get_db_connection
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT * FROM monitored_proxies WHERE is_active = 1 "
                    "AND name NOT IN ('NO CONFIGURADO', 'SIN CONFIGURAR') "
                    "ORDER BY letter"
                )
                rows = cur.fetchall()
                if not rows:
                    return []

                proxies: List[ProxyConfig] = []
                for r in rows:
                    letter = r["letter"].upper()
                    name = (r["name"] or f"Proxy {letter}").strip()
                    ip_port = (r["ip_port"] or "").strip()
                    auth = (r.get("auth_userpass") or "").strip()
                    if not ip_port:
                        continue
                    if auth and ":" in auth:
                        u, p = auth.split(":", 1)
                        u_enc = urllib.parse.quote(u)
                        p_enc = urllib.parse.quote(p)
                        url = f"http://{u_enc}:{p_enc}@{ip_port}"
                    else:
                        url = f"http://{ip_port}"
                    proxies.append(ProxyConfig(letter=letter, name=name, ip_port=ip_port, user_pass=auth, url=url))
                return proxies
        finally:
            conn.close()
    except Exception:
        return []


def get_formatted_datetime() -> Tuple[str, str]:
    """Retorna fecha en formato formal en español y hora HH:MM:SS."""
    now = datetime.now()
    dias = ["lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"]
    meses = [
        "enero", "febrero", "marzo", "abril", "mayo", "junio",
        "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"
    ]
    dia_nombre = dias[now.weekday()].capitalize()
    mes_nombre = meses[now.month - 1].capitalize()
    fecha_str = f"{dia_nombre}, {now.day} de {mes_nombre} de {now.year}."
    hora_str = now.strftime("%H:%M:%S")
    return fecha_str, hora_str


def render_template(template_str: str, variables: Dict[str, str]) -> str:
    """Sustituye variables estilo Bash ($VAR o ${VAR}) en la plantilla de texto, resolviendo variables anidadas."""
    def replacer(match: re.Match) -> str:
        var_name = match.group(1) or match.group(2)
        val = variables.get(var_name)
        if val is not None:
            return str(val)
        return ""

    # Primera pasada de sustitución
    result = re.sub(r'\$\{([A-Za-z0-9_]+)\}|\$([A-Za-z0-9_]+)', replacer, template_str)
    # Segunda pasada para variables anidadas (ej. $NAMESERVICEA dentro de $STHOSTA)
    if '$' in result:
        result = re.sub(r'\$\{([A-Za-z0-9_]+)\}|\$([A-Za-z0-9_]+)', replacer, result)

    return result


class MonitorConfigLoader:
    """Cargador estructurado de configuraciones de monitoreo con MariaDB como SSOT."""

    def __init__(self):
        self.monitoreo_path = get_config_path("monitoreo.conf")
        self.mensajes_path = get_config_path("mensajes.conf")
        self.bot_path = get_config_path("bot.conf")

        self.raw_monitoreo = parse_bash_config(self.monitoreo_path)
        self.raw_bot = parse_bash_config(self.bot_path)
        self.templates = parse_bash_templates(self.mensajes_path)

        # 1. Overlay dinámico de MariaDB para variables bash
        db_raw_monitoreo, db_raw_bot = load_raw_configs_from_mariadb()
        if db_raw_monitoreo:
            self.raw_monitoreo.update(db_raw_monitoreo)
        if db_raw_bot:
            self.raw_bot.update(db_raw_bot)

        # 2. Overlay dinámico de MariaDB para plantillas de mensajes
        db_templates = load_templates_from_mariadb()
        if db_templates:
            self.templates.update(db_templates)

    def get_services(self) -> List[ServiceConfig]:
        """Extrae la lista de servicios configurados (primero MariaDB, fallback monitoreo.conf)."""
        db_services = load_services_from_mariadb()
        if db_services:
            return db_services

        # Fallback histórico a monitoreo.conf
        services: List[ServiceConfig] = []
        for code in range(ord('A'), ord('Z') + 1):
            letter = chr(code)
            stype = self.raw_monitoreo.get(f"TYPESERVICE{letter}")
            name = self.raw_monitoreo.get(f"NAMESERVICE{letter}")
            if not stype or not name or name.strip().upper() in ("NO CONFIGURADO", "SIN CONFIGURAR", "") or stype.strip().upper() in ("DESACTIVADO", "INACTIVO"):
                continue

            svc = ServiceConfig(
                letter=letter,
                service_type=stype.strip().upper(),
                name=name.strip(),
                web_url=self.raw_monitoreo.get(f"WEBSERVICE{letter}", "").strip(),
                ip_host=self.raw_monitoreo.get(f"IPSERVICE{letter}", "").strip(),
                cups_port_ip=self.raw_monitoreo.get(f"CUPSPORTIP{letter}", "").strip(),
                ldap_port_ip=self.raw_monitoreo.get(f"LDAPPORTIP{letter}", "").strip(),
                smtp_port=self.raw_monitoreo.get(f"SMTPPORT{letter}", "").strip(),
                net_interface=self.raw_monitoreo.get(f"NETINTERFACE{letter}", "").strip(),
                dns_test_host=self.raw_monitoreo.get(f"TESTHOSTDNS{letter}", "").strip(),
                proxy_user_pass=self.raw_monitoreo.get(f"PROXYUSERPASSW{letter}", "").strip(),
                proxy_ip_port=self.raw_monitoreo.get(f"PROXYIPPORT{letter}", "").strip(),
                url_test_site=self.raw_monitoreo.get(f"URLTESTSITE{letter}", "").strip(),
                msg_normal=self.raw_monitoreo.get(f"NORMALESTATEMSG{letter}", f"✅ {name}: Operativo").strip(),
                msg_error=self.raw_monitoreo.get(f"ERRORESTATEMSG{letter}", f"❌ {name}: Falla").strip()
            )
            services.append(svc)
        return services

    def get_sites(self) -> List[SiteConfig]:
        """Extrae la lista de sedes y equipos de comunicación (primero MariaDB, fallback monitoreo.conf)."""
        db_sites = load_sites_from_mariadb()
        if db_sites:
            return db_sites

        # Fallback histórico a monitoreo.conf
        sites: List[SiteConfig] = []
        for code in range(ord('A'), ord('H') + 1):
            letter = chr(code)
            site_name = self.raw_monitoreo.get(f"NAMESITE{letter}")
            site_ip = self.raw_monitoreo.get(f"IPSITE{letter}")
            if not site_name or not site_ip or site_name.strip().upper() in ("NO CONFIGURADO", "SIN CONFIGURAR", ""):
                continue

            site = SiteConfig(
                letter=letter,
                name=site_name.strip(),
                ip_host=site_ip.strip(),
                msg_normal=self.raw_monitoreo.get(f"NORMALSITE{letter}", f"✅ {site_name}: Operativo").strip(),
                msg_error=self.raw_monitoreo.get(f"ERRORSITE{letter}", f"❌ {site_name}: Falla").strip(),
                equipment=[]
            )

            for num in range(1, 9):
                eq_name = self.raw_monitoreo.get(f"NAMESITE{letter}EQUIPO{num}")
                eq_ip = self.raw_monitoreo.get(f"IPSITE{letter}EQUIPO{num}")
                if not eq_name or not eq_ip or eq_name.strip().upper() in ("NO CONFIGURADO", "SIN CONFIGURAR", ""):
                    continue

                eq = EquipmentConfig(
                    site_letter=letter,
                    num=num,
                    name=eq_name.strip(),
                    ip_host=eq_ip.strip(),
                    msg_normal=self.raw_monitoreo.get(f"NORMALSITE{letter}EQUIPO{num}", f"✅ {eq_name}: Operativo").strip(),
                    msg_error=self.raw_monitoreo.get(f"ERRORSITE{letter}EQUIPO{num}", f"❌ {eq_name}: Falla").strip()
                )
                site.equipment.append(eq)

            sites.append(site)
        return sites

    def get_proxies(self) -> List[ProxyConfig]:
        """Extrae la lista de proxies (primero MariaDB, fallback bot.conf / monitoreo.conf)."""
        db_proxies = load_proxies_from_mariadb()
        if db_proxies:
            return db_proxies

        # Fallback histórico
        proxies: List[ProxyConfig] = []
        for letter in ("A", "B", "C", "D"):
            ip = self.raw_bot.get(f"IPADDRPORTPROXY{letter}") or self.raw_monitoreo.get(f"DEFAULTPROXY{letter}")
            auth = self.raw_bot.get(f"USERPASSWDPROXY{letter}") or self.raw_monitoreo.get(f"USUARIOCLAVEDEFAULT{letter}", "")
            name = self.raw_bot.get(f"NAMEPROXY{letter}", f"Proxy {letter}")
            if ip:
                if auth and ":" in auth:
                    u, p = auth.split(":", 1)
                    u_enc = urllib.parse.quote(u)
                    p_enc = urllib.parse.quote(p)
                    url = f"http://{u_enc}:{p_enc}@{ip}"
                else:
                    url = f"http://{ip}"
                proxies.append(ProxyConfig(letter=letter, name=name, ip_port=ip, user_pass=auth, url=url))
        return proxies
