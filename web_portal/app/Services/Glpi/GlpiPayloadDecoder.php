<?php

namespace App\Services\Glpi;

/**
 * Decodificador de Cargas Útiles (Payloads) del Agente GLPI.
 * Responsabilidad Única (S): Descompresión adaptativa (Gzip/Zlib/Deflate) y parseo de formatos (JSON/XML).
 */
class GlpiPayloadDecoder
{
    /**
     * Descomprime y analiza una carga útil cruda enviada por un agente GLPI.
     *
     * @param string $rawContent Contenido crudo recibido en la petición HTTP
     * @param string|null $contentEncoding Cabecera Content-Encoding si está presente
     * @return array ['format' => string, 'parsed' => ?array, 'safe_payload' => string, 'is_prolog' => bool]
     */
    public function decode(string $rawContent, ?string $contentEncoding = null): array
    {
        // 1. Descomprimir adaptativamente: Gzip (\x1f\x8b), Zlib/Deflate (\x78...) o raw deflate
        $payload = $rawContent;
        $encodingLower = strtolower((string)$contentEncoding);

        if ($encodingLower === 'gzip' || str_starts_with($rawContent, "\x1f\x8b")) {
            $decompressed = @gzdecode($rawContent);
            if ($decompressed !== false) {
                $payload = $decompressed;
            }
        } elseif ($encodingLower === 'deflate' || str_starts_with($rawContent, "\x78") || !mb_check_encoding($rawContent, 'UTF-8')) {
            $decompressed = @gzuncompress($rawContent);
            if ($decompressed === false) {
                $decompressed = @gzinflate($rawContent);
            }
            if ($decompressed === false) {
                $decompressed = @gzdecode($rawContent);
            }
            if ($decompressed !== false) {
                $payload = $decompressed;
            }
        }

        // Sanitización para asegurar que no falle la inserción UTF-8 en MariaDB
        $safePayload = mb_check_encoding($payload, 'UTF-8')
            ? $payload
            : mb_convert_encoding($payload, 'UTF-8', 'ISO-8859-1');

        // 2. Determinar si es JSON o XML
        $format = 'unknown';
        $parsed = null;
        $trimmed = trim($payload);

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $format = 'json';
            $parsed = json_decode($trimmed, true);
        } elseif (str_starts_with($trimmed, '<')) {
            $format = 'xml';
            $xml = @simplexml_load_string($trimmed, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml) {
                $parsed = json_decode(json_encode($xml), true);
            }
        }

        // 3. Evaluar si es un mensaje de saludo / handshake / consulta (PROLOG / getConfig / contact)
        $isProlog = false;
        if (is_array($parsed)) {
            $action = strtolower((string)($parsed['action'] ?? $parsed['QUERY'] ?? ''));
            $hasInventoryData = isset($parsed['content']['hardware'])
                || isset($parsed['content']['HARDWARE'])
                || isset($parsed['content']['bios'])
                || isset($parsed['hardware'])
                || isset($parsed['HARDWARE'])
                || isset($parsed['CONTENT']['HARDWARE']);

            if (in_array($action, ['getconfig', 'prolog', 'contact', 'status'], true) || !$hasInventoryData) {
                $isProlog = true;
            }
        }

        return [
            'format' => $format,
            'parsed' => $parsed,
            'safe_payload' => $safePayload,
            'is_prolog' => $isProlog,
        ];
    }
}
