"""
# ==============================================================================
# Suite Unitaria de Verificación de Reglas de Seguridad (SEC-001 a SEC-011)
# ==============================================================================
"""

import sys
from pathlib import Path
import pytest

BASE_DIR = Path(__file__).resolve().parent.parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from tools.audit_security_rules import (
    check_sec_001_zero_global_tokens,
    check_sec_002_cluster_hmac,
    check_sec_003_command_registry,
    check_sec_004_ldap_encryption,
    check_sec_005_dependency_pinning,
    check_sec_006_telnet_security_warning,
    check_sec_007_sanitized_readme,
    check_sec_008_rate_limiting,
    check_sec_009_snmp_encrypted_communities,
    check_sec_010_zero_tmp_flags,
    check_sec_011_composer_plugins_lockdown,
    check_arch_cleanliness,
    run_full_security_audit,
)


def test_sec_001_zero_global_tokens():
    passed, msg = check_sec_001_zero_global_tokens()
    assert passed, f"Fallo SEC-001: {msg}"


def test_sec_002_cluster_hmac():
    passed, msg = check_sec_002_cluster_hmac()
    assert passed, f"Fallo SEC-002: {msg}"


def test_sec_003_command_registry():
    passed, msg = check_sec_003_command_registry()
    assert passed, f"Fallo SEC-003: {msg}"


def test_sec_004_ldap_encryption():
    passed, msg = check_sec_004_ldap_encryption()
    assert passed, f"Fallo SEC-004: {msg}"


def test_sec_005_dependency_pinning():
    passed, msg = check_sec_005_dependency_pinning()
    assert passed, f"Fallo SEC-005: {msg}"


def test_sec_006_telnet_security_warning():
    passed, msg = check_sec_006_telnet_security_warning()
    assert passed, f"Fallo SEC-006: {msg}"


def test_sec_007_sanitized_readme():
    passed, msg = check_sec_007_sanitized_readme()
    assert passed, f"Fallo SEC-007: {msg}"


def test_sec_008_rate_limiting():
    passed, msg = check_sec_008_rate_limiting()
    assert passed, f"Fallo SEC-008: {msg}"


def test_sec_009_snmp_encrypted_communities():
    passed, msg = check_sec_009_snmp_encrypted_communities()
    assert passed, f"Fallo SEC-009: {msg}"


def test_sec_010_zero_tmp_flags():
    passed, msg = check_sec_010_zero_tmp_flags()
    assert passed, f"Fallo SEC-010: {msg}"


def test_sec_011_composer_plugins_lockdown():
    passed, msg = check_sec_011_composer_plugins_lockdown()
    assert passed, f"Fallo SEC-011: {msg}"


def test_arch_cleanliness():
    passed, msg = check_arch_cleanliness()
    assert passed, f"Fallo ARCH-001/002: {msg}"


def test_full_security_audit_runner():
    assert run_full_security_audit() is True
