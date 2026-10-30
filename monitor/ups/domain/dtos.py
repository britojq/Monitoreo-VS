"""
# ==============================================================================
# 🔋 UPS DOMAIN DTOS: dtos.py
# DTOs inmutables para telemetría, métricas y especificaciones de hardware de UPS
# Ubicación: /opt/sonar/monitor/ups/domain/dtos.py
# ==============================================================================
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class BatteryMetricsDto:
    """Métricas de estado y nivel del banco de baterías."""
    battery_voltage: float
    battery_percent: int
    v_cell: float
    total_cells: int = 96


@dataclass(frozen=True)
class UpsTelemetrySnapshotDto:
    """Instantánea inmutable de telemetría devuelta por protocolo Megatec Q1."""
    input_voltage: float
    input_fault_voltage: float
    output_voltage: float
    load_percent: int
    frequency: float
    battery_voltage: float
    battery_percent: int
    temperature_c: float
    is_online: bool
    is_on_battery: bool
    is_battery_low: bool
    is_bypass: bool
    is_ups_failed: bool
    beeper_on: bool
    raw_status_bits: str

    def to_dict(self) -> dict:
        """Serializa a diccionario para compatibilidad con código cliente legado."""
        return {
            "input_voltage": self.input_voltage,
            "input_fault_voltage": self.input_fault_voltage,
            "output_voltage": self.output_voltage,
            "load_percent": self.load_percent,
            "frequency": self.frequency,
            "battery_voltage": self.battery_voltage,
            "battery_percent": self.battery_percent,
            "temperature_c": self.temperature_c,
            "is_online": self.is_online,
            "is_on_battery": self.is_on_battery,
            "is_battery_low": self.is_battery_low,
            "is_bypass": self.is_bypass,
            "is_ups_failed": self.is_ups_failed,
            "beeper_on": self.beeper_on,
            "raw_status_bits": self.raw_status_bits,
        }


@dataclass(frozen=True)
class UpsHardwareInfoDto:
    """Información de identidad y firmware devuelta por comando 'I'."""
    raw_info: str
    brand: str
    model: str
    firmware_version: str
    is_corrupt_padding: bool

    def to_dict(self) -> dict:
        return {
            "raw_info": self.raw_info,
            "brand": self.brand,
            "model": self.model,
            "firmware_version": self.firmware_version,
            "is_corrupt_padding": self.is_corrupt_padding,
        }


@dataclass(frozen=True)
class UpsRatingDto:
    """Especificaciones nominales de placa devueltas por comando 'F'."""
    raw_rating: str
    rating_voltage: float
    rating_current: float
    rating_battery_voltage: float
    rating_frequency: float

    def to_dict(self) -> dict:
        return {
            "raw_rating": self.raw_rating,
            "rating_voltage": self.rating_voltage,
            "rating_current": self.rating_current,
            "rating_battery_voltage": self.rating_battery_voltage,
            "rating_frequency": self.rating_frequency,
        }


@dataclass(frozen=True)
class UsbMetadataDto:
    """Metadatos USB obtenidos vía sysfs."""
    usb_vendor: str
    usb_model: str
    usb_serial: str

    def to_dict(self) -> dict:
        return {
            "usb_vendor": self.usb_vendor,
            "usb_model": self.usb_model,
            "usb_serial": self.usb_serial,
        }
