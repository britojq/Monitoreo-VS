#!/usr/bin/env python3
"""
# ==============================================================================
# 🛡️ WIDGET VISUAL DE SEGURIDAD Y ADVERTENCIA: shield_dialog.py (@IA_ValleSeco_bot)
# Interfaz gráfica Qt para advertencias de terminal, contención y desbloqueo maestro
# Ubicación: /scripts/telegram-admin-bot/monitor/shield_dialog.py
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
def setup_gui_environment():
    if "DISPLAY" not in os.environ:
        os.environ["DISPLAY"] = ":0"
    if "WAYLAND_DISPLAY" not in os.environ and Path("/run/user/1000/wayland-0").exists():
        os.environ["WAYLAND_DISPLAY"] = "wayland-0"
    if "XDG_RUNTIME_DIR" not in os.environ:
        os.environ["XDG_RUNTIME_DIR"] = "/run/user/1000"


setup_gui_environment()

BASE_DIR = Path(__file__).resolve().parent.parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

try:
    from PyQt5.QtCore import QBasicTimer, QCoreApplication, Qt, QTimer
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
    sys.exit(1)

from monitor.core_shield import verify_system_auth_token
from monitor.terminal_shield import execute_master_override, is_master_override_active


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
        self.setFixedSize(640, 440)

        # Contenedor principal con estilo y bordes redondeados
        container = QWidget(self)
        container.setGeometry(10, 10, 620, 420)

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
                "La consola interactiva ha sido cerrada por intento de ejecución "
                "no autorizada de comandos con privilegios administrativos (<b>sudo</b>).<br><br>"
                f"<b>Infracción {self.attempt} de {self.max_attempts}:</b> Al registrarse el 3er intento no autorizado, "
                "el equipo <b>se reiniciará automáticamente</b> por política de seguridad y protección del sistema."
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
        self.progress_bar.setFixedHeight(6)
        self.progress_bar.setStyleSheet(f"""
            QProgressBar {{
                background-color: #1e293b;
                border: none;
                border-radius: 3px;
            }}
            QProgressBar::chunk {{
                background: {progress_gradient};
                border-radius: 3px;
            }}
        """)
        layout.addWidget(self.progress_bar)

        # 4. Sección de Validación Administrativa
        override_card = QWidget()
        override_card.setStyleSheet("""
            QWidget {
                background-color: rgba(15, 23, 42, 0.85);
                border: 1px solid #334155;
                border-radius: 8px;
            }
        """)
        override_vbox = QVBoxLayout(override_card)
        override_vbox.setContentsMargins(12, 8, 12, 8)
        override_vbox.setSpacing(6)

        key_hint_label = QLabel("AUTORIZACIÓN ADMINISTRATIVA — TOKEN DE CONTROL")
        key_hint_label.setStyleSheet("border: none; background: transparent; color: #94a3b8; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; font-weight: bold; letter-spacing: 0.5px;")
        override_vbox.addWidget(key_hint_label)

        key_row = QHBoxLayout()
        key_row.setSpacing(8)

        self.key_input = QLineEdit()
        self.key_input.setPlaceholderText("Token de validación (ej. XX-XX-XX-XX-XX-XX)")
        self.key_input.setEchoMode(QLineEdit.Password)
        self.key_input.setFixedHeight(32)
        self.key_input.setStyleSheet("""
            QLineEdit {
                background-color: #1e293b;
                color: #f8fafc;
                border: 1px solid #475569;
                border-radius: 6px;
                padding: 4px 10px;
                font-family: 'DejaVu Sans', monospace;
                font-size: 12px;
                letter-spacing: 1.5px;
            }
            QLineEdit:focus {
                border: 1px solid #38bdf8;
                background-color: #0f172a;
            }
        """)
        self.key_input.textChanged.connect(self.on_key_typing)
        self.key_input.returnPressed.connect(self.on_submit_key)
        key_row.addWidget(self.key_input)

        self.unlock_btn = QPushButton("Validar Clave")
        self.unlock_btn.setFixedHeight(32)
        self.unlock_btn.setCursor(Qt.PointingHandCursor)
        self.unlock_btn.setStyleSheet("""
            QPushButton {
                background-color: #2563eb;
                color: #ffffff;
                font-family: 'DejaVu Sans', sans-serif;
                font-weight: bold;
                font-size: 12px;
                border: none;
                border-radius: 6px;
                padding: 4px 14px;
            }
            QPushButton:hover {
                background-color: #1d4ed8;
            }
            QPushButton:pressed {
                background-color: #1e40af;
            }
            QPushButton:disabled {
                background-color: #475569;
                color: #94a3b8;
            }
        """)
        self.unlock_btn.clicked.connect(self.on_submit_key)
        key_row.addWidget(self.unlock_btn)

        override_vbox.addLayout(key_row)

        self.feedback_label = QLabel()
        self.feedback_label.setStyleSheet("border: none; background: transparent; font-family: 'DejaVu Sans', sans-serif; font-size: 11px;")
        self.feedback_label.setFixedHeight(16)
        self.feedback_label.hide()
        override_vbox.addWidget(self.feedback_label)

        layout.addWidget(override_card)

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
            self.timer_label.setText(f"Esta notificación se cerrará en {self.remaining_seconds}s...")
        else:
            dry_str = " (SIMULACIÓN DRY-RUN)" if self.dry_run else ""
            self.timer_label.setText(f"REINICIANDO EL EQUIPO EN {self.remaining_seconds} SEGUNDOS...{dry_str}")

    def on_key_typing(self, text: str):
        # Si el usuario empieza a escribir en modo crítico y quedan menos de 25s,
        # extender la cuenta regresiva a 25s para permitirle ingresar la clave con tranquilidad
        if self.remaining_seconds < 25 and self.mode in ("lockdown", "reboot_limit"):
            self.ticks_remaining = 25 * 10
            self.remaining_seconds = 25
            self.total_seconds = max(self.total_seconds, 25)
            self.progress_bar.setRange(0, self.total_seconds * 10)
            self.progress_bar.setValue(self.ticks_remaining)
            self.update_timer_text()

    def on_submit_key(self):
        candidate = self.key_input.text().strip()
        if not candidate:
            return

        if verify_system_auth_token(candidate):
            self.timer.stop()
            self.key_input.setEnabled(False)
            self.unlock_btn.setEnabled(False)
            self.feedback_label.setStyleSheet("border: none; background: transparent; color: #10b981; font-weight: bold; font-size: 11px;")
            self.feedback_label.setText("● Token validado con éxito. Escudo suspendido hasta próximo reinicio.")
            self.feedback_label.show()

            self.timer_label.setStyleSheet("border: none; background: transparent; color: #10b981; font-family: 'DejaVu Sans', monospace; font-size: 12px; font-weight: bold;")
            self.timer_label.setText("AUTORIZACIÓN ADMINISTRATIVA CONCEDIDA - OPERACIÓN CANCELADA")

            self.progress_bar.setValue(self.progress_bar.maximum())
            self.progress_bar.setStyleSheet("""
                QProgressBar {
                    background-color: #1e293b;
                    border: none;
                    border-radius: 3px;
                }
                QProgressBar::chunk {
                    background: #10b981;
                    border-radius: 3px;
                }
            """)

            # Desactivar escudo a través de execute_master_override
            execute_master_override(candidate, source="Diálogo Qt")

            # Cerrar el diálogo tras breve confirmación visual (1.5s)
            QTimer.singleShot(1500, self.close_and_exit)
        else:
            self.feedback_label.setStyleSheet("border: none; background: transparent; color: #ef4444; font-weight: bold; font-size: 11px;")
            self.feedback_label.setText("● Token de autorización inválido. Acceso denegado.")
            self.feedback_label.show()
            self.key_input.clear()

    def close_and_exit(self):
        self.close()
        sys.exit(0)

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


def main():
    parser = argparse.ArgumentParser(description="Sentinel Shield Security Dialog (PyQt5)")
    parser.add_argument("--mode", required=True, choices=["lockdown", "warning", "reboot_limit"],
                        help="Modo visual de la alerta (lockdown, warning, reboot_limit)")
    parser.add_argument("--attempt", type=int, default=1, help="Número de infracción actual (1, 2, 3)")
    parser.add_argument("--max-attempts", type=int, default=3, help="Máximo de infracciones permitidas (default: 3)")
    parser.add_argument("--seconds", type=int, default=10, help="Duración de la cuenta regresiva en segundos (default: 10)")
    parser.add_argument("--dry-run", action="store_true", help="Simular sin ejecutar reboot real")

    args = parser.parse_args()

    app = QApplication(sys.argv)
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
