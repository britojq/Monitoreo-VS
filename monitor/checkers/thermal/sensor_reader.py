"""
Lector de sensores térmicos físicos de CPU mediante sysfs (/sys/class/hwmon).
"""
import json
import logging
from pathlib import Path
import subprocess
from typing import Any, Dict, List, Optional

from monitor.checkers.thermal.constants import (
    DEFAULT_TEMP_CRITICAL,
    DEFAULT_TEMP_WARNING,
)

logger = logging.getLogger("monitor.checkers.thermal.sensor_reader")


def get_local_ip_addresses() -> List[str]:
    """Obtiene las direcciones IP locales no loopback del host."""
    ips = []
    try:
        res = subprocess.run(["hostname", "-I"], capture_output=True, text=True, timeout=2.0)
        for ip in res.stdout.strip().split():
            if ip and not ip.startswith("127.") and ":" not in ip:
                ips.append(ip)
    except Exception:
        pass
    return ips or ["127.0.0.1"]


def get_cpu_temperatures() -> Dict[str, Any]:
    """Lee temperaturas de la CPU directamente desde sysfs (/sys/class/hwmon)."""
    package_temp: Optional[float] = None
    core_temps: Dict[str, float] = {}
    other_temps: Dict[str, float] = {}
    found_coretemp = False

    hwmon_base = Path("/sys/class/hwmon")
    if hwmon_base.exists():
        try:
            for hdir in sorted(hwmon_base.iterdir()):
                if not hdir.is_dir():
                    continue
                name_file = hdir / "name"
                drv_name = name_file.read_text(encoding="utf-8").strip() if name_file.exists() else ""

                if drv_name in ("coretemp", "k10temp"):
                    found_coretemp = True
                    for temp_file in sorted(hdir.glob("temp*_input")):
                        label_file = temp_file.parent / f"{temp_file.name[:-6]}_label"
                        label = label_file.read_text(encoding="utf-8").strip() if label_file.exists() else temp_file.name
                        try:
                            milli_c = int(temp_file.read_text(encoding="utf-8").strip())
                            deg_c = round(milli_c / 1000.0, 1)
                            if "Package" in label or "Tdie" in label or "Tctl" in label:
                                package_temp = deg_c
                            elif "Core" in label:
                                core_temps[label] = deg_c
                            else:
                                other_temps[label] = deg_c
                        except Exception:
                            continue
                elif drv_name in ("acpitz", "cpu_thermal") and not found_coretemp:
                    for temp_file in sorted(hdir.glob("temp*_input")):
                        try:
                            milli_c = int(temp_file.read_text(encoding="utf-8").strip())
                            deg_c = round(milli_c / 1000.0, 1)
                            other_temps[f"{drv_name}_{temp_file.stem}"] = deg_c
                        except Exception:
                            continue
        except Exception as e:
            logger.warning(f"Error explorando /sys/class/hwmon: {e}")

    if not package_temp and not core_temps and not other_temps:
        thermal_base = Path("/sys/class/thermal")
        if thermal_base.exists():
            try:
                for zdir in sorted(thermal_base.glob("thermal_zone*")):
                    temp_f = zdir / "temp"
                    type_f = zdir / "type"
                    z_type = type_f.read_text(encoding="utf-8").strip() if type_f.exists() else zdir.name
                    if temp_f.exists():
                        try:
                            milli_c = int(temp_f.read_text(encoding="utf-8").strip())
                            deg_c = round(milli_c / 1000.0, 1)
                            other_temps[z_type] = deg_c
                        except Exception:
                            continue
            except Exception as e:
                logger.warning(f"Error en fallback /sys/class/thermal: {e}")

    if not package_temp and not core_temps and not other_temps:
        try:
            res = subprocess.run(["sensors", "-j"], capture_output=True, text=True, timeout=2.0)
            if res.returncode == 0 and res.stdout:
                data = json.loads(res.stdout)
                for chip, sensors in data.items():
                    if "coretemp" in chip.lower() or "k10temp" in chip.lower():
                        for s_name, s_vals in sensors.items():
                            if isinstance(s_vals, dict):
                                for k, val in s_vals.items():
                                    if "input" in k and isinstance(val, (int, float)):
                                        val_f = round(float(val), 1)
                                        if "package" in s_name.lower():
                                            package_temp = val_f
                                        elif "core" in s_name.lower():
                                            core_temps[s_name] = val_f
        except Exception:
            pass

    all_readings = []
    if package_temp is not None:
        all_readings.append(package_temp)
    all_readings.extend(core_temps.values())
    all_readings.extend(other_temps.values())

    max_temp = max(all_readings) if all_readings else 0.0
    avg_temp = round(sum(all_readings) / len(all_readings), 1) if all_readings else 0.0

    if max_temp >= DEFAULT_TEMP_CRITICAL:
        level = "CRITICAL"
        badge = "🔴"
    elif max_temp >= DEFAULT_TEMP_WARNING:
        level = "WARNING"
        badge = "🟡"
    else:
        level = "NORMAL"
        badge = "🟢"

    return {
        "package_temp": package_temp if package_temp is not None else max_temp,
        "core_temps": core_temps,
        "other_temps": other_temps,
        "max_temp": max_temp,
        "avg_temp": avg_temp,
        "level": level,
        "badge": badge
    }
