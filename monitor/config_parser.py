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
    """Cargador estructurado de configuraciones de monitoreo."""

    def __init__(self):
        self.monitoreo_path = get_config_path("monitoreo.conf")
        self.mensajes_path = get_config_path("mensajes.conf")
        self.bot_path = get_config_path("bot.conf")

        self.raw_monitoreo = parse_bash_config(self.monitoreo_path)
        self.raw_bot = parse_bash_config(self.bot_path)
        self.templates = parse_bash_templates(self.mensajes_path)
        db_templates = load_templates_from_mariadb()
        if db_templates:
            self.templates.update(db_templates)

    def get_services(self) -> List[ServiceConfig]:
        """Extrae la lista de servicios configurados (letras A a Z)."""
        services: List[ServiceConfig] = []
        for code in range(ord('A'), ord('Z') + 1):
            letter = chr(code)
            stype = self.raw_monitoreo.get(f"TYPESERVICE{letter}")
            name = self.raw_monitoreo.get(f"NAMESERVICE{letter}")
            if not stype or not name or name.strip().upper() in ("NO CONFIGURADO", "SIN CONFIGURAR", ""):
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
        """Extrae la lista de sedes (A a H) y sus equipos de comunicación (1 a 8)."""
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
        """Extrae la lista de proxies configurados en bot.conf o monitoreo.conf."""
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
