#!/usr/bin/env python3
# ==============================================================================
# 🧠 MOTOR DE ANÁLISIS PREDICTIVO E IA: predictive_analyzer.py (@IA_ValleSeco_bot)
# Detección de anomalías estadísticas, tendencias alcistas y proyección de capacidad
# Ubicación: /scripts/telegram-admin-bot/monitor/predictive_analyzer.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================

from __future__ import annotations

import argparse
import json
import logging
import math
import os
import sys
from datetime import datetime, timedelta
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import pymysql

try:
    from monitor.monitor_web_sync import get_db_connection
except ImportError:
    def get_db_connection():
        return pymysql.connect(
            host="127.0.0.1",
            user="root",
            password="",
            database="monitoreo_vs",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )

LOG_DIR = Path("/tmp/monitor")
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "predictive_analyzer.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] [predictive] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
logger = logging.getLogger("predictive")


def calculate_linear_regression(points: List[Tuple[float, float]]) -> Tuple[float, float, float]:
    """
    Calcula la regresión lineal y=mx+b y el coeficiente de determinación R^2.
    points: Lista de (x, y) donde x es tiempo relativo (horas o pasos) e y es el valor métrico.
    Retorna: (pendiente m, intercepto b, r_squared)
    """
    n = len(points)
    if n < 3:
        return 0.0, 0.0, 0.0

    sum_x = sum(p[0] for p in points)
    sum_y = sum(p[1] for p in points)
    sum_xx = sum(p[0] ** 2 for p in points)
    sum_yy = sum(p[1] ** 2 for p in points)
    sum_xy = sum(p[0] * p[1] for p in points)

    denom_m = (n * sum_xx - sum_x ** 2)
    if denom_m == 0:
        return 0.0, sum_y / n, 0.0

    m = (n * sum_xy - sum_x * sum_y) / denom_m
    b = (sum_y - m * sum_x) / n

    denom_r = math.sqrt(abs((n * sum_xx - sum_x ** 2) * (n * sum_yy - sum_y ** 2)))
    if denom_r == 0:
        r2 = 0.0
    else:
        r = (n * sum_xy - sum_x * sum_y) / denom_r
        r2 = r ** 2

    return m, b, r2


def calculate_stats(values: List[float]) -> Tuple[float, float]:
    """Calcula media y desviación estándar."""
    n = len(values)
    if n == 0:
        return 0.0, 0.0
    mean = sum(values) / n
    variance = sum((x - mean) ** 2 for x in values) / max(1, n - 1)
    std_dev = math.sqrt(variance)
    return mean, std_dev


