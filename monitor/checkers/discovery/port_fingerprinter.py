"""
Fingerprinting profundo de puertos, NetBIOS, SMB y clasificación de tipos de dispositivo.
"""
import logging
import os
import re
import subprocess
from typing import Any, Dict, List, Optional, Tuple
import xml.etree.ElementTree as ET

from monitor.infrastructure.db_context import db_cursor

logger = logging.getLogger("monitor.checkers.discovery.fingerprinter")


def lookup_oui_vendor(mac: str, conn=None) -> Tuple[Optional[str], Optional[str]]:
    """Resuelve fabricante por prefijo OUI consultando MariaDB (SSOT)."""
    if not mac or len(mac) < 8:
        return ("Dispositivo Desconocido", None)

    prefix = mac[:8].upper()
    try:
        with db_cursor() as cur:
            cur.execute("SELECT vendor_name FROM oui_vendors WHERE oui_prefix = %s LIMIT 1", (prefix,))
            row = cur.fetchone()
            if row and row.get("vendor_name"):
                return (row["vendor_name"], prefix)
    except Exception as e:
        logger.debug(f"Error consultando OUI {prefix}: {e}")

    return ("Fabricante Desconocido", prefix)


def infer_device_type(
    vendor: Optional[str],
    hostname: str,
    asset_type: Optional[str] = None,
    inferred_type: Optional[str] = None
) -> str:
    """Clasifica el tipo de dispositivo de acuerdo con las categorías autorizadas."""
    if asset_type:
        asset_clean = asset_type.lower().strip()
        if asset_clean in ["router", "switch", "firewall", "server", "printer", "ap", "camera", "ups", "phone"]:
            return asset_clean
        if asset_clean in ["desktop", "laptop", "pc", "workstation"]:
            return "workstation"
        if asset_clean in ["ip_phone"]:
            return "phone"

    if inferred_type and inferred_type in ["router", "switch", "firewall", "server", "printer", "ap", "camera", "ups", "phone", "workstation"]:
        return inferred_type

    text = f"{vendor or ''} {hostname}".lower()
    if any(k in text for k in ["switch", "c2960", "c3750", "catalyst", "procurve", "edgeswitch", "sw-", "sw_"]):
        return "switch"
    if any(k in text for k in ["router", "mikrotik", "pfsense", "fortinet", "gateway", "gw-"]):
        return "router"
    if any(k in text for k in ["vmware", "kvm", "qemu", "server", "srv", "poweredge", "proliant", "debian", "ubuntu", "linux"]):
        return "server"
    if any(k in text for k in ["printer", "laserjet", "epson", "xerox", "canon"]):
        return "printer"
    if any(k in text for k in ["ap-", "unifi", "ubiquiti", "wireless", "access point", "tp-link"]):
        return "ap"
    if any(k in text for k in ["camera", "cam", "dahua", "hikvision"]):
        return "camera"
    if any(k in text for k in ["apc", "schneider", "ups"]):
        return "ups"
    if any(k in text for k in ["avaya", "grandstream", "yealink", "polycom", "voip", "phone", "telefono", "teléfono"]):
        return "phone"
    if any(k in text for k in ["hp prodesk", "hp inc", "dell inc", "intel", "lenovo", "micro-star", "msi", "foxconn", "hon hai", "workstation", "desktop", "laptop", "pc-", "windows"]):
        return "workstation"

    return "unknown"


def deep_fingerprint_hosts(ip_list: List[str]) -> Dict[str, Dict[str, Any]]:
    """Inspección dirigida y rápida sobre los hosts activos detectados."""
    if not ip_list:
        return {}

    fp_results: Dict[str, Dict[str, Any]] = {}
    nmap_path = "/usr/bin/nmap"
    if not os.path.exists(nmap_path):
        return fp_results

    try:
        cmd = [
            nmap_path, "-Pn", "-sT",
            "-p", "22,80,135,139,443,445,3389,5800,5900,9100",
            "--script", "smb-os-discovery,nbstat",
            "-T4", "-oX", "-"
        ] + ip_list

        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        if proc.returncode == 0 and proc.stdout.strip():
            root = ET.fromstring(proc.stdout)
            for host in root.findall("host"):
                ip = None
                hostname = None
                os_name = None
                open_ports: List[int] = []

                for addr in host.findall("address"):
                    if addr.get("addrtype") == "ipv4":
                        ip = addr.get("addr")

                if not ip:
                    continue

                hostnames_elem = host.find("hostnames")
                if hostnames_elem is not None:
                    hn = hostnames_elem.find("hostname")
                    if hn is not None and hn.get("name"):
                        hostname = hn.get("name")

                ports_elem = host.find("ports")
                if ports_elem is not None:
                    for p in ports_elem.findall("port"):
                        state_elem = p.find("state")
                        if state_elem is not None and state_elem.get("state") == "open":
                            try:
                                open_ports.append(int(p.get("portid")))
                            except (ValueError, TypeError):
                                pass

                hostscript = host.find("hostscript")
                if hostscript is not None:
                    for script in hostscript.findall("script"):
                        sid = script.get("id")
                        if sid == "smb-os-discovery":
                            out = script.get("output", "")
                            for line in out.splitlines():
                                if "OS:" in line:
                                    os_name = line.split("OS:", 1)[1].strip()
                                elif "Computer name:" in line and not hostname:
                                    hostname = line.split("Computer name:", 1)[1].strip()
                        elif sid == "nbstat":
                            out = script.get("output", "")
                            for line in out.splitlines():
                                if "NetBIOS name:" in line and not hostname:
                                    hostname = line.split("NetBIOS name:", 1)[1].split(",")[0].strip()

                if not os_name:
                    if 22 in open_ports and 135 not in open_ports:
                        os_name = "Linux / Unix (SSH)"
                    elif 135 in open_ports or 445 in open_ports or 3389 in open_ports:
                        os_name = "Windows (MSRPC / SMB)"
                    elif 9100 in open_ports:
                        os_name = "Impresora de Red"

                inferred_type = None
                if 9100 in open_ports:
                    inferred_type = "printer"
                elif 135 in open_ports or 445 in open_ports or 3389 in open_ports or 5900 in open_ports or 22 in open_ports:
                    inferred_type = "workstation"

                fp_results[ip] = {
                    "hostname": hostname,
                    "os_detected": os_name,
                    "open_ports": open_ports,
                    "inferred_type": inferred_type,
                }
    except Exception as e:
        logger.warning(f"Aviso en deep_fingerprint_hosts: {e}")

    for ip in ip_list:
        if ip not in fp_results:
            fp_results[ip] = {"hostname": None, "os_detected": None, "open_ports": [], "inferred_type": None}
        if not fp_results[ip].get("os_detected"):
            try:
                p_res = subprocess.run(["ping", "-c", "1", "-W", "1", ip], capture_output=True, text=True, timeout=2)
                if p_res.returncode == 0:
                    ttl_match = re.search(r"ttl=(\d+)", p_res.stdout.lower())
                    if ttl_match:
                        ttl_val = int(ttl_match.group(1))
                        if ttl_val <= 64:
                            fp_results[ip]["os_detected"] = "Linux / Unix"
                            fp_results[ip]["inferred_type"] = "workstation"
                        elif ttl_val <= 128:
                            fp_results[ip]["os_detected"] = "Windows"
                            fp_results[ip]["inferred_type"] = "workstation"
            except Exception:
                pass

    return fp_results
