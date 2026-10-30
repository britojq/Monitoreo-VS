<?php

namespace App\Services;

use App\Models\GlpiInventoryLog;
use App\Services\Glpi\GlpiAssetSyncService;
use App\Services\Glpi\GlpiHardwareNormalizer;
use App\Services\Glpi\GlpiPayloadDecoder;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador y Fachada de Ingesta de Inventario GLPI Agent.
 * Aplica Principio de Responsabilidad Única (S) e Inversión de Dependencias (D).
 * Mantiene 100% de compatibilidad hacia atrás con controladores y suites de pruebas existentes.
 */
class GlpiInventoryService
{
    protected ?TelegramNotificationService $notifier;
    protected GlpiPayloadDecoder $decoder;
    protected GlpiHardwareNormalizer $normalizer;
    protected ?GlpiAssetSyncService $syncService;

    public function __construct(
        ?TelegramNotificationService $notifier = null,
        ?GlpiPayloadDecoder $decoder = null,
        ?GlpiHardwareNormalizer $normalizer = null,
        ?GlpiAssetSyncService $syncService = null
    ) {
        $this->normalizer = $normalizer ?? new GlpiHardwareNormalizer();
        $this->decoder = $decoder ?? new GlpiPayloadDecoder();
        $this->notifier = $notifier ?? (function_exists('app') && app()->has(TelegramNotificationService::class) ? app(TelegramNotificationService::class) : null);
        $this->syncService = $syncService;
    }

    /**
     * Procesa la carga útil del inventario reportada por el agente GLPI (XML/JSON/Deflate).
     */
    public function processRawPayload(string $rawContent, string $ipAddress, ?string $userAgent = null, ?string $contentEncoding = null): array
    {
        // 1. Decodificar payload crudo y descomprimir
        $decoded = $this->decoder->decode($rawContent, $contentEncoding);

        // 2. Crear registro forense inicial en glpi_inventory_logs
        $log = GlpiInventoryLog::create([
            'ip_address' => $ipAddress,
            'user_agent' => substr((string)$userAgent, 0, 191),
            'device_name' => null,
            'serial_number' => null,
            'mac_address' => null,
            'payload_format' => $decoded['format'],
            'raw_payload' => $decoded['safe_payload'],
            'processed' => false,
        ]);

        if (!$decoded['parsed'] || !is_array($decoded['parsed'])) {
            $log->update(['error_message' => 'No se pudo interpretar el payload como JSON ni XML válido.']);
            return [
                'status' => 'error',
                'message' => 'Formato no reconocido o payload corrupto',
            ];
        }

        // 3. Evaluar si es un mensaje de saludo / handshake / consulta (PROLOG / getConfig / contact)
        if ($decoded['is_prolog']) {
            $log->update(['processed' => true]);
            return [
                'status' => 'ok',
                'type' => 'prolog',
                'expiration' => 24,
                'frequency' => 24,
                'message' => 'Handshake/contacto de agente reconocido',
            ];
        }

        // 4. Normalizar datos de inventario
        try {
            $normalized = $this->normalizer->normalize($decoded['parsed'], $ipAddress);

            $log->update([
                'device_name' => substr((string)($normalized['name'] ?? ''), 0, 128),
                'serial_number' => substr((string)($normalized['serial_number'] ?? ''), 0, 128),
                'mac_address' => substr((string)($normalized['mac_address'] ?? ''), 0, 48),
            ]);

            // 5. Emparejar activo existente o registrar nuevo y auditar diferencias
            $sync = $this->syncService ?? new GlpiAssetSyncService(null, $this->notifier);
            $result = $sync->sync($normalized);

            $log->update(['processed' => true]);

            return [
                'status' => 'ok',
                'action' => $result['action'],
                'asset_id' => $result['asset']->id,
                'asset_name' => $result['asset']->name,
                'message' => 'Inventario procesado y sincronizado exitosamente',
            ];

        } catch (\Throwable $e) {
            Log::error("Error procesando inventario GLPI: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $log->update(['error_message' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mantiene retrocompatibilidad para métodos públicos llamados en tests o controladores.
     */
    public function normalizeInventoryData(array $parsed, string $fallbackIp): array
    {
        return $this->normalizer->normalize($parsed, $fallbackIp);
    }

    public function isGenericSerial(?string $serial): bool
    {
        return $this->normalizer->isGenericSerial($serial);
    }

    public function getDecoder(): GlpiPayloadDecoder
    {
        return $this->decoder;
    }

    public function getNormalizer(): GlpiHardwareNormalizer
    {
        return $this->normalizer;
    }

    public function getSyncService(): GlpiAssetSyncService
    {
        return $this->syncService ?? new GlpiAssetSyncService(null, $this->notifier);
    }
}
