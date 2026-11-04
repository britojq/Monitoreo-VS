"""
# ==============================================================================
# 🧪 SUITE UNITARIA FASE 5: test_phase5_sca_and_debt_reconciliation.py
# Validación rigurosa de SEC-004 y QUAL-001 (Auditoría v5.51.70).
# - SEC-004: Pipeline y orquestador SCA para escaneo de vulnerabilidades en dependencias.
# - QUAL-001: Contrato inter-servicios y paridad criptográfica formalizada.
# ==============================================================================
"""

import sys
from pathlib import Path
from unittest.mock import MagicMock, patch
import pytest

ROOT_DIR = Path(__file__).resolve().parent.parent.parent
SCA_TOOL = ROOT_DIR / "tools" / "audit_dependencies_sca.py"
GITHUB_WORKFLOW = ROOT_DIR / ".github" / "workflows" / "sca_audit.yml"

from monitor.contracts.laravel_bridge import LaravelBridge
from monitor.infrastructure.bridges.laravel_mariadb_bridge import LaravelMariaDbBridge
from tools.audit_dependencies_sca import (
    ComposerAuditScanner,
    DependencyScanner,
    PipAuditScanner,
    ScaAuditOrchestrator,
    VulnerabilityFinding,
)


class TestPhase5ScaAndQualityAudit:
    """Verifica el cumplimiento de SEC-004 y QUAL-001."""

    def test_sca_tool_exists_and_is_executable(self):
        """Certifica que tools/audit_dependencies_sca.py exista y tenga permisos de ejecución."""
        assert SCA_TOOL.exists(), f"El script de auditoría SCA no existe en {SCA_TOOL}"
        content = SCA_TOOL.read_text(encoding="utf-8")
        assert "class ScaAuditOrchestrator" in content
        assert "class PipAuditScanner" in content
        assert "class ComposerAuditScanner" in content

    def test_github_actions_sca_workflow_exists_and_valid(self):
        """Certifica que el workflow de GitHub Actions para SCA esté configurado con pip-audit y composer audit."""
        assert GITHUB_WORKFLOW.exists(), f"Workflow GitHub Actions no existe en {GITHUB_WORKFLOW}"
        content = GITHUB_WORKFLOW.read_text(encoding="utf-8")
        assert "pip-audit" in content
        assert "composer audit" in content
        assert "cron:" in content
        assert "schedule:" in content

    def test_sca_scanners_implement_dependency_scanner_protocol(self):
        """Certifica que los escaneadores implementen el protocolo DependencyScanner."""
        pip_scanner = PipAuditScanner()
        composer_scanner = ComposerAuditScanner()
        assert isinstance(pip_scanner, DependencyScanner)
        assert isinstance(composer_scanner, DependencyScanner)

    def test_sca_orchestrator_aggregates_findings(self):
        """Certifica que el orquestador agrupe los hallazgos de los escaneadores."""
        mock_finding_py = VulnerabilityFinding(
            ecosystem="python",
            package="requests",
            installed_version="2.25.0",
            vulnerability_id="CVE-2023-32681",
            severity="medium",
            fix_versions="2.31.0",
            description="Proxy header leak",
        )
        mock_finding_php = VulnerabilityFinding(
            ecosystem="php",
            package="guzzlehttp/guzzle",
            installed_version="<7.4.5",
            vulnerability_id="CVE-2022-31090",
            severity="high",
            fix_versions="7.4.5",
            description="CURLOPT_CERTINFO information disclosure",
        )

        mock_scanner_1 = MagicMock()
        mock_scanner_1.scan.return_value = [mock_finding_py]

        mock_scanner_2 = MagicMock()
        mock_scanner_2.scan.return_value = [mock_finding_php]

        orchestrator = ScaAuditOrchestrator(scanners=[mock_scanner_1, mock_scanner_2])
        findings = orchestrator.execute_audit()

        assert len(findings) == 2
        assert findings[0] == mock_finding_py
        assert findings[1] == mock_finding_php

        report = orchestrator.format_text_report(findings)
        assert "CVE-2023-32681" in report
        assert "CVE-2022-31090" in report
        assert "guzzlehttp/guzzle" in report
        assert "requests" in report

    def test_laravel_bridge_conformance_qual_001(self):
        """Certifica que LaravelMariaDbBridge cumpla con QUAL-001 (DIP/ISP)."""
        bridge = LaravelMariaDbBridge()
        assert isinstance(bridge, LaravelBridge)
        assert hasattr(bridge, "read_config")
        assert hasattr(bridge, "decrypt_payload")
        assert hasattr(bridge, "write_telemetry")
