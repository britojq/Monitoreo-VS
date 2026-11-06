# monitor/sync/probes/ssl_factory.py
import ssl

def create_permissive_ssl_context() -> ssl.SSLContext:
    """Crea un contexto SSL permisivo compatible con servidores legacy (TLS 1.0+, ciphers antiguos, autofirmados)."""
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    try:
        ctx.set_ciphers("DEFAULT@SECLEVEL=0")
    except Exception:
        pass
    try:
        ctx.minimum_version = ssl.TLSVersion.TLSv1
    except Exception:
        pass
    return ctx

SSL_PERMISSIVE_CTX = create_permissive_ssl_context()
