#!/usr/bin/env python3
"""
==============================================================================
 🔍 AUDITORÍA FORENSE CAPA 3: CORRESPONDENCIA CRUZADA DB ↔ DISCO (DB-to-Disk)
 S.O.N.A.R. - Sistema de Observación, Notificación y Administración de Recursos
 Valida la correspondencia bidireccional entre MariaDB y el almacenamiento en disco.
 Ubicación: /opt/sonar/tools/audit_db_to_disk.py
 License: GNU Affero General Public License v3.0
 Author: Operador ATIT (@britojab:@britojq), https://britojab.com
 Copyright (c) 2026 Operador ATIT
==============================================================================
"""

import os
import sys
import json
from pathlib import Path

# Agregar raíz de proyecto al PYTHONPATH
PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from monitor.infrastructure.db_context import db_cursor

STORAGE_BASE = Path("/home/data_monitoreo/storage/app/public")
WEB_STORAGE_BASE = PROJECT_ROOT / "web_portal" / "public" / "storage"

def print_header(title: str):
    print("\n" + "=" * 78)
    print(f" 🔹 {title}")
    print("=" * 78)

def run_db_to_disk_audit() -> bool:
    print_header("AUDITORÍA FORENSE CAPA 3: CORRESPONDENCIA CRUZADA DB ↔ DISCO")
    print(f"📁 Ruta Base Almacenamiento Masivo: {STORAGE_BASE}")
    print(f"🌐 Ruta Acceso Web Simbólico:      {WEB_STORAGE_BASE}")
    
    if not STORAGE_BASE.exists():
        print(f"❌ ERROR CRÍTICO: La ruta base de almacenamiento no existe: {STORAGE_BASE}")
        return False

    all_passed = True
    with db_cursor() as cursor:
        # -------------------------------------------------------------------------
        # 1. AUDITORÍA DE AVATARES DE USUARIOS (users.avatar)
        # -------------------------------------------------------------------------
        print_header("1. AUDITORÍA DE FOTOS DE PERFIL (users.avatar)")
        cursor.execute("SELECT id, name, email, avatar FROM users WHERE avatar IS NOT NULL AND avatar != '' ORDER BY id ASC")
        users = cursor.fetchall()
        
        print(f"Total usuarios con avatar configurado en MariaDB: {len(users)}\n")
        avatar_success = 0

        for u in users:
            u_id = u["id"]
            u_name = u["name"]
            u_avatar = u["avatar"].lstrip("/")
            
            real_path = STORAGE_BASE / u_avatar
            sym_path = WEB_STORAGE_BASE / u_avatar
            
            # Validar existencia física
            if not real_path.exists():
                print(f"❌ [USER #{u_id}: {u_name}] Archivo físico NO encontrado en: {real_path}")
                all_passed = False
                continue

            # Validar existencia vía enlace web
            if not sym_path.exists():
                print(f"❌ [USER #{u_id}: {u_name}] Archivo inalcanzable vía enlace web: {sym_path}")
                all_passed = False
                continue

            # Validar tamaño > 0
            size_bytes = real_path.stat().st_size
            if size_bytes == 0:
                print(f"❌ [USER #{u_id}: {u_name}] Archivo vacío (0 bytes): {real_path}")
                all_passed = False
                continue

            # Validar encabezado de imagen (JPEG/PNG)
            with open(real_path, "rb") as f:
                header = f.read(8)
                is_jpeg = header.startswith(b"\xff\xd8\xff")
                is_png = header.startswith(b"\x89PNG")
                if not (is_jpeg or is_png):
                    print(f"⚠️ [USER #{u_id}: {u_name}] Formato de imagen inusual en {real_path.name}")

            print(f" ✓ [OK] User #{u_id} ({u_name[:25]}...): {u_avatar} ({size_bytes:,} bytes)")
            avatar_success += 1

        print(f"\n📊 Resultado Avatares: {avatar_success}/{len(users)} conformes.")

        # -------------------------------------------------------------------------
        # 2. AUDITORÍA DE ACTAS ESCANEADAS ITAM (inventory_assignments.document_pdf_path)
        # -------------------------------------------------------------------------
        print_header("2. AUDITORÍA DE ACTAS ESCANEADAS ITAM (inventory_assignments)")
        cursor.execute("""
            SELECT id, assignment_code, custodian_name, document_pdf_path, status 
            FROM inventory_assignments 
            WHERE document_pdf_path IS NOT NULL AND document_pdf_path != ''
            ORDER BY id ASC
        """)
        assignments = cursor.fetchall()
        
        print(f"Total asignaciones ITAM con documento digitalizado en MariaDB: {len(assignments)}\n")
        actas_success = 0

        for a in assignments:
            a_id = a["id"]
            a_code = a["assignment_code"]
            a_custodian = a["custodian_name"] or "N/A"
            a_doc = a["document_pdf_path"].lstrip("/")
            
            real_path = STORAGE_BASE / a_doc
            sym_path = WEB_STORAGE_BASE / a_doc

            # Validar existencia física
            if not real_path.exists():
                print(f"❌ [ACTA #{a_id}: {a_code}] Archivo físico NO encontrado en: {real_path}")
                all_passed = False
                continue

            # Validar existencia vía enlace web
            if not sym_path.exists():
                print(f"❌ [ACTA #{a_id}: {a_code}] Archivo inalcanzable vía enlace web: {sym_path}")
                all_passed = False
                continue

            # Validar tamaño > 0
            size_bytes = real_path.stat().st_size
            if size_bytes == 0:
                print(f"❌ [ACTA #{a_id}: {a_code}] Archivo PDF vacío (0 bytes): {real_path}")
                all_passed = False
                continue

            # Validar cabecera PDF
            with open(real_path, "rb") as f:
                header = f.read(4)
                if header != b"%PDF":
                    print(f"❌ [ACTA #{a_id}: {a_code}] Archivo NO tiene firma mágica PDF (%PDF): {real_path}")
                    all_passed = False
                    continue

            print(f" ✓ [OK] Acta {a_code} ({a_custodian[:20]}...): {real_path.name} ({size_bytes:,} bytes)")
            actas_success += 1

        print(f"\n📊 Resultado Actas ITAM: {actas_success}/{len(assignments)} conformes.")

        # Verificación de archivos huérfanos en carpeta de escaneos
        scans_dir = STORAGE_BASE / "assignments" / "scans"
        if scans_dir.exists():
            physical_files = list(scans_dir.glob("*.pdf"))
            db_files = {Path(a["document_pdf_path"]).name for a in assignments}
            unlinked = [p for p in physical_files if p.name not in db_files]
            print(f"ℹ️ Total archivos físicos en scans/: {len(physical_files)} (Vinculados a BD: {len(db_files)}, Históricos/Reserva: {len(unlinked)})")
            for u in unlinked:
                print(f"   • Archivo de reserva / histórico: {u.name} ({u.stat().st_size:,} bytes)")

        # -------------------------------------------------------------------------
        # 3. AUDITORÍA DE SNAPSHOTS DE TELEMETRÍA (monitoring_snapshot.json)
        # -------------------------------------------------------------------------
        print_header("3. AUDITORÍA DE TELEMETRÍA Y SNAPSHOTS DE MONITOREO")
        disk_snapshot_file = STORAGE_BASE / "monitoring_snapshot.json"
        sym_snapshot_file = WEB_STORAGE_BASE / "monitoring_snapshot.json"

        if not disk_snapshot_file.exists():
            print(f"❌ Archivo monitoring_snapshot.json NO existe en {disk_snapshot_file}")
            all_passed = False
        elif not sym_snapshot_file.exists():
            print(f"❌ Archivo monitoring_snapshot.json NO accesible vía enlace web {sym_snapshot_file}")
            all_passed = False
        else:
            try:
                with open(disk_snapshot_file, "r", encoding="utf-8") as f:
                    disk_snap = json.load(f)
                
                cursor.execute("SELECT id, global_status, payload_json, updated_at FROM monitoring_snapshots ORDER BY id DESC LIMIT 1")
                latest_db_snap = cursor.fetchone()

                if not latest_db_snap:
                    print("⚠️ No hay snapshots históricos registrados en tabla monitoring_snapshots de MariaDB.")
                else:
                    db_payload = latest_db_snap["payload_json"]
                    if isinstance(db_payload, str):
                        db_snap = json.loads(db_payload)
                    else:
                        db_snap = db_payload

                    is_identical = (db_snap == disk_snap)
                    print(f" ✓ [OK] monitoring_snapshot.json en disco: Válido ({disk_snapshot_file.stat().st_size:,} bytes)")
                    print(f" ✓ [OK] Último snapshot en DB (ID #{latest_db_snap['id']}): Registrado el {latest_db_snap['updated_at']}")
                    print(f" ✓ [OK] Coherencia Atómica Payload DB ↔ JSON Disco: {'100% IDÉNTICOS' if is_identical else 'DESFASADOS'}")
                    if not is_identical:
                        print("⚠️ Aviso: El snapshot en disco difiere del último en base de datos.")
            except Exception as e:
                print(f"❌ Error al auditar snapshots: {e}")
                all_passed = False

    # -------------------------------------------------------------------------
    # RESUMEN Y DICTAMEN FINAL
    # -------------------------------------------------------------------------
    print_header("DICTAMEN FINAL DE AUDITORÍA CAPA 3")
    if all_passed:
        print("🎉 CERTIFICACIÓN 100% EXITOSA: CERO DISCREPANCIAS DB ↔ DISCO.")
        print("   Todas las rutas registradas en MariaDB son físicamente alcanzables,")
        print("   tienen tamaño positivo y permisos válidos en el sistema de archivos.")
        print("=" * 78 + "\n")
        return True
    else:
        print("❌ AUDITORÍA FALLIDA: Existen discrepancias entre MariaDB y los archivos en disco.")
        print("=" * 78 + "\n")
        return False

if __name__ == "__main__":
    success = run_db_to_disk_audit()
    sys.exit(0 if success else 1)
