"""
Ejecutor de captura asíncrona de paquetes mediante tcpdump.
"""
import asyncio
import logging
from pathlib import Path

logger = logging.getLogger("monitor.checkers.analyzer.tcpdump_runner")


async def capture_packets(
    interface: str,
    pcap_path: Path,
    duration_seconds: int = 120
) -> int:
    """Captura tráfico de red con tcpdump durante el tiempo indicado (default 120s)."""
    pcap_path.parent.mkdir(parents=True, exist_ok=True)
    if pcap_path.exists():
        pcap_path.unlink()

    logger.info(f"Iniciando captura con tcpdump en '{interface}' durante {duration_seconds}s (máx 5000 paquetes)...")
    proc = await asyncio.create_subprocess_exec(
        "sudo", "tcpdump", "-i", interface, "-s", "0", "-c", "5000", "-w", str(pcap_path), "-nn", "-p",
        stdout=asyncio.subprocess.DEVNULL,
        stderr=asyncio.subprocess.DEVNULL
    )

    try:
        await asyncio.sleep(duration_seconds)
    finally:
        try:
            kill_proc = await asyncio.create_subprocess_exec(
                "sudo", "kill", "-2", str(proc.pid),
                stdout=asyncio.subprocess.DEVNULL,
                stderr=asyncio.subprocess.DEVNULL
            )
            await kill_proc.wait()
        except Exception:
            pass

        try:
            await asyncio.wait_for(proc.wait(), timeout=3.0)
        except Exception:
            try:
                proc.kill()
            except Exception:
                pass

    try:
        chmod_proc = await asyncio.create_subprocess_exec(
            "sudo", "chmod", "666", str(pcap_path),
            stdout=asyncio.subprocess.DEVNULL,
            stderr=asyncio.subprocess.DEVNULL
        )
        await chmod_proc.wait()
    except Exception:
        pass

    pkt_count = 0
    if pcap_path.exists() and pcap_path.stat().st_size > 0:
        try:
            cap_proc = await asyncio.create_subprocess_exec(
                "sudo", "tcpdump", "-r", str(pcap_path), "-nn",
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.DEVNULL
            )
            stdout, _ = await cap_proc.communicate()
            pkt_count = len(stdout.splitlines())
        except Exception as e:
            logger.error(f"Error al contar paquetes pcap: {e}")

    logger.info(f"Captura finalizada. Total paquetes: {pkt_count}")
    return pkt_count
