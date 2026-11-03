"""
Renderizador de plantillas y utilidades de fechas y parseo de configuraciones Bash.
"""

from datetime import datetime
from pathlib import Path
import re
from typing import Dict, Tuple, Union


def parse_bash_config(filepath: Union[Path, str]) -> Dict[str, str]:
    """Parsea archivos de configuración de Bash con formato CLAVE=VALOR, soportando valores multilínea."""
    data: Dict[str, str] = {}
    filepath = Path(filepath)
    if not filepath.exists():
        return data

    content = filepath.read_text(encoding="utf-8", errors="ignore")
    pattern = re.compile(r"^[ \t]*([A-Za-z0-9_]+)[ \t]*=[ \t]*(?:\"([^\"]*)\"|'([^']*)'|([^#\r\n]*))", re.MULTILINE)
    for m in pattern.finditer(content):
        k = m.group(1).strip()
        v = m.group(2) if m.group(2) is not None else (m.group(3) if m.group(3) is not None else m.group(4))
        data[k] = (v or "").strip()
    return data


def parse_bash_templates(filepath: Path) -> Dict[str, str]:
    """Parsea plantillas multilínea en archivos tipo Bash (como mensajes.conf)."""
    if not filepath.exists():
        return {}

    content = filepath.read_text(encoding="utf-8", errors="ignore")
    data: Dict[str, str] = {}
    pattern = re.compile(r'^([A-Za-z0-9_]+)=([\"\'])(.*?)\2', re.MULTILINE | re.DOTALL)
    for m in pattern.finditer(content):
        data[m.group(1)] = m.group(3)
    return data


def get_formatted_datetime() -> Tuple[str, str]:
    """Retorna fecha en formato formal en español y hora HH:MM:SS."""
    now = datetime.now()
    dias = ["lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"]
    meses = [
        "enero", "febrero", "marzo", "abril", "mayo", "junio",
        "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"
    ]
    dia_nombre = dias[now.weekday()].capitalize()
    mes_nombre = meses[now.month - 1].capitalize()
    fecha_str = f"{dia_nombre}, {now.day} de {mes_nombre} de {now.year}."
    hora_str = now.strftime("%H:%M:%S")
    return fecha_str, hora_str


def render_template(template_str: str, variables: Dict[str, str]) -> str:
    """Sustituye variables estilo Bash ($VAR o ${VAR}) en la plantilla de texto, resolviendo variables anidadas."""
    def replacer(match: re.Match) -> str:
        """Ejecuta la función replacer conforme a la especificación técnica.

        Args:
            match: Parámetro operativo de entrada.
        """
        var_name = match.group(1) or match.group(2)
        val = variables.get(var_name)
        if val is not None:
            return str(val)
        return ""

    # Primera pasada de sustitución
    result = re.sub(r'\$\{([A-Za-z0-9_]+)\}|\$([A-Za-z0-9_]+)', replacer, template_str)
    # Segunda pasada para variables anidadas (ej. $NAMESERVICEA dentro de $STHOSTA)
    if '$' in result:
        result = re.sub(r'\$\{([A-Za-z0-9_]+)\}|\$([A-Za-z0-9_]+)', replacer, result)

    return result
