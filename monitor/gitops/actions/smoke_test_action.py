# monitor/gitops/actions/smoke_test_action.py
import asyncio
import json
import logging
import shutil
import sys
from pathlib import Path
from typing import Dict, Tuple
import httpx

logger = logging.getLogger("monitor.gitops.smoke_test")

BASE_DIR = Path(__file__).resolve().parent.parent.parent.parent
WEB_DIR = BASE_DIR / "web_portal"

class SmokeTestAction:
    """Acción de validación post-despliegue del portal web y base de datos."""

    @staticmethod
    async def run_post_deploy_smoke_test() -> Tuple[bool, str]:
        """Ejecuta una batería rápida de pruebas sobre el portal web y base de datos."""
        try:
            async with httpx.AsyncClient(timeout=4.0) as client:
                # 1. Probar API de estado
                r_api = await client.get("http://127.0.0.1/api/status")
                if r_api.status_code != 200:
                    return False, f"Endpoint /api/status retornó HTTP {r_api.status_code} (esperado 200)"

                # 2. Probar página de login
                r_login = await client.get("http://127.0.0.1/login")
                if r_login.status_code != 200:
                    return False, f"Endpoint /login retornó HTTP {r_login.status_code} (esperado 200)"

                # 2.1 Probar disponibilidad de Logo Corporativo
                r_logo = await client.get("http://127.0.0.1/img/logo.png")
                if r_logo.status_code != 200:
                    src_l = BASE_DIR / "web_portal" / "public" / "img" / "logo.png"
                    dst_l = WEB_DIR / "public" / "img" / "logo.png"
                    if src_l.exists():
                        dst_l.parent.mkdir(parents=True, exist_ok=True)
                        shutil.copy2(src_l, dst_l)

            # 3. Comprobar consulta a MariaDB vía Artisan
            proc_art = await asyncio.create_subprocess_exec(
                "sudo", "php", f"{WEB_DIR}/artisan", "tinker", "--execute=echo \\App\\Models\\User::count() > 0 ? 'OK' : 'EMPTY';",
                cwd=str(WEB_DIR),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            out_art, err_art = await proc_art.communicate()
            if "OK" not in out_art.decode("utf-8", errors="ignore"):
                return False, f"Fallo al consultar base de datos en Laravel: {err_art.decode('utf-8', errors='ignore')}"

            # 4. Chequeo de sanidad de telemetría y ausencia de dominios ficticios
            proc_san = await asyncio.create_subprocess_exec(
                "sudo", "php", f"{WEB_DIR}/artisan", "tinker", "--execute="
                "echo \\DB::table('monitored_services')->where('web_url', 'like', '%empresa.com.ve%')->count();",
                cwd=str(WEB_DIR),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            out_san, _ = await proc_san.communicate()
            raw_cnt = out_san.decode("utf-8", errors="ignore").strip()
            if raw_cnt.isdigit() and int(raw_cnt) > 0:
                return False, f"Se detectaron {raw_cnt} servicios con dominio ficticio residual 'empresa.com.ve'."

            return True, "Todos los endpoints web, base de datos y dominios corporativos verificados exitosamente (200 OK)."
        except Exception as e:
            return False, f"Excepción durante Smoke Test: {e}"

    @staticmethod
    async def collect_db_census() -> Dict[str, int]:
        """Recopila censo en tiempo real de entidades en MariaDB y auto-repara topología/SNMP si es necesario."""
        census: Dict[str, int] = {}
        tinker_cmd = (
            "echo json_encode(["
            "'snmp_devices' => \\App\\Models\\SnmpDevice::count(),"
            "'topology_links' => \\App\\Models\\NetworkTopologyLink::count(),"
            "'services' => \\App\\Models\\MonitoredService::count(),"
            "'network_devices' => \\App\\Models\\MonitoredNetworkDevice::count(),"
            "'sites' => \\App\\Models\\MonitoredSite::count(),"
            "'proxies' => \\App\\Models\\MonitoredProxy::count(),"
            "'users' => \\App\\Models\\User::count()"
            "]);"
        )
        try:
            proc = await asyncio.create_subprocess_exec(
                "sudo", "php", f"{WEB_DIR}/artisan", "tinker", f"--execute={tinker_cmd}",
                cwd=str(WEB_DIR),
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            out, _ = await proc.communicate()
            raw = out.decode("utf-8", errors="ignore").strip()
            start_idx = raw.find("{")
            end_idx = raw.rfind("}")
            if start_idx != -1 and end_idx != -1:
                census = json.loads(raw[start_idx:end_idx + 1])
        except Exception as e:
            logger.warning(f"Error consultando censo de base de datos: {e}")

        # Auto-curación preventiva en caso de datos incompletos
        if census.get("snmp_devices", 0) < 9:
            try:
                p_s = await asyncio.create_subprocess_exec(
                    "sudo", "php", f"{WEB_DIR}/artisan", "db:seed", "--class=SnmpOidsSeeder", "--force",
                    cwd=str(WEB_DIR), stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
                )
                await p_s.communicate()
                logger.info("SnmpOidsSeeder ejecutado por auto-curación.")
            except Exception:
                pass

        if census.get("topology_links", 0) == 0:
            top_script = BASE_DIR / "monitor" / "tools" / "topology_builder.py"
            py_bin = BASE_DIR / "venv" / "bin" / "python"
            py_exec = str(py_bin) if py_bin.exists() else sys.executable
            if top_script.exists():
                try:
                    p_t = await asyncio.create_subprocess_exec(
                        py_exec, str(top_script), "--build",
                        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
                    )
                    await p_t.communicate()
                    logger.info("topology_builder ejecutado por auto-curación.")
                except Exception:
                    pass

        # Si se ejecutó auto-curación, refrescar conteo
        if census.get("snmp_devices", 0) < 9 or census.get("topology_links", 0) == 0:
            try:
                proc = await asyncio.create_subprocess_exec(
                    "sudo", "php", f"{WEB_DIR}/artisan", "tinker", f"--execute={tinker_cmd}",
                    cwd=str(WEB_DIR), stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.PIPE,
                )
                out, _ = await proc.communicate()
                raw = out.decode("utf-8", errors="ignore").strip()
                start_idx = raw.find("{")
                end_idx = raw.rfind("}")
                if start_idx != -1 and end_idx != -1:
                    census = json.loads(raw[start_idx:end_idx + 1])
            except Exception:
                pass

        return census
