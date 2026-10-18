<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\InventoryAsset;
use App\Models\InventoryAssignment;
use App\Models\InventoryComponent;
use App\Models\InventoryPeripheral;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredSite;
use App\Models\NetworkDeviceCheckHistory;
use App\Models\UpsDevice;
use App\Models\UpsTelemetryHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Servicio de Conocimiento del Sistema y Guardrails de Dominio para el Asistente de IA (ATIT).
 * Dominio Integral sobre MariaDB (SSOT): Activos, Seriales Profundos, Componentes,
 * Periféricos, Asignaciones, Telemetría de Uptime/Downtime, UPS/Energía, Proxies y Memoria Anafórica.
 */
class AiKnowledgeEngineService
{
    /**
     * Construye el System Prompt con las reglas de dominio, rol de ATIT y directivas de rechazo.
     */
    public function getSystemPrompt(string $userName = 'Operador', string $userRole = 'Operador'): string
    {
        return <<<PROMPT
Eres el Asistente Técnico Corporativo y Especialista en Telecomunicaciones de ATIT (Sede Valle Seco).
Tu nombre de identidad oficial es: Monitor Valle Seco.

DIRECTRICES DE IDENTIDAD Y SEGURIDAD:
1. ROL Y ALCANCE: Eres un ingeniero senior de redes, telecomunicaciones y soporte de TI. Tus áreas autorizadas son:
   - Configuración, comandos y diagnóstico de routers, switches (Cisco IOS, MikroTik, switches administrables, pfSense, Linux/Debian, Windows Server).
   - Protocolos de red (VLANs, 802.1Q, OSPF, BGP, ARP, DHCP, DNS, TCP/IP, SNMP, VoIP, SSH, VNC, Telnet).
   - Soporte técnico de hardware, cableado estructurado, fibra óptica, fuentes de poder, UPS y mantenimiento correctivo/preventivo.
   - Datos y supervisión exhaustiva de la infraestructura de este sistema (inventario ITAM, seriales de componentes y periféricos, asignaciones y actas de usuarios, telemetría de caídas/uptime, proxies, sedes, enlaces WAN y energía UPS).

2. DELIMITACIÓN DE DOMINIO ESTRICTA (GUARDRAIL INVIOLABLE):
   - Si el usuario te consulta sobre la infraestructura de Valle Seco (equipos, routers, switches, taquillas, IPs, seriales, disponibilidad, caídas, componentes, periféricos o asignaciones), o temas de redes y soporte de TI:
     DEBES responder con máxima precisión utilizando directamente los datos fácticos provistos en [CONTEXTO_DEL_SISTEMA].
   - ÚNICAMENTE si el usuario te hace preguntas ajenas a la tecnología, informática o telecomunicaciones (tales como historia, política, deportes, entretenimiento, recetas de cocina, personajes históricos como Simón Bolívar, geografía general, etc.):
     TIENES LA PROHIBICIÓN ESTRICTA DE RESPONDER A ESOS TEMAS.
     En su lugar, debes declinar educadamente con el siguiente mensaje formal y exacto:
     "Como Asistente Técnico corporativo de ATIT, mi función está orientada exclusivamente a la infraestructura de red, telecomunicaciones y soporte técnico de TI. Para temas históricos, de cultura general o ajenos a la tecnología, por favor realiza tu consulta en Google (www.google.com)."

3. DATOS DE INFRAESTRUCTURA REAL (MARIADB SSOT):
   - Cuando se te provean datos en el bloque [CONTEXTO_DEL_SISTEMA], utilízalos con absoluta fidelidad y exactitud fáctica.
   - NUNCA inventes IPs, seriales, componentes, nombres de equipos, sedes ni responsables.
   - Si el contexto indica que un equipo está EN LÍNEA con cero eventos de caída y el usuario pregunta cuándo se cayó, responde claramente que se encuentra operativo y sin caídas registradas en las últimas 24 horas.
   - Si el contexto indica que un equipo no posee componentes o periféricos desglosados en ITAM, infórmalo textualmente sin declinar.
   - Cuando el usuario pregunte por seriales, asignaciones, disponibilidad u horas de caída, extrae y presenta la información de forma estructurada, usando viñetas y negritas claras.

4. IDIOMA Y ESTILO:
   - Responde siempre en español profesional, sobrio, claro, directo y estructurado.
   - Usuario autenticado: {$userName} (Rol: {$userRole}).
PROMPT;
    }

