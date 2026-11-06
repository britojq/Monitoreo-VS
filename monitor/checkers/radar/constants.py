"""
Constantes de red, firmas de repositorios y utilidades de formateo.
"""
from typing import List

WINDOWS_UPDATE_DOMAINS: List[str] = [
    "windowsupdate.com",
    "update.microsoft.com",
    "delivery.mp.microsoft.com",
    "download.windowsupdate.com",
    "fe2.update.microsoft.com",
    "fe3.delivery.mp.microsoft.com",
    "tlu.dl.delivery.mp.microsoft.com",
    "dl.delivery.mp.microsoft.com",
    "do.dsp.mp.microsoft.com",
    "emdl.ws.microsoft.com",
    "ws.symantec.com",
    "download.microsoft.com",
    "msftconnecttest.com",
]

LINUX_REPO_DOMAINS: List[str] = [
    "deb.debian.org",
    "security.debian.org",
    "ftp.debian.org",
    "archive.ubuntu.com",
    "security.ubuntu.com",
    "ports.ubuntu.com",
    "mirror.centos.org",
    "repo.almalinux.org",
    "download.rockylinux.org",
    "mirror.fedoraproject.org",
    "archive.raspberrypi.org",
    "packages.microsoft.com",
    "download.docker.com",
    "repo.saltproject.io",
]


def format_bytes_human(bytes_val: int) -> str:
    """Convierte bytes a formato legible (KB, MB, GB)."""
    if bytes_val >= 1073741824:
        return f"{bytes_val / 1073741824:.2f} GB"
    if bytes_val >= 1048576:
        return f"{bytes_val / 1048576:.2f} MB"
    if bytes_val >= 1024:
        return f"{bytes_val / 1024:.2f} KB"
    return f"{bytes_val} B"
