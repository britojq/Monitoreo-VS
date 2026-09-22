"""
# ==============================================================================
# 💾 GESTOR DE RESPALDO DE CONFIGURACIONES Y DETECCIÓN DE CAMBIOS: config_backup.py
# Respaldo automatizado de routers, switches y firewalls (Cisco, pfSense, Linux)
# Detección de cambios por hash SHA-256 y diferencias unificadas estilo git.
# Ubicación: /scripts/telegram-admin-bot/monitor/config_backup.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import asyncio
import difflib
import hashlib
import json
import logging
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import pymysql

try:
    from monitor.snmp_poller import decrypt_laravel_string
except ImportError:
    def decrypt_laravel_string(val: str) -> str:
        return val

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [config.backup] %(message)s",
    handlers=[
        logging.FileHandler(LOG_DIR / "config_backup.log", encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("config.backup")


class ConfigBackupManager:
    """Gestiona la captura, versionado y comparación diferencial de configuraciones de red."""

    def __init__(self):
        self.db = None
        self._connect_db()

    def _connect_db(self):
        from monitor.monitor_web_sync import get_db_connection
        try:
            self.db = get_db_connection()
        except Exception as e:
            logger.error(f"Error conectando a MariaDB: {e}")
            self.db = None

    def _ensure_db(self):
        if not self.db:
            self._connect_db()
        else:
            try:
                self.db.ping(reconnect=True)
            except Exception:
                self._connect_db()

    @staticmethod
    def normalize_config(raw_config: str, device_type: str = "cisco_switch") -> str:
        """
        Normaliza el texto de configuración eliminando marcas de tiempo volátiles
        que generarían falsos positivos en el hash SHA-256.
        """
        if not raw_config:
            return ""

        lines = raw_config.splitlines()
        filtered_lines = []

        for line in lines:
            # Eliminar timestamps volátiles típicos de Cisco y otros fabricantes
            if re.match(r"^!\s*(Last configuration change at|NVRAM config last updated at|Current configuration|Time:)", line, re.IGNORECASE):
                continue
            if re.match(r"^#\s*(Generated on|Backup date:)", line, re.IGNORECASE):
                continue
            filtered_lines.append(line.rstrip())

        # Unificar saltos de línea Unix
        return "\n".join(filtered_lines).strip() + "\n"

    @staticmethod
    def calculate_hash(text: str) -> str:
        """Calcula el hash SHA-256 del texto normalizado."""
        return hashlib.sha256(text.encode("utf-8")).hexdigest()

    @staticmethod
    def generate_unified_diff(
        old_text: str,
        new_text: str,
        label_old: str = "Versión Previa",
        label_new: str = "Versión Actual"
    ) -> Tuple[str, int, int]:
        """
        Genera un diff unificado estilo git y computa líneas añadidas/removidas.
        """
        old_lines = old_text.splitlines(keepends=True)
        new_lines = new_text.splitlines(keepends=True)

        diff = list(difflib.unified_diff(
            old_lines,
            new_lines,
            fromfile=label_old,
            tofile=label_new,
            lineterm=""
        ))

        diff_str = "\n".join(diff)
        lines_added = sum(1 for line in diff if line.startswith("+") and not line.startswith("+++"))
        lines_removed = sum(1 for line in diff if line.startswith("-") and not line.startswith("---"))

        return diff_str, lines_added, lines_removed

    async def fetch_cisco_config_ssh(
        self,
        ip: str,
        username: str,
        password: str,
        port: int = 22,
        secret: Optional[str] = None,
        timeout: float = 25.0
    ) -> Tuple[bool, str, str]:
        """
        Obtiene la configuración activa (running-config) de un equipo Cisco vía SSH o Telnet usando Netmiko.
        """
        def _exec():
            driver_type = "cisco_ios_telnet" if int(port) == 23 else "cisco_ios"
            proto_name = "Telnet" if int(port) == 23 else "SSH"
            try:
                from netmiko import ConnectHandler
                from netmiko.exceptions import (
                    NetmikoAuthenticationException,
                    NetmikoTimeoutException,
                )

                device_params = {
                    "device_type": driver_type,
                    "host": ip,
                    "username": username,
                    "password": password,
                    "port": int(port),
                    "secret": secret or password,
                    "conn_timeout": timeout,
                    "auth_timeout": timeout,
                    "fast_cli": False,
                }

                with ConnectHandler(**device_params) as net_connect:
                    # Elevar a modo privilegiado (#) si se cuenta con clave secret o prompt en modo usuario (>)
                    if secret or not net_connect.check_enable_mode():
                        try:
                            net_connect.enable()
                        except Exception as e:
                            logger.warning(f"Aviso de elevación enable en {ip}: {e}")

                    # show running-config con timeout extendido para configs de switches grandes
                    output = net_connect.send_command("show running-config", read_timeout=90)
                    if output and len(output.strip()) > 50 and ("version" in output.lower() or "hostname" in output.lower() or "interface" in output.lower()):
                        return True, output, f"Captura exitosa vía Netmiko ({proto_name})"
                    elif output and len(output.strip()) > 20:
                        return True, output, f"Captura completada vía Netmiko ({proto_name})"
                    else:
                        return False, "", f"Respuesta vacía o incompleta de {ip} vía {proto_name}."

            except Exception as e:
                err_str = str(e)
                if "Authentication" in err_str or "password" in err_str.lower():
                    return False, "", f"Fallo de autenticación {proto_name} en {ip}:{port}. Verifique usuario, contraseña o enable secret."
                elif "timed out" in err_str.lower() or "timeout" in err_str.lower():
                    return False, "", f"Tiempo de espera agotado al conectar por {proto_name} a {ip}:{port} (equipo inaccesible o puerto cerrado)."
                return False, "", f"Error {proto_name} ({ip}:{port}): {err_str}"

        return await asyncio.to_thread(_exec)

    async def fetch_pfsense_config_ssh(
        self,
        ip: str,
        username: str,
        password: str,
        port: int = 22,
        timeout: float = 20.0
    ) -> Tuple[bool, str, str]:
        """
        Obtiene el archivo config.xml de un firewall pfSense vía SSH/SFTP.
        """
        def _exec():
            try:
                import paramiko
                ssh = paramiko.SSHClient()
                ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
                ssh.connect(ip, port=port, username=username, password=password, timeout=timeout)
                stdin, stdout, stderr = ssh.exec_command("cat /cf/conf/config.xml")
                cfg_data = stdout.read().decode("utf-8", errors="ignore")
                err_data = stderr.read().decode("utf-8", errors="ignore")
                ssh.close()
                if cfg_data and "<pfsense>" in cfg_data:
                    return True, cfg_data, "Captura exitosa config.xml pfSense"
                return False, "", f"Salida inválida de pfSense: {err_data}"
            except Exception as e:
                return False, "", f"Fallo SSH pfSense ({ip}): {e}"

        return await asyncio.to_thread(_exec)

    def save_device_backup(
        self,
        raw_config: str,
        device_name: str,
        device_ip: str,
        device_type: str = "cisco_switch",
        network_device_id: Optional[int] = None,
        snmp_device_id: Optional[int] = None,
        captured_by: str = "cron",
        notes: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Procesa, normaliza, compara y persiste la configuración del dispositivo.
        Detecta si es línea base, modificación o reversión a versión histórica.
        """
        self._ensure_db()
        norm_config = self.normalize_config(raw_config, device_type)
        new_hash = self.calculate_hash(norm_config)
        size_bytes = len(norm_config.encode("utf-8"))
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        with self.db.cursor() as cur:
            # Buscar último respaldo exitoso de este dispositivo
            query_parts = []
            params = []
            if snmp_device_id:
                query_parts.append("snmp_device_id = %s")
                params.append(snmp_device_id)
            elif network_device_id:
                query_parts.append("network_device_id = %s")
                params.append(network_device_id)
            else:
                query_parts.append("device_ip = %s")
                params.append(device_ip)

            where_clause = " OR ".join(query_parts)
            cur.execute(
                f"""
                SELECT id, config_hash, config_text, captured_at, status
                FROM device_configurations
                WHERE ({where_clause}) AND status = 'success'
                ORDER BY captured_at DESC
                LIMIT 1
                """,
                tuple(params)
            )
            last_backup = cur.fetchone()

            # Caso 1: Sin respaldo previo -> Línea Base Inicial
            if not last_backup:
                cur.execute(
                    """
                    INSERT INTO device_configurations
                    (network_device_id, snmp_device_id, device_name, device_ip, device_type,
                     config_text, config_hash, config_size_bytes, captured_at, captured_by, status, notes, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'success', %s, %s, %s)
                    """,
                    (
                        network_device_id, snmp_device_id, device_name, device_ip, device_type,
                        norm_config, new_hash, size_bytes, now, captured_by, notes, now, now
                    )
                )
                new_id = cur.lastrowid

                # Registrar log inicial
                cur.execute(
                    """
                    INSERT INTO config_change_logs
                    (device_configuration_id, previous_config_id, network_device_id, snmp_device_id,
                     change_type, diff_summary, diff_unified, lines_added, lines_removed, detected_at, alerted, created_at, updated_at)
                    VALUES (%s, NULL, %s, %s, 'initial', %s, NULL, %s, 0, %s, 0, %s, %s)
                    """,
                    (
                        new_id, network_device_id, snmp_device_id,
                        f"Línea base inicial registrada ({size_bytes} bytes)",
                        norm_config.count("\n"), now, now, now
                    )
                )

                logger.info(f"💾 Línea base creada para {device_name} ({device_ip}) - ID #{new_id}")
                return {
                    "action": "initial_baseline",
                    "changed": False,
                    "configuration_id": new_id,
                    "hash": new_hash,
                    "size_bytes": size_bytes,
                    "message": "Línea base inicial establecida con éxito."
                }

            # Caso 2: El hash coincide con el último respaldo -> Sin cambios
            if last_backup["config_hash"] == new_hash:
                logger.info(f"✅ Sin cambios en {device_name} ({device_ip}) - Hash: {new_hash[:12]}...")
                return {
                    "action": "no_change",
                    "changed": False,
                    "configuration_id": last_backup["id"],
                    "hash": new_hash,
                    "size_bytes": size_bytes,
                    "message": "La configuración no ha sufrido modificaciones."
                }

            # Caso 3: Cambio detectado -> Guardar nueva versión y generar diff
            prev_id = last_backup["id"]
            prev_text = last_backup["config_text"] or ""
            prev_date = last_backup["captured_at"].strftime("%d/%m/%Y %H:%M") if last_backup.get("captured_at") else "Previa"

            diff_unified, lines_added, lines_removed = self.generate_unified_diff(
                prev_text,
                norm_config,
                label_old=f"{device_name} ({prev_date})",
                label_new=f"{device_name} ({now})"
            )

            # Verificar si este hash corresponde a una versión histórica previa (Reversión)
            cur.execute(
                f"""
                SELECT id, captured_at FROM device_configurations
                WHERE ({where_clause}) AND config_hash = %s AND id != %s
                LIMIT 1
                """,
                tuple(params) + (new_hash, prev_id)
            )
            reverted_row = cur.fetchone()
            change_type = "reverted" if reverted_row else "modified"
            diff_summary = (
                f"Reversión a configuración del {reverted_row['captured_at']} (+{lines_added}/-{lines_removed})"
                if reverted_row else
                f"Configuración modificada: +{lines_added} líneas añadidas, -{lines_removed} líneas eliminadas"
            )

            cur.execute(
                """
                INSERT INTO device_configurations
                (network_device_id, snmp_device_id, device_name, device_ip, device_type,
                 config_text, config_hash, config_size_bytes, captured_at, captured_by, status, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'success', %s, %s, %s)
                """,
                (
                    network_device_id, snmp_device_id, device_name, device_ip, device_type,
                    norm_config, new_hash, size_bytes, now, captured_by, notes, now, now
                )
            )
            new_id = cur.lastrowid

            # Insertar registro detallado de cambio
            cur.execute(
                """
                INSERT INTO config_change_logs
                (device_configuration_id, previous_config_id, network_device_id, snmp_device_id,
                 change_type, diff_summary, diff_unified, lines_added, lines_removed, detected_at, alerted, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 0, %s, %s)
                """,
                (
                    new_id, prev_id, network_device_id, snmp_device_id,
                    change_type, diff_summary, diff_unified, lines_added, lines_removed, now, now, now
                )
            )
            log_id = cur.lastrowid

            logger.warning(f"⚠️ CAMBIO DETECTADO en {device_name} ({device_ip}): {diff_summary} (Log #{log_id})")

            return {
                "action": change_type,
                "changed": True,
                "configuration_id": new_id,
                "previous_id": prev_id,
                "change_log_id": log_id,
                "hash": new_hash,
                "lines_added": lines_added,
                "lines_removed": lines_removed,
                "diff_summary": diff_summary,
                "diff_unified": diff_unified,
                "message": diff_summary
            }

    def record_backup_failure(
        self,
        device_name: str,
        device_ip: str,
        device_type: str,
        error_message: str,
        network_device_id: Optional[int] = None,
        snmp_device_id: Optional[int] = None,
        captured_by: str = "cron"
    ):
        """Registra un intento fallido de respaldo para trazabilidad operativa."""
        self._ensure_db()
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        with self.db.cursor() as cur:
            cur.execute(
                """
                INSERT INTO device_configurations
                (network_device_id, snmp_device_id, device_name, device_ip, device_type,
                 config_text, config_hash, config_size_bytes, captured_at, captured_by, status, error_message, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, '', '', 0, %s, %s, 'failed', %s, %s, %s)
                """,
                (
                    network_device_id, snmp_device_id, device_name, device_ip, device_type,
                    now, captured_by, error_message[:500], now, now
                )
            )

    async def backup_target_device(self, target: Dict[str, Any], captured_by: str = "cron") -> Dict[str, Any]:
        """Ejecuta el respaldo de un dispositivo de red según sus credenciales y tipo."""
        ip = target.get("ip_address") or target.get("ip")
        name = target.get("name") or ip
        dev_type = target.get("device_type", "cisco_switch")
        snmp_id = target.get("snmp_device_id") or (target.get("id") if "snmp_version" in target else None)
        net_id = target.get("network_device_id") or (target.get("id") if "access_type" in target else None)

        ssh_user = target.get("ssh_username")
        raw_pass_enc = target.get("ssh_password_encrypted")
        raw_sec_enc = target.get("ssh_enable_secret_encrypted")

        ssh_pass = target.get("ssh_password_decrypted") or (decrypt_laravel_string(raw_pass_enc) if raw_pass_enc else "") or target.get("ssh_password") or ""
        ssh_secret = target.get("ssh_enable_secret_decrypted") or (decrypt_laravel_string(raw_sec_enc) if raw_sec_enc else "") or target.get("ssh_enable_secret") or None
        ssh_port = int(target.get("access_port") or target.get("ssh_port") or 22)

        if not ssh_user or not ssh_pass:
            err = f"Dispositivo '{name}' ({ip}) no cuenta con credenciales SSH (usuario y contraseña) configuradas."
            logger.warning(f"⚠️ {err}")
            self.record_backup_failure(name, ip, dev_type, err, net_id, snmp_id, captured_by)
            return {"action": "failed", "changed": False, "error": err, "device": name}

        # Si el tipo es Cisco
        if "cisco" in dev_type.lower() or dev_type in ("router", "switch", "cisco_switch", "cisco_router"):
            ok, cfg_text, err = await self.fetch_cisco_config_ssh(ip, ssh_user, ssh_pass, port=ssh_port, secret=ssh_secret)
            if ok:
                return self.save_device_backup(
                    raw_config=cfg_text,
                    device_name=name,
                    device_ip=ip,
                    device_type="cisco_switch" if "switch" in dev_type.lower() else "cisco_router",
                    network_device_id=net_id,
                    snmp_device_id=snmp_id,
                    captured_by=captured_by
                )
            else:
                self.record_backup_failure(name, ip, dev_type, err, net_id, snmp_id, captured_by)
                return {"action": "failed", "changed": False, "error": err, "device": name}

        # Si es pfSense
        elif "pfsense" in dev_type.lower() or dev_type == "firewall":
            ok, cfg_text, err = await self.fetch_pfsense_config_ssh(ip, ssh_user, ssh_pass, port=ssh_port)
            if ok:
                return self.save_device_backup(
                    raw_config=cfg_text,
                    device_name=name,
                    device_ip=ip,
                    device_type="pfsense",
                    network_device_id=net_id,
                    snmp_device_id=snmp_id,
                    captured_by=captured_by
                )
            else:
                self.record_backup_failure(name, ip, dev_type, err, net_id, snmp_id, captured_by)
                return {"action": "failed", "changed": False, "error": err, "device": name}

        return {"action": "skipped", "changed": False, "error": "Tipo de dispositivo no soportado", "device": name}

    def backup_network_device_by_id(self, net_id: int, captured_by: str = "manual") -> Dict[str, Any]:
        """Carga y respalda un dispositivo desde monitored_network_devices."""
        self._ensure_db()
        with self.db.cursor() as cur:
            cur.execute(
                "SELECT id, name, ip, model, access_type, access_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
                "FROM monitored_network_devices WHERE id = %s",
                (net_id,)
            )
            dev = cur.fetchone()
        if not dev:
            return {"action": "failed", "error": f"Dispositivo #{net_id} no encontrado."}

        dev_type = "cisco_router" if "router" in ((dev.get("name") or "") + " " + (dev.get("model") or "")).lower() else "cisco_switch"
        dev["device_type"] = dev_type
        dev["network_device_id"] = dev["id"]
        return asyncio.run(self.backup_target_device(dev, captured_by=captured_by))

    def backup_snmp_device_by_id(self, snmp_id: int, captured_by: str = "manual") -> Dict[str, Any]:
        """Carga y respalda un dispositivo desde snmp_devices."""
        self._ensure_db()
        with self.db.cursor() as cur:
            cur.execute(
                "SELECT id, name, ip_address, device_type, ssh_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
                "FROM snmp_devices WHERE id = %s",
                (snmp_id,)
            )
            dev = cur.fetchone()
        if not dev:
            return {"action": "failed", "error": f"Dispositivo SNMP #{snmp_id} no encontrado."}

        dev["snmp_device_id"] = dev["id"]
        return asyncio.run(self.backup_target_device(dev, captured_by=captured_by))

    def backup_all_configured_devices(self, captured_by: str = "cron") -> List[Dict[str, Any]]:
        """Respaldar todos los equipos de red que tengan credenciales SSH configuradas."""
        self._ensure_db()
        results = []
        with self.db.cursor() as cur:
            cur.execute(
                "SELECT id, name, ip, model, access_type, access_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
                "FROM monitored_network_devices WHERE is_active = 1 AND ssh_username IS NOT NULL AND ssh_username != ''"
            )
            net_devices = cur.fetchall()

            cur.execute(
                "SELECT id, name, ip_address, device_type, ssh_port, ssh_username, ssh_password_encrypted, ssh_enable_secret_encrypted "
                "FROM snmp_devices WHERE is_active = 1 AND ssh_username IS NOT NULL AND ssh_username != ''"
            )
            snmp_devices = cur.fetchall()

        for d in net_devices:
            dev_type = "cisco_router" if "router" in ((d.get("name") or "") + " " + (d.get("model") or "")).lower() else "cisco_switch"
            d["device_type"] = dev_type
            d["network_device_id"] = d["id"]
            res = asyncio.run(self.backup_target_device(d, captured_by=captured_by))
            results.append(res)

        for d in snmp_devices:
            d["snmp_device_id"] = d["id"]
            res = asyncio.run(self.backup_target_device(d, captured_by=captured_by))
            results.append(res)

        return results

    def generate_simulated_cisco_config(self, hostname: str, vlan_count: int = 4, extra_desc: str = "") -> str:
        """Genera una configuración Cisco IOS sintética y realista para entornos de prueba y certificación."""
        dt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cfg = f"""!
! Last configuration change at {dt} by admin
! NVRAM config last updated at {dt} by admin
!
version 15.2
no service pad
service timestamps debug datetime msec
service timestamps log datetime msec
service password-encryption
!
hostname {hostname}
!
boot-start-marker
boot-end-marker
!
vrf definition Mgmt-intf
 address-family ipv4
 exit-address-family
!
enable secret 5 $1$mERr$hx5rVt7rPNoS4wqbXKX7m0
!
username admin privilege 15 secret 5 $1$mERr$hx5rVt7rPNoS4wqbXKX7m0
aaa new-model
aaa authentication login default local
aaa authorization exec default local
!
ip domain-name empresa.gob.ve
ip name-server 10.20.0.1
ip name-server 10.20.0.2
!
spanning-tree mode rapid-pvst
spanning-tree extend system-id
!
"""
        for v in range(1, vlan_count + 1):
            vid = v * 10
            cfg += f"vlan {vid}\n name VLAN_DATOS_{vid}\n!\n"

        cfg += f"""interface GigabitEthernet0/0
 description Enlace Troncal Uplink {extra_desc}
 switchport mode trunk
 switchport trunk allowed vlan 10,20,30,40
!
interface GigabitEthernet0/1
 description Servidor Monitoreo Valle Seco (10.20.23.221)
 switchport mode access
 switchport access vlan 10
 spanning-tree portfast
!
interface Vlan10
 description Segmento de Gestion Administrativa
 ip address 10.20.23.1 255.255.255.0
 no shutdown
!
ip default-gateway 10.20.23.254
ip http server
ip http secure-server
!
snmp-server community c0rp03l3c RO
snmp-server location Planta Valle Seco
snmp-server contact britojq@gmail.com
snmp-server enable traps
!
line con 0
 exec-timeout 15 0
 stopbits 1
line vty 0 4
 transport input ssh
 exec-timeout 15 0
line vty 5 15
 transport input ssh
!
end
"""
        return cfg

    def run_simulated_seed(self) -> List[Dict[str, Any]]:
        """
        Puebla la base de datos con respaldos y cambios diferenciales realistas
        sobre los dispositivos de red existentes para certificar visualmente la Fase 5.
        """
        self._ensure_db()
        results = []
        with self.db.cursor() as cur:
            cur.execute("SELECT id, name, ip, model FROM monitored_network_devices WHERE is_active = 1 LIMIT 5")
            devices = cur.fetchall()

        if not devices:
            # Fallback con dispositivos virtuales
            devices = [
                {"id": 1, "name": "SWITCH_CORE_VALLE_SECO", "ip": "10.20.23.1", "model": "Cisco Catalyst 2960-X"},
                {"id": 2, "name": "ROUTER_BORDE_CENCAR", "ip": "10.20.0.1", "model": "Cisco ISR 4331"},
                {"id": 3, "name": "FIREWALL_PFSENSE_VS", "ip": "10.20.23.254", "model": "pfSense Netgate 6100"},
            ]

        for d in devices:
            d_name = d.get("name") or "Switch"
            d_ip = d.get("ip") or "10.20.23.1"
            d_id = d.get("id")

            # 1. Crear línea base inicial
            cfg_base = self.generate_simulated_cisco_config(d_name, vlan_count=3)
            r1 = self.save_device_backup(
                raw_config=cfg_base,
                device_name=d_name,
                device_ip=d_ip,
                device_type="cisco_switch",
                network_device_id=d_id,
                captured_by="manual",
                notes="Respaldo inicial de calibración"
            )
            results.append(r1)

            # 2. Generar una modificación con VLAN adicional para crear historial de diff
            if d_id in (1, devices[0].get("id")):
                time.sleep(0.05)
                cfg_mod = self.generate_simulated_cisco_config(
                    d_name,
                    vlan_count=5,
                    extra_desc="[REDUNDANCIA FIBRA OPTICA ACTIVA]"
                )
                r2 = self.save_device_backup(
                    raw_config=cfg_mod,
                    device_name=d_name,
                    device_ip=d_ip,
                    device_type="cisco_switch",
                    network_device_id=d_id,
                    captured_by="cron",
                    notes="Actualización programada: nuevas VLANs y enlace redundante"
                )
                results.append(r2)

        return results

    def get_backups_summary(self) -> Dict[str, Any]:
        """Calcula estadísticas generales de respaldos para HUD del portal."""
        self._ensure_db()
        with self.db.cursor() as cur:
            cur.execute("SELECT COUNT(DISTINCT device_ip) as total_devices FROM device_configurations WHERE status = 'success'")
            tot_dev = cur.fetchone()["total_devices"]

            cur.execute("SELECT COUNT(*) as total_backups FROM device_configurations WHERE status = 'success'")
            tot_bkups = cur.fetchone()["total_backups"]

            cur.execute("SELECT COUNT(*) as backups_today FROM device_configurations WHERE DATE(captured_at) = CURDATE()")
            bkups_today = cur.fetchone()["backups_today"]

            cur.execute("SELECT COUNT(*) as total_changes FROM config_change_logs WHERE change_type = 'modified'")
            tot_changes = cur.fetchone()["total_changes"]

            cur.execute("SELECT IFNULL(SUM(config_size_bytes), 0) as total_bytes FROM device_configurations")
            tot_bytes = cur.fetchone()["total_bytes"]

        return {
            "total_devices": tot_dev,
            "total_backups": tot_bkups,
            "backups_today": bkups_today,
            "total_changes": tot_changes,
            "total_size_bytes": tot_bytes,
            "total_size_formatted": f"{round(tot_bytes / 1024, 1)} KB" if tot_bytes < 1048576 else f"{round(tot_bytes / 1048576, 2)} MB"
        }

    def list_latest_backups(self, limit: int = 30) -> List[Dict[str, Any]]:
        """Lista los últimos respaldos con detalles de cambio."""
        self._ensure_db()
        with self.db.cursor() as cur:
            cur.execute(
                f"""
                SELECT c.id, c.device_name, c.device_ip, c.device_type, c.config_hash,
                       c.config_size_bytes, c.captured_at, c.captured_by, c.status, c.notes,
                       l.change_type, l.diff_summary, l.lines_added, l.lines_removed
                FROM device_configurations c
                LEFT JOIN config_change_logs l ON l.device_configuration_id = c.id
                ORDER BY c.captured_at DESC
                LIMIT {limit}
                """
            )
            return cur.fetchall()

    def get_diff_for_configuration(self, config_id: int) -> Optional[Dict[str, Any]]:
        """Obtiene el diff unificado asociado a una configuración específica."""
        self._ensure_db()
        with self.db.cursor() as cur:
            cur.execute(
                """
                SELECT l.*, c.device_name, c.device_ip, c.captured_at,
                       p.captured_at as previous_captured_at
                FROM config_change_logs l
                JOIN device_configurations c ON c.id = l.device_configuration_id
                LEFT JOIN device_configurations p ON p.id = l.previous_config_id
                WHERE l.device_configuration_id = %s
                LIMIT 1
                """,
                (config_id,)
            )
            return cur.fetchone()


