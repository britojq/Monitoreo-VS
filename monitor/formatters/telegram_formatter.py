"""
# ==============================================================================
# 📝 FORMATEADOR VISUAL DE TELEGRAM: telegram_formatter.py
# Conversión de Markdown estándar a marcado HTML admitido por Telegram Bot API
# Responsabilidad Única (S): Formateo de texto visual sin mezclar dependencias de red
# ==============================================================================
"""

from __future__ import annotations

import html
import re


def markdown_to_telegram_html(text: str) -> str:
    """Convierte Markdown estándar (títulos, negritas, código de consola) a HTML compatible con Telegram."""
    if not text:
        return ""

    # 1. Proteger bloques de código multilínea ```lang ... ```
    code_blocks = []
    def _save_code_block(match):
        lang = (match.group(1) or "").strip()
        code_content = match.group(2)
        idx = len(code_blocks)
        code_blocks.append((lang, code_content))
        return f"QQQBLOCKCODE{idx}ZZZ"

    text = re.sub(r'```([a-zA-Z0-9_-]*)\n?(.*?)```', _save_code_block, text, flags=re.DOTALL)

    # 2. Proteger código inline `code`
    inline_codes = []
    def _save_inline_code(match):
        code_content = match.group(1)
        idx = len(inline_codes)
        inline_codes.append(code_content)
        return f"QQQINLINECODE{idx}ZZZ"

    text = re.sub(r'`([^`\n]+)`', _save_inline_code, text)

    # 2.1 Proteger etiquetas HTML válidas soportadas por Telegram
    valid_tag_pattern = r'</?(?:b|strong|i|em|u|ins|s|strike|del|span|tg-spoiler|a|tg-emoji|code|pre|blockquote)(?:\s+[^>]*)?>'
    html_tags = []
    def _save_html_tag(match):
        idx = len(html_tags)
        html_tags.append(match.group(0))
        return f"QQQVALIDHTMLTAG{idx}ZZZ"

    text = re.sub(valid_tag_pattern, _save_html_tag, text, flags=re.IGNORECASE)

    # 3. Escapar caracteres HTML básicos en el texto general
    text = html.escape(text)

    # 4. Normalizar viñetas de listas (* item o - item -> • item)
    text = re.sub(r'^[ \t]*[\*\-][ \t]+', r'• ', text, flags=re.MULTILINE)

    # 5. Títulos y subtítulos (# Título -> <b>Título</b>)
    text = re.sub(r'^(#{1,6})\s+(.+)$', r'<b>\2</b>', text, flags=re.MULTILINE)

    # 6. Negrita + Cursiva (***texto*** o ___texto___)
    text = re.sub(r'\*\*\*(.+?)\*\*\*', r'<b><i>\1</i></b>', text, flags=re.DOTALL)
    text = re.sub(r'___(.+?)___', r'<b><i>\1</i></b>', text, flags=re.DOTALL)

    # 7. Negrita (**texto** o __texto__)
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text, flags=re.DOTALL)
    text = re.sub(r'(?<![a-zA-Z0-9])__(.+?)__(?![a-zA-Z0-9])', r'<b>\1</b>', text, flags=re.DOTALL)

    # 8. Cursiva (*texto* o _texto_)
    text = re.sub(r'(?<!\*)\*([^\*\n]+)\*(?!\*)', r'<i>\1</i>', text)
    text = re.sub(r'(?<![a-zA-Z0-9_])_([^_\n]+)_(?![a-zA-Z0-9_])', r'<i>\1</i>', text)

    # 9. Restaurar etiquetas HTML válidas preexistentes
    for idx, tag in enumerate(html_tags):
        text = text.replace(f"QQQVALIDHTMLTAG{idx}ZZZ", tag)

    # 10. Restaurar bloques de código multilínea <pre><code>...</code></pre>
    for idx, (lang, code_content) in enumerate(code_blocks):
        escaped_code = html.escape(code_content.strip('\r\n'))
        if lang:
            replacement = f'<pre><code class="language-{html.escape(lang)}">{escaped_code}</code></pre>'
        else:
            replacement = f'<pre><code>{escaped_code}</code></pre>'
        text = text.replace(f"QQQBLOCKCODE{idx}ZZZ", replacement)

    # 11. Restaurar código inline <code>...</code>
    for idx, inline_content in enumerate(inline_codes):
        escaped_inline = html.escape(inline_content)
        replacement = f'<code>{escaped_inline}</code>'
        text = text.replace(f"QQQINLINECODE{idx}ZZZ", replacement)

    return text
