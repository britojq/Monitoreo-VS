#!/usr/bin/env python3
"""
tools/bump_version.py
=============================================================================
Motor Automatizado de Control y Cálculo de Versiones del Sistema (Regla de Oro #12)
Ecosistema NOC + ITAM - Plataforma Integral Valle Seco

Aritmética y Reglas Formales:
- Formato Oficial: MAYOR.MENOR.PARCHE (Base inicial 5.0.1)
- fix:  Suma +10 al segmento Parche (5.0.1 -> 5.0.11 -> 5.0.21...)
        Si Parche >= 100 (10 correcciones): Menor += 1 y Parche = 0 (5.0.91 -> 5.1.0)
- feat: Suma +1 al segmento Menor y reinicia Parche a 0 (5.0.1 -> 5.1.0)
- both: Suma +1 al segmento Menor y Parche inicia en 10 (5.0.1 -> 5.1.10)

Archivos Sincronizados Atómicamente:
1. web_portal/resources/views/layouts/admin.blade.php (Header badge y créditos)
2. web_portal/app/Services/Cluster/RuntimeParityInspector.php (API Clúster)
3. web_portal/app/Http/Controllers/Admin/HelpController.php (Centro de ayuda)
4. docs/manual_api_verificacion_cluster.md (Documentación técnica)
+ Réplica a /var/www/monitoreo (si existe) y purga de caché.
=============================================================================
"""

from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Dict, List, Optional, Tuple

# Colores ANSI para terminal
GREEN = "\033[0;32m"
YELLOW = "\033[1;33m"
CYAN = "\033[0;36m"
RED = "\033[0;31m"
BOLD = "\033[1m"
NC = "\033[0m"


def get_repo_root() -> Path:
    """Obtiene la ruta raíz del repositorio."""
    return Path(__file__).resolve().parent.parent


def parse_version(raw_version: str) -> Tuple[int, int, int]:
    """
    Parsea una versión en formato [v]X.Y.Z a una tupla de enteros (mayor, menor, parche).
    """
    cleaned = raw_version.strip().lstrip("vV")
    match = re.match(r"^(\d+)\.(\d+)\.(\d+)$", cleaned)
    if not match:
        raise ValueError(f"Formato de versión inválido: '{raw_version}'. Debe ser X.Y.Z (ej. 5.0.1)")
    return int(match.group(1)), int(match.group(2)), int(match.group(3))


def calculate_next_version(
    major: int, minor: int, patch: int, bump_type: str
) -> Tuple[int, int, int]:
    """
    Calcula la siguiente versión aplicando la regla formal:
    - fix:  patch += 10. Si patch >= 100 -> minor += 1, patch = 0.
    - feat: minor += 1, patch = 0.
    - both: minor += 1, patch = 10.
    """
    bump = bump_type.lower().strip()
    if bump == "fix":
        new_patch = patch + 10
        if new_patch >= 100:
            return major, minor + 1, 0
        return major, minor, new_patch
    elif bump == "feat":
        return major, minor + 1, 0
    elif bump == "both":
        return major, minor + 1, 10
    else:
        raise ValueError(f"Tipo de incremento desconocido: '{bump_type}'. Use 'fix', 'feat' o 'both'")


def detect_current_version(repo_root: Path) -> str:
    """
    Lee la versión actual vigente desde RuntimeParityInspector.php.
    """
    inspector_file = (
        repo_root
        / "web_portal"
        / "app"
        / "Services"
        / "Cluster"
        / "RuntimeParityInspector.php"
    )
    if not inspector_file.is_file():
        raise FileNotFoundError(f"No se encontró el archivo: {inspector_file}")

    content = inspector_file.read_text(encoding="utf-8")
    match = re.search(r"['\"]system_version['\"]\s*=>\s*['\"]v?(\d+\.\d+\.\d+)['\"]", content)
    if not match:
        raise ValueError("No se pudo detectar la versión en RuntimeParityInspector.php")
    return match.group(1)


