"""
# ==============================================================================
# 💾 DATABASE BACKUP & RESTORE MANAGER: database_backup.py
# Respaldo atómico pre-actualización y restauración de base de datos MariaDB
# Garantiza CERO pérdida de datos durante migraciones y despliegues Git.
# ==============================================================================
"""

import asyncio
import glob
import logging
import os
import shutil
import subprocess
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

logger = logging.getLogger("monitor.database_backup")

BASE_DIR = Path(__file__).resolve().parent.parent
BACKUPS_DIR = BASE_DIR / "database" / "backups"
MAX_BACKUPS_TO_KEEP = 15


from monitor.monitor_db import load_env_db_config as load_db_config


def get_git_commit_short() -> str:
    """Obtiene el hash corto del commit actual de Git."""
    try:
        res = subprocess.run(
            ["git", "rev-parse", "--short", "HEAD"],
            cwd=str(BASE_DIR),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            timeout=5
        )
        if res.returncode == 0:
            return res.stdout.strip()
    except Exception:
        pass
    return "unknown"


def create_db_snapshot(tag: str = "pre_update") -> Tuple[bool, str, str]:
    """
    Ejecuta un mysqldump atómico comprimido con gzip antes de aplicar cambios o migraciones.
    Retorna: (success, backup_filepath, status_message)
    """
    BACKUPS_DIR.mkdir(parents=True, exist_ok=True)
    cfg = load_db_config()
    commit = get_git_commit_short()
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    backup_filename = f"db_backup_{tag}_{ts}_{commit}.sql.gz"
    backup_path = BACKUPS_DIR / backup_filename

    dump_cmd = [
        "mysqldump",
        f"-h{cfg['host']}",
        f"-P{cfg['port']}",
        f"-u{cfg['username']}",
        f"-p{cfg['password']}",
        "--single-transaction",
        "--quick",
        "--routines",
        "--triggers",
        "--no-tablespaces",
        cfg["database"]
    ]

    try:
        # Ejecutar mysqldump | gzip > backup_path
        with open(backup_path, "wb") as f_out:
            p_dump = subprocess.Popen(dump_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            p_gzip = subprocess.Popen(["gzip", "-c"], stdin=p_dump.stdout, stdout=f_out, stderr=subprocess.PIPE)
            p_dump.stdout.close()
            _, err_gzip = p_gzip.communicate()
            _, err_dump = p_dump.communicate()

        if p_dump.returncode != 0:
            err_msg = err_dump.decode("utf-8", errors="ignore").strip()
            if backup_path.exists():
                backup_path.unlink()
            return False, "", f"Error en mysqldump: {err_msg}"

        if not backup_path.exists() or backup_path.stat().st_size == 0:
            return False, "", "El archivo de respaldo resultante tiene 0 bytes."

        size_kb = round(backup_path.stat().st_size / 1024, 1)
        _rotate_backups()
        return True, str(backup_path), f"Respaldo creado ({size_kb} KB): {backup_filename}"

    except Exception as e:
        if backup_path.exists():
            backup_path.unlink()
        return False, "", f"Excepción durante respaldo de BD: {e}"


def restore_db_snapshot(backup_path: Optional[str] = None) -> Tuple[bool, str]:
    """
    Restaura la base de datos MariaDB desde un archivo .sql.gz.
    Si no se especifica ruta, toma el último respaldo disponible.
    """
    if not backup_path:
        latest = get_latest_backup()
        if not latest:
            return False, "No se encontró ningún respaldo de base de datos previo."
        target_file = latest
    else:
        target_file = Path(backup_path)

    if not target_file.exists() or target_file.stat().st_size == 0:
        return False, f"El archivo de respaldo no existe o está corrupto: {target_file}"

    cfg = load_db_config()
    mysql_cmd = [
        "mysql",
        f"-h{cfg['host']}",
        f"-P{cfg['port']}",
        f"-u{cfg['username']}",
        f"-p{cfg['password']}",
        cfg["database"]
    ]

    try:
        # zcat backup_path | mysql
        p_zcat = subprocess.Popen(["gzip", "-dc", str(target_file)], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        p_mysql = subprocess.Popen(mysql_cmd, stdin=p_zcat.stdout, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        p_zcat.stdout.close()
        out_mysql, err_mysql = p_mysql.communicate()
        _, err_zcat = p_zcat.communicate()

        if p_mysql.returncode != 0:
            err_msg = err_mysql.decode("utf-8", errors="ignore").strip()
            return False, f"Error restaurando base de datos: {err_msg}"

        return True, f"Base de datos {cfg['database']} restaurada exitosamente desde: {target_file.name}"

    except Exception as e:
        return False, f"Excepción restaurando base de datos: {e}"


def get_latest_backup() -> Optional[Path]:
    """Obtiene la ruta del archivo de respaldo más reciente."""
    if not BACKUPS_DIR.exists():
        return None
    files = sorted(BACKUPS_DIR.glob("db_backup_*.sql.gz"), key=lambda f: f.stat().st_mtime, reverse=True)
    return files[0] if files else None


def list_backups(limit: int = 5) -> List[Dict[str, str]]:
    """Lista los respaldos recientes disponibles."""
    if not BACKUPS_DIR.exists():
        return []
    files = sorted(BACKUPS_DIR.glob("db_backup_*.sql.gz"), key=lambda f: f.stat().st_mtime, reverse=True)
    results = []
    for f in files[:limit]:
        mtime = datetime.fromtimestamp(f.stat().st_mtime).strftime("%d/%m/%Y %H:%M:%S")
        size_kb_val = round(f.stat().st_size / 1024, 1)
        results.append({
            "filename": f.name,
            "path": str(f),
            "date": mtime,
            "size": f"{size_kb_val} KB",
            "size_kb": size_kb_val
        })
    return results


def _rotate_backups():
    """Conserva como máximo MAX_BACKUPS_TO_KEEP archivos de respaldo."""
    if not BACKUPS_DIR.exists():
        return
    files = sorted(BACKUPS_DIR.glob("db_backup_*.sql.gz"), key=lambda f: f.stat().st_mtime, reverse=True)
    if len(files) > MAX_BACKUPS_TO_KEEP:
        for old_file in files[MAX_BACKUPS_TO_KEEP:]:
            try:
                old_file.unlink()
            except Exception:
                pass


if __name__ == "__main__":
    print("Testing backup creation in dev...")
    ok, path, msg = create_db_snapshot("test_cli")
    print(f"Backup Result: {ok} | {msg} | Path: {path}")
    print("Backups list:")
    for b in list_backups():
        print(f" - {b['filename']} ({b['size']}, {b['date']})")