class PredictiveAnalyzer:
    """Motor de análisis predictivo proactivo e inferencia estadística."""

    def __init__(self):
        pass

    def run_full_analysis(self) -> List[Dict[str, Any]]:
        """Ejecuta análisis sobre todas las entidades monitoreadas."""
        logger.info("Iniciando ciclo de análisis predictivo e inferencia de telemetría...")
        anomalies_detected: List[Dict[str, Any]] = []

        conn = get_db_connection()
        try:
            anomalies_detected.extend(self._analyze_network_devices(conn))
            anomalies_detected.extend(self._analyze_snmp_devices(conn))
            anomalies_detected.extend(self._analyze_services(conn))
            anomalies_detected.extend(self._analyze_sites(conn))

            # Guardar anomalías no redundantes en la base de datos
            saved_count = self._persist_anomalies(conn, anomalies_detected)
            logger.info(f"Análisis completado: {len(anomalies_detected)} evaluadas, {saved_count} registradas.")
            return anomalies_detected
        finally:
            conn.close()

    def _analyze_network_devices(self, conn) -> List[Dict[str, Any]]:
        """Analiza tendencias de latencia y disponibilidad en MonitoredNetworkDevice."""
        anomalies = []
        with conn.cursor() as cursor:
            # Obtener equipos de red
            cursor.execute("SELECT id, name, ip FROM monitored_network_devices")
            devices = cursor.fetchall()

            for dev in devices:
                dev_id = dev["id"]
                dev_name = dev["name"]

                # Obtener últimas 60 muestras de latencia
                cursor.execute(
                    """
                    SELECT latency_ms, checked_at 
                    FROM network_device_check_histories 
                    WHERE monitored_network_device_id = %s 
                    ORDER BY checked_at DESC LIMIT 60
                    """,
                    (dev_id,)
                )
                rows = cursor.fetchall()
                if len(rows) < 5:
                    continue

                rows.reverse()
                latencies = [float(r["latency_ms"]) for r in rows]
                mean_lat, std_lat = calculate_stats(latencies)
                latest_lat = latencies[-1]

                # 1. Detección de Outliers (Z-Score > 2.5)
                if std_lat > 1.0 and (latest_lat - mean_lat) > (2.5 * std_lat):
                    confidence = min(99.0, 70.0 + ((latest_lat - mean_lat) / std_lat) * 8.0)
                    anomalies.append({
                        "entity_type": "network_device",
                        "entity_id": dev_id,
                        "anomaly_type": "outlier",
                        "metric_name": "latency_ms",
                        "confidence": round(confidence, 2),
                        "description": f"Pico anómalo de latencia en {dev_name}: {latest_lat:.1f}ms (Promedio habitual: {mean_lat:.1f}ms, Desv: ±{std_lat:.1f}ms).",
                        "predicted_impact": f"Degradación transitoria en tiempo de respuesta hacia {dev['ip']}.",
                    })

                # 2. Tendencia sostenida alcista de degradación
                points = [(float(idx), val) for idx, val in enumerate(latencies)]
                slope, _, r2 = calculate_linear_regression(points)

                if slope > 0.15 and r2 > 0.60:
                    confidence = round(r2 * 100, 2)
                    projected_lat = latest_lat + (slope * 20)
                    anomalies.append({
                        "entity_type": "network_device",
                        "entity_id": dev_id,
                        "anomaly_type": "trend_upward",
                        "metric_name": "latency_ms",
                        "confidence": confidence,
                        "description": f"Tendencia continua al alza en latencia ICMP de {dev_name} (+{slope:.2f}ms/ciclo, R²={r2:.2f}).",
                        "predicted_impact": f"Latencia estimada de {projected_lat:.1f}ms en próximos ciclos de persistir la congestión.",
                    })

        return anomalies

    def _analyze_snmp_devices(self, conn) -> List[Dict[str, Any]]:
        """Analiza telemetría de dispositivos SNMP (CPU, Memoria, Interfaces)."""
        anomalies = []
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, name, ip_address FROM snmp_devices WHERE is_active = 1")
            devices = cursor.fetchall()

            for dev in devices:
                dev_id = dev["id"]
                dev_name = dev["name"]

                # Obtener métricas históricas de CPU o Memoria
                cursor.execute(
                    """
                    SELECT h.metric_value, h.collected_at, o.name as oid_name
                    FROM snmp_metrics_history h
                    JOIN snmp_oids o ON h.snmp_oid_id = o.id
                    WHERE h.snmp_device_id = %s
                    ORDER BY h.collected_at DESC LIMIT 50
                    """,
                    (dev_id,)
                )
                rows = cursor.fetchall()
                if len(rows) < 5:
                    continue

                # Agrupar por métrica
                by_metric: Dict[str, List[float]] = {}
                for r in rows:
                    m_name = (r.get("oid_name") or "metric").lower()
                    if r["metric_value"] is not None:
                        by_metric.setdefault(m_name, []).append(float(r["metric_value"]))

                for m_name, vals in by_metric.items():
                    if len(vals) < 5:
                        continue

                    # Ignorar contadores monótonos acumulativos normales (uptime, etc.)
                    if any(x in m_name for x in ["uptime", "timeticks", "sysuptime"]):
                        continue

                    vals.reverse()
                    latest = vals[-1]
                    mean, std = calculate_stats(vals)
                    points = [(float(i), v) for i, v in enumerate(vals)]
                    slope, _, r2 = calculate_linear_regression(points)

                    # Si hay tendencia alcista sostenida
                    if slope > 0.2 and r2 > 0.65:
                        confidence = round(r2 * 100, 2)
                        anomalies.append({
                            "entity_type": "snmp_device",
                            "entity_id": dev_id,
                            "anomaly_type": "trend_upward",
                            "metric_name": m_name,
                            "confidence": confidence,
                            "description": f"Crecimiento continuo en {m_name.upper()} para {dev_name} (pendiente: +{slope:.2f}/ciclo, R²={r2:.2f}).",
                            "predicted_impact": "Riesgo de saturación de recursos en dispositivo de red central.",
                        })

                    # Outlier en métrica SNMP
                    if std > 2.0 and (latest - mean) > (2.5 * std):
                        anomalies.append({
                            "entity_type": "snmp_device",
                            "entity_id": dev_id,
                            "anomaly_type": "outlier",
                            "metric_name": m_name,
                            "confidence": round(min(98.0, 75.0 + (latest - mean) / std * 5), 2),
                            "description": f"Desviación abrupta en {m_name.upper()} de {dev_name}: {latest:.1f} vs promedio {mean:.1f}.",
                            "predicted_impact": "Comportamiento operacional anómalo detectado por motor predictivo.",
                        })

        return anomalies

    def _analyze_services(self, conn) -> List[Dict[str, Any]]:
        """Analiza tiempos de respuesta en servicios monitoreados."""
        anomalies = []
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, name FROM monitored_services")
            services = cursor.fetchall()

            for srv in services:
                s_id = srv["id"]
                s_name = srv["name"]

                cursor.execute(
                    """
                    SELECT latency_ms, is_up 
                    FROM service_check_histories 
                    WHERE monitored_service_id = %s 
                    ORDER BY checked_at DESC LIMIT 50
                    """,
                    (s_id,)
                )
                rows = cursor.fetchall()
                if len(rows) < 5:
                    continue

                times = [float(r["latency_ms"]) for r in rows if r["latency_ms"] is not None]
                if len(times) < 5:
                    continue

                times.reverse()
                mean, std = calculate_stats(times)
                latest = times[-1]

                if std > 10.0 and (latest - mean) > (2.8 * std):
                    anomalies.append({
                        "entity_type": "service",
                        "entity_id": s_id,
                        "anomaly_type": "outlier",
                        "metric_name": "latency_ms",
                        "confidence": 88.50,
                        "description": f"Pico crítico de latencia en servicio '{s_name}': {latest:.0f}ms (media: {mean:.0f}ms, σ=±{std:.0f}ms).",
                        "predicted_impact": "Posible degradación de servicio o encolamiento en backend.",
                    })

        return anomalies

    def _analyze_sites(self, conn) -> List[Dict[str, Any]]:
        """Analiza latencia y disponibilidad de enlaces hacia sedes remotas."""
        anomalies = []
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, name FROM monitored_sites")
            sites = cursor.fetchall()

            for site in sites:
                site_id = site["id"]
                site_name = site["name"]

                cursor.execute(
                    """
                    SELECT latency_ms 
                    FROM site_check_histories 
                    WHERE monitored_site_id = %s 
                    ORDER BY checked_at DESC LIMIT 40
                    """,
                    (site_id,)
                )
                rows = cursor.fetchall()
                if len(rows) < 5:
                    continue

                times = [float(r["latency_ms"]) for r in rows if r["latency_ms"] is not None]
                if len(times) < 5:
                    continue

                times.reverse()
                mean, std = calculate_stats(times)
                latest = times[-1]

                if std > 5.0 and (latest - mean) > (2.5 * std):
                    anomalies.append({
                        "entity_type": "site",
                        "entity_id": site_id,
                        "anomaly_type": "outlier",
                        "metric_name": "latency_ms",
                        "confidence": 82.00,
                        "description": f"Fluctuación inusual en enlace hacia sede '{site_name}': {latest:.1f}ms.",
                        "predicted_impact": "Intermitencia potencial en transporte WAN o proveedor ISP.",
                    })

        return anomalies

    def _persist_anomalies(self, conn, anomalies: List[Dict[str, Any]]) -> int:
        """Guarda anomalías en la tabla predictive_anomalies evitando duplicados recientes."""
        if not anomalies:
            return 0

        saved = 0
        cutoff = (datetime.now() - timedelta(hours=4)).strftime("%Y-%m-%d %H:%M:%S")

        with conn.cursor() as cursor:
            for a in anomalies:
                # Comprobar si existe una anomalía idéntica no resuelta en las últimas 4 horas
                cursor.execute(
                    """
                    SELECT id FROM predictive_anomalies
                    WHERE entity_type = %s 
                      AND entity_id = %s 
                      AND anomaly_type = %s 
                      AND metric_name = %s 
                      AND acknowledged = 0
                      AND detected_at >= %s
                    LIMIT 1
                    """,
                    (a["entity_type"], a["entity_id"], a["anomaly_type"], a["metric_name"], cutoff)
                )
                if cursor.fetchone():
                    continue

                cursor.execute(
                    """
                    INSERT INTO predictive_anomalies 
                    (entity_type, entity_id, anomaly_type, metric_name, confidence, description, predicted_impact, detected_at, acknowledged)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), 0)
                    """,
                    (
                        a["entity_type"],
                        a["entity_id"],
                        a["anomaly_type"],
                        a["metric_name"],
                        a["confidence"],
                        a["description"],
                        a["predicted_impact"],
                    )
                )
                saved += 1

        return saved

    def get_recent_anomalies(self, limit: int = 15, unacknowledged_only: bool = False) -> List[Dict[str, Any]]:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                sql = (
                    "SELECT id, entity_type, entity_id, anomaly_type, metric_name, confidence, "
                    "description, predicted_impact, detected_at, acknowledged "
                    "FROM predictive_anomalies "
                )
                if unacknowledged_only:
                    sql += "WHERE acknowledged = 0 "
                sql += "ORDER BY detected_at DESC LIMIT %s"
                cursor.execute(sql, (limit,))
                return cursor.fetchall()
        finally:
            conn.close()

    def resolve_anomaly(self, anomaly_id: int) -> bool:
        conn = get_db_connection()
        try:
            with conn.cursor() as cursor:
                cursor.execute("UPDATE predictive_anomalies SET acknowledged = 1 WHERE id = %s", (anomaly_id,))
                return cursor.rowcount > 0
        finally:
            conn.close()


