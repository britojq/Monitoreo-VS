# monitor/sync/probes/network_probes.py
import asyncio
import logging
import re
import time
from typing import Any, Dict, Tuple
import httpx
from monitor.sync.probes.ssl_factory import SSL_PERMISSIVE_CTX
from monitor.sync.dtos.scan_dtos import WanQualityResult

logger = logging.getLogger("monitor.sync.probes")

async def check_ping(host: str, count: int = 2, timeout: float = 2.5) -> Tuple[bool, float]:
    """Realiza un ping ICMP asíncrono con tolerancia ante fluctuaciones WAN (2 paquetes, timeout 2s)."""
    if not host or host in ("0.0.0.0", "127.0.0.1"):
        return False, 0.0
    start = time.perf_counter()
    proc = await asyncio.create_subprocess_exec(
        "ping", "-c", str(count), "-W", "2", host,
        stdout=asyncio.subprocess.DEVNULL,
        stderr=asyncio.subprocess.DEVNULL
    )
    try:
        await asyncio.wait_for(proc.wait(), timeout=timeout + 1.0)
        elapsed = (time.perf_counter() - start) * 1000.0
        return (proc.returncode == 0), round(elapsed, 1)
    except asyncio.TimeoutError:
        try:
            proc.kill()
        except Exception:
            pass
        return False, 0.0

async def check_wan_quality(host: str, count: int = 5, timeout: float = 3.5) -> Dict[str, Any]:
    """Realiza un ping ICMP multiráfaga para calcular latencia, pérdida de paquetes y jitter (mdev)."""
    default_res = WanQualityResult(
        is_up=False,
        latency_ms=0.0,
        packet_loss_pct=100.0,
        jitter_ms=0.0,
        min_rtt_ms=0.0,
        max_rtt_ms=0.0,
        mdev_ms=0.0,
    ).to_dict()

    if not host or host.strip() in ("0.0.0.0", "NO CONFIGURADO"):
        return default_res

    try:
        proc = await asyncio.create_subprocess_exec(
            "ping", "-c", str(count), "-i", "0.2", "-W", "1", host,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.DEVNULL
        )
        try:
            stdout, _ = await asyncio.wait_for(proc.communicate(), timeout=timeout)
            out_str = stdout.decode("utf-8", errors="ignore")
        except asyncio.TimeoutError:
            try:
                proc.kill()
            except Exception:
                pass
            return default_res

        loss_m = re.search(r"(\d+(?:\.\d+)?)%\s+packet loss", out_str)
        rtt_m = re.search(r"rtt min/avg/max/mdev = ([\d\.]+)/([\d\.]+)/([\d\.]+)/([\d\.]+)", out_str)

        loss = float(loss_m.group(1)) if loss_m else 100.0
        if rtt_m:
            min_rtt, avg_rtt, max_rtt, mdev = map(float, rtt_m.groups())
        else:
            min_rtt = avg_rtt = max_rtt = mdev = 0.0

        is_ok = (loss < 100.0)
        return WanQualityResult(
            is_up=is_ok,
            latency_ms=round(avg_rtt, 2),
            packet_loss_pct=round(loss, 2),
            jitter_ms=round(mdev, 4),
            min_rtt_ms=round(min_rtt, 4),
            max_rtt_ms=round(max_rtt, 4),
            mdev_ms=round(mdev, 4),
        ).to_dict()
    except Exception as e:
        logger.warning(f"Error evaluando calidad WAN para {host}: {e}")
        return default_res

async def check_tcp_port(host: str, port: int, timeout: float = 4.0) -> Tuple[bool, float]:
    """Comprueba conexión TCP a un puerto específico con tolerancia de latencia WAN."""
    if not host or not port:
        return False, 0.0
    start = time.perf_counter()
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(host, port),
            timeout=timeout
        )
        writer.close()
        await writer.wait_closed()
        elapsed = (time.perf_counter() - start) * 1000.0
        return True, round(elapsed, 1)
    except Exception:
        return False, 0.0

async def check_web_service(url: str, timeout: float = 7.0) -> Tuple[bool, int, float]:
    """Comprueba un aplicativo web vía HTTP/HTTPS con soporte TLS 1.0+ legacy y follow_redirects."""
    if not url:
        return False, 0, 0.0
    start = time.perf_counter()
    try:
        async with httpx.AsyncClient(verify=SSL_PERMISSIVE_CTX, timeout=timeout, follow_redirects=True) as client:
            r = await client.get(url)
            elapsed = (time.perf_counter() - start) * 1000.0
            is_ok = (r.status_code in (200, 301, 302, 304, 307, 308, 401))
            return is_ok, r.status_code, round(elapsed, 1)
    except Exception:
        return False, 0, 0.0