def detect_bump_type_from_git(repo_root: Path) -> str:
    """
    Inspecciona los commits recientes o archivos modificados en git para inferir el bump_type.
    - Si se detecta 'feat', 'modulo', 'nueva' -> 'feat'
    - De lo contrario -> 'fix'
    """
    try:
        res = subprocess.run(
            ["git", "diff", "--cached", "--name-only"],
            cwd=str(repo_root),
            capture_output=True,
            text=True,
            check=False,
        )
        staged_files = res.stdout.strip().splitlines()

        # Si hay nuevas migraciones o nuevos controladores, sugerir feat
        has_new_features = any(
            "migrations/" in f or "Http/Controllers/" in f for f in staged_files
        )
        if has_new_features:
            return "feat"
    except Exception:
        pass
    return "fix"


def update_target_files(
    repo_root: Path, old_ver: str, new_ver: str, dry_run: bool = False
) -> Dict[str, bool]:
    """
    Actualiza la versión en los 4 archivos clave del sistema.
    """
    results: Dict[str, bool] = {}

    targets = [
        # 1. layouts/admin.blade.php
        (
            repo_root / "web_portal" / "resources" / "views" / "layouts" / "admin.blade.php",
            [
                (rf"v{re.escape(old_ver)}", f"v{new_ver}"),
            ],
        ),
        # 2. RuntimeParityInspector.php
        (
            repo_root
            / "web_portal"
            / "app"
            / "Services"
            / "Cluster"
            / "RuntimeParityInspector.php",
            [
                (
                    rf"('system_version'\s*=>\s*')v?{re.escape(old_ver)}(')",
                    rf"\g<1>v{new_ver}\g<2>",
                ),
            ],
        ),
        # 3. HelpController.php
        (
            repo_root
            / "web_portal"
            / "app"
            / "Http"
            / "Controllers"
            / "Admin"
            / "HelpController.php",
            [
                (
                    rf"(\$systemVersion\s*=\s*')v?{re.escape(old_ver)}(';)",
                    rf"\g<1>v{new_ver}\g<2>",
                ),
            ],
        ),
        # 4. docs/manual_api_verificacion_cluster.md
        (
            repo_root / "docs" / "manual_api_verificacion_cluster.md",
            [
                (
                    rf"(\*\*Versión:\*\*\s*)v?{re.escape(old_ver)}",
                    rf"\g<1>{new_ver}",
                ),
                (
                    rf"(\"system_version\":\s*\")v?{re.escape(old_ver)}(\")",
                    rf"\g<1>v{new_ver}\g<2>",
                ),
            ],
        ),
    ]

    for file_path, patterns in targets:
        rel_path = file_path.relative_to(repo_root) if file_path.is_relative_to(repo_root) else file_path
        if not file_path.is_file():
            results[str(rel_path)] = False
            continue

        content = file_path.read_text(encoding="utf-8")
        modified = False
        new_content = content

        for pattern, replacement in patterns:
            new_content, count = re.subn(pattern, replacement, new_content)
            if count > 0:
                modified = True

        if modified:
            if not dry_run:
                file_path.write_text(new_content, encoding="utf-8")
            results[str(rel_path)] = True
        else:
            results[str(rel_path)] = False

    return results


def sync_to_live_web(repo_root: Path, dry_run: bool = False) -> List[str]:
    """
    Sincroniza los archivos actualizados hacia /var/www/monitoreo si existe.
    """
    live_dir = Path("/var/www/monitoreo")
    synced_files: List[str] = []

    if not live_dir.is_dir():
        return synced_files

    files_to_sync = [
        "resources/views/layouts/admin.blade.php",
        "app/Services/Cluster/RuntimeParityInspector.php",
        "app/Http/Controllers/Admin/HelpController.php",
    ]

    web_src = repo_root / "web_portal"

    for rel in files_to_sync:
        src = web_src / rel
        dst = live_dir / rel
        if src.is_file() and dst.parent.is_dir():
            if not dry_run:
                shutil.copy2(src, dst)
            synced_files.append(rel)

    # Limpiar caché de vistas si no es dry-run
    if not dry_run and synced_files:
        try:
            subprocess.run(
                ["php", "artisan", "view:clear"],
                cwd=str(live_dir),
                capture_output=True,
                check=False,
            )
        except Exception:
            pass

    return synced_files


