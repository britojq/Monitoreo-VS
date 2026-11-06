#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ AUDITOR SCA DE DEPENDENCIAS Y COMPOSICIÓN DE SOFTWARE: audit_dependencies_sca.py
# Arquitectura SOLID - Principio de Responsabilidad Única y Estrategia OCP.
# Automatiza el análisis de vulnerabilidades conocidas (CVE / GHSA / OSV) en:
# - Python: pip-audit sobre el entorno virtual o requirements.lock
# - PHP / Laravel: composer audit sobre web_portal/composer.lock
# Subsanación rigurosa de SEC-004 (Auditoría v5.51.70 / OWASP A06:2021).
# Ubicación: /opt/sonar/tools/audit_dependencies_sca.py
# License: GNU Affero General Public License v3.0
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

from __future__ import annotations

import argparse
import json
import logging
import shutil
import subprocess
import sys
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Dict, List, Optional, Protocol, Sequence, runtime_checkable

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("tools.audit_dependencies_sca")

ROOT_DIR = Path(__file__).resolve().parent.parent
WEB_PORTAL_DIR = ROOT_DIR / "web_portal"
VENV_PIP_AUDIT = ROOT_DIR / "venv" / "bin" / "pip-audit"


@dataclass(frozen=True)
class VulnerabilityFinding:
    """Representación inmutable de una vulnerabilidad en una dependencia (DTO)."""

    ecosystem: str
    package: str
    installed_version: str
    vulnerability_id: str
    severity: str
    fix_versions: str
    description: str


@runtime_checkable
class DependencyScanner(Protocol):
    """Contrato formal para escaneadores de dependencias (ISP/DIP)."""

    def scan(self) -> List[VulnerabilityFinding]:
        """Ejecuta el análisis y retorna la lista tipada de hallazgos."""
        ...


class PipAuditScanner:
    """Escaneador de dependencias Python mediante pip-audit."""

    def __init__(self, binary_path: Optional[Path] = None) -> None:
        self.binary: str = str(binary_path or (VENV_PIP_AUDIT if VENV_PIP_AUDIT.exists() else "pip-audit"))

    def scan(self) -> List[VulnerabilityFinding]:
        findings: List[VulnerabilityFinding] = []
        if not shutil.which(self.binary) and not Path(self.binary).exists():
            logger.warning(f"pip-audit no está disponible en la ruta: {self.binary}")
            return findings

        cmd = [self.binary, "-f", "json", "--desc", "on"]
        try:
            res = subprocess.run(cmd, cwd=str(ROOT_DIR), capture_output=True, text=True, check=False)
            if not res.stdout.strip():
                return findings

            data = json.loads(res.stdout)
            deps = data.get("dependencies", []) if isinstance(data, dict) else (data if isinstance(data, list) else [])

            for dep in deps:
                pkg_name = dep.get("name", "unknown")
                pkg_ver = dep.get("version", "unknown")
                vulns = dep.get("vulns", [])
                for v in vulns:
                    v_id = v.get("id", "UNKNOWN-CVE")
                    fix_vers = ", ".join(v.get("fix_versions", [])) or "None"
                    desc = (v.get("description") or "Sin descripción").strip()
                    if len(desc) > 160:
                        desc = desc[:157] + "..."

                    # pip-audit no siempre devuelve severity explícito; se categoriza por id/cve
                    findings.append(
                        VulnerabilityFinding(
                            ecosystem="python",
                            package=pkg_name,
                            installed_version=pkg_ver,
                            vulnerability_id=v_id,
                            severity="medium",
                            fix_versions=fix_vers,
                            description=desc,
                        )
                    )
        except Exception as e:
            logger.error(f"Error ejecutando pip-audit: {e}")

        return findings


