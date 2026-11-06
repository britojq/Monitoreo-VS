"""
# ==============================================================================
# 💾 PERSISTENCIA TRANSACCIONAL ITAM Y AUDITORÍA: itam_persistence.py
# Ubicación: monitor/actas/itam_persistence.py
# License: GNU AGPLv3
# ==============================================================================
"""

from __future__ import annotations

import hashlib
import json
import logging
import subprocess
from pathlib import Path
from typing import Any, List, Optional

from monitor.actas.models import PageActaConfig

logger = logging.getLogger("actas_itam_persistence")


class ActaItamPersistence:
    """Gestiona la persistencia atómica en MariaDB SSOT para activos, periféricos y actas."""

    def __init__(self, base_dir: Optional[Path] = None):
        self.base_dir = base_dir or Path(__file__).resolve().parent.parent.parent

    @staticmethod
    def escape_sql(val: Optional[Any]) -> str:
        """Escapa un valor para inserción SQL segura."""
        if val is None:
            return "NULL"
        return "'" + str(val).replace("'", "''") + "'"

    def run_mysql(self, query: str) -> str:
        """Ejecuta una consulta SQL en MariaDB SSOT mediante subprocess seguro."""
        cmd = ["sudo", "mariadb", "monitoreo_vs", "-e", query]
        res = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return res.stdout

    def get_checkpoint(self) -> str:
        """Obtiene el último checkpoint registrado en bot_settings."""
        try:
            q = "SELECT setting_value FROM bot_settings WHERE setting_key = 'actas_batch_checkpoint';"
            out = self.run_mysql(q).strip().splitlines()
            return out[1].strip() if len(out) > 1 else "NONE"
        except Exception as e:
            logger.warning(f"No se pudo consultar checkpoint: {e}")
            return "NONE"

    def set_checkpoint(self, checkpoint_value: str) -> None:
        """Establece el checkpoint en bot_settings."""
        esc = self.escape_sql(checkpoint_value)
        sql = f"""
        INSERT INTO bot_settings (
            setting_key, setting_value, setting_group, title, description, type, created_at, updated_at
        ) VALUES (
            'actas_batch_checkpoint', {esc}, 'itam',
            'Punto de Control Actas', 'Punto de control de procesamiento de actas digitalizadas', 'text', NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW();
        """
        self.run_mysql(sql)

    def process_lot(self, lot_num: int, pages: List[PageActaConfig]) -> None:
        """Procesa un lote de actas bajo una transacción atómica MariaDB."""
        logger.info(f"▶ PROCESANDO LOTE {lot_num} ({len(pages)} actas)...")
        sql_stmts: List[str] = ["START TRANSACTION;"]

        for item in pages:
            code = item.code
            cust = item.custodian
            asset_info = item.asset
            mov_type = item.movement_type
            assigned_date = item.date
            pdf_rel_path = f"assignments/scans/{item.pdf_name}"
            obs = item.observations

            # 1. Resolver el ID del Activo en MariaDB
            asset_id: Optional[int] = asset_info.exists_id
            if not asset_id:
                check_q = f"SELECT id FROM inventory_assets WHERE serial_number = {self.escape_sql(asset_info.serial)};"
                out = self.run_mysql(check_q).strip().splitlines()
                if len(out) > 1 and out[1].strip():
                    asset_id = int(out[1].strip())

            if not asset_id:
                tag = f"NET-{asset_info.serial}"[:64]
                ip_val = asset_info.ip_address
                mac_val = asset_info.mac_address
                os_val = asset_info.operating_system or "Windows 7"
                status_val = "in_stock" if mov_type == "return" else "assigned"
                cust_name_val = None if mov_type == "return" else cust.name
                cust_id_val = None if mov_type == "return" else cust.cedula
                cust_dept_val = None if mov_type == "return" else cust.department

                ins_asset_sql = f"""
                INSERT INTO inventory_assets (
                    asset_tag, name, type, status, brand, model, serial_number,
                    ip_address, mac_address, operating_system,
                    monitored_site_id, custodian_name, custodian_id_number, custodian_department,
                    access_type, notes, is_active, created_at, updated_at
                ) VALUES (
                    {self.escape_sql(tag)}, {self.escape_sql(asset_info.name)}, {self.escape_sql(asset_info.type)}, {self.escape_sql(status_val)},
                    {self.escape_sql(asset_info.brand)}, {self.escape_sql(asset_info.model)}, {self.escape_sql(asset_info.serial)},
                    {self.escape_sql(ip_val)}, {self.escape_sql(mac_val)}, {self.escape_sql(os_val)},
                    1, {self.escape_sql(cust_name_val)}, {self.escape_sql(cust_id_val)}, {self.escape_sql(cust_dept_val)},
                    'VNC', {self.escape_sql(asset_info.notes)}, {0 if not ip_val else 1}, NOW(), NOW()
                );
                """
                sql_stmts.append(ins_asset_sql)
                sql_stmts.append("SET @current_asset_id = LAST_INSERT_ID();")
            else:
                if mov_type == "return":
                    up_asset_sql = f"""
                    UPDATE inventory_assets SET
                        custodian_name = NULL,
                        custodian_id_number = NULL,
                        custodian_department = NULL,
                        status = 'in_stock',
                        updated_at = NOW()
                    WHERE id = {asset_id};
                    """
                else:
                    up_asset_sql = f"""
                    UPDATE inventory_assets SET
                        custodian_name = {self.escape_sql(cust.name)},
                        custodian_id_number = {self.escape_sql(cust.cedula)},
                        custodian_department = {self.escape_sql(cust.department)},
                        status = 'assigned',
                        updated_at = NOW()
                    WHERE id = {asset_id};
                    """
                sql_stmts.append(up_asset_sql)
                sql_stmts.append(f"SET @current_asset_id = {asset_id};")

            # 2. Periféricos vinculados
            periph_json_list = []
            for p in item.peripherals:
                p_notes = p.notes or f"Registrado en Acta {code}"
                periph_json_list.append({
                    "type": p.label,
                    "brand": p.brand,
                    "model": p.model,
                    "serial_number": p.serial,
                    "observations": p_notes,
                })
                p_status = "in_stock" if mov_type == "return" else "assigned"
                prefix_map = {
                    "monitor": "MON",
                    "keyboard": "KBD",
                    "mouse": "MSE",
                    "surge_protector": "PWR",
                    "ups": "UPS",
                    "printer": "PRN",
                    "other": "CMP",
                }
                pref = prefix_map.get(p.type, "PER")
                hash_in = f"{p.type}_{p.serial}_{p.brand}_{code}"
                tag_p = f"{pref}-{hashlib.md5(hash_in.encode()).hexdigest()[:6].upper()}"

                if p.serial and p.serial != "N/A":
                    up_periph = f"""
                    INSERT INTO inventory_peripherals (
                        asset_tag, type, brand, model, serial_number, status,
                        monitored_site_id, current_asset_id, notes, created_at, updated_at
                    ) VALUES (
                        {self.escape_sql(tag_p)}, {self.escape_sql(p.type)}, {self.escape_sql(p.brand)}, {self.escape_sql(p.model)},
                        {self.escape_sql(p.serial)}, {self.escape_sql(p_status)}, 1, @current_asset_id, {self.escape_sql(p_notes)}, NOW(), NOW()
                    ) ON DUPLICATE KEY UPDATE
                        current_asset_id = IF({self.escape_sql(mov_type)} = 'return', NULL, @current_asset_id),
                        status = {self.escape_sql(p_status)},
                        updated_at = NOW();
                    """
                    sql_stmts.append(up_periph)
                else:
                    ins_periph = f"""
                    INSERT INTO inventory_peripherals (
                        asset_tag, type, brand, model, serial_number, status,
                        monitored_site_id, current_asset_id, notes, created_at, updated_at
                    ) VALUES (
                        {self.escape_sql(tag_p)}, {self.escape_sql(p.type)}, {self.escape_sql(p.brand)}, {self.escape_sql(p.model)},
                        NULL, {self.escape_sql(p_status)}, 1, @current_asset_id, {self.escape_sql(p_notes)}, NOW(), NOW()
                    );
                    """
                    sql_stmts.append(ins_periph)

            # 3. Registrar en inventory_assignments
            periph_json_str = json.dumps(periph_json_list, ensure_ascii=False)
            ins_acta = f"""
            INSERT INTO inventory_assignments (
                assignment_code, inventory_asset_id, user_id, custodian_name,
                custodian_id_number, custodian_department, monitored_site_id,
                movement_type, provenance, assigned_at, document_pdf_path,
                peripherals_json, observations, status, created_at, updated_at
            ) VALUES (
                {self.escape_sql(code)}, @current_asset_id, 1, {self.escape_sql(cust.name)},
                {self.escape_sql(cust.cedula)}, {self.escape_sql(cust.department)}, 1,
                {self.escape_sql(mov_type)}, 'fisico_existente', {self.escape_sql(assigned_date)}, {self.escape_sql(pdf_rel_path)},
                {self.escape_sql(periph_json_str)}, {self.escape_sql(obs)}, 'active', NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                inventory_asset_id = @current_asset_id,
                custodian_name = VALUES(custodian_name),
                custodian_id_number = VALUES(custodian_id_number),
                custodian_department = VALUES(custodian_department),
                movement_type = VALUES(movement_type),
                document_pdf_path = VALUES(document_pdf_path),
                peripherals_json = VALUES(peripherals_json),
                observations = VALUES(observations),
                updated_at = NOW();
            """
            sql_stmts.append(ins_acta)

            # 4. Auditoría
            audit_sql = f"""
            INSERT INTO audit_logs (
                user_id, user_name, user_role, event, module, entity_name,
                description, created_at, updated_at
            ) VALUES (
                1, 'Administrador del Sistema', 'admin', 'import_acta', 'itam', {self.escape_sql(code)},
                {self.escape_sql(f'Carga resiliente de Acta {code} ({mov_type}) - Custodio: {cust.name}')}, NOW(), NOW()
            );
            """
            sql_stmts.append(audit_sql)

        # 5. Checkpoint y commit
        ckpt_sql = f"""
        INSERT INTO bot_settings (
            setting_key, setting_value, setting_group, title, description, type, created_at, updated_at
        ) VALUES (
            'actas_batch_checkpoint', 'LOTE_{lot_num}_COMPLETED', 'itam',
            'Punto de Control Actas', 'Punto de control de procesamiento de actas digitalizadas', 'text', NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW();
        """
        sql_stmts.append(ckpt_sql)
        sql_stmts.append("COMMIT;")

        full_query = "\n".join(sql_stmts)
        self.run_mysql(full_query)
        logger.info(f"✅ LOTE {lot_num} PROCESADO Y CONFIRMADO EXITOSAMENTE.")

        self.clear_laravel_cache()

    def clear_laravel_cache(self) -> None:
        """Purga cachés de Laravel para reflejar de inmediato los cambios."""
        artisan_bin = self.base_dir / "web_portal" / "artisan"
        if artisan_bin.exists():
            subprocess.run(["php", str(artisan_bin), "cache:clear"], capture_output=True, check=False)
            subprocess.run(["php", str(artisan_bin), "view:clear"], capture_output=True, check=False)
