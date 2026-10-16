<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\InventoryAsset;
use App\Models\MonitoredNetworkDevice;
use App\Models\MonitoredProxy;
use App\Models\MonitoredSite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Servicio de Conocimiento del Sistema y Guardrails de Dominio para el Asistente de IA (ATIT).
 * Responsabilidad Única (S): Detección de intenciones sobre MariaDB (SSOT),
 * inyección de contexto de infraestructura y delimitación estricta de alcance tecnológico.
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
   - Datos y supervisión de la infraestructura de este sistema (proxies, sedes, enlaces WAN, inventario de equipos y telemetría).

2. DELIMITACIÓN DE DOMINIO ESTRICTA (GUARDRAIL INVIOLABLE):
   - Si el usuario te hace preguntas técnicas de telecomunicaciones o TI (ej. "¿Cómo configurar una VLAN en un switch Cisco?", "¿Cómo reiniciar un servicio en Linux?"), responde con máxima solvencia técnica, explicando los pasos y entregando los comandos formateados en bloques de código (```cisco o ```bash).
   - Si el usuario te hace preguntas ajenas a la tecnología, informática o telecomunicaciones (tales como historia, política, deportes, entretenimiento, recetas de cocina, personajes históricos como Simón Bolívar, geografía general, etc.):
     TIENES LA PROHIBICIÓN ESTRICTA DE RESPONDER A ESOS TEMAS.
     En su lugar, debes declinar educadamente con el siguiente mensaje formal y exacto:
     "Como Asistente Técnico corporativo de ATIT, mi función está orientada exclusivamente a la infraestructura de red, telecomunicaciones y soporte técnico de TI. Para temas históricos, de cultura general o ajenos a la tecnología, por favor realiza tu consulta en Google (www.google.com)."

3. DATOS DE INFRAESTRUCTURA REAL (MARIADB SSOT):
   - Cuando se te provean datos en el bloque [CONTEXTO_DEL_SISTEMA], utilízalos con absoluta fidelidad y exactitud. Nunca inventes IPs, seriales, nombres de equipos ni sedes.
   - Si no encuentras un dato específico en el contexto provisto, indícalo con franqueza sin alucinar.

4. IDIOMA Y ESTILO:
   - Responde siempre en español profesional, sobrio, claro y estructurado con viñetas y negritas.
   - Usuario autenticado: {$userName} (Rol: {$userRole}).
