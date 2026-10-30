"""
==============================================================================
🛡️ SERVICIO DE GUARDRAILS Y BLINDAJE DE SEGURIDAD: ai_guardrails_service.py
Principio de Responsabilidad Única (SRP) y Defensa en Profundidad (OWASP).
Filtra, sanitiza y protege la confidencialidad de datos de infraestructura.
==============================================================================
"""

from __future__ import annotations

import logging
import re
from typing import Optional

from bot_core.contracts.ai_contracts import (
    AiGuardrailsServiceProtocol,
    AiTelemetryProviderProtocol,
)
from bot_core.services.ai_telemetry_provider import MariaDbAiTelemetryProvider

logger = logging.getLogger("bot.services.ai_guardrails")


class AiGuardrailsService(AiGuardrailsServiceProtocol):
    """
    Servicio especializado en validación de salida, mitigación de alucinaciones
    y cumplimiento estricto de privacidad de infraestructura por contexto (Regla #1 y #2).
    """

    def __init__(self, telemetry_provider: Optional[AiTelemetryProviderProtocol] = None) -> None:
        self._telemetry = telemetry_provider or MariaDbAiTelemetryProvider()

    def is_negative_or_refusal(self, text: str) -> bool:
        """Detecta si una respuesta fue una declinación o fallo para no contaminar el historial."""
        normalized = (text or "").lower()
        refusal_patterns = (
            "no tengo capacidad",
            "no puedo responder",
            "no dispongo de información",
            "no tengo acceso",
            "no tengo acceso directo",
            "declino responder",
            "realiza tu consulta en google",
            "google.com",
            "como asistente técnico corporativo",
            "entorno corporativo y privado",
            "lo siento, pero no tengo",
            "lo siento, el motor local",
            "discúlpeme",
            "disculpeme",
            "malentendido anterior",
            "ocurrió un error",
            "no pude conectar",
            "tardó demasiado",
            "consulta la base de datos",
            "revisa la base de datos",
            "consultar directamente las bases de datos",
            "contactar a la coordinación",
            "contactar a la coordinacion",
            "select * from",
            "select *",
            "from equipos",
            "proporciona la información específica que necesitas",
            "te gustaría ver un conjunto particular",
        )
        if any(p in normalized for p in refusal_patterns):
            return True
        if re.search(r"\bselect\b.*?\bfrom\b", normalized):
            return True
        return False

    def validate_and_sanitize(
        self,
        content: str,
        user_text: str,
        dynamic_context: Optional[str] = None,
        is_group_context: bool = False,
    ) -> str:
        """
        Inspecciona la respuesta generada por el motor de inferencia antes de entregarla al usuario.
        Aplica aislamiento estricto de contexto y corrige alucinaciones contra MariaDB SSOT.
        """
        if not content:
            return content

        norm_user = user_text.lower().strip()
        norm_content = content.lower().strip()

        # Detección de consultas sobre infraestructura, inventario y actas
        is_acta_query = any(k in norm_user for k in ("acta", "actas", "asignacion", "asignación", "asignaciones", "uinf"))
        is_serial_or_inventory = any(k in norm_user for k in (
            "serial", "seriales", "listado", "lista", "censo", "cuantos equipos", "cuántos equipos",
            "inventario", "activo", "activos"
        ))
        is_infra_query = is_acta_query or is_serial_or_inventory or any(k in norm_user for k in (
            "servicio", "servicios", "sede", "sedes", "segmento", "segmentos", "subred", "subredes",
            "red", "redes", "switch", "switches", "router", "routers", "ip", "ups", "bateria", "batería",
            "caido", "caído", "caidos", "caídos", "caida", "caída", "caidas", "caídas",
            "otrs", "intranet", "factura", "claves", "atit", "valle seco", "equipo", "equipos"
        ))

        # 1. Regla de Aislamiento de Contexto: En grupos corporativos está estrictamente prohibido responder sobre infraestructura interna
        if is_group_context and is_infra_query:
            logger.info("Guardrail activado: consulta de infraestructura interna rechazada en grupo corporativo.")
            return (
                "🔒 <b>Información Restringida:</b>\n"
                "Los datos de inventario, activos de la sede, actas y telemetría interna del centro de operaciones "
                "son de acceso reservado exclusivo para el Administrador a través de su canal privado."
            )

        # 2. Detección de Falsa Declinación sobre temas de infraestructura, inventario y actas
        if is_infra_query and self.is_negative_or_refusal(content):
            logger.warning("Guardrail de salida activado: el modelo emitió una declinación falsa sobre infraestructura/actas/inventario.")
            if is_acta_query:
                actas_dto = self._telemetry.get_live_actas_summary()
                if actas_dto.items:
                    return (
                        f"De acuerdo con los registros oficiales en MariaDB SSOT, actualmente existen {actas_dto.acta_count} equipos "
                        f"con acta de asignación física firmada y escaneada en formato PDF (de un total de {actas_dto.total_assets} activos):\n\n"
                        + "\n\n".join(actas_dto.items)
                    )
                return "Consulte el catálogo de asignaciones de inventario en el sistema."

            if any(k in norm_user for k in ("segmento", "segmentos", "subred", "subredes")):
                subnets = self._telemetry.get_subnets_summary()
                if subnets:
                    sub_list = "\n".join(subnets)
                    return f"Los segmentos de red operativos registrados en el sistema son:\n\n{sub_list}"
                return "Consulte los segmentos de red configurados en el sistema."

            if any(k in norm_user for k in ("servicio", "servicios", "caido", "caído", "caidos", "caídos")):
                outage_dto = self._telemetry.get_live_outage_summary()
                if outage_dto.down_services:
                    down_list_str = "\n".join([f"• {s}" for s in outage_dto.down_services])
                    return (
                        f"De acuerdo con la telemetría del sistema en tiempo real, actualmente hay exactamente "
                        f"{len(outage_dto.down_services)} servicios caídos fuera de línea:\n\n"
                        f"{down_list_str}\n\n"
                        f"Los restantes {outage_dto.serv_up} servicios corporativos se encuentran en línea y 100% operativos "
                        f"({outage_dto.serv_up} de {outage_dto.serv_total})."
                    )
                return (
                    f"De acuerdo con la telemetría del sistema, los {outage_dto.serv_total} servicios corporativos "
                    f"se encuentran 100% en línea y operativos."
                )

            if is_serial_or_inventory:
                inv_dto = self._telemetry.get_live_inventory_summary()
                return (
                    f"De acuerdo con los registros oficiales en MariaDB SSOT, actualmente hay {inv_dto.with_serial} equipos registrados "
                    f"que tienen serial de chasis/CPU verificado (de un total de {inv_dto.total_assets} activos registrados en el sistema)."
                )

        # 3. Detección de Contradicción Fáctica sobre Servicios Caídos
        is_asking_outages = any(k in norm_user for k in (
            "servicios caidos", "servicios caídos", "cuantos servicios", "cuántos servicios",
            "que servicios caidos", "qué servicios caídos", "servicios caídos?", "servicios caidos?"
        ))
        claims_no_outages = any(p in norm_content for p in (
            "no hay ningún servicio", "no hay ningun servicio", "no hay servicios caídos",
            "no hay servicios caidos", "todos los servicios están en línea", "todos los servicios estan en linea",
            "todos los servicios se encuentran en línea", "todos los servicios se encuentran en linea",
            "cero servicios caídos", "0 servicios caídos", "sin estado de caída"
        ))

        if is_asking_outages and claims_no_outages:
            outage_dto = self._telemetry.get_live_outage_summary()
            if outage_dto.down_services:
                logger.warning("Guardrail de salida activado: corrección de contradicción fáctica de servicios caídos.")
                down_list_str = "\n".join([f"• {s}" for s in outage_dto.down_services])
                return (
                    f"Hay exactamente {len(outage_dto.down_services)} servicios caídos fuera de línea en el sistema:\n\n"
                    f"{down_list_str}\n\n"
                    f"Actualmente {outage_dto.serv_up} servicios corporativos se encuentran operativos de un total de {outage_dto.serv_total}."
                )

        # 4. Detección de Evasión con SQL o Solicitud de Consultar BD en Inventario, Seriales o Actas
        has_sql_evasion = (
            bool(re.search(r"\bselect\b.*?\bfrom\b", norm_content)) or
            bool(re.search(r"\b(consultar?|revisar?|acceder?)\b.*?\b(base|bases)\s+de\s+datos\b", norm_content)) or
            any(p in norm_content for p in (
                "consulta la base de datos", "revisa la base de datos",
                "proporciona la información específica",
                "conjunto particular de equipos",
                "select * from", "from equipos",
                "no tengo acceso directo",
                "no tengo acceso a la información",
                "no tengo acceso a la informacion"
            ))
        )

        if (is_acta_query or is_serial_or_inventory) and has_sql_evasion:
            logger.warning("Guardrail de salida activado: interceptada evasión/SQL en inventario/actas.")
            if is_acta_query:
                actas_dto = self._telemetry.get_live_actas_summary()
                if actas_dto.items:
                    return (
                        f"De acuerdo con los registros oficiales en MariaDB SSOT, actualmente existen {actas_dto.acta_count} equipos "
                        f"con acta de asignación física firmada y escaneada en formato PDF (de un total de {actas_dto.total_assets} activos):\n\n"
                        + "\n\n".join(actas_dto.items)
                    )
                return "Consulte el catálogo de asignaciones de inventario en el centro de operaciones."

            if is_serial_or_inventory:
                inv_dto = self._telemetry.get_live_inventory_summary()
                return (
                    f"De acuerdo con los registros oficiales en MariaDB SSOT, actualmente hay {inv_dto.with_serial} equipos registrados "
                    f"que tienen serial de chasis/CPU verificado (de un total de {inv_dto.total_assets} activos registrados en el sistema)."
                )

        return content