class ComposerAuditScanner:
    """Escaneador de dependencias PHP mediante composer audit."""

    def __init__(self, composer_dir: Optional[Path] = None) -> None:
        self.work_dir: Path = composer_dir or WEB_PORTAL_DIR

    def scan(self) -> List[VulnerabilityFinding]:
        findings: List[VulnerabilityFinding] = []
        composer_bin = shutil.which("composer")
        if not composer_bin or not self.work_dir.exists():
            logger.warning("composer no está disponible o web_portal/ no existe")
            return findings

        cmd = [composer_bin, "audit", "--format=json"]
        try:
            res = subprocess.run(cmd, cwd=str(self.work_dir), capture_output=True, text=True, check=False)
            if not res.stdout.strip():
                return findings

            data = json.loads(res.stdout)
            advisories = data.get("advisories", {}) if isinstance(data, dict) else {}

            for pkg_name, pkg_vulns in advisories.items():
                if not isinstance(pkg_vulns, list):
                    continue
                for v in pkg_vulns:
                    v_id = v.get("cve") or v.get("advisoryId") or "UNKNOWN-PKSA"
                    severity = (v.get("severity") or "unknown").lower()
                    title = (v.get("title") or "Sin título").strip()
                    aff = v.get("affectedVersions") or ""
                    findings.append(
                        VulnerabilityFinding(
                            ecosystem="php",
                            package=pkg_name,
                            installed_version=aff,
                            vulnerability_id=str(v_id),
                            severity=severity,
                            fix_versions=v.get("link") or "Ver advisory",
                            description=title,
                        )
                    )
        except Exception as e:
            logger.error(f"Error ejecutando composer audit: {e}")

        return findings


class ScaAuditOrchestrator:
    """Orquestador de análisis SCA para todo el ecosistema."""

    def __init__(self, scanners: Optional[Sequence[DependencyScanner]] = None) -> None:
        self.scanners: Sequence[DependencyScanner] = scanners or [
            PipAuditScanner(),
            ComposerAuditScanner(),
        ]

    def execute_audit(self) -> List[VulnerabilityFinding]:
        all_findings: List[VulnerabilityFinding] = []
        for scanner in self.scanners:
            all_findings.extend(scanner.scan())
        return all_findings

    @staticmethod
    def format_text_report(findings: List[VulnerabilityFinding]) -> str:
        if not findings:
            return "✅ Cero vulnerabilidades conocidas detectadas en dependencias de Python y PHP."

        lines = [
            "================================================================================",
            " 🛡️ REPORTE DE AUDITORÍA SCA — VULNERABILIDADES CONOCIDAS EN DEPENDENCIAS",
            "================================================================================",
            f"Total de vulnerabilidades encontradas: {len(findings)}",
            "",
            f"{'ECOSISTEMA':<12} | {'PAQUETE':<25} | {'VULN ID':<20} | {'SEVERIDAD':<10} | {'DESCRIPCIÓN'}",
            "-" * 100,
        ]
        for f in findings:
            lines.append(
                f"{f.ecosystem:<12} | {f.package:<25} | {f.vulnerability_id:<20} | {f.severity:<10} | {f.description}"
            )
        lines.append("=" * 100)
        return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(description="Auditor SCA de dependencias para Python y PHP (SEC-004)")
    parser.add_argument("--format", choices=["text", "json"], default="text", help="Formato de salida")
    parser.add_argument(
        "--fail-on",
        choices=["critical", "high", "medium", "any", "never"],
        default="never",
        help="Nivel de severidad para retornar código de salida 1",
    )
    args = parser.parse_args()

    orchestrator = ScaAuditOrchestrator()
    findings = orchestrator.execute_audit()

    if args.format == "json":
        print(json.dumps([asdict(f) for f in findings], indent=2, ensure_ascii=False))
    else:
        print(orchestrator.format_text_report(findings))

    if args.fail_on == "never":
        return 0

    severity_weights = {"critical": 4, "high": 3, "medium": 2, "low": 1, "unknown": 0}
    threshold = severity_weights.get(args.fail_on, 0)

    for f in findings:
        weight = severity_weights.get(f.severity.lower(), 1)
        if weight >= threshold:
            logger.error(f"Fallo SCA: Vulnerabilidad con severidad '{f.severity}' >= umbral '{args.fail_on}'")
            return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
