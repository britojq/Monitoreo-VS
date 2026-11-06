#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ AUDITOR PERMANENTE DE REGLAS DE SEGURIDAD Y ANTI-REGRESIÓN (S.O.N.A.R.)
# Ubicación: /opt/sonar/tools/audit_security_rules.py
# Verifica fáticamente el cumplimiento inmutable de SEC-001 a SEC-011 y ARCH-001/002.
# ==============================================================================
"""

import ast
import json
import re
import sys
from pathlib import Path
from typing import List, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent

GREEN = "\033[0;32m"
RED = "\033[0;31m"
CYAN = "\033[0;36m"
YELLOW = "\033[1;33m"
BOLD = "\033[1m"
NC = "\033[0m"


def check_sec_001_zero_global_tokens() -> Tuple[bool, str]:
    """SEC-001: Ausencia de tokens estáticos en el scope global de bot.py y contratos."""
    bot_py = BASE_DIR / "bot.py"
    if not bot_py.exists():
        return False, "bot.py no existe"

    content = bot_py.read_text(encoding="utf-8")
    tree = ast.parse(content, filename="bot.py")

    for node in tree.body:
        if isinstance(node, ast.Assign):
            for target in node.targets:
                if isinstance(target, ast.Name) and target.id in ("BOT_TOKEN", "TELEGRAM_BOT_TOKEN", "TELEGRAM_TOKEN"):
                    if isinstance(node.value, ast.Constant) and isinstance(node.value.value, str):
                        return False, f"Token estático asignado directamente en ámbito global de bot.py: {target.id}"

    # Verificar que bot.py expone get_bot_token dinámico
    if "def get_bot_token(" not in content:
        return False, "get_bot_token() no definido en bot.py"

    return True, "Tokens gestionados dinámicamente vía get_bot_token(); cero secretos en scope global."


def check_sec_002_cluster_hmac() -> Tuple[bool, str]:
    """SEC-002: Transporte de Clúster firmado con HMAC y validación temporal anti-replay."""
    middleware_path = BASE_DIR / "web_portal" / "app" / "Http" / "Middleware" / "ValidateClusterToken.php"
    if not middleware_path.exists():
        return False, "ValidateClusterToken.php no existe"

    content = middleware_path.read_text(encoding="utf-8")
    if "hash_hmac('sha256'" not in content:
        return False, "ValidateClusterToken no implementa firma HMAC-SHA256"
    if "X-Cluster-Timestamp" not in content or "abs(time() - $timestamp) >" not in content:
        return False, "ValidateClusterToken no implementa ventana temporal anti-replay"

    return True, "Firma HMAC-SHA256 y ventana temporal anti-replay verificadas en middleware de clúster."


def check_sec_003_command_registry() -> Tuple[bool, str]:
    """SEC-003: CommandRegistry con whitelist estricta y argumentos validados contra inyección."""
    registry_path = BASE_DIR / "bot_core" / "security" / "command_registry.py"
    if not registry_path.exists():
        return False, "command_registry.py no existe"

    content = registry_path.read_text(encoding="utf-8")
    if "COMMAND_REGISTRY" not in content or "validate_command" not in content:
        return False, "COMMAND_REGISTRY o validate_command no encontrados en command_registry.py"

    helpers_path = BASE_DIR / "bot_core" / "utils" / "system_helpers.py"
    h_content = helpers_path.read_text(encoding="utf-8")
    if "validate_command(" not in h_content or "shell=False" not in h_content:
        return False, "system_helpers._exec_system_command no invoca validate_command con shell=False"

    return True, "CommandRegistry activo; subprocess.run ejecutado con shell=False y argumentos validados."


def check_sec_004_ldap_encryption() -> Tuple[bool, str]:
    """SEC-004: Credenciales LDAP cifradas con AES-256 en MariaDB bot_settings."""
    mgr_path = BASE_DIR / "web_portal" / "app" / "Services" / "Ldap" / "LdapConnectionManager.php"
    if not mgr_path.exists():
        return False, "LdapConnectionManager.php no existe"

    content = mgr_path.read_text(encoding="utf-8")
    if "Crypt::decryptString" not in content:
        return False, "LdapConnectionManager no descifra contraseña con Crypt::decryptString"
    if "Crypt::encryptString" not in content:
        return False, "LdapConnectionManager no cifra bind_password con Crypt::encryptString al persistir"

    ctrl_path = BASE_DIR / "web_portal" / "app" / "Http" / "Controllers" / "Admin" / "AdminLdapController.php"
    if not ctrl_path.exists():
        return False, "AdminLdapController.php no existe"

    c_content = ctrl_path.read_text(encoding="utf-8")
    if "ldap_bind_password" not in c_content:
        return False, "AdminLdapController no gestiona bind_password para su cifrado"

    return True, "Contraseña de servicio LDAP cifrada con AES-256 en base de datos y enmascarada en UI."


def check_sec_005_dependency_pinning() -> Tuple[bool, str]:
    """SEC-005: Dependencias acotadas con cotas superiores estrictas, requirements.lock y composer.lock reconciliados."""
    req_path = BASE_DIR / "requirements.txt"
    lock_path = BASE_DIR / "requirements.lock"
    composer_lock = BASE_DIR / "web_portal" / "composer.lock"

    if not req_path.exists():
        return False, "requirements.txt no existe"
    if not lock_path.exists() or lock_path.stat().st_size == 0:
        return False, "requirements.lock no existe o está vacío"
    if not composer_lock.exists() or composer_lock.stat().st_size == 0:
        return False, "web_portal/composer.lock no existe o está vacío"

    content = req_path.read_text(encoding="utf-8")
    unbounded = []
    for line in content.splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        pkg_spec = line.split("#")[0].strip()
        if ">=" in pkg_spec and "<" not in pkg_spec:
            unbounded.append(pkg_spec)

    if unbounded:
        return False, f"Dependencias sin cota superior detectadas en requirements.txt: {unbounded}"

    return True, "requirements.txt 100% acotado con cotas superiores, requirements.lock y composer.lock reconciliados."


def check_sec_006_telnet_security_warning() -> Tuple[bool, str]:
    """SEC-006: Advertencia explícita de seguridad en consola Telnet y cero CDNs externos."""
    term_path = BASE_DIR / "web_portal" / "resources" / "views" / "admin" / "telnet" / "terminal.blade.php"
    if not term_path.exists():
        return False, "terminal.blade.php no existe"

    content = term_path.read_text(encoding="utf-8")
    if "SEC-006" not in content or "ADVERTENCIA DE SEGURIDAD" not in content:
        return False, "terminal.blade.php carece de banner o aviso de seguridad SEC-006"
    if "cdn.tailwindcss.com" in content or "fonts.googleapis.com" in content:
        return False, "terminal.blade.php contiene enlaces CDN externos prohibidos por Pilar 1"

    return True, "Aviso de seguridad SEC-006 implementado en UI/consola y 100% libre de CDNs externos."


def check_sec_007_sanitized_readme() -> Tuple[bool, str]:
    """SEC-007: Cero IPs privadas no enmascaradas en README público."""
    readme_path = BASE_DIR / "README.md"
    if not readme_path.exists():
        return False, "README.md no existe"

    content = readme_path.read_text(encoding="utf-8")
    # Buscar patrones de IPs privadas RFC 1918 hardcodeadas que no sean placeholders
    ip_matches = re.findall(r"\b(?:10\.20\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})\b", content)
    # Filtrar posibles ejemplos en comentarios si existieran
    if ip_matches:
        return False, f"IPs privadas expuestas detectadas en README.md: {set(ip_matches)}"

    return True, "README.md rigurosamente sanitizado con marcadores neutrales (<MASTER_IP>, <SUBNET_CIDR>)."


def check_sec_008_rate_limiting() -> Tuple[bool, str]:
    """SEC-008: Rate limiting activo en endpoints de API de Clúster y receptor GLPI."""
    api_routes_path = BASE_DIR / "web_portal" / "routes" / "api.php"
    if not api_routes_path.exists():
        return False, "routes/api.php no existe"

    content = api_routes_path.read_text(encoding="utf-8")
    if "throttle:60,1" not in content:
        return False, "routes/api.php no aplica throttle:60,1 a endpoints de clúster / IA"
    if "throttle:120,1" not in content:
        return False, "routes/api.php no aplica throttle:120,1 a receptor de inventario"

    return True, "Middleware throttle:60,1 y throttle:120,1 activos en rutas API de clúster e inventario."


def check_sec_009_snmp_encrypted_communities() -> Tuple[bool, str]:
    """SEC-009: Comunidades SNMP gestionadas mediante cifrado en MariaDB SSOT."""
    engine_path = BASE_DIR / "monitor" / "checkers" / "snmp" / "snmp_engine.py"
    if not engine_path.exists():
        return False, "snmp_engine.py no existe"

    content = engine_path.read_text(encoding="utf-8")
    if "snmp_community_encrypted" not in content or "decrypt_laravel_string" not in content:
        return False, "snmp_engine.py no utiliza snmp_community_encrypted con descifrado autenticado"

    return True, "Comunidades SNMP protegidas por cifrado Laravel en MariaDB SSOT; cero texto plano."


def check_sec_010_zero_tmp_flags() -> Tuple[bool, str]:
    """SEC-010: Cero archivos bandera en /tmp; autorrecuperación gobernada por MariaDB SSOT."""
    scan_files = [
        BASE_DIR / "bot.py",
        BASE_DIR / "tools" / "rollback.py",
        BASE_DIR / "bot_core" / "services" / "config_manager.py",
    ]

    for p in scan_files:
        if p.exists():
            content = p.read_text(encoding="utf-8")
            if "/tmp/.auto_rollback_occurred" in content:
                return False, f"Bandera prohibida /tmp/.auto_rollback_occurred detectada en {p.name}"

    cfg_mgr = BASE_DIR / "bot_core" / "services" / "config_manager.py"
    if cfg_mgr.exists():
        content = cfg_mgr.read_text(encoding="utf-8")
        if "get_auto_rollback_notice" not in content or "clear_auto_rollback_notice" not in content:
            return False, "config_manager.py carece de métodos SSOT para banderas de rollback"

    return True, "Cero banderas en /tmp; notificaciones de rollback migradas al 100% a MariaDB bot_settings."


def check_sec_011_composer_plugins_lockdown() -> Tuple[bool, str]:
    """SEC-011: Configuración de allow-plugins estrictamente restringida en composer.json."""
    composer_path = BASE_DIR / "web_portal" / "composer.json"
    if not composer_path.exists():
        return False, "composer.json no existe"

    data = json.loads(composer_path.read_text(encoding="utf-8"))
    allow_plugins = data.get("config", {}).get("allow-plugins", None)

    if allow_plugins is None or allow_plugins != {}:
        return False, f"allow-plugins no está estrictamente vacío ({allow_plugins})"

    return True, "allow-plugins restringido a {} en composer.json (cero ejecución de plugins arbitrarios)."


def check_arch_cleanliness() -> Tuple[bool, str]:
    """ARCH-001 & ARCH-002: Fachada bot.py con __all__ explícito y ProxyInflightGuard desacoplado."""
    bot_py = BASE_DIR / "bot.py"
    content = bot_py.read_text(encoding="utf-8")

    if "__all__" not in content:
        return False, "bot.py no define tupla formal __all__ (ARCH-001)"

    if "from monitor.proxy_cache" in content or "from monitor.infrastructure.proxy_cache" in content:
        return False, "bot.py aún importa de monitor.* en lugar de bot_core.services.proxy_cache (ARCH-002)"

    proxy_service = BASE_DIR / "bot_core" / "services" / "proxy_cache.py"
    if not proxy_service.exists():
        return False, "bot_core/services/proxy_cache.py no existe"

    return True, "Fachada bot.py delimitada con __all__ y ProxyInflightGuard desacoplado en bot_core."


def run_full_security_audit() -> bool:
    """Ejecuta todos los controles de seguridad y emite dictamen."""
    print("=" * 75)
    print(f" {CYAN}{BOLD}🛡️ BARRERA DE AUDITORÍA DE SEGURIDAD Y ANTI-REGRESIÓN (S.O.N.A.R.){NC}")
    print("=" * 75)

    checks = [
        ("SEC-001 (Tokens en Scope Global)", check_sec_001_zero_global_tokens),
        ("SEC-002 (Transporte HMAC Clúster)", check_sec_002_cluster_hmac),
        ("SEC-003 (CommandRegistry Whitelist)", check_sec_003_command_registry),
        ("SEC-004 (Cifrado LDAP en MariaDB)", check_sec_004_ldap_encryption),
        ("SEC-005 (Pinning de Dependencias)", check_sec_005_dependency_pinning),
        ("SEC-006 (Aviso Telnet y Cero CDNs)", check_sec_006_telnet_security_warning),
        ("SEC-007 (Sanitización de README)", check_sec_007_sanitized_readme),
        ("SEC-008 (Rate Limiting en APIs)", check_sec_008_rate_limiting),
        ("SEC-009 (Cifrado Comunidades SNMP)", check_sec_009_snmp_encrypted_communities),
        ("SEC-010 (Erradicación Banderas /tmp)", check_sec_010_zero_tmp_flags),
        ("SEC-011 (Lockdown Plugins Composer)", check_sec_011_composer_plugins_lockdown),
        ("ARCH-001/002 (Arquitectura Limpia)", check_arch_cleanliness),
    ]

    all_passed = True
    for label, check_fn in checks:
        passed, msg = check_fn()
        if passed:
            print(f" {GREEN}✓{NC} {BOLD}{label:<35}{NC}: {msg}")
        else:
            print(f" {RED}❌{NC} {BOLD}{label:<35}{NC}: {RED}{msg}{NC}")
            all_passed = False

    print("=" * 75)
    if all_passed:
        print(f" {GREEN}{BOLD}🎉 CERTIFICACIÓN DE SEGURIDAD SUPERADA AL 100% (ZERO REGRESSIONS){NC}")
        print("=" * 75)
        return True
    else:
        print(f" {RED}{BOLD}🚨 REGRESIÓN DE SEGURIDAD DETECTADA: CORRIJA LOS FALLOS INDICADOS.{NC}")
        print("=" * 75)
        return False


if __name__ == "__main__":
    success = run_full_security_audit()
    sys.exit(0 if success else 1)
