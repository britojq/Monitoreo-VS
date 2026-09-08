#!/usr/bin/env python3
"""
Módulo CLI para migrar y re-cifrar el Token del Bot con DRM de Hardware.
Uso:
  /scripts/telegram-admin-bot/venv/bin/python3 /scripts/telegram-admin-bot/cambiar_token.py <NUEVO_TOKEN>
o interactivo:
  /scripts/telegram-admin-bot/venv/bin/python3 /scripts/telegram-admin-bot/cambiar_token.py
"""

import sys
import json
import subprocess
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from monitor.core_shield import migrate_core_token

def main():
    if len(sys.argv) > 1:
        token = sys.argv[1].strip()
    else:
        token = input("Ingresa el nuevo Bot Token de Telegram: ").strip()

    if not token or ":" not in token or len(token) < 30:
        print("❌ Error: Formato de token inválido (debe contener ':' y ser el provisto por @BotFather).")
        sys.exit(1)

    print("🔄 Conectando con Telegram para validar el token y re-cifrarlo con DRM de hardware...")
    ok, msg = migrate_core_token(token)
    if not ok:
        print(f"❌ Error al migrar token: {msg}")
        sys.exit(1)

    # Limpiar formato HTML para consola
    import re
    clean_msg = re.sub(r'<[^>]+>', '', msg)
    print(f"{clean_msg}")

    # Actualizar config.json para consistencia
    cfg_file = BASE_DIR / "config" / "config.json"
    if cfg_file.exists():
        try:
            with open(cfg_file, "r", encoding="utf-8") as f:
                data = json.load(f)
            data["bot_token"] = token
            with open(cfg_file, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
            print("✓ Token sincronizado en config/config.json")
        except Exception as e:
            print(f"⚠️ Advertencia al actualizar config.json: {e}")

    # Reiniciar servicio systemd
    print("🔄 Reiniciando tg-admin-bot.service...")
    try:
        subprocess.run(["sudo", "systemctl", "restart", "tg-admin-bot.service"], check=True)
        print("✅ Servicio reiniciado exitosamente.")
    except Exception as e:
        print(f"⚠️ Ejecuta manualmente: sudo systemctl restart tg-admin-bot.service ({e})")

if __name__ == "__main__":
    main()
