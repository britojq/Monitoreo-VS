"""
# ==============================================================================
# 📋 REPLICADOR DE INVENTARIO ITAM: itam_replicator.py
# Sincroniza activos de inventario, componentes, periféricos, asignaciones y tickets.
# Ubicación: /opt/sonar/monitor/sync/cluster/replicators/itam_replicator.py
# ==============================================================================
"""
import json
from typing import Any, Dict

from monitor.sync.cluster.contracts import TableReplicatorProtocol
from monitor.sync.cluster.datetime_sanitizer import clean_mysql_dt


class ItamReplicator(TableReplicatorProtocol):
    """Replicador atómico para inventory_assets, componentes, periféricos, asignaciones y mantenimientos."""

    def replicate(self, cursor: Any, data: Dict[str, Any]) -> int:
        cfg = data.get("config", {})
        total_synced = 0

        # 1. Activos de Inventario
        inv_assets = cfg.get("inventory_assets", [])
        if inv_assets:
            inv_asset_sql = """
                INSERT IGNORE INTO inventory_assets
                (id, asset_tag, name, type, status, brand, model, serial_number, mac_address, ip_address,
                 monitored_site_id, location_detail, monitored_network_device_id, current_user_id,
                 custodian_name, custodian_id_number, custodian_department, operating_system, os_kernel,
                 purchase_date, warranty_expiration, last_glpi_sync_at, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                asset_tag=VALUES(asset_tag), name=VALUES(name), type=VALUES(type), status=VALUES(status),
                brand=VALUES(brand), model=VALUES(model), serial_number=VALUES(serial_number),
                mac_address=VALUES(mac_address), ip_address=VALUES(ip_address),
                monitored_site_id=VALUES(monitored_site_id), location_detail=VALUES(location_detail),
                monitored_network_device_id=VALUES(monitored_network_device_id), current_user_id=VALUES(current_user_id),
                custodian_name=VALUES(custodian_name), custodian_id_number=VALUES(custodian_id_number),
                custodian_department=VALUES(custodian_department), operating_system=VALUES(operating_system),
                os_kernel=VALUES(os_kernel), purchase_date=VALUES(purchase_date),
                warranty_expiration=VALUES(warranty_expiration), last_glpi_sync_at=VALUES(last_glpi_sync_at),
                notes=VALUES(notes), updated_at=VALUES(updated_at)
            """
            inv_asset_records = [
                (
                    ia["id"], ia.get("asset_tag"), ia["name"], ia.get("type", "desktop"),
                    ia.get("status", "in_stock"), ia.get("brand"), ia.get("model"),
                    ia.get("serial_number"), ia.get("mac_address"), ia.get("ip_address"),
                    ia.get("monitored_site_id"), ia.get("location_detail"),
                    ia.get("monitored_network_device_id"), ia.get("current_user_id"),
                    ia.get("custodian_name"), ia.get("custodian_id_number"),
                    ia.get("custodian_department"), ia.get("operating_system"),
                    ia.get("os_kernel"), clean_mysql_dt(ia.get("purchase_date"), date_only=True),
                    clean_mysql_dt(ia.get("warranty_expiration"), date_only=True),
                    clean_mysql_dt(ia.get("last_glpi_sync_at")), ia.get("notes"),
                    clean_mysql_dt(ia.get("created_at")),
                    clean_mysql_dt(ia.get("updated_at") or ia.get("created_at"))
                )
                for ia in inv_assets if "id" in ia and "name" in ia
            ]
            if inv_asset_records:
                cursor.executemany(inv_asset_sql, inv_asset_records)
                total_synced += len(inv_asset_records)

        # 2. Componentes de Inventario
        inv_comps = cfg.get("inventory_components", [])
        if inv_comps:
            inv_comp_sql = """
                INSERT IGNORE INTO inventory_components
                (id, inventory_asset_id, type, name, brand, model, capacity, serial_number, slot_or_bus, status, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                inventory_asset_id=VALUES(inventory_asset_id), type=VALUES(type), name=VALUES(name),
                brand=VALUES(brand), model=VALUES(model), capacity=VALUES(capacity),
                serial_number=VALUES(serial_number), slot_or_bus=VALUES(slot_or_bus),
                status=VALUES(status), updated_at=VALUES(updated_at)
            """
            inv_comp_records = [
                (
                    ic["id"], ic["inventory_asset_id"], ic["type"], ic["name"],
                    ic.get("brand"), ic.get("model"), ic.get("capacity"),
                    ic.get("serial_number"), ic.get("slot_or_bus"),
                    ic.get("status", "active"), clean_mysql_dt(ic.get("created_at")),
                    clean_mysql_dt(ic.get("updated_at") or ic.get("created_at"))
                )
                for ic in inv_comps if "id" in ic and "inventory_asset_id" in ic
            ]
            if inv_comp_records:
                cursor.executemany(inv_comp_sql, inv_comp_records)
                total_synced += len(inv_comp_records)

        # 3. Periféricos de Inventario
        inv_periphs = cfg.get("inventory_peripherals", [])
        if inv_periphs:
            inv_peri_sql = """
                INSERT IGNORE INTO inventory_peripherals
                (id, asset_tag, type, brand, model, serial_number, status, monitored_site_id, current_asset_id, current_user_id, notes, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                asset_tag=VALUES(asset_tag), type=VALUES(type), brand=VALUES(brand),
                model=VALUES(model), serial_number=VALUES(serial_number), status=VALUES(status),
                monitored_site_id=VALUES(monitored_site_id), current_asset_id=VALUES(current_asset_id),
                current_user_id=VALUES(current_user_id), notes=VALUES(notes), updated_at=VALUES(updated_at)
            """
            inv_peri_records = [
                (
                    ip["id"], ip.get("asset_tag"), ip["type"], ip.get("brand"),
                    ip.get("model"), ip.get("serial_number"), ip.get("status", "in_stock"),
                    ip.get("monitored_site_id"), ip.get("current_asset_id"),
                    ip.get("current_user_id"), ip.get("notes"),
                    clean_mysql_dt(ip.get("created_at")),
                    clean_mysql_dt(ip.get("updated_at") or ip.get("created_at"))
                )
                for ip in inv_periphs if "id" in ip and "type" in ip
            ]
            if inv_peri_records:
                cursor.executemany(inv_peri_sql, inv_peri_records)
                total_synced += len(inv_peri_records)

        # 4. Asignaciones de Inventario
        inv_assigns = cfg.get("inventory_assignments", [])
        if inv_assigns:
            inv_asg_sql = """
                INSERT INTO inventory_assignments
                (id, assignment_code, inventory_asset_id, user_id, custodian_name, custodian_id_number,
                 custodian_department, custodian_role, phone_extension, monitored_site_id,
                 checkpoint_name, gate_operator_name, departure_time, entry_time, control_number,
                 movement_type, provenance, provenance_other, peripherals_json, hardware_specs,
                 assigned_at, returned_at, document_pdf_path, status, delivered_by_user_id,
                 observations, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                assignment_code=VALUES(assignment_code), inventory_asset_id=VALUES(inventory_asset_id),
                user_id=VALUES(user_id), custodian_name=VALUES(custodian_name),
                custodian_id_number=VALUES(custodian_id_number), custodian_department=VALUES(custodian_department),
                custodian_role=VALUES(custodian_role), phone_extension=VALUES(phone_extension),
                monitored_site_id=VALUES(monitored_site_id), checkpoint_name=VALUES(checkpoint_name),
                gate_operator_name=VALUES(gate_operator_name), departure_time=VALUES(departure_time),
                entry_time=VALUES(entry_time), control_number=VALUES(control_number),
                movement_type=VALUES(movement_type), provenance=VALUES(provenance),
                provenance_other=VALUES(provenance_other), peripherals_json=VALUES(peripherals_json),
                hardware_specs=VALUES(hardware_specs), assigned_at=VALUES(assigned_at),
                returned_at=VALUES(returned_at), document_pdf_path=VALUES(document_pdf_path),
                status=VALUES(status), delivered_by_user_id=VALUES(delivered_by_user_id),
                observations=VALUES(observations), updated_at=VALUES(updated_at)
            """
            inv_asg_records = [
                (
                    ia["id"], ia["assignment_code"], ia["inventory_asset_id"],
                    ia.get("user_id"), ia["custodian_name"], ia.get("custodian_id_number"),
                    ia.get("custodian_department"), ia.get("custodian_role"),
                    ia.get("phone_extension"), ia.get("monitored_site_id"),
                    ia.get("checkpoint_name"), ia.get("gate_operator_name"),
                    ia.get("departure_time"), ia.get("entry_time"), ia.get("control_number"),
                    ia.get("movement_type", "assignment"), ia.get("provenance", "stock"),
                    ia.get("provenance_other"),
                    json.dumps(ia.get("peripherals_json")) if isinstance(ia.get("peripherals_json"), (list, dict)) else ia.get("peripherals_json"),
                    json.dumps(ia.get("hardware_specs")) if isinstance(ia.get("hardware_specs"), (list, dict)) else ia.get("hardware_specs"),
                    clean_mysql_dt(ia.get("assigned_at")), clean_mysql_dt(ia.get("returned_at")),
                    ia.get("document_pdf_path"), ia.get("status", "active"),
                    ia.get("delivered_by_user_id"), ia.get("observations"),
                    clean_mysql_dt(ia.get("created_at")),
                    clean_mysql_dt(ia.get("updated_at") or ia.get("created_at"))
                )
                for ia in inv_assigns if "id" in ia and "assignment_code" in ia
            ]
            if inv_asg_records:
                cursor.executemany(inv_asg_sql, inv_asg_records)
                total_synced += len(inv_asg_records)

        # 5. Tickets de Mantenimiento
        inv_tickets = cfg.get("maintenance_tickets", [])
        if inv_tickets:
            inv_tkt_sql = """
                INSERT INTO maintenance_tickets
                (id, ticket_code, inventory_asset_id, type, status, priority, title, description,
                 scheduled_at, started_at, completed_at, technician_user_id, resolution_notes,
                 parts_replaced_json, warranty_applied, cost, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                ticket_code=VALUES(ticket_code), inventory_asset_id=VALUES(inventory_asset_id),
                type=VALUES(type), status=VALUES(status), priority=VALUES(priority),
                title=VALUES(title), description=VALUES(description),
                scheduled_at=VALUES(scheduled_at), started_at=VALUES(started_at),
                completed_at=VALUES(completed_at), technician_user_id=VALUES(technician_user_id),
                resolution_notes=VALUES(resolution_notes), parts_replaced_json=VALUES(parts_replaced_json),
                warranty_applied=VALUES(warranty_applied), cost=VALUES(cost), updated_at=VALUES(updated_at)
            """
            inv_tkt_records = [
                (
                    it["id"], it["ticket_code"], it["inventory_asset_id"],
                    it.get("type", "preventive"), it.get("status", "scheduled"),
                    it.get("priority", "medium"), it["title"], it.get("description"),
                    clean_mysql_dt(it.get("scheduled_at")), clean_mysql_dt(it.get("started_at")),
                    clean_mysql_dt(it.get("completed_at")), it.get("technician_user_id"),
                    it.get("resolution_notes"),
                    json.dumps(it.get("parts_replaced_json")) if isinstance(it.get("parts_replaced_json"), (list, dict)) else it.get("parts_replaced_json"),
                    1 if it.get("warranty_applied") else 0, float(it.get("cost", 0.0) or 0.0),
                    clean_mysql_dt(it.get("created_at")),
                    clean_mysql_dt(it.get("updated_at") or it.get("created_at"))
                )
                for it in inv_tickets if "id" in it and "ticket_code" in it
            ]
            if inv_tkt_records:
                cursor.executemany(inv_tkt_sql, inv_tkt_records)
                total_synced += len(inv_tkt_records)

        # 6. Historial de Componentes
        inv_hist = data.get("inventory_components_histories", [])
        if inv_hist:
            inv_h_sql = """
                INSERT IGNORE INTO inventory_components_histories
                (id, inventory_asset_id, action, component_type, old_value, new_value, reason, registered_by_user_id, recorded_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            inv_h_records = [
                (
                    ih["id"], ih["inventory_asset_id"], ih["action"], ih["component_type"],
                    json.dumps(ih.get("old_value")) if isinstance(ih.get("old_value"), (list, dict)) else ih.get("old_value"),
                    json.dumps(ih.get("new_value")) if isinstance(ih.get("new_value"), (list, dict)) else ih.get("new_value"),
                    ih.get("reason"), ih.get("registered_by_user_id"),
                    clean_mysql_dt(ih.get("recorded_at"))
                )
                for ih in inv_hist if "id" in ih and "inventory_asset_id" in ih
            ]
            if inv_h_records:
                cursor.executemany(inv_h_sql, inv_h_records)
                total_synced += len(inv_h_records)

        return total_synced