# ==============================================================================
# CLI Y PUNTO DE ENTRADA
# ==============================================================================
def main():
    parser = argparse.ArgumentParser(description="Gestor de Respaldos de Configuraciones de Red (@IA_ValleSeco_bot)")
    parser.add_argument("--target-net", type=int, help="Ejecuta respaldo para un dispositivo de red por ID")
    parser.add_argument("--target-snmp", type=int, help="Ejecuta respaldo para un dispositivo SNMP por ID")
    parser.add_argument("--backup-all", action="store_true", help="Ejecuta respaldo de todos los equipos con credenciales SSH")
    parser.add_argument("--seed", action="store_true", help="Genera línea base y cambios simulados para pruebas de laboratorio")
    parser.add_argument("--list", action="store_true", help="Lista los respaldos más recientes")
    parser.add_argument("--diff", type=int, help="Muestra el diff unificado para una configuración específica por ID")
    parser.add_argument("--stats", action="store_true", help="Muestra estadísticas de respaldos")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")

    args = parser.parse_args()
    mgr = ConfigBackupManager()

    if args.target_net:
        res = mgr.backup_network_device_by_id(args.target_net, captured_by="manual")
        if args.json:
            print(json.dumps(res, default=str))
        else:
            status_ico = "✅" if res.get("action") in ("initial_baseline", "no_change", "modified", "reverted") else "❌"
            print(f"{status_ico} Respaldo de Dispositivo #{args.target_net}: {res.get('message') or res.get('error') or res.get('action')}")
        sys.exit(0 if res.get("action") != "failed" else 1)

    if args.target_snmp:
        res = mgr.backup_snmp_device_by_id(args.target_snmp, captured_by="manual")
        if args.json:
            print(json.dumps(res, default=str))
        else:
            status_ico = "✅" if res.get("action") in ("initial_baseline", "no_change", "modified", "reverted") else "❌"
            print(f"{status_ico} Respaldo de Dispositivo SNMP #{args.target_snmp}: {res.get('message') or res.get('error') or res.get('action')}")
        sys.exit(0 if res.get("action") != "failed" else 1)

    if args.backup_all:
        results = mgr.backup_all_configured_devices(captured_by="cron")
        if args.json:
            print(json.dumps(results, default=str))
        else:
            print(f"✅ Ciclo de respaldo finalizado: {len(results)} equipos procesados.")
        sys.exit(0)

    if args.seed:
        res = mgr.run_simulated_seed()
        if args.json:
            print(json.dumps(res, default=str))
        else:
            print(f"✅ Se generaron {len(res)} versiones de respaldo y auditoría diferencial.")
        sys.exit(0)

    if args.diff:
        diff_info = mgr.get_diff_for_configuration(args.diff)
        if not diff_info:
            print(f"❌ No se encontró diff para la configuración #{args.diff}")
            sys.exit(1)
        if args.json:
            print(json.dumps(diff_info, default=str))
        else:
            print(f"📋 DIFF PARA {diff_info.get('device_name')} (#{args.diff}):")
            print(f"   Tipo: {diff_info.get('change_type')} | +{diff_info.get('lines_added')}/-{diff_info.get('lines_removed')}")
            print("=" * 60)
            print(diff_info.get("diff_unified") or "Sin cambios registrados con la versión previa.")
        sys.exit(0)

    if args.stats:
        stats = mgr.get_backups_summary()
        if args.json:
            print(json.dumps(stats, default=str))
        else:
            print("📊 ESTADÍSTICAS DE RESPALDOS DE CONFIGURACIÓN:")
            print(f"• Equipos respaldados: {stats['total_devices']}")
            print(f"• Total respaldos: {stats['total_backups']}")
            print(f"• Respaldos hoy: {stats['backups_today']}")
            print(f"• Modificaciones detectadas: {stats['total_changes']}")
            print(f"• Espacio total: {stats['total_size_formatted']}")
        sys.exit(0)

    # Por defecto o con --list: listar
    backups = mgr.list_latest_backups()
    if args.json:
        print(json.dumps(backups, default=str))
    else:
        print(f"💾 ÚLTIMOS RESPALDOS REGISTRADOS ({len(backups)}):")
        print("-" * 75)
        for b in backups[:15]:
            ch_str = f" [{b.get('change_type', '').upper()}]" if b.get('change_type') else ""
            print(f"#{b['id']:<4} {b.get('device_name', 'N/A')[:25]:<26} {b.get('device_ip', ''):<15} {b.get('config_size_bytes', 0)}B {ch_str} {b.get('captured_at')}")


if __name__ == "__main__":
    main()
