#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ WIDGET VISUAL DE SEGURIDAD Y ADVERTENCIA: shield_dialog.py (@IA_ValleSeco_bot)
# Interfaz gráfica Qt para advertencias de terminal, contención y desbloqueo maestro
# Ubicación: /opt/sonar/monitor/shield_dialog.py
# Sistema Objetivo: Debian 12 / 13 GNU/Linux (amd64) o Ubuntu Server (KDE Plasma)
# License: GNU Affero General Public License v3.0 
# Author: Operador ATIT (@britojab:@britojq), https://britojab.com
# Copyright (c) 2026 Operador ATIT
# ==============================================================================
"""

import argparse
import os
import subprocess
import sys
from pathlib import Path

# Asegurar variables de entorno gráfico para KDE Plasma (Wayland / X11)
def setup_gui_environment() -> None:
    """Configura el entorno gráfico para KDE Plasma (Wayland / X11) y previene fallos por display."""
    if "QT_QPA_PLATFORM" not in os.environ:
        os.environ["QT_QPA_PLATFORM"] = "wayland;xcb"

    # Runtime dir y Wayland display
    uid = os.getuid()
    candidates_runtime = [f"/run/user/{uid}", "/run/user/1000"]
    for rdir in candidates_runtime:
        if Path(rdir).exists():
            if "XDG_RUNTIME_DIR" not in os.environ:
                os.environ["XDG_RUNTIME_DIR"] = rdir
            break

    runtime_dir = Path(os.environ.get("XDG_RUNTIME_DIR", f"/run/user/{uid}"))
    if "WAYLAND_DISPLAY" not in os.environ:
        if (runtime_dir / "wayland-0").exists() or Path("/run/user/1000/wayland-0").exists():
            os.environ["WAYLAND_DISPLAY"] = "wayland-0"

    if "DISPLAY" not in os.environ:
        os.environ["DISPLAY"] = ":0"

    # Resolver XAUTHORITY si existe para compatibilidad con XCB
    if "XAUTHORITY" not in os.environ:
        try:
            xauth_candidates = list(runtime_dir.glob("xauth_*"))
            if not xauth_candidates and Path("/run/user/1000").exists():
                xauth_candidates = list(Path("/run/user/1000").glob("xauth_*"))
            if xauth_candidates:
                os.environ["XAUTHORITY"] = str(xauth_candidates[0])
            elif Path("/home/britojab/.Xauthority").exists():
                os.environ["XAUTHORITY"] = "/home/britojab/.Xauthority"
        except Exception:
            pass


def is_gui_available() -> bool:
    """Comprueba si existe un socket de servidor gráfico accesible (Wayland o X11)."""
    # 1. Comprobar socket Wayland
    xdg_runtime = Path(os.environ.get("XDG_RUNTIME_DIR", f"/run/user/{os.getuid()}"))
    wayland_display = os.environ.get("WAYLAND_DISPLAY", "wayland-0")
    if (xdg_runtime / wayland_display).exists() or Path(f"/run/user/1000/{wayland_display}").exists():
        return True

    # 2. Comprobar socket X11
    display = os.environ.get("DISPLAY", ":0")
    disp_num = display.split(":")[-1].split(".")[0] if ":" in display else "0"
    if Path(f"/tmp/.X11-unix/X{disp_num}").exists():
        return True

    return False


setup_gui_environment()

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

try:
    from PyQt5.QtCore import (
        QBasicTimer,
        QCoreApplication,
        Qt,
        QtCriticalMsg,
        QtFatalMsg,
        QTimer,
        QtWarningMsg,
        qInstallMessageHandler,
    )
    from PyQt5.QtGui import QColor, QFont, QIcon, QLinearGradient, QPainter, QPalette
    from PyQt5.QtWidgets import (
        QApplication,
        QDialog,
        QGraphicsDropShadowEffect,
        QHBoxLayout,
        QLabel,
        QLineEdit,
        QProgressBar,
        QPushButton,
        QVBoxLayout,
        QWidget,
    )
except ImportError as e:
    print(f"Error importando PyQt5: {e}", file=sys.stderr)
    sys.exit(0)

import json
from datetime import datetime
from monitor.core_shield import verify_system_auth_token

MASTER_OVERRIDE_FLAG = Path("/dev/shm/shield_master_override_active")


def is_master_override_active() -> bool:
    """Comprueba si el Override Maestro de emergencia del Owner está activo."""
    try:
        return MASTER_OVERRIDE_FLAG.exists() and MASTER_OVERRIDE_FLAG.stat().st_size > 0
    except Exception:
        return False


def execute_master_override(candidate: str, source: str = "Diálogo Qt") -> bool:
    """Activa el override maestro localmente sin acoplamientos a base de datos."""
    payload = {
        "activated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "activated_by": "Administrador (Token validado)",
        "source": source,
        "expires": "until_reboot",
    }
    try:
        MASTER_OVERRIDE_FLAG.write_text(json.dumps(payload), encoding="utf-8")
        try:
            os.chmod(str(MASTER_OVERRIDE_FLAG), 0o666)
        except Exception:
            pass
        return True
    except Exception:
        return False


class SecurityShieldDialog(QDialog):
    def __init__(self, mode: str, attempt: int = 1, max_attempts: int = 3, seconds: int = 10, dry_run: bool = False):
        super().__init__()
        self.mode = mode
        self.attempt = attempt
        self.max_attempts = max_attempts
        self.total_seconds = seconds
        self.remaining_seconds = seconds
        self.dry_run = dry_run

        self.init_ui()

    def init_ui(self):
        # Configuración de ventana: siempre encima, sin bordes del SO, centrada
        self.setWindowFlags(Qt.WindowStaysOnTopHint | Qt.FramelessWindowHint | Qt.Tool)
        self.setAttribute(Qt.WA_TranslucentBackground, True)
        self.setFixedSize(620, 270)

        # Contenedor principal con estilo y bordes redondeados
        container = QWidget(self)
        container.setGeometry(10, 10, 600, 250)

        # Efecto de sombra exterior profunda
        shadow = QGraphicsDropShadowEffect(self)
        shadow.setBlurRadius(30)
        shadow.setXOffset(0)
        shadow.setYOffset(10)

        # Selección de colores según el modo
        if self.mode == "warning":
            border_color = "#f59e0b"  # Ámbar
            badge_bg = "rgba(245, 158, 11, 0.15)"
            badge_text = "#fbbf24"
            badge_title = f"● ADVERTENCIA DE SEGURIDAD (INFRACCIÓN {self.attempt} DE {self.max_attempts})"
            shadow.setColor(QColor(245, 158, 11, 160))
            progress_gradient = "qlineargradient(x1:0, y1:0, x2:1, y2:0, stop:0 #d97706, stop:1 #f59e0b)"
        else:
            border_color = "#ef4444"  # Rojo crítico
            badge_bg = "rgba(239, 68, 68, 0.15)"
            badge_text = "#f87171"
            badge_title = "● PROTOCOLO CRÍTICO DE CONTENCIÓN ACTIVO" if self.mode == "lockdown" else "● REINCIDENCIA CRÍTICA: LÍMITE DE FALTAS ALCANZADO"
            shadow.setColor(QColor(239, 68, 68, 200))
            progress_gradient = "qlineargradient(x1:0, y1:0, x2:1, y2:0, stop:0 #b91c1c, stop:1 #ef4444)"

        container.setGraphicsEffect(shadow)
        container.setStyleSheet(f"""
            QWidget {{
                background-color: #0b1329;
                border: 2px solid {border_color};
                border-radius: 14px;
            }}
        """)

        layout = QVBoxLayout(container)
        layout.setContentsMargins(26, 20, 26, 20)
        layout.setSpacing(12)

        # 1. Cabecera Badge
        header_layout = QHBoxLayout()
        badge_label = QLabel(badge_title)
        badge_label.setStyleSheet(f"""
            QLabel {{
                background-color: {badge_bg};
                color: {badge_text};
                font-family: 'Inter', 'Segoe UI', 'DejaVu Sans', sans-serif;
                font-size: 11px;
                font-weight: bold;
                letter-spacing: 0.5px;
                border: 1px solid {border_color};
                border-radius: 6px;
                padding: 4px 10px;
            }}
        """)
        header_layout.addWidget(badge_label)
        header_layout.addStretch()
        layout.addLayout(header_layout)

        # 2. Título Principal
        title_label = QLabel()
        title_label.setFont(QFont("DejaVu Sans", 15, QFont.Bold))

        desc_label = QLabel()
        desc_label.setStyleSheet("border: none; background: transparent; color: #cbd5e1; line-height: 1.4;")
        desc_label.setFont(QFont("DejaVu Sans", 10))
        desc_label.setWordWrap(True)

        if self.mode == "warning":
            title_label.setText("TERMINAL CLAUSURADA POR SEGURIDAD")
            title_label.setStyleSheet("border: none; background: transparent; color: #ffffff;")
            desc_label.setText(
                "La consola interactiva ha sido pausada preventivamente por intento de ejecución "
                "no autorizada de comandos con privilegios administrativos (<b>sudo</b>).<br><br>"
                f"<b>Infracción {self.attempt} de {self.max_attempts}:</b> Al registrarse el 3er intento no autorizado, "
                "el equipo <b>se reiniciará automáticamente</b> por política de seguridad y protección del sistema.<br><br>"
                "👉 <i>Para autorizar esta acción y cerrar esta ventana, presione el botón <b>'SOY YO'</b> en el chat privado del Administrador en Telegram.</i>"
            )
        elif self.mode == "reboot_limit":
            title_label.setText("LÍMITE MÁXIMO DE REINCIDENCIAS ALCANZADO")
            title_label.setStyleSheet("border: none; background: transparent; color: #ef4444;")
            desc_label.setText(
                f"Se ha alcanzado el límite de <b>{self.attempt} de {self.max_attempts} intentos no autorizados</b> de comandos administrativos en terminal.<br><br>"
                "Por política de seguridad y protección activa del servidor, "
                "<b>el equipo se reiniciará automáticamente.</b>"
            )
        else:  # lockdown
            title_label.setText("REINICIO DE CONTENCIÓN POR SEGURIDAD")
            title_label.setStyleSheet("border: none; background: transparent; color: #ef4444;")
            desc_label.setText(
                "Se ha detectado un intento de ejecución de comandos no autorizados en una terminal interactiva.<br><br>"
                "Para salvaguardar la integridad y estabilidad del sistema, "
                "<b>el equipo se reiniciará inmediatamente.</b>"
            )

        layout.addWidget(title_label)
        layout.addWidget(desc_label)

        # 3. Contador Regresivo y Barra de Progreso
        countdown_box = QHBoxLayout()
        countdown_box.setSpacing(10)

        self.timer_label = QLabel()
        self.timer_label.setStyleSheet(f"""
            QLabel {{
                border: none;
                background: transparent;
                color: {border_color};
                font-family: 'DejaVu Sans', monospace;
                font-size: 12px;
                font-weight: bold;
            }}
        """)
        self.update_timer_text()

        countdown_box.addWidget(self.timer_label)
        countdown_box.addStretch()
        layout.addLayout(countdown_box)

        # Barra de progreso
        self.progress_bar = QProgressBar()
        self.progress_bar.setRange(0, self.total_seconds * 10)
        self.progress_bar.setValue(self.total_seconds * 10)
        self.progress_bar.setTextVisible(False)
        self.progress_bar.setFixedHeight(8)
        self.progress_bar.setStyleSheet(f"""
            QProgressBar {{
                background-color: #1e293b;
                border: none;
                border-radius: 4px;
            }}
            QProgressBar::chunk {{
                background: {progress_gradient};
                border-radius: 4px;
            }}
        """)
        layout.addWidget(self.progress_bar)

        # Centrar en pantalla
        self.center_on_screen()

        # Iniciar Timer de 100ms para suavidad en la barra
        self.timer = QTimer(self)
        self.timer.setInterval(100)
        self.timer.timeout.connect(self.on_tick)
        self.ticks_remaining = self.total_seconds * 10
        self.timer.start()

    def center_on_screen(self):
        screen = QApplication.primaryScreen()
        if screen:
            geo = screen.geometry()
            x = (geo.width() - self.width()) // 2
            y = (geo.height() - self.height()) // 2
            self.move(x, y)

    def update_timer_text(self):
        if self.mode == "warning":
            self.timer_label.setText(f"Esta notificación se cerrará automáticamente en {self.remaining_seconds}s...")
        else:
            dry_str = " (SIMULACIÓN DRY-RUN)" if self.dry_run else ""
            self.timer_label.setText(f"REINICIANDO EL EQUIPO EN {self.remaining_seconds} SEGUNDOS...{dry_str}")

    def on_tick(self):
        self.ticks_remaining -= 1
        self.progress_bar.setValue(self.ticks_remaining)

        new_remaining = (self.ticks_remaining + 9) // 10
        if new_remaining != self.remaining_seconds:
            self.remaining_seconds = new_remaining
            self.update_timer_text()

        if self.ticks_remaining <= 0:
            self.timer.stop()
            self.on_timeout()
            self.timer.stop()
            self.on_timeout()

    def on_timeout(self):
        self.close()
        if self.mode in ("lockdown", "reboot_limit"):
            if is_master_override_active():
                print("[SENTINEL] Override maestro activo. Reinicio cancelado.")
                sys.exit(0)

            if self.dry_run:
                print(f"[DRY-RUN] Cuenta regresiva completada. Acción de reinicio simulada (Modo: {self.mode}).")
            else:
                print(f"Cuenta regresiva completada. Ejecutando reinicio forzado del sistema...")
                try:
                    subprocess.run(["sudo", "systemctl", "reboot"], check=False)
                except Exception as e:
                    print(f"Error ejecutando reboot: {e}", file=sys.stderr)
        sys.exit(0)


def qt_safe_message_handler(mode: int, context: object, message: str) -> None:
    """Captura mensajes de Qt y previene abortos fatales (SIGABRT / core dump)."""
    if mode == QtFatalMsg:
        sys.stderr.write(f"[SHIELD-DIALOG] Interceptado error fatal de Qt (evitando abort): {message}\n")
        sys.stderr.flush()
        sys.exit(0)
    elif mode == QtCriticalMsg:
        sys.stderr.write(f"[SHIELD-DIALOG] Qt Crítico: {message}\n")
    elif mode == QtWarningMsg:
        if "QSocketNotifier" not in message and "Empty key passed" not in message:
            sys.stderr.write(f"[SHIELD-DIALOG] Qt Aviso: {message}\n")


def main() -> None:
    parser = argparse.ArgumentParser(description="Sentinel Shield Security Dialog (PyQt5)")
    parser.add_argument("--mode", required=True, choices=["lockdown", "warning", "reboot_limit"],
                        help="Modo visual de la alerta (lockdown, warning, reboot_limit)")
    parser.add_argument("--attempt", type=int, default=1, help="Número de infracción actual (1, 2, 3)")
    parser.add_argument("--max-attempts", type=int, default=3, help="Máximo de infracciones permitidas (default: 3)")
    parser.add_argument("--seconds", type=int, default=10, help="Duración de la cuenta regresiva en segundos (default: 10)")
    parser.add_argument("--dry-run", action="store_true", help="Simular sin ejecutar reboot real")

    args = parser.parse_args()

    if not is_gui_available():
        sys.stderr.write("[SHIELD-DIALOG] Servidor gráfico no disponible (modo headless o sin sesión activa). Omitiendo diálogo Qt.\n")
        sys.exit(0)

    qInstallMessageHandler(qt_safe_message_handler)

    try:
        app = QApplication(sys.argv)
    except SystemExit:
        sys.exit(0)
    except Exception as e:
        sys.stderr.write(f"[SHIELD-DIALOG] Error inicializando QApplication: {e}\n")
        sys.exit(0)

    dialog = SecurityShieldDialog(
        mode=args.mode,
        attempt=args.attempt,
        max_attempts=args.max_attempts,
        seconds=args.seconds,
        dry_run=args.dry_run,
    )
    dialog.show()
    sys.exit(app.exec_())


if __name__ == "__main__":
    main()
