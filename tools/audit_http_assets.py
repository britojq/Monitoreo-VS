#!/usr/bin/env python3
"""
==============================================================================
 🔍 AUDITORÍA FORENSE CAPA 4: ASSETS MULTIMEDIA Y DESCARGAS HTTP
 S.O.N.A.R. - Sistema de Observación, Notificación y Administración de Recursos
 Realiza peticiones HTTP HEAD/GET reales contra Apache para certificar cero 404s
 en avatares, actas ITAM, instaladores GLPI, logos, estilos, fuentes y telemetría.
 Ubicación: /opt/sonar/tools/audit_http_assets.py
 License: GNU Affero General Public License v3.0
 Author: Operador ATIT (@britojab:@britojq), https://britojab.com
 Copyright (c) 2026 Operador ATIT
==============================================================================
"""

import sys
import urllib.request
import urllib.error
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from monitor.infrastructure.db_context import db_cursor

BASE_URL = "http://127.0.0.1"
PUBLIC_DIR = PROJECT_ROOT / "web_portal" / "public"

def print_header(title: str):
    print("\n" + "=" * 78)
    print(f" 🔹 {title}")
    print("=" * 78)

def check_asset(url_path: str, expected_type_prefix: tuple[str, ...] | str = None, expected_type_prefixes: tuple[str, ...] | str = None) -> tuple[bool, str]:
    """Solicita un asset vía HTTP HEAD a Apache y valida código 200, Content-Length y Content-Type."""
    full_url = f"{BASE_URL}/{url_path.lstrip('/')}"
    req = urllib.request.Request(full_url, method="HEAD")
    req.add_header("User-Agent", "SonarForensicAuditor/1.0")

    prefixes = expected_type_prefixes or expected_type_prefix
    if isinstance(prefixes, str):
        prefixes = (prefixes,)

    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            status = response.status
            content_length = response.headers.get("Content-Length")
            content_type = response.headers.get("Content-Type", "")

            if status != 200:
                return False, f"HTTP {status} (esperado 200)"

            size = int(content_length) if content_length and content_length.isdigit() else 0
            if size <= 0:
                return False, f"Content-Length inválido ({size} bytes)"

            if prefixes:
                matched = any(content_type.startswith(prefix) for prefix in prefixes)
                if not matched:
                    return False, f"Content-Type '{content_type}' no concuerda con {prefixes}"

            return True, f"200 OK | {size:,} bytes | {content_type}"
    except urllib.error.HTTPError as e:
        return False, f"HTTP ERROR {e.code}: {e.reason}"
    except Exception as e:
        return False, f"ERROR DE CONEXIÓN: {e}"

