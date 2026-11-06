"""
# ==============================================================================
# 🧪 PRUEBAS UNITARIAS FASE 6: test_phase6_actas_modularization.py
# Valida la subsanación integral de ARC-05 y la modularización en monitor/actas/.
# ==============================================================================
"""

import inspect
from pathlib import Path
from unittest.mock import MagicMock, patch
import pytest

from monitor.actas import (
    ActaItamPersistence,
    ActaPdfExtractor,
    ActaQrDecoder,
    PAGES_REGISTRY,
)
from monitor.actas.models import (
    AssetData,
    CustodianData,
    PageActaConfig,
    PeripheralData,
)
from tools.process_actas_batch_resilient import resolve_pdf_source


class TestActaPdfExtractor:
    """Pruebas unitarias para ActaPdfExtractor."""

    def test_extract_page_raises_file_not_found_on_missing_source(self, tmp_path):
        non_existent = tmp_path / "missing.pdf"
        output_pdf = tmp_path / "out.pdf"
        with pytest.raises(FileNotFoundError):
            ActaPdfExtractor.extract_page(non_existent, 1, output_pdf)

    @patch("subprocess.run")
    def test_extract_page_calls_ghostscript_with_structured_args(self, mock_run, tmp_path):
        mock_source = tmp_path / "source.pdf"
        mock_source.write_bytes(b"%PDF-1.4 dummy")
        output_pdf = tmp_path / "out.pdf"

        mock_run.return_value = MagicMock(returncode=0, stdout="", stderr="")

        ActaPdfExtractor.extract_page(mock_source, 3, output_pdf)

        mock_run.assert_called_once()
        args, kwargs = mock_run.call_args
        cmd = args[0]
        assert cmd[0] == "gs"
        assert "-dFirstPage=3" in cmd
        assert "-dLastPage=3" in cmd
        assert f"-sOutputFile={output_pdf}" in cmd
        assert str(mock_source) in cmd


class TestActaQrDecoder:
    """Pruebas unitarias para ActaQrDecoder."""

    def test_calculate_file_hash(self, tmp_path):
        test_file = tmp_path / "sample.pdf"
        content = b"PDF-TEST-HASH-DATA"
        test_file.write_bytes(content)

        expected_hash = "d8cc3fe595927f2c0defbc1668906b5012dddec819b0fd1b2f6e065275d99daf"
        actual_hash = ActaQrDecoder.calculate_file_hash(test_file)
        assert actual_hash == expected_hash

    def test_extract_acta_code_from_filename(self):
        filename = "UINF-4.05-2026-0025_DEVOLUCION_VALIDADORA_NAHYR_GENNER.pdf"
        code = ActaQrDecoder.extract_acta_code_from_filename(filename)
        assert code == "UINF-4.05-2026-0025"

        invalid_filename = "documento_sin_codigo.pdf"
        assert ActaQrDecoder.extract_acta_code_from_filename(invalid_filename) is None

    def test_verify_acta_integrity(self, tmp_path):
        test_file = tmp_path / "acta.pdf"
        test_file.write_bytes(b"Valid PDF Content")

        assert ActaQrDecoder.verify_acta_integrity(test_file) is True

        empty_file = tmp_path / "empty.pdf"
        empty_file.touch()
        assert ActaQrDecoder.verify_acta_integrity(empty_file) is False


class TestActaItamPersistence:
    """Pruebas unitarias para ActaItamPersistence."""

    def test_escape_sql(self):
        assert ActaItamPersistence.escape_sql(None) == "NULL"
        assert ActaItamPersistence.escape_sql("LENOVO") == "'LENOVO'"
        assert ActaItamPersistence.escape_sql("D'Ocon") == "'D''Ocon'"

    @patch.object(ActaItamPersistence, "run_mysql")
    def test_get_and_set_checkpoint(self, mock_run_mysql):
        mock_run_mysql.return_value = "setting_value\nLOTE_2_COMPLETED"
        persistence = ActaItamPersistence()

        ckpt = persistence.get_checkpoint()
        assert ckpt == "LOTE_2_COMPLETED"

        persistence.set_checkpoint("LOTE_3_COMPLETED")
        mock_run_mysql.assert_called()
        last_sql = mock_run_mysql.call_args[0][0]
        assert "LOTE_3_COMPLETED" in last_sql
        assert "actas_batch_checkpoint" in last_sql


class TestActasRegistryAndArc05Compliance:
    """Verificación de integridad del registro y erradicación total de rutas personales."""

    def test_pages_registry_structure_and_completeness(self):
        assert len(PAGES_REGISTRY) == 5
        total_actas = sum(len(pages) for pages in PAGES_REGISTRY.values())
        assert total_actas == 25

        for lot_id, actas in PAGES_REGISTRY.items():
            assert len(actas) == 5
            for a in actas:
                assert a.code.startswith("UINF-4.05-2026-")
                assert a.pdf_name.endswith(".pdf")
                assert a.custodian.name != ""
                assert a.asset.serial != ""

    def test_resolve_pdf_source_cli_precedence(self, tmp_path):
        custom_pdf = tmp_path / "custom.pdf"
        custom_pdf.write_bytes(b"%PDF-1.4 dummy")

        resolved = resolve_pdf_source(str(custom_pdf))
        assert resolved == custom_pdf

    def test_zero_hardcoded_developer_paths_in_actas_scripts(self):
        """Verifica que los scripts de actas no contengan rutas a directorios personales."""
        base_dir = Path(__file__).resolve().parent.parent.parent
        actas_scripts = [
            base_dir / "tools" / "process_actas_batch_resilient.py",
            base_dir / "tools" / "apply_transmision_actas.py",
            base_dir / "tools" / "apply_scanned_actas_batch.py",
            base_dir / "monitor" / "actas" / "itam_persistence.py",
            base_dir / "monitor" / "actas" / "pdf_extractor.py",
            base_dir / "monitor" / "actas" / "qr_decoder.py",
            base_dir / "monitor" / "actas" / "registry.py",
        ]

        forbidden = "/home/britojab/Escritorio"
        for script in actas_scripts:
            content = script.read_text(encoding="utf-8")
            assert forbidden not in content, f"Ruta personal hardcodeada encontrada en {script.name}"
