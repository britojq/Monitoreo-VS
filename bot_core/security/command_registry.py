"""
# ==============================================================================
# 🛡️ REGISTRO DE COMANDOS SEGUROS: command_registry.py
# Whitelist estricta y validación de argumentos para ejecución de comandos de consola.
# Mitigación OWASP A03:2021 (CWE-78 - OS Command Injection).
# Ubicación: /opt/sonar/bot_core/security/command_registry.py
# ==============================================================================
"""

from __future__ import annotations

import os
import re
from dataclasses import dataclass
from typing import Dict, Pattern, Sequence, Tuple

# Patrón estricto de caracteres y metacaracteres de shell prohibidos
_DANGEROUS_SHELL_CHARS_RE: Pattern[str] = re.compile(r"[\x00;&|`$><\n\r\\]")

# Patrones genéricos seguros para argumentos
_SAFE_IP_OR_HOST_RE: Pattern[str] = re.compile(r"^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$")
_SAFE_SERVICE_NAME_RE: Pattern[str] = re.compile(r"^[a-zA-Z0-9_@.-]+(?:\.service)?$")
_SAFE_PATH_RE: Pattern[str] = re.compile(r"^/[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*$")
_SAFE_OPTION_RE: Pattern[str] = re.compile(r"^-[a-zA-Z0-9]{1,4}$")


@dataclass(frozen=True)
class CommandSpec:
    """Especificación inmutable de validación para un binario autorizado."""

    binary_name: str
    allowed_subcommands: Tuple[str, ...] = ()
    allowed_argument_patterns: Tuple[str, ...] = ()
    max_arguments: int = 10
    allow_safe_flags_and_targets: bool = False


# Catálogo estricto de comandos de diagnóstico y sistema autorizados
COMMAND_REGISTRY: Dict[str, CommandSpec] = {
    "ping": CommandSpec(
        binary_name="ping",
        allowed_argument_patterns=(
            r"^-c$",
            r"^\d{1,2}$",
            r"^-W$",
            r"^-w$",
            r"^-i$",
            r"^\d{1,2}(?:\.\d+)?$",
            r"^-s$",
            r"^\d{1,4}$",
            r"^-q$",
            r"^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$",
        ),
        max_arguments=8,
    ),
    "traceroute": CommandSpec(
        binary_name="traceroute",
        allowed_argument_patterns=(
            r"^-m$",
            r"^\d{1,2}$",
            r"^-w$",
            r"^-q$",
            r"^-n$",
            r"^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$",
        ),
        max_arguments=8,
    ),
    "systemctl": CommandSpec(
        binary_name="systemctl",
        allowed_subcommands=("status", "is-active", "is-failed"),
        allowed_argument_patterns=(
            r"^--no-pager$",
            r"^-l$",
            r"^[a-zA-Z0-9_@.-]+(?:\.service)?$",
        ),
        max_arguments=6,
    ),
    "df": CommandSpec(
        binary_name="df",
        allowed_argument_patterns=(
            r"^-[hkmT]+$",
            r"^/[a-zA-Z0-9_.-]*$",
        ),
        max_arguments=5,
    ),
    "free": CommandSpec(
        binary_name="free",
        allowed_argument_patterns=(
            r"^-[mhgbk]+$",
            r"^--mega$",
            r"^--giga$",
        ),
        max_arguments=4,
    ),
    "uptime": CommandSpec(
        binary_name="uptime",
        allowed_argument_patterns=(
            r"^-[ps]$",
            r"^--pretty$",
            r"^--since$",
        ),
        max_arguments=3,
    ),
    "ip": CommandSpec(
        binary_name="ip",
        allowed_subcommands=("addr", "address", "route", "link", "show"),
        allowed_argument_patterns=(
            r"^-[46brs]+$",
            r"^-brief$",
            r"^show$",
            r"^dev$",
            r"^[a-zA-Z0-9_.-]+$",
        ),
        max_arguments=8,
    ),
    "git": CommandSpec(
        binary_name="git",
        allowed_subcommands=("status", "log", "rev-parse", "diff", "branch", "rev-list"),
        allowed_argument_patterns=(
            r"^-[a-zA-Z0-9-]+$",
            r"^--[a-zA-Z0-9-]+(?:=[a-zA-Z0-9_.-]+)?$",
            r"^[a-zA-Z0-9_./-]+$",
        ),
        max_arguments=10,
    ),
    "python3": CommandSpec(
        binary_name="python3",
        allowed_argument_patterns=(
            r"^/opt/sonar/(?:tools|monitor|bot_core)/[a-zA-Z0-9_.-]+(?:\.py)?$",
            r"^-[a-zA-Z0-9]+$",
            r"^[a-zA-Z0-9_.-]+$",
        ),
        max_arguments=10,
    ),
    "python": CommandSpec(
        binary_name="python",
        allowed_argument_patterns=(
            r"^/opt/sonar/(?:tools|monitor|bot_core)/[a-zA-Z0-9_.-]+(?:\.py)?$",
            r"^-[a-zA-Z0-9]+$",
            r"^[a-zA-Z0-9_.-]+$",
        ),
        max_arguments=10,
    ),
}


def validate_command(command: Sequence[str]) -> Tuple[bool, str]:
    """
    Valida exhaustivamente un comando y sus argumentos contra la lista blanca estricta.

    Args:
        command: Secuencia de argumentos que conforma el comando a ejecutar (ej. ["ping", "-c", "4", "192.168.1.1"]).

    Returns:
        Tuple[bool, str]: (True, "OK") si el comando es seguro y autorizado; (False, motivo) en caso de rechazo.
    """
    if not command:
        return False, "Comando vacío o nulo"

    raw_binary = command[0].strip()
    if not raw_binary:
        return False, "Ejecutable no especificado"

    binary = os.path.basename(raw_binary).lower()
    spec = COMMAND_REGISTRY.get(binary)
    if not spec:
        return False, f"Ejecutable '{binary}' no autorizado en lista blanca (SEC-003)"

    if len(command) > spec.max_arguments:
        return False, f"Número de argumentos ({len(command)}) excede el límite permitido ({spec.max_arguments})"

    # Validar metacaracteres de inyección en todos los argumentos
    for idx, arg in enumerate(command):
        if not isinstance(arg, str):
            return False, f"El argumento en posición {idx} no es de tipo string"
        if _DANGEROUS_SHELL_CHARS_RE.search(arg):
            return False, f"Argumento '{arg}' contiene caracteres prohibidos o metacaracteres de shell"

    # Si hay subcomandos definidos, validar que el segundo elemento coincida
    args = command[1:]
    if spec.allowed_subcommands:
        if not args:
            return False, f"Se requiere un subcomando válido para '{binary}' ({', '.join(spec.allowed_subcommands)})"
        subcmd = args[0].lower()
        if subcmd not in spec.allowed_subcommands:
            return False, f"Subcomando '{subcmd}' no permitido para '{binary}'"
        args = args[1:]

    # Validar argumentos restantes contra los patrones permitidos
    compiled_patterns = [re.compile(p) for p in spec.allowed_argument_patterns]
    for arg in args:
        matched = False
        for pat in compiled_patterns:
            if pat.match(arg):
                matched = True
                break
        if not matched:
            return False, f"Argumento '{arg}' no coincide con ningún patrón seguro permitido para '{binary}'"

    return True, "OK"


def is_command_allowed(command: Sequence[str]) -> bool:
    """Verifica de forma booleana si un comando está autorizado y es seguro."""
    ok, _ = validate_command(command)
    return ok