def install_git_hook(repo_root: Path) -> bool:
    """
    Instala un hook pre-commit en .git/hooks/pre-commit para asegurar que
    la versión se verifique o incremente automáticamente en cada commit.
    """
    hook_path = repo_root / ".git" / "hooks" / "pre-commit"
    if not hook_path.parent.is_dir():
        return False

    hook_content = """#!/usr/bin/env bash
# ==============================================================================
# 🛡️ GIT HOOK PRE-COMMIT: CONTROL AUTOMÁTICO DE VERSIÓN (REGLA DE ORO #12)
# ==============================================================================
REPO_DIR="$(git rev-parse --show-toplevel)"
BUMP_SCRIPT="$REPO_DIR/tools/bump_version.py"

if [ -f "$BUMP_SCRIPT" ]; then
    # Verificar si ya se modificaron los archivos de versión en este commit
    VERSION_TOUCHED=$(git diff --cached --name-only | grep -E "admin\\.blade\\.php|RuntimeParityInspector\\.php|HelpController\\.php" || true)
    
    if [ -z "$VERSION_TOUCHED" ]; then
        echo -e "\\033[0;36m[AUTO-VERSION] Aplicando incremento de versión automático por cambios staged...\\033[0m"
        python3 "$BUMP_SCRIPT" --auto --quiet || true
        # Stage de los archivos de versión modificados
        git add "$REPO_DIR/web_portal/resources/views/layouts/admin.blade.php" \\
                "$REPO_DIR/web_portal/app/Services/Cluster/RuntimeParityInspector.php" \\
                "$REPO_DIR/web_portal/app/Http/Controllers/Admin/HelpController.php" \\
                "$REPO_DIR/docs/manual_api_verificacion_cluster.md" 2>/dev/null || true
    fi
fi
exit 0
"""
    hook_path.write_text(hook_content, encoding="utf-8")
    hook_path.chmod(0o755)
    return True


