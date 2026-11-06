"""
# ==============================================================================
# 🔒 ACTIONS: MONITOREO SSL/TLS: ssl_action.py
# Inspección de vigencia, cadenas de confianza y detalle de certificados SSL/TLS.
# Ubicación: /opt/sonar/bot_core/handlers/services/actions/ssl_action.py
# ==============================================================================
"""
from __future__ import annotations

import html
import json
import logging
from typing import Any

from telegram import Update
from telegram.ext import ContextTypes

from bot_core.handlers.services.helpers import get_active_config, reply
from bot_core.security.auth import check_authorization
from monitor.shield.secure_core import IMMUTABLE_OWNER_ID
from monitor.infrastructure.db_context import db_connection, db_cursor

logger = logging.getLogger("bot.handlers.services.ssl")


async def handle_ssl(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Comando de Monitoreo de Certificados SSL/TLS (Fase 3)."""
    if not update.message:
        return

    if not await check_authorization(update, context):
        return

    args = context.args or []
    subcmd = args[0].lower() if args else "list"

    user_id = update.effective_user.id if update.effective_user else 0
    cfg = get_active_config()
    owner_id = int(cfg.get("owner_id", IMMUTABLE_OWNER_ID))
    is_admin = (user_id == IMMUTABLE_OWNER_ID or user_id == owner_id)

    try:
        if subcmd in ("list", "resumen", "status", "todos"):
            with db_cursor() as cur:
                cur.execute("""
                    SELECT domain, port, days_remaining, valid_to, issuer_cn, last_check_status, is_wildcard, is_self_signed
                    FROM ssl_certificates
                    WHERE is_active = 1
                    ORDER BY days_remaining ASC
                """)
                certs = cur.fetchall()

            if not certs:
                await reply(
                    update.message,
                    "🔒 <b>MONITOREO DE CERTIFICADOS SSL/TLS</b>\n\n"
                    "ℹ️ No hay certificados registrados en el inventario."
                )
                return

            total = len(certs)
            valid = sum(1 for c in certs if c["days_remaining"] > 30 and c["last_check_status"] != "error")
            expiring = sum(1 for c in certs if 0 <= c["days_remaining"] <= 30 and c["last_check_status"] != "error")
            critical = sum(1 for c in certs if 0 <= c["days_remaining"] <= 7 and c["last_check_status"] != "error")
            expired = sum(1 for c in certs if c["days_remaining"] < 0 or c["last_check_status"] == "expired")
            errors = sum(1 for c in certs if c["last_check_status"] == "error")

            lines = [
                "🔒 <b>MONITOREO DE CERTIFICADOS SSL/TLS</b>",
                "🏢 <i>Centro de Telecomunicaciones Valle Seco</i>\n",
                f"📊 <b>Resumen General:</b>",
                f"• Total registrados: <b>{total}</b>",
                f"• 🟢 Válidos (&gt;30d): <b>{valid}</b>",
                f"• 🟡 Por Vencer (&le;30d): <b>{expiring}</b>",
                f"• 🔴 Críticos (&le;7d): <b>{critical}</b>",
                f"• ❌ Expirados: <b>{expired}</b>",
                f"• ⚠️ Fallos / Inalcanzable: <b>{errors}</b>\n",
                "📋 <b>Inventario de Dominios:</b>",
            ]

            for c in certs[:15]:
                dom = html.escape(c["domain"])
                port = c["port"]
                days = c["days_remaining"]
                status = c["last_check_status"]

                if status == "error":
                    icon = "⚠️"
                    day_str = "Error de conexión"
                elif days < 0 or status == "expired":
                    icon = "❌"
                    day_str = f"Expiró hace {abs(days)}d"
                elif days <= 7:
                    icon = "🔴"
                    day_str = f"Crítico: {days}d restantes"
                elif days <= 30:
                    icon = "🟡"
                    day_str = f"Por vencer: {days}d restantes"
                else:
                    icon = "🟢"
                    day_str = f"{days}d restantes"

                wild_badge = " [WILDCARD]" if c.get("is_wildcard") else ""
                self_badge = " [SELF-SIGNED]" if c.get("is_self_signed") else ""
                lines.append(f"{icon} <code>{dom}:{port}</code>{wild_badge}{self_badge}\n   └ {day_str}")

            if len(certs) > 15:
                lines.append(f"\n<i>...y {len(certs) - 15} certificados más. Use /certificados detail &lt;dominio&gt;</i>")

            lines.append("\n💡 <i>Comandos: /certificado &lt;dominio&gt; | /certificados expirando [días]</i>")
            await reply(update.message, "\n".join(lines))

        elif subcmd in ("expiring", "por_vencer", "vencimiento", "expirando"):
            limit_days = 30
            if len(args) > 1 and args[1].isdigit():
                limit_days = int(args[1])

            with db_cursor() as cur:
                cur.execute("""
                    SELECT domain, port, days_remaining, valid_to, issuer_cn, last_check_status
                    FROM ssl_certificates
                    WHERE is_active = 1 AND days_remaining <= %s
                    ORDER BY days_remaining ASC
                """, (limit_days,))
                expiring_list = cur.fetchall()

            if not expiring_list:
                await reply(
                    update.message,
                    f"🟢 <b>Todos los certificados vigentes</b>\n\n"
                    f"No hay certificados que expiren en los próximos <b>{limit_days} días</b>."
                )
                return

            lines = [
                f"⏰ <b>CERTIFICADOS POR VENCER (&le; {limit_days} días)</b>\n",
            ]
            for c in expiring_list:
                dom = html.escape(c["domain"])
                days = c["days_remaining"]
                icon = "❌" if days < 0 else ("🔴" if days <= 7 else "🟡")
                v_to = c["valid_to"].strftime("%d/%m/%Y") if c["valid_to"] else "N/A"
                lines.append(f"{icon} <code>{dom}:{c['port']}</code>\n   └ {days} días restantes (Vence: {v_to})")

            await reply(update.message, "\n".join(lines))

        elif subcmd in ("detail", "detalle", "info") or (args and subcmd not in ("check", "scan", "verificar")):
            target_domain = args[1] if subcmd in ("detail", "detalle", "info") and len(args) > 1 else args[0]
            with db_cursor() as cur:
                cur.execute("""
                    SELECT * FROM ssl_certificates
                    WHERE domain LIKE %s OR domain = %s
                    LIMIT 1
                """, (f"%{target_domain}%", target_domain))
                c = cur.fetchone()

            if not c:
                await reply(
                    update.message,
                    f"🔍 No se encontró ningún certificado registrado para '<code>{html.escape(target_domain)}</code>'."
                )
                return

            days = c["days_remaining"]
            status = c["last_check_status"]
            icon = "❌" if days < 0 else ("🔴" if days <= 7 else ("🟡" if days <= 30 else "🟢"))
            if status == "error":
                icon = "⚠️"

            v_from = c["valid_from"].strftime("%d/%m/%Y %H:%M") if c["valid_from"] else "N/A"
            v_to = c["valid_to"].strftime("%d/%m/%Y %H:%M") if c["valid_to"] else "N/A"
            l_chk = c["last_checked_at"].strftime("%d/%m/%Y %H:%M") if c["last_checked_at"] else "N/A"

            sans = ""
            if c.get("san_entries"):
                try:
                    s_list = json.loads(c["san_entries"]) if isinstance(c["san_entries"], str) else c["san_entries"]
                    sans = ", ".join(s_list[:4])
                    if len(s_list) > 4:
                        sans += f" (+{len(s_list)-4} más)"
                except Exception:
                    pass

            msg = (
                f"{icon} <b>DETALLE CERTIFICADO SSL/TLS</b>\n"
                f"━━━━━━━━━━━━━━━━━━━━━━\n"
                f"🌐 <b>Dominio:</b> <code>{html.escape(c['domain'])}:{c['port']}</code>\n"
                f"📊 <b>Estado:</b> <code>{status.upper() if status else 'N/A'}</code> ({days} días)\n"
                f"🏢 <b>Subject CN:</b> <code>{html.escape(c.get('subject_cn') or 'N/A')}</code>\n"
                f"🏛️ <b>Issuer CA:</b> <code>{html.escape(c.get('issuer_cn') or c.get('issuer_org') or 'N/A')}</code>\n"
                f"📅 <b>Válido Desde:</b> {v_from}\n"
                f"⏳ <b>Válido Hasta:</b> {v_to}\n"
                f"🔑 <b>Clave Pública:</b> {c.get('public_key_algorithm') or 'RSA'} {c.get('public_key_bits') or ''} bits\n"
                f"✍️ <b>Firma:</b> <code>{c.get('signature_algorithm') or 'N/A'}</code>\n"
                f"🔢 <b>N° Serie:</b> <code>{c.get('serial_number') or 'N/A'}</code>\n"
                f"🔄 <b>Renovaciones:</b> {c.get('renewal_count', 0)}\n"
                + (f"🏷️ <b>SANs:</b> <code>{html.escape(sans)}</code>\n" if sans else "")
                + f"🕒 <b>Última Inspección:</b> {l_chk}\n"
                f"━━━━━━━━━━━━━━━━━━━━━━"
            )
            await reply(update.message, msg)

        elif subcmd in ("check", "scan", "verificar"):
            if not is_admin:
                await reply(update.message, "⛔ La re-inspección forzada está reservada para el Administrador.")
                return

            target = args[1] if len(args) > 1 else ""
            if not target:
                await reply(update.message, "⚠️ Especifique el dominio a inspeccionar. Ej: <code>/ssl check core.telegram.org</code>")
                return

            await reply(update.message, f"⏳ <i>Inspeccionando certificado TLS para <code>{html.escape(target)}</code>...</i>")

            from monitor.checkers.ssl import check_and_update_certificate, extract_cert_info
            with db_connection() as conn:
                with conn.cursor() as cur:
                    cur.execute("SELECT * FROM ssl_certificates WHERE domain = %s", (target,))
                    rec = cur.fetchone()

                if rec:
                    res = check_and_update_certificate(conn, rec, timeout=5.0)
                else:
                    res = extract_cert_info(target, 443, timeout=5.0)

            if res["success"]:
                await reply(
                    update.message,
                    f"✅ <b>Inspección Exitosa</b>\n"
                    f"• Dominio: <code>{html.escape(res['domain'])}</code>\n"
                    f"• Días Restantes: <b>{res['days_remaining']} días</b>\n"
                    f"• Vence: {res['valid_to']}\n"
                    f"• Emisor: <code>{html.escape(res['issuer_cn'] or 'N/A')}</code>\n"
                    f"• Estado: <b>{res['status'].upper()}</b>"
                )
            else:
                await reply(
                    update.message,
                    f"❌ <b>Fallo en Inspección</b>\n"
                    f"• Dominio: <code>{html.escape(res['domain'])}</code>\n"
                    f"• Error: {html.escape(res.get('error_message') or 'Timeout / Inalcanzable')}"
                )

        else:
            await reply(
                update.message,
                "⚠️ Subcomando no reconocido. Use:\n"
                "• <code>/certificados</code> — Lista general\n"
                "• <code>/certificado &lt;dominio&gt;</code> — Detalle completo\n"
                "• <code>/certificados expirando [días]</code> — Próximos a vencer\n"
                + ("• <code>/ssl check &lt;dominio&gt;</code> — Inspeccionar en vivo\n" if is_admin else "")
            )

    except Exception as e:
        logger.error(f"Error en cmd_ssl: {e}")
        await reply(update.message, f"❌ Error procesando comando SSL: {html.escape(str(e))}")