def main():
    parser = argparse.ArgumentParser(description="Motor de Análisis Predictivo e IA Estadística (Fase 7)")
    parser.add_argument("--analyze", action="store_true", help="Ejecutar análisis predictivo sobre toda la telemetría")
    parser.add_argument("--recent", action="store_true", help="Listar anomalías predictivas recientes")
    parser.add_argument("--resolve", type=int, metavar="ID", help="Marcar anomalía como atendida/resuelta")
    parser.add_argument("--limit", type=int, default=15, help="Límite de registros para --recent (def: 15)")
    parser.add_argument("--json", action="store_true", help="Salida en formato JSON")
    args = parser.parse_args()

    analyzer = PredictiveAnalyzer()

    if args.analyze:
        res = analyzer.run_full_analysis()
        if args.json:
            print(json.dumps(res, default=str, indent=2))
        else:
            print(f"✅ Ciclo de análisis predictivo completado. {len(res)} anomalías o tendencias identificadas.")
        return

    if args.resolve:
        ok = analyzer.resolve_anomaly(args.resolve)
        if ok:
            print(f"✅ Anomalía #{args.resolve} marcada como atendida/resuelta.")
        else:
            print(f"❌ No se encontró la anomalía #{args.resolve}.", file=sys.stderr)
            sys.exit(1)
        return

    if args.recent or (not args.analyze and not args.resolve):
        recent = analyzer.get_recent_anomalies(limit=args.limit)
        if args.json:
            print(json.dumps(recent, default=str, indent=2))
        else:
            print("\n============================================================")
            print("🧠 ANOMALÍAS PREDICTIVAS Y TENDENCIAS DETECTADAS")
            print("============================================================")
            if not recent:
                print("No se registran anomalías predictivas pendientes.")
            for a in recent:
                status_icon = "✅ Atendida" if a["acknowledged"] else "🚨 ACTIVA"
                print(f"• [#{a['id']:03d}] {status_icon} | {a['anomaly_type'].upper():<14} | Confianza: {a['confidence']}%")
                print(f"  Entidad:  {a['entity_type']} (ID: {a['entity_id']}) -> Métrica: {a.get('metric_name') or 'N/A'}")
                print(f"  Detalle:  {a['description']}")
                if a.get('predicted_impact'):
                    print(f"  Impacto:  {a['predicted_impact']}")
                print(f"  Fecha:    {a['detected_at']}")
                print("------------------------------------------------------------")
            print("============================================================\n")


if __name__ == "__main__":
    main()