def display_status(repo_root: Path) -> None:
    """Muestra el estado actual de la versión en todos los archivos."""
    current_ver = detect_current_version(repo_root)
    print(f"\n{BOLD}{CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{NC}")
    print(f"{BOLD}{CYAN} 📦 ESTADO DE VERSIÓN DEL SISTEMA: v{current_ver}{NC}")
    print(f"{BOLD}{CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{NC}")

    files = [
        ("Inspector de Clúster", repo_root / "web_portal/app/Services/Cluster/RuntimeParityInspector.php"),
        ("Controlador de Ayuda", repo_root / "web_portal/app/Http/Controllers/Admin/HelpController.php"),
        ("Layout Admin Blade", repo_root / "web_portal/resources/views/layouts/admin.blade.php"),
        ("Manual del Clúster", repo_root / "docs/manual_api_verificacion_cluster.md"),
    ]

    for label, path in files:
        if path.is_file():
            content = path.read_text(encoding="utf-8")
            found = re.findall(rf"v?({re.escape(current_ver)})", content)
            status = f"{GREEN}✓ Sincronizado ({len(found)} ref){NC}" if found else f"{RED}✗ Desincronizado{NC}"
            print(f"  • {label:22}: {status} ({path.name})")
        else:
            print(f"  • {label:22}: {YELLOW}No encontrado{NC}")

    live_inspector = Path("/var/www/monitoreo/app/Services/Cluster/RuntimeParityInspector.php")
    if live_inspector.is_file():
        live_content = live_inspector.read_text(encoding="utf-8")
        live_found = re.findall(rf"v?({re.escape(current_ver)})", live_content)
        live_status = f"{GREEN}✓ v{current_ver}{NC}" if live_found else f"{RED}✗ Desfasado{NC}"
        print(f"  • Producción/Live Web   : {live_status} (/var/www/monitoreo)")
    print("")


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Motor Automatizado de Control y Cálculo de Versiones del Sistema (Regla de Oro #12)"
    )
    parser.add_argument(
        "--type",
        choices=["fix", "feat", "both"],
        default=None,
        help="Tipo de incremento: fix (+10 parche, acarreo a 100), feat (+1 menor, parche 0), both (+1 menor, parche 10)",
    )
    parser.add_argument(
        "--auto",
        action="store_true",
        help="Detectar automáticamente si es fix o feat según cambios en git",
    )
    parser.add_argument(
        "--set",
        dest="set_version",
        type=str,
        default=None,
        help="Forzar una versión específica (formato X.Y.Z)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Simular el cálculo y reemplazo sin modificar ningún archivo",
    )
    parser.add_argument(
        "--status",
        action="store_true",
        help="Consultar el estado actual de versión en los archivos del sistema",
    )
    parser.add_argument(
        "--install-hook",
        action="store_true",
        help="Instalar el git hook pre-commit para auto-versionado",
    )
    parser.add_argument(
        "--quiet",
        action="store_true",
        help="Salida silenciosa (ideal para hooks y scripts)",
    )

    args = parser.parse_args()
    repo_root = get_repo_root()

    if args.install_hook:
        ok = install_git_hook(repo_root)
        if not args.quiet:
            if ok:
                print(f"{GREEN}✓ Hook pre-commit instalado exitosamente en .git/hooks/pre-commit{NC}")
            else:
                print(f"{RED}✗ No se pudo instalar el hook (.git/hooks no encontrado){NC}")
        return 0 if ok else 1

    if args.status:
        display_status(repo_root)
        return 0

    try:
        current_str = detect_current_version(repo_root)
        major, minor, patch = parse_version(current_str)
    except Exception as e:
        print(f"{RED}Error al detectar versión actual: {e}{NC}", file=sys.stderr)
        return 1

    # Determinar nueva versión
    if args.set_version:
        new_major, new_minor, new_patch = parse_version(args.set_version)
        bump_type = "manual_set"
    else:
        bump_type = args.type
        if not bump_type:
            if args.auto:
                bump_type = detect_bump_type_from_git(repo_root)
            else:
                bump_type = "fix"

        new_major, new_minor, new_patch = calculate_next_version(
            major, minor, patch, bump_type
        )

    new_str = f"{new_major}.{new_minor}.{new_patch}"

    if not args.quiet:
        print(f"\n{BOLD}{CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{NC}")
        print(f"{BOLD}{CYAN} 🚀 MOTOR DE VERSIONADO AUTOMÁTICO (REGLA DE ORO #12){NC}")
        print(f"{BOLD}{CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━{NC}")
        print(f"  • Versión Actual  : {YELLOW}v{current_str}{NC}")
        print(f"  • Tipo de Cambio  : {BOLD}{bump_type.upper()}{NC}")
        print(f"  • Nueva Versión   : {GREEN}v{new_str}{NC}")
        if args.dry_run:
            print(f"  • Modo            : {YELLOW}DRY-RUN (Simulación sin cambios){NC}")

    # Ejecutar reemplazo en archivos
    update_res = update_target_files(repo_root, current_str, new_str, dry_run=args.dry_run)
    synced_live = sync_to_live_web(repo_root, dry_run=args.dry_run)

    if not args.quiet:
        print(f"\n{BOLD}Archivos actualizados:{NC}")
        for path, modified in update_res.items():
            status = f"{GREEN}✓ Actualizado{NC}" if modified else f"{YELLOW}– Sin cambios{NC}"
            print(f"  [{status}] {path}")

        if synced_live:
            print(f"\n{BOLD}Sincronización a servidor web activo (/var/www/monitoreo):{NC}")
            for sf in synced_live:
                print(f"  [{GREEN}✓ Copiado{NC}] {sf}")

        print(f"\n{GREEN}{BOLD}✔ Proceso completado exitosamente: v{current_str} ➔ v{new_str}{NC}\n")

    return 0


if __name__ == "__main__":
    sys.exit(main())
