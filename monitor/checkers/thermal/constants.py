"""
Constantes operacionales y umbrales de seguridad para Thermal Guard.
"""
IMMUTABLE_OWNER_ID: int = <OWNER_TELEGRAM_ID>

DEFAULT_TEMP_WARNING: float = 68.0
DEFAULT_TEMP_CRITICAL: float = 75.0
DEFAULT_TEMP_RECOVERY: float = 55.0
DEFAULT_COOLDOWN_SECONDS: int = 180
CHECK_INTERVAL_SECONDS: int = 5

AI_SERVICE_UNIT: str = "ollama.service"