def run_http_assets_audit() -> bool:
    print_header("AUDITORÍA FORENSE CAPA 4: ASSETS MULTIMEDIA Y DESCARGAS HTTP")
    print(f"🌐 Servidor Objetivo: {BASE_URL} (Apache 2.4 /opt/sonar/web_portal/public)")

    all_passed = True
    total_audited = 0
    total_successful = 0

    with db_cursor() as cursor:
        cursor.execute("SELECT id, name, avatar FROM users WHERE avatar IS NOT NULL AND avatar != '' ORDER BY id ASC")
        users = cursor.fetchall()
        cursor.execute("SELECT id, assignment_code, document_pdf_path FROM inventory_assignments WHERE document_pdf_path IS NOT NULL AND document_pdf_path != '' ORDER BY id ASC")
        assignments = cursor.fetchall()

    # -------------------------------------------------------------------------
    # 1. AVATARES DE USUARIO
    # -------------------------------------------------------------------------
    print_header("1. AVATARES DE PERFIL (users.avatar)")
    for u in users:
        total_audited += 1
        path = f"storage/{u['avatar'].lstrip('/')}"
        ok, detail = check_asset(path, expected_type_prefix="image/")
        if ok:
            total_successful += 1
            print(f" ✓ [OK] User #{u['id']} ({u['name'][:22]}...): {path} -> {detail}")
        else:
            all_passed = False
            print(f" ❌ [FAIL] User #{u['id']} ({u['name']}): {path} -> {detail}")

    # -------------------------------------------------------------------------
    # 2. ACTAS ESCANEADAS ITAM
    # -------------------------------------------------------------------------
    print_header("2. ACTAS DIGITALIZADAS ITAM (inventory_assignments.document_pdf_path)")
    for a in assignments:
        total_audited += 1
        path = f"storage/{a['document_pdf_path'].lstrip('/')}"
        ok, detail = check_asset(path, expected_type_prefix="application/pdf")
        if ok:
            total_successful += 1
            print(f" ✓ [OK] Acta {a['assignment_code']}: {path} -> {detail}")
        else:
            all_passed = False
            print(f" ❌ [FAIL] Acta {a['assignment_code']}: {path} -> {detail}")

    # -------------------------------------------------------------------------
    # 3. INSTALADORES Y SCRIPTS DE AGENTES GLPI
    # -------------------------------------------------------------------------
    print_header("3. INSTALADORES Y SCRIPTS GLPI (/instaladores/)")
    installer_files = [
        ("instaladores/glpi-agent_all.deb", ""),
        ("instaladores/glpi-agent_1.20-1_all.deb", ""),
        ("instaladores/GLPI-Agent-x64.msi", ""),
        ("instaladores/GLPI-Agent-1.20-x64.msi", ""),
        ("instaladores/GLPI-Agent-x86.msi", ""),
        ("instaladores/GLPI-Agent-1.7.2-x86.msi", ""),
        ("instaladores/glpi-linux.sh", "text/"),
        ("instaladores/glpi-win.ps1", "text/"),
        ("instaladores/glpi-switch.sh", "text/"),
        ("instaladores/glpi-switch.ps1", "text/"),
    ]

    for rel_path, exp_type in installer_files:
        total_audited += 1
        ok, detail = check_asset(rel_path, expected_type_prefix=exp_type or None)
        if ok:
            total_successful += 1
            print(f" ✓ [OK] Instalador: {rel_path} -> {detail}")
        else:
            all_passed = False
            print(f" ❌ [FAIL] Instalador: {rel_path} -> {detail}")

    # -------------------------------------------------------------------------
    # 4. LOGOS, BRANDING Y FAVICONS
    # -------------------------------------------------------------------------
    print_header("4. LOGOS, BRANDING Y FAVICONS (/img/, /assets/img/)")
    branding_assets = [
        ("img/logo.png", "image/png"),
        ("assets/img/logo.webp", "image/webp"),
        ("favicon.ico", "image/"),
        ("assets/img/favicon.png", "image/png"),
        ("assets/img/apple-touch-icon.png", "image/png"),
    ]

    for rel_path, exp_type in branding_assets:
        total_audited += 1
        ok, detail = check_asset(rel_path, expected_type_prefix=exp_type)
        if ok:
            total_successful += 1
            print(f" ✓ [OK] Branding: {rel_path} -> {detail}")
        else:
            all_passed = False
            print(f" ❌ [FAIL] Branding: {rel_path} -> {detail}")

    # -------------------------------------------------------------------------
    # 5. ILUSTRACIONES DEL CENTRO DE AYUDA (/img/help/)
    # -------------------------------------------------------------------------
    print_header("5. ILUSTRACIONES DEL CENTRO DE AYUDA (/img/help/)")
    help_dir = PUBLIC_DIR / "img" / "help"
    help_files = sorted([p.name for p in help_dir.glob("*.png")]) if help_dir.exists() else []

    for hf in help_files:
        total_audited += 1
        rel_path = f"img/help/{hf}"
        ok, detail = check_asset(rel_path, expected_type_prefix="image/png")
        if ok:
            total_successful += 1
            print(f" ✓ [OK] Manual Ayuda: {rel_path} -> {detail}")
        else:
            all_passed = False
            print(f" ❌ [FAIL] Manual Ayuda: {rel_path} -> {detail}")

    # -------------------------------------------------------------------------
    # 6. ESTILOS, SCRIPTS Y FUENTES NATIVAS
    # -------------------------------------------------------------------------
    print_header("6. ESTILOS, JAVASCRIPT Y FUENTES NATIVAS (/assets/, /vendor/, /js/)")
    js_types = ("text/javascript", "application/javascript", "application/x-javascript")
    core_bundles = [
        ("assets/css/main.css", "text/css"),
        ("assets/js/main.js", js_types),
        ("js/monitoring-app.min.js", js_types),
        ("vendor/tailwindcss/tailwind.min.js", js_types),
        ("vendor/chartjs/chart.umd.min.js", js_types),
        ("vendor/cytoscape/cytoscape.min.js", js_types),
        ("vendor/fonts/fonts.css", "text/css"),
        ("vendor/material-symbols/material-symbols.css", "text/css"),
        ("vendor/xterm/xterm.css", "text/css"),
        ("vendor/xterm/xterm.min.js", js_types),
    ]

    for rel_path, exp_type in core_bundles:
        total_audited += 1
        ok, detail = check_asset(rel_path, expected_type_prefixes=exp_type)
        if ok:
            total_successful += 1
            print(f" ✓ [OK] Bundle/Fuente: {rel_path} -> {detail}")
        else:
            all_passed = False
            print(f" ❌ [FAIL] Bundle/Fuente: {rel_path} -> {detail}")

    # -------------------------------------------------------------------------
    # 7. TELEMETRÍA Y SNAPSHOT ESTÁTICO
    # -------------------------------------------------------------------------
    print_header("7. TELEMETRÍA Y SNAPSHOT ESTÁTICO (/storage/monitoring_snapshot.json)")
    total_audited += 1
    ok, detail = check_asset("storage/monitoring_snapshot.json", expected_type_prefix="application/json")
    if ok:
        total_successful += 1
        print(f" ✓ [OK] Snapshot Telemetría: storage/monitoring_snapshot.json -> {detail}")
    else:
        all_passed = False
        print(f" ❌ [FAIL] Snapshot Telemetría: storage/monitoring_snapshot.json -> {detail}")

    # -------------------------------------------------------------------------
    # DICTAMEN FINAL
    # -------------------------------------------------------------------------
    print_header("DICTAMEN FINAL DE AUDITORÍA CAPA 4")
    print(f"📊 Total Recursos Auditados: {total_audited}")
    print(f"✅ Total Recursos Conformes (HTTP 200 OK): {total_successful}")
    print(f"❌ Total Fallos / 404 / 500 / 0 Bytes: {total_audited - total_successful}")

    if all_passed and total_audited == total_successful:
        print("\n🎉 CERTIFICACIÓN 100% EXITOSA: CERO ERRORES 404 EN ASSETS Y DESCARGAS HTTP.")
        print("   Apache entrega sin restricciones todos los archivos estáticos y multimedia.")
        print("=" * 78 + "\n")
        return True
    else:
        print("\n❌ AUDITORÍA FALLIDA: Se detectaron recursos inalcanzables o con errores HTTP.")
        print("=" * 78 + "\n")
        return False

if __name__ == "__main__":
    success = run_http_assets_audit()
    sys.exit(0 if success else 1)
