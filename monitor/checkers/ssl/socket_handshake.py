"""
Manejador de contexto SSL y handshake de sockets permisivo para entornos corporativos y legacy.
"""
import logging
import socket
import ssl
from typing import Optional, Tuple
import warnings

logger = logging.getLogger("monitor.checkers.ssl.socket_handshake")


def create_permissive_ssl_context() -> ssl.SSLContext:
    """Crea un contexto SSL permisivo y compatible con servidores legacy y CAs internas."""
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    try:
        with warnings.catch_warnings():
            warnings.simplefilter("ignore", DeprecationWarning)
            ctx.minimum_version = ssl.TLSVersion.TLSv1
    except Exception:
        pass
    return ctx


def perform_ssl_handshake(
    hostname: str,
    port: int = 443,
    timeout: float = 5.0
) -> Tuple[Optional[bytes], Optional[str], Optional[Tuple[str, str, int]], Optional[str]]:
    """
    Establece conexión TCP + TLS con SNI.
    Retorna (der_cert_bytes, tls_version, cipher_tuple, error_msg).
    """
    ctx = create_permissive_ssl_context()
    try:
        with socket.create_connection((hostname, port), timeout=timeout) as sock:
            with ctx.wrap_socket(sock, server_hostname=hostname) as ssock:
                der_cert = ssock.getpeercert(binary_form=True)
                if not der_cert:
                    return None, None, None, "No se recibió certificado del servidor remoto."
                return der_cert, ssock.version(), ssock.cipher(), None
    except Exception as e:
        return None, None, None, str(e)