PROMPT;
    }

    /**
     * Analiza el mensaje del usuario y, si consulta sobre la infraestructura de MariaDB,
     * extrae la información en tiempo real para inyectarla como contexto fáctico.
     */
    public function resolveSystemContext(string $userMessage): ?string
    {
        $normalized = Str::lower($userMessage);
        $contextBlocks = [];

        // 1. Consulta de Proxies corporativos
        if (Str::contains($normalized, ['proxy', 'proxies', 'squid', 'navegacion'])) {
            $proxies = MonitoredProxy::all();
            if ($proxies->isNotEmpty()) {
                $proxyLines = ["LISTA OFICIAL DE PROXIES CORPORATIVOS (MariaDB):"];
                foreach ($proxies as $p) {
                    $status = $p->is_active ? 'ACTIVO' : 'INACTIVO';
                    $proxyLines[] = "- Proxy {$p->letter}: {$p->name} | IP y Puerto: {$p->ip_port} | Estado: {$status}";
                }
                $contextBlocks[] = implode("\n", $proxyLines);
            }
        }

        // 2. Consulta de Inventario o conteo por Sede
        $sites = MonitoredSite::where('is_active', true)->get();
        foreach ($sites as $site) {
            $siteNormalized = Str::lower($site->name);
            // Comprobar si el mensaje menciona el nombre de la sede o palabras clave
            $keywords = explode(' ', $siteNormalized);
            $matches = false;

            if (Str::contains($normalized, $siteNormalized)) {
                $matches = true;
            } else {
                // Comprobar coincidencias específicas como "paseo mariño", "consolidado", "moron", "valle seco", "trasmision"
                if (Str::contains($siteNormalized, 'mariño') && Str::contains($normalized, ['mariño', 'marino', 'paseo'])) {
                    $matches = true;
                } elseif (Str::contains($siteNormalized, 'consolidado') && Str::contains($normalized, 'consolidado')) {
                    $matches = true;
                } elseif (Str::contains($siteNormalized, 'moron') && Str::contains($normalized, ['moron', 'morón'])) {
                    $matches = true;
                } elseif (Str::contains($siteNormalized, 'trasmision') && Str::contains($normalized, ['transmision', 'trasmision', 'transmisión', 'planta centro'])) {
                    $matches = true;
                } elseif (Str::contains($siteNormalized, 'valle seco') && Str::contains($normalized, 'valle seco')) {
                    // Distinguir entre SEDE VALLE SECO y CIAU VALLE SECO
                    if (Str::contains($siteNormalized, 'ciau') && Str::contains($normalized, ['ciau', 'comercial', 'taquilla', 'oficina'])) {
                        $matches = true;
                    } elseif (!Str::contains($siteNormalized, 'ciau') && !Str::contains($normalized, ['ciau', 'comercial'])) {
                        $matches = true;
                    }
                }
            }

            if ($matches && Str::contains($normalized, ['equipo', 'equipos', 'cuantos', 'cuántos', 'inventario', 'sede', 'dispositivos', 'activos', 'hay', 'tienen', 'computadoras', 'pc'])) {
                $assetCount = InventoryAsset::where('monitored_site_id', $site->id)->count();
                $netDeviceCount = MonitoredNetworkDevice::where('monitored_site_id', $site->id)->count();
                
                // Desglose por tipo
                $breakdown = InventoryAsset::where('monitored_site_id', $site->id)
                    ->select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray();

                $breakdownStr = [];
                foreach ($breakdown as $type => $count) {
                    $breakdownStr[] = "{$count} {$type}(s)";
                }

                $breakdownText = !empty($breakdownStr) ? implode(', ', $breakdownStr) : 'Sin desglose de tipos';

                // Equipos de red y puestos destacados
                $devicesList = InventoryAsset::where('monitored_site_id', $site->id)
                    ->take(15)
                    ->get(['name', 'ip_address', 'serial_number', 'type'])
                    ->map(fn($a) => "- {$a->name} ({$a->type}) | IP: {$a->ip_address} | Serial: {$a->serial_number}")
                    ->implode("\n");

                $contextBlocks[] = <<<SITE_INFO
DATOS DE LA SEDE: {$site->name} (ID: {$site->id}, Subred: {$site->ip})
- Total Equipos Registrados en Inventario ITAM: {$assetCount}
- Dispositivos de Red Monitoreados en NOC: {$netDeviceCount}
- Desglose por Tipos: {$breakdownText}
- Equipos registrados en esta sede:
{$devicesList}
SITE_INFO;
            }
        }

        // 3. Consulta de búsqueda por Serial, IP o Modelo
        // Detectar si el texto contiene patrones de seriales (ej. letras y números de 6+ caracteres)
        preg_match_all('/\b([A-Za-z0-9\-_]{6,25})\b/', $userMessage, $matches);
        $potentialTokens = $matches[1] ?? [];

        foreach ($potentialTokens as $token) {
            $tokenClean = trim($token);
            // Ignorar palabras comunes de red o comandos
            if (in_array(Str::lower($tokenClean), ['cisco', 'switch', 'router', 'debian', 'valle', 'moron', 'puerto', 'config', 'serial', 'servidor', 'direccion', 'ip'], true)) {
                continue;
            }

            $asset = InventoryAsset::with('site')
                ->where('serial_number', 'like', "%{$tokenClean}%")
                ->orWhere('asset_tag', 'like', "%{$tokenClean}%")
                ->orWhere('ip_address', $tokenClean)
                ->first();

            if ($asset) {
                $siteName = $asset->site?->name ?? 'Sede no asignada';
                $responsable = $asset->custodian_name ?: 'Sin responsable asignado';
                $contextBlocks[] = <<<ASSET_INFO
FICHA TÉCNICA DEL ACTIVO DETECTADO (MariaDB):
- Nombre Etiqueta: {$asset->name}
- Tipo: {$asset->type} | Marca: {$asset->brand} | Modelo: {$asset->model}
- Número de Serie (Serial): {$asset->serial_number}
- Código de Activo (Asset Tag): {$asset->asset_tag}
- Dirección IP: {$asset->ip_address} | Dirección MAC: {$asset->mac_address}
- Sede: {$siteName}
- Responsable Asignado: {$responsable}
- Estado Operativo: {$asset->status}
ASSET_INFO;
                break; // Encontrado
            }
        }

        // 4. Resumen Global o Estatus General
        if (Str::contains($normalized, ['resumen general', 'estatus global', 'censo total', 'cuantas sedes', 'cuántas sedes'])) {
            $totalSites = MonitoredSite::where('is_active', true)->count();
            $totalAssets = InventoryAsset::count();
            $totalProxies = MonitoredProxy::where('is_active', true)->count();
            $totalNetDevs = MonitoredNetworkDevice::where('is_active', true)->count();

            $contextBlocks[] = <<<CENSUS
CENSO GLOBAL DE INFRAESTRUCTURA (MariaDB):
- Sedes Activas: {$totalSites}
- Total Activos en Inventario ITAM: {$totalAssets}
- Dispositivos de Red Monitoreados: {$totalNetDevs}
- Proxies Oficiales Activos: {$totalProxies}
CENSUS;
        }

        if (empty($contextBlocks)) {
            return null;
        }

        return "[CONTEXTO_DEL_SISTEMA - DATOS FÁCTICOS DE MARIADB]:\n" . implode("\n\n", $contextBlocks);
    }
}
