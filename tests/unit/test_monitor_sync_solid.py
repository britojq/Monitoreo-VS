# tests/unit/test_monitor_sync_solid.py
import pytest
import ssl
from monitor.sync.dtos import (
    WanQualityResult,
    ServiceEvaluationResult,
    SiteDeviceEvaluationResult,
    SiteEvaluationResult,
    ProxyEvaluationResult,
    NetworkDeviceEvaluationResult,
)
from pathlib import Path
from monitor.sync.cluster import clean_mysql_dt, MasterClusterClient
from monitor.sync.probes import create_permissive_ssl_context
import monitor.sync as sync_package

def test_wan_quality_result_dto():
    dto = WanQualityResult(
        is_up=True,
        latency_ms=12.5,
        packet_loss_pct=0.0,
        jitter_ms=1.2,
        min_rtt_ms=10.0,
        max_rtt_ms=15.0,
        mdev_ms=1.2,
    )
    d = dto.to_dict()
    assert d["is_up"] is True
    assert d["latency_ms"] == 12.5
    assert d["packet_loss_pct"] == 0.0

def test_clean_mysql_dt():
    assert clean_mysql_dt(None) is None
    assert clean_mysql_dt("") is None
    assert clean_mysql_dt("null") is None
    assert clean_mysql_dt("2026-09-22T18:29:57.000000Z") == "2026-09-22 18:29:57"
    assert clean_mysql_dt("2026-09-22T18:29:57.000000Z", date_only=True) == "2026-09-22"
    assert clean_mysql_dt("2026-10-30 08:30:00") == "2026-10-30 08:30:00"

def test_permissive_ssl_context():
    ctx = create_permissive_ssl_context()
    assert isinstance(ctx, ssl.SSLContext)
    assert ctx.check_hostname is False
    assert ctx.verify_mode == ssl.CERT_NONE

def test_master_cluster_client_init():
    client = MasterClusterClient(master_url="http://192.168.1.250/", cluster_token="test_token")
    assert client.master_url == "http://192.168.1.250"
    assert client.cluster_token == "test_token"

def test_sync_package_exports():
    expected_attrs = [
        "get_last_scan_time",
        "update_last_scan_time",
        "should_run_web_scan",
        "create_permissive_ssl_context",
        "SSL_PERMISSIVE_CTX",
        "check_ping",
        "check_wan_quality",
        "check_tcp_port",
        "check_web_service",
        "get_known_proxy_auth",
        "check_proxy_service",
        "evaluate_service",
        "evaluate_site",
        "evaluate_proxy",
        "evaluate_network_device",
        "run_full_scan",
        "clean_mysql_dt",
        "sync_from_master",
    ]
    for attr in expected_attrs:
        assert hasattr(sync_package, attr), f"Falta el atributo canónico '{attr}' en monitor.sync"