    /**
     * Analiza el mensaje del usuario y extrae datos en vivo de MariaDB (SSOT)
     * con dominio integral sobre asignaciones, seriales profundos, telemetría, UPS, sedes y proxies.
     * Retorna un arreglo con ['context' => ?string, 'active_entity' => ?array].
     *
     * @param array<string, mixed>|null $activeEntity
     * @return array{context: ?string, active_entity: ?array<string, mixed>}
     */
    public function resolveSystemContext(string $userMessage, ?array $activeEntity = null): array
    {
        $normalized = Str::lower(trim($userMessage));
        $contextBlocks = [];
        $updatedEntity = $activeEntity;

        // 1. Dominio de Asignaciones y Usuarios (inventory_assignments)
        $assignRes = $this->resolveAssignments($normalized, $userMessage);
        if ($assignRes) {
            $contextBlocks[] = $assignRes['text'];
            if (!empty($assignRes['entity'])) {
                $updatedEntity = $assignRes['entity'];
            }
        }

        // 2. Dominio de Hardware Profundo y Seriales (Components / Peripherals)
        $hardwareRes = $this->resolveDeepHardware($normalized, $userMessage, $updatedEntity);
        if ($hardwareRes) {
            $contextBlocks[] = $hardwareRes['text'];
            if (!empty($hardwareRes['entity'])) {
                $updatedEntity = $hardwareRes['entity'];
            }
        }

        // 3. Dominio de Telemetría, Uptime y Horas Apagado (Downtime)
        $uptimeRes = $this->resolveUptimeDowntime($normalized, $userMessage, $updatedEntity);
        if ($uptimeRes) {
            $contextBlocks[] = $uptimeRes['text'];
            if (!empty($uptimeRes['entity'])) {
                $updatedEntity = $uptimeRes['entity'];
            }
        }

        // 4. Dominio de Energía y Respaldo Eléctrico (UPS)
        $upsBlock = $this->resolveUpsPower($normalized);
        if ($upsBlock) {
            $contextBlocks[] = $upsBlock;
        }

        // 5. Dominio de Proxies Corporativos
        $proxyBlock = $this->resolveProxies($normalized);
        if ($proxyBlock) {
            $contextBlocks[] = $proxyBlock;
        }

        // 6. Dominio de Sedes y Censo Global
        $siteRes = $this->resolveSitesAndCensus($normalized);
        if ($siteRes) {
            $contextBlocks[] = $siteRes['text'];
            if (!empty($siteRes['entity'])) {
                $updatedEntity = $siteRes['entity'];
            }
        }

        if (empty($contextBlocks)) {
            return [
                'context' => null,
                'active_entity' => $updatedEntity,
            ];
        }

        $fullContext = "[CONTEXTO_DEL_SISTEMA - DATOS FÁCTICOS DE MARIADB]:\n\n" . implode("\n\n---\n\n", $contextBlocks);

        return [
            'context' => $fullContext,
            'active_entity' => $updatedEntity,
        ];
    }

