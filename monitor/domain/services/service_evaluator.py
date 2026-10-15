"""
# ==============================================================================
# 🧩 EVALUADOR PURO DE SERVICIOS Y ALERTAS: service_evaluator.py
# Principio de Responsabilidad Única (S) - Dominio Puro sin I/O ni dependencias externas.
# ==============================================================================
"""

from __future__ import annotations

import html
from datetime import datetime
from typing import Optional, Tuple


def format_duration(seconds: float | int) -> str:
    """Convierte segundos a formato legible en español."""
    total_sec = max(0, int(seconds))
    if total_sec < 60:
        return f"{total_sec} seg"
    minutes = total_sec // 60
    rem_sec = total_sec % 60
    if minutes < 60:
        return f"{minutes} min {rem_sec} seg" if rem_sec > 0 else f"{minutes} min"
    hours = minutes // 60
    rem_min = minutes % 60
    return f"{hours} h {rem_min} min"


def evaluate_state_transition(
    is_currently_up: bool,
    previously_up: Optional[bool],
) -> Tuple[bool, bool]:
    """
    Evalúa si ha ocurrido una transición de estado relevante para notificar.
    Retorna: (should_notify: bool, is_recovery: bool).
    """
    if previously_up is None:
        return False, False  # Primer ciclo de inicialización, sin notificar

    if previously_up and not is_currently_up:
        return True, False  # Caída (UP -> DOWN)

    if not previously_up and is_currently_up:
        return True, True   # Recuperación (DOWN -> UP)

    return False, False


def build_service_alert_message(
    name: str,
    target: str,
    protocol: str,
    is_down: bool,
    diagnosis: str = "Timeout",
    latency_ms: float = 0.0,
    duration_str: Optional[str] = None,
    timestamp: Optional[datetime] = None,
) -> str:
    """Construye el mensaje corporativo formateado en HTML para eventos de servicios."""
    safe_name = html.escape(name or "Servicio No Identificado")
    safe_target = html.escape(target or "N/A")
    safe_protocol = html.escape(protocol or "WEB")
    dt = timestamp or datetime.now()
    now_str = dt.strftime("%d/%m/%Y %I:%M:%S %p")

    if is_down:
        return (
            "🔴 <b>ALERTA: SERVICIO CAÍDO / SIN RESPUESTA</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Servicio:</b> <code>{safe_name}</code>\n"
            f"🌐 <b>Destino / URL:</b> <code>{safe_target}</code>\n"
            f"⚙️ <b>Protocolo:</b> {safe_protocol}\n"
            f"⚠️ <b>Diagnóstico:</b> {html.escape(diagnosis)}\n"
            f"🕒 <b>Detectado:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
    else:
        dur = duration_str or "Desconocido"
        lat_txt = f"{float(latency_ms):.1f} ms" if latency_ms else "< 10 ms"
        return (
            "✅ <b>RECUPERACIÓN: SERVICIO OPERATIVO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Servicio:</b> <code>{safe_name}</code>\n"
            f"🌐 <b>Destino / URL:</b> <code>{safe_target}</code>\n"
            f"⚙️ <b>Protocolo:</b> {safe_protocol}\n"
            f"📊 <b>Respuesta:</b> Operacional (Latencia: {lat_txt})\n"
            f"⏱️ <b>Tiempo fuera:</b> {dur}\n"
            f"🕒 <b>Restablecido:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )


def build_device_alert_message(
    name: str,
    ip: str,
    site_name: str,
    access_type: str = "ICMP",
    access_port: Optional[str | int] = None,
    is_down: bool = True,
    latency_ms: float = 0.0,
    duration_str: Optional[str] = None,
    timestamp: Optional[datetime] = None,
) -> str:
    """Construye el mensaje corporativo formateado en HTML para eventos de dispositivos de red."""
    safe_name = html.escape(name or "Dispositivo")
    safe_ip = html.escape(ip or "0.0.0.0")
    safe_site = html.escape(site_name or "Infraestructura de Red")
    safe_acc = html.escape(access_type or "ICMP")
    acc_txt = f"{safe_acc} (Puerto {access_port})" if access_port else safe_acc
    dt = timestamp or datetime.now()
    now_str = dt.strftime("%d/%m/%Y %I:%M:%S %p")

    if is_down:
        return (
            "🔴 <b>ALERTA: EQUIPO DESCONECTADO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Dispositivo:</b> <code>{safe_name}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{safe_ip}</code>\n"
            f"🏢 <b>Sede / Ubicación:</b> {safe_site}\n"
            f"⚙️ <b>Acceso:</b> {acc_txt}\n"
            "⚠️ <b>Estado:</b> Sin respuesta ICMP / Inalcanzable\n"
            f"🕒 <b>Detectado:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )
    else:
        dur = duration_str or "Desconocido"
        lat_txt = f"{float(latency_ms):.1f} ms" if latency_ms else "< 10 ms"
        return (
            "✅ <b>RECUPERACIÓN: EQUIPO RECONECTADO</b>\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            f"📍 <b>Dispositivo:</b> <code>{safe_name}</code>\n"
            f"🌐 <b>Dirección IP:</b> <code>{safe_ip}</code>\n"
            f"🏢 <b>Sede / Ubicación:</b> {safe_site}\n"
            f"⚙️ <b>Acceso:</b> {acc_txt}\n"
            f"📊 <b>Estado:</b> Conectividad restaurada (Latencia: {lat_txt})\n"
            f"⏱️ <b>Tiempo fuera:</b> {dur}\n"
            f"🕒 <b>Restablecido:</b> {now_str}\n"
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        )


def resolve_safe_target(
    target_type: str,
    node_role: str,
    owner_id: str,
    group_id: str,
    is_non_prod: bool,
) -> str:
    """
    Resuelve el destinatario de Telegram garantizando la Regla de Oro #1.
    Si es 'group' pero estamos en esclavo, desarrollo o testing, retorna el Owner.
    """
    if str(target_type).strip().lower() == "group":
        if is_non_prod or str(node_role).strip().lower() == "slave":
            return owner_id
        return group_id
    return owner_id
