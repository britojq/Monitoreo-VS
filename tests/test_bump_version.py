"""
tests/test_bump_version.py
=============================================================================
Suite de Pruebas Unitarias para el Motor de Versionado Automático (Regla de Oro #12)
Plataforma Integral Valle Seco
=============================================================================
"""

import unittest
from tools.bump_version import parse_version, calculate_next_version


class TestBumpVersion(unittest.TestCase):
    def test_parse_version_standard(self):
        self.assertEqual(parse_version("5.0.1"), (5, 0, 1))
        self.assertEqual(parse_version("v5.0.1"), (5, 0, 1))
        self.assertEqual(parse_version("V5.1.0"), (5, 1, 0))
        self.assertEqual(parse_version("  v5.2.10  "), (5, 2, 10))

    def test_parse_version_invalid(self):
        with self.assertRaises(ValueError):
            parse_version("5.0")
        with self.assertRaises(ValueError):
            parse_version("5.0.1.2")
        with self.assertRaises(ValueError):
            parse_version("invalid")

    def test_bump_fix_increments_patch_by_10(self):
        self.assertEqual(calculate_next_version(5, 0, 1, "fix"), (5, 0, 11))
        self.assertEqual(calculate_next_version(5, 0, 11, "fix"), (5, 0, 21))
        self.assertEqual(calculate_next_version(5, 0, 81, "fix"), (5, 0, 91))

    def test_bump_fix_rollover_at_100(self):
        # 91 + 10 = 101 >= 100 -> minor + 1, patch = 0
        self.assertEqual(calculate_next_version(5, 0, 91, "fix"), (5, 1, 0))
        # 90 + 10 = 100 >= 100 -> minor + 1, patch = 0
        self.assertEqual(calculate_next_version(5, 0, 90, "fix"), (5, 1, 0))
        # En versión 5.1.0, el siguiente fix es 5.1.10
        self.assertEqual(calculate_next_version(5, 1, 0, "fix"), (5, 1, 10))
        # En versión 5.1.90, el siguiente fix desborda a 5.2.0
        self.assertEqual(calculate_next_version(5, 1, 90, "fix"), (5, 2, 0))

    def test_bump_feat_increments_minor_and_resets_patch_to_0(self):
        self.assertEqual(calculate_next_version(5, 0, 1, "feat"), (5, 1, 0))
        self.assertEqual(calculate_next_version(5, 0, 11, "feat"), (5, 1, 0))
        self.assertEqual(calculate_next_version(5, 1, 40, "feat"), (5, 2, 0))

    def test_bump_both_increments_minor_and_sets_patch_to_10(self):
        self.assertEqual(calculate_next_version(5, 0, 1, "both"), (5, 1, 10))
        self.assertEqual(calculate_next_version(5, 0, 21, "both"), (5, 1, 10))
        self.assertEqual(calculate_next_version(5, 2, 0, "both"), (5, 3, 10))

    def test_bump_invalid_type(self):
        with self.assertRaises(ValueError):
            calculate_next_version(5, 0, 1, "invalid_type")


if __name__ == "__main__":
    unittest.main()