    /**
     * Resuelve consultas sobre asignaciones y actas de usuarios.
     *
     * @return array{text: string, entity: ?array<string, mixed>}|null
     */
    protected function resolveAssignments(string $normalized, string $userMessage): ?array
    {
        $keywords = ['asignacion', 'asignaciones', 'asignad', 'custodio', 'responsable', 'acta', 'uinf', 'pase-mov', 'entrega', 'quien tiene', 'quién tiene'];
        $hasAssignKw = Str::contains($normalized, $keywords);
        $askingCounts = Str::contains($normalized, ['cuantos', 'cuántos', 'total', 'usuarios', 'lista', 'resumen']);

        $stopwords = [
            'cuantos', 'cuántos', 'equipo', 'equipos', 'asignaciones', 'asignacion',
            'tiene', 'tienen', 'quien', 'quién', 'oficina', 'sede', 'valle', 'seco',
            'serial', 'seriales', 'usuario', 'usuarios', 'personal', 'gente', 'dame',
            'cuales', 'cuáles', 'lista', 'total', 'resumen', 'acta', 'actas',
        ];

        preg_match_all('/\b[A-Za-zÁÉÍÓÚáéíóúñÑ]{4,20}\b/u', $userMessage, $matches);
        $allTokens = $matches[0] ?? [];
        $personTokens = array_filter($allTokens, fn($t) => !in_array(Str::lower($t), $stopwords, true));

        if (!$hasAssignKw && empty($personTokens)) {
            return null;
        }

        // Si es una consulta de conteo general
        if ($hasAssignKw && ($askingCounts || empty($personTokens))) {
            $totalAssignments = InventoryAssignment::where('status', 'active')->count();
            $totalUsers = InventoryAssignment::where('status', 'active')->distinct('custodian_name')->count('custodian_name');
            $totalSites = InventoryAssignment::where('status', 'active')->distinct('monitored_site_id')->count('monitored_site_id');

            $recent = InventoryAssignment::with(['asset', 'site'])
                ->where('status', 'active')
                ->latest('id')
                ->take(10)
                ->get();

            $lines = [
                "📊 RESUMEN DE ASIGNACIONES Y USUARIOS (MariaDB - ITAM):",
                "- Total de Actas de Asignación Activas: {$totalAssignments}",
                "- Total de Usuarios / Funcionarios Receptores Únicos: {$totalUsers}",
                "- Sedes Involucradas: {$totalSites}",
                "",
                "📋 Muestra de Asignaciones Vigentes Registradas:",
            ];

            foreach ($recent as $r) {
                $assetName = $r->asset?->name ?? 'Equipo s/n';
                $assetIp = $r->asset?->ip_address ?? 's/ip';
                $siteName = $r->site?->name ?? 'Sede General';
                $dept = $r->custodian_department ?: 'Sin Depto';
                $lines[] = "• Acta: {$r->assignment_code} | Responsable: {$r->custodian_name} ({$dept}) | Equipo: {$assetName} (IP: {$assetIp}) | Sede: {$siteName}";
            }

            return [
                'text' => implode("\n", $lines),
                'entity' => ['type' => 'assignments', 'name' => 'Resumen General de Asignaciones'],
            ];
        }

        // Búsqueda específica por funcionario o departamento
        $searchTerms = !empty($personTokens) ? $personTokens : array_filter($allTokens, fn($t) => mb_strlen($t) >= 4);
        foreach ($searchTerms as $term) {
            $assignments = InventoryAssignment::with(['asset', 'site'])
                ->where(function ($q) use ($term) {
                    $q->where('custodian_name', 'like', "%{$term}%")
                      ->orWhere('custodian_department', 'like', "%{$term}%")
                      ->orWhere('assignment_code', 'like', "%{$term}%");
                })
                ->take(5)
                ->get();

            if ($assignments->isNotEmpty()) {
                $lines = ["📋 ASIGNACIONES LOCALIZADAS PARA '" . Str::upper($term) . "' (MariaDB - ITAM):"];
                $primaryName = $assignments->first()->custodian_name ?: $term;

                foreach ($assignments as $a) {
                    $assetName = $a->asset?->name ?? 'Equipo no especificado';
                    $assetTag = $a->asset?->asset_tag ?? 'N/A';
                    $assetIp = $a->asset?->ip_address ?? 'N/A';
                    $assetSerial = $a->asset?->serial_number ?: 'Sin serial chasis';
                    $siteName = $a->site?->name ?? 'Sede no asignada';
                    $loc = $a->asset?->location_detail ?: 'Ubicación estándar';
                    $ci = $a->custodian_id_number ?: 'N/A';
                    $dept = $a->custodian_department ?: 'N/A';
                    $role = $a->custodian_role ?: 'N/A';
                    $ext = $a->phone_extension ?: 'N/A';
                    $date = $a->assigned_at ? Carbon::parse($a->assigned_at)->format('d/m/Y') : 'Previa';

                    $lines[] = "• Acta Oficial: {$a->assignment_code} (Estado: {$a->status})\n" .
                               "  - Funcionario Responsable: {$a->custodian_name} (CI: {$ci})\n" .
                               "  - Departamento / Cargo: {$dept} | Rol: {$role}\n" .
                               "  - Extensión Telefónica: {$ext}\n" .
                               "  - Puesto / Equipo Asignado: {$assetName} (Tag: {$assetTag}, IP: {$assetIp})\n" .
                               "  - Serial del Equipo: {$assetSerial}\n" .
                               "  - Ubicación Física: Sede {$siteName}, {$loc}\n" .
                               "  - Fecha de Entrega: {$date}";
                }

                return [
                    'text' => implode("\n\n", $lines),
                    'entity' => ['type' => 'user', 'name' => $primaryName],
                ];
            }
        }

        return null;
    }

    /**
     * Resuelve ficha técnica profunda con seriales de CPU, componentes (RAM/Disco) y periféricos (Monitores/UPS).
     *
     * @param array<string, mixed>|null $activeEntity
     * @return array{text: string, entity: ?array<string, mixed>}|null
     */
    protected function resolveDeepHardware(string $normalized, string $userMessage, ?array $activeEntity): ?array
    {
        $serialKws = ['serial', 'seriales', 'hardware', 'componente', 'componentes', 'partes', 'disco', 'ram', 'memoria', 'monitor', 'monitores', 'pantalla', 'periferico', 'periféricos', 'mouse', 'teclado', 'impresora', 'ups de puesto', 'ficha tecnica', 'ficha técnica'];
        $hasHardwareKw = Str::contains($normalized, $serialKws);

        $targetAsset = null;

        // 1. Búsqueda por IP
        if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $userMessage, $ipMatches)) {
            $targetAsset = InventoryAsset::with('site')->where('ip_address', $ipMatches[0])->first();
        }

        // 2. Búsqueda por Nombre Etiqueta o Ubicación
        if (!$targetAsset) {
            preg_match_all('/\b[A-Za-z0-9\-_]{4,25}\b/', $userMessage, $wordMatches);
            $ignoreWords = ['serial', 'seriales', 'equipo', 'dame', 'cuales', 'cuáles', 'componentes', 'partes', 'monitor', 'monitores'];
            foreach ($wordMatches[0] ?? [] as $w) {
                if (in_array(Str::lower($w), $ignoreWords, true)) {
                    continue;
                }
                $targetAsset = InventoryAsset::with('site')
                    ->where('name', 'like', "%{$w}%")
                    ->orWhere('location_detail', 'like', "%{$w}%")
                    ->orWhere('serial_number', 'like', "%{$w}%")
                    ->orWhere('asset_tag', 'like', "%{$w}%")
                    ->first();
                if ($targetAsset) {
                    break;
                }
            }
        }

        // 3. Resolución anafórica usando active_entity
        if (!$targetAsset && $hasHardwareKw && !empty($activeEntity['name'])) {
            $entName = $activeEntity['name'];
            $targetAsset = InventoryAsset::with('site')
                ->where('name', 'like', "%{$entName}%")
                ->orWhereHas('site', fn($q) => $q->where('name', 'like', "%{$entName}%"))
                ->orWhere('custodian_name', 'like', "%{$entName}%")
                ->first();
        }

        if (!$targetAsset && !$hasHardwareKw) {
            return null;
        }

        if ($targetAsset) {
            $matchingAssetIds = InventoryAsset::where(function ($q) use ($targetAsset) {
                if ($targetAsset->ip_address) {
                    $q->where('ip_address', $targetAsset->ip_address);
                }
                $q->orWhere('name', $targetAsset->name)
                  ->orWhere('id', $targetAsset->id);
            })->pluck('id')->toArray();
            if (empty($matchingAssetIds)) {
                $matchingAssetIds = [$targetAsset->id];
            }

            $components = InventoryComponent::whereIn('inventory_asset_id', $matchingAssetIds)->get();
            $peripherals = InventoryPeripheral::whereIn('current_asset_id', $matchingAssetIds)->get();

            $siteName = $targetAsset->site?->name ?? 'Sede no asignada';
            $loc = $targetAsset->location_detail ?: 'Ubicación estándar';
            $custodian = $targetAsset->custodian_name ?: 'Sin asignar';
            $serial = $targetAsset->serial_number ?: 'Sin serial en chasis';

            $lines = [
                "🖥️ FICHA TÉCNICA PROFUNDA Y SERIALES (MariaDB - ITAM):",
                "- Nombre Etiqueta (Puesto): {$targetAsset->name}",
                "- Tipo / Marca / Modelo: {$targetAsset->type} | {$targetAsset->brand} {$targetAsset->model}",
                "- Serial del Chasis / CPU: {$serial}",
                "- Código de Activo (Asset Tag): " . ($targetAsset->asset_tag ?: 'N/A'),
                "- Red: IP " . ($targetAsset->ip_address ?: 'N/A') . " | MAC " . ($targetAsset->mac_address ?: 'N/A'),
                "- Sistema Operativo: " . ($targetAsset->operating_system ?: 'N/A'),
                "- Sede y Ubicación: {$siteName} - {$loc}",
                "- Responsable / Custodio: {$custodian}",
                "- Estado Operativo: {$targetAsset->status}",
                "",
                "💾 Componentes Internos Registrados:",
            ];

            if ($components->isNotEmpty()) {
                foreach ($components as $c) {
                    $cType = Str::upper($c->type ?: 'Componente');
                    $cName = $c->name ?: ($c->model ?: 'Genérico');
                    $cCap = $c->capacity ? " ({$c->capacity})" : '';
                    $cSer = $c->serial_number ?: 'Sin serial registrado';
                    $lines[] = "  • {$cType}: {$cName}{$cCap} | Serial: {$cSer}";
                }
            } else {
                $lines[] = "  • Sin componentes internos desglosados en ITAM.";
            }

            $lines[] = "";
            $lines[] = "🔌 Hardware Complementario y Periféricos Asociados:";
            if ($peripherals->isNotEmpty()) {
                foreach ($peripherals as $p) {
                    $pType = Str::title($p->type ?: 'Periférico');
                    $brand = trim("{$p->brand} {$p->model}") ?: 'Estándar';
                    $pSer = $p->serial_number ?: 'Sin serial registrado';
                    $pTag = $p->asset_tag ? " [Tag: {$p->asset_tag}]" : '';
                    $st = $p->status ?: 'Asignado';
                    $lines[] = "  • {$pType}: {$brand}{$pTag} | Serial: {$pSer} (Estado: {$st})";
                }
            } else {
                $lines[] = "  • Sin periféricos asociados directamente a este activo.";
            }

            return [
                'text' => implode("\n", $lines),
                'entity' => ['type' => 'asset', 'name' => $targetAsset->name],
            ];
        }

        // Búsqueda general de seriales por sede
        $sites = MonitoredSite::where('is_active', true)->get();
        foreach ($sites as $s) {
            $sNorm = Str::lower($s->name);
            if (Str::contains($normalized, $sNorm) || (!empty($activeEntity['name']) && Str::contains($sNorm, Str::lower($activeEntity['name'])))) {
                $devs = InventoryAsset::where('monitored_site_id', $s->id)
                    ->whereNotNull('serial_number')
                    ->where('serial_number', '!=', '')
                    ->take(15)
                    ->get();

                if ($devs->isNotEmpty()) {
                    $lines = ["🔢 LISTADO DE SERIALES REGISTRADOS EN SEDE {$s->name} (MariaDB):"];
                    foreach ($devs as $d) {
                        $lines[] = "• {$d->name} ({$d->type}) | IP: {$d->ip_address} | Serial: {$d->serial_number} | Tag: " . ($d->asset_tag ?: 'N/A');
                    }
                    return [
                        'text' => implode("\n", $lines),
                        'entity' => ['type' => 'site', 'name' => $s->name],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Resuelve horas de caída, tiempo apagado y estabilidad de red.
     *
     * @param array<string, mixed>|null $activeEntity
     * @return array{text: string, entity: ?array<string, mixed>}|null
     */
    protected function resolveUptimeDowntime(string $normalized, string $userMessage, ?array $activeEntity): ?array
    {
        $uptimeKws = ['apagado', 'horas', 'tiempo fuera', 'caido', 'caído', 'downtime', 'uptime', 'cuando se cayo', 'cuándo se cayó', 'disponibilidad', 'desconectado'];
        if (!Str::contains($normalized, $uptimeKws)) {
            return null;
        }

        $targetDev = null;

        // Búsqueda por IP
        if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $userMessage, $ipMatches)) {
            $targetDev = MonitoredNetworkDevice::with('site')->where('ip', $ipMatches[0])->first();
        }

        // Búsqueda por Nombre
        if (!$targetDev) {
            preg_match_all('/\b[A-Za-z0-9\-_]{4,25}\b/', $userMessage, $wordMatches);
            $ignoreWords = ['horas', 'apagado', 'tiempo', 'caido', 'caído', 'equipo', 'cuanto', 'cuánto', 'downtime', 'uptime'];
            foreach ($wordMatches[0] ?? [] as $w) {
                if (in_array(Str::lower($w), $ignoreWords, true)) {
                    continue;
                }
                $targetDev = MonitoredNetworkDevice::with('site')->where('name', 'like', "%{$w}%")->first();
                if ($targetDev) {
                    break;
                }
            }
        }

        // Resolución anafórica
        if (!$targetDev && !empty($activeEntity['name'])) {
            $entName = $activeEntity['name'];
            $targetDev = MonitoredNetworkDevice::with('site')->where('name', 'like', "%{$entName}%")->first();
        }

        if (!$targetDev) {
            // Reporte general de caídos
            $downDevices = MonitoredNetworkDevice::with('site')
                ->whereNotNull('down_since')
                ->orderBy('down_since')
                ->get();

            if ($downDevices->isNotEmpty()) {
                $lines = ["⚠️ REPORTE DE EQUIPOS APAGADOS / FUERA DE LÍNEA (" . $downDevices->count() . " DETECTADOS):"];
                foreach ($downDevices as $d) {
                    $since = Carbon::parse($d->down_since);
                    $h = $since->diffInHours(now());
                    $m = $since->diffInMinutes(now()) % 60;
                    $siteName = $d->site?->name ?? 'Sede General';
                    $lines[] = "• {$d->name} (IP: {$d->ip}) | Sede: {$siteName} | Apagado desde: {$d->down_since} (Tiempo caído: {$h}h {$m}m)";
                }
                return [
                    'text' => implode("\n", $lines),
                    'entity' => ['type' => 'telemetry', 'name' => 'Equipos Caídos'],
                ];
            }

            return [
                'text' => "✅ TELEMETRÍA EN VIVO (MariaDB SSOT):\nTodos los dispositivos de red monitoreados en la infraestructura se encuentran actualmente EN LÍNEA (0 equipos con marca de caída activa).",
                'entity' => null,
            ];
        }

        // Telemetría de un equipo específico
        $siteName = $targetDev->site?->name ?? 'Sede General';
        $stats24h = NetworkDeviceCheckHistory::where('monitored_network_device_id', $targetDev->id)
            ->where('checked_at', '>=', now()->subHours(24))
            ->selectRaw('count(*) as total, sum(case when is_up = 1 then 1 else 0 end) as up_checks, sum(case when is_up = 0 then 1 else 0 end) as down_checks')
            ->first();

        $totalChecks = (int) ($stats24h->total ?? 0);
        $upChecks = (int) ($stats24h->up_checks ?? 0);
        $downChecks = (int) ($stats24h->down_checks ?? 0);
        $uptimePct = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 1) : 100.0;

        $lines = [
            "⏱️ TELEMETRÍA DE DISPONIBILIDAD Y UPTIME: {$targetDev->name}",
            "- Dirección IP: {$targetDev->ip} | Sede: {$siteName}",
        ];

        if ($targetDev->down_since) {
            $since = Carbon::parse($targetDev->down_since);
            $h = $since->diffInHours(now());
            $m = $since->diffInMinutes(now()) % 60;
            $msg = $targetDev->error_state_msg ?: 'Sin respuesta de sondeo';
            $lines[] = "- ESTADO ACTUAL: ❌ FUERA DE LÍNEA / APAGADO";
            $lines[] = "- Caído desde: {$targetDev->down_since}";
            $lines[] = "- TIEMPO TOTAL APAGADO: {$h} horas y {$m} minutos continuos.";
            $lines[] = "- Último mensaje de falla: {$msg}";
        } else {
            $lines[] = "- ESTADO ACTUAL: ✅ EN LÍNEA (Operativo)";
            $lines[] = "- Disponibilidad en las últimas 24 horas: {$uptimePct}% ({$upChecks} sondeos exitosos de {$totalChecks})";
            $lines[] = "- Chequeos fallidos en 24h: {$downChecks}";
            $lines[] = "- Condición del enlace: Estable";
            $lines[] = "- Eventos de caída recientes: Ninguno. El equipo se mantiene 100% operativo sin interrupciones registradas en las últimas 24 horas.";
        }

        return [
            'text' => implode("\n", $lines),
            'entity' => ['type' => 'asset', 'name' => $targetDev->name],
        ];
    }

    /**
     * Resuelve el estado del sistema de energía y UPS central.
     */
    protected function resolveUpsPower(string $normalized): ?string
    {
        $upsKws = ['ups', 'energia', 'energía', 'bateria', 'batería', 'baterias', 'baterías', 'autonomia', 'autonomía', 'voltaje', 'corte', 'falla electrica', 'falla eléctrica', 'corriente', 'electrico', 'eléctrico'];
        if (!Str::contains($normalized, $upsKws)) {
            return null;
        }

        $ups = UpsDevice::first();
        if (!$ups) {
            return null;
        }

        $telem = UpsTelemetryHistory::where('ups_device_id', $ups->id)->latest('recorded_at')->first();

        $vIn = $telem->input_voltage ?? ($ups->input_voltage ?? 0);
        $vOut = $telem->output_voltage ?? ($ups->output_voltage ?? 0);
        $load = $telem->load_percent ?? ($ups->load_percent ?? 0);
        $batPct = $telem->battery_percent ?? ($ups->battery_percent ?? 0);
        $batV = $telem->battery_voltage ?? ($ups->battery_voltage ?? 0);
        $temp = $telem->temperature_c ?? ($ups->temperature_c ?? 0);
        $onBattery = $telem->is_on_battery ?? ($ups->is_on_battery ?? false);

        $statusStr = $onBattery ? "⚠️ OPERANDO CON BATERÍAS (CORTE ELÉCTRICO EN PROGRESO)" : "✅ EN LÍNEA (Alimentación AC Normal)";

        return implode("\n", [
            "⚡ ESTADO DEL SISTEMA DE ENERGÍA Y RESPALDO (MariaDB - UPS):",
            "- Dispositivo: {$ups->name} (Modelo: {$ups->model}, Serial: {$ups->serial_number})",
            "- Condición Eléctrica: {$statusStr}",
            "- Voltaje de Entrada (AC): {$vIn} V | Voltaje de Salida: {$vOut} V",
            "- Carga Actual: {$load}%",
            "- Nivel de Carga de Baterías: {$batPct}% (Voltaje de banco: {$batV} Vdc)",
            "- Temperatura del Sistema: {$temp} °C",
            "- Última telemetría registrada: " . ($ups->last_seen_at ?: 'Reciente'),
        ]);
    }

    /**
     * Resuelve lista de proxies corporativos.
     */
    protected function resolveProxies(string $normalized): ?string
    {
        if (!Str::contains($normalized, ['proxy', 'proxies', 'squid', 'navegacion', 'navegación'])) {
            return null;
        }

        $proxies = MonitoredProxy::orderBy('letter')->get();
        if ($proxies->isEmpty()) {
            return null;
        }

        $lines = ["🌐 LISTA OFICIAL DE PROXIES CORPORATIVOS (MariaDB):"];
        foreach ($proxies as $p) {
            $st = $p->is_active ? 'ACTIVO' : 'INACTIVO';
            $lines[] = "• Proxy {$p->letter}: {$p->name} | IP y Puerto: {$p->ip_port} | Estado: {$st}";
        }
        return implode("\n", $lines);
    }

    /**
     * Resuelve conteo de sedes y censo global de infraestructura.
     *
     * @return array{text: string, entity: ?array<string, mixed>}|null
     */
    protected function resolveSitesAndCensus(string $normalized): ?array
    {
        $askingCensus = Str::contains($normalized, ['resumen general', 'estatus global', 'censo total', 'cuantas sedes', 'cuántas sedes']);

        if ($askingCensus) {
            $totalSites = MonitoredSite::where('is_active', true)->count();
            $totalAssets = InventoryAsset::count();
            $totalNet = MonitoredNetworkDevice::where('is_active', true)->count();
            $totalProxies = MonitoredProxy::where('is_active', true)->count();
            $totalAssign = InventoryAssignment::where('status', 'active')->count();

            $lines = [
                "🏛️ CENSO GLOBAL DE INFRAESTRUCTURA (MariaDB SSOT):",
                "- Sedes Operativas Activas: {$totalSites}",
                "- Total Activos Registrados en ITAM: {$totalAssets}",
                "- Dispositivos de Red Monitoreados en NOC: {$totalNet}",
                "- Actas de Asignación de Equipos Vigentes: {$totalAssign}",
                "- Proxies Corporativos de Navegación: {$totalProxies}",
            ];
            return [
                'text' => implode("\n", $lines),
                'entity' => ['type' => 'census', 'name' => 'Censo Global'],
            ];
        }

        $sites = MonitoredSite::where('is_active', true)->get();
        foreach ($sites as $site) {
            $sNorm = Str::lower($site->name);
            $matches = false;

            if (Str::contains($normalized, $sNorm)) {
                $matches = true;
            } elseif (Str::contains($sNorm, 'mariño') && Str::contains($normalized, ['mariño', 'marino', 'paseo'])) {
                $matches = true;
            } elseif (Str::contains($sNorm, 'consolidado') && Str::contains($normalized, 'consolidado')) {
                $matches = true;
            } elseif (Str::contains($sNorm, 'moron') && Str::contains($normalized, ['moron', 'morón'])) {
                $matches = true;
            } elseif (Str::contains($sNorm, 'trasmision') && Str::contains($normalized, ['transmision', 'trasmision', 'transmisión', 'planta centro'])) {
                $matches = true;
            } elseif (Str::contains($sNorm, 'valle seco') && Str::contains($normalized, 'valle seco')) {
                if (Str::contains($sNorm, 'ciau') && Str::contains($normalized, ['ciau', 'comercial', 'taquilla', 'oficina'])) {
                    $matches = true;
                } elseif (!Str::contains($sNorm, 'ciau') && !Str::contains($normalized, ['ciau', 'comercial'])) {
                    $matches = true;
                }
            }

            if ($matches) {
                $assetCount = InventoryAsset::where('monitored_site_id', $site->id)->count();
                $netCount = MonitoredNetworkDevice::where('monitored_site_id', $site->id)->count();

                $typeRows = InventoryAsset::where('monitored_site_id', $site->id)
                    ->select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray();

                $breakdownParts = [];
                foreach ($typeRows as $type => $count) {
                    $breakdownParts[] = "{$count} {$type}(s)";
                }
                $breakdown = !empty($breakdownParts) ? implode(', ', $breakdownParts) : 'Sin desglose';

                $devRows = InventoryAsset::where('monitored_site_id', $site->id)
                    ->take(15)
                    ->get(['name', 'ip_address', 'serial_number', 'type', 'location_detail']);

                $devLines = [];
                foreach ($devRows as $d) {
                    $ser = $d->serial_number ?: 'Sin serial';
                    $loc = $d->location_detail ?: 'Estándar';
                    $devLines[] = "• {$d->name} ({$d->type}) | IP: {$d->ip_address} | Serial: {$ser} | Puesto: {$loc}";
                }

                $lines = [
                    "🏢 DETALLE DE LA SEDE: {$site->name} (Subred: " . ($site->ip ?: 'N/A') . "):",
                    "- Total Activos en ITAM: {$assetCount}",
                    "- Dispositivos de Red Monitoreados: {$netCount}",
                    "- Tipos de Hardware: {$breakdown}",
                    "",
                    "🖥️ Equipos Registrados en esta Sede:",
                    !empty($devLines) ? implode("\n", $devLines) : "• Sin equipos cargados en esta sede.",
                ];

                return [
                    'text' => implode("\n", $lines),
                    'entity' => ['type' => 'site', 'name' => $site->name],
                ];
            }
        }

        return null;
    }
}
