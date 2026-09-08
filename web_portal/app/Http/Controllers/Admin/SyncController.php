<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Process;

class SyncController extends Controller
{
    public function exportToConfigFiles(): bool
    {
        try {
            $confPath = '/scripts/telegram-admin-bot/config/monitoreo.conf';
            $botConfPath = '/scripts/telegram-admin-bot/config/bot.conf';

            // 1. Construir monitoreo.conf
            $lines = [];
            $lines[] = "# ==============================================================================";
            $lines[] = "# 📊 CONFIGURACIÓN DE MONITOREO: monitoreo.conf";
            $lines[] = "# Sincronizado automáticamente desde el Panel Web ATIT Valle Seco";
            $lines[] = "# Fecha: " . date('Y-m-d H:i:s');
            $lines[] = "# ==============================================================================";
            $lines[] = "";
            $lines[] = "DEBUG=DESACTIVADO";
            $lines[] = "NOPROXYIP=8.8.8.8";
            $lines[] = "";
            $lines[] = "######################### SECCION DE CHEQUEO SERVICIOS #########################";

            $services = MonitoredService::orderBy('sort_order')->get();
            foreach ($services as $s) {
                $L = $s->letter;
                $lines[] = "################################### HOST ({$L}) ###################################";
                $lines[] = "TYPESERVICE{$L}=" . ($s->is_active ? $s->type : 'DESACTIVADO');
                $lines[] = "NAMESERVICE{$L}=\"" . addslashes($s->name) . "\"";
                $lines[] = "WEBSERVICE{$L}=" . ($s->web_url ?? 'http://127.0.0.1');
                $rawHost = $s->host_ip ?? '127.0.0.1';
                $cleanHost = explode(':', $rawHost)[0];
                $servicePort = $s->port ?? (isset(explode(':', $rawHost)[1]) ? explode(':', $rawHost)[1] : null);

                $lines[] = "IPSERVICE{$L}=" . $cleanHost;
                $lines[] = "CUPSPORTIP{$L}=" . $cleanHost . ":" . ($servicePort ?? 631);
                $lines[] = "LDAPPORTIP{$L}=" . ($servicePort ?? 389);
                $lines[] = "SMTPPORT{$L}=" . ($servicePort ?? 25);
                $lines[] = "NETINTERFACE{$L}=" . ($s->check_interface ?? 'eno1');
                $lines[] = "TESTHOSTDNS{$L}=\"" . addslashes($s->dns_test_domain ?? 'intranet.empresa.local') . "\"";
                $lines[] = "PROXYUSERPASSW{$L}=" . ($s->credentials ?? 'USUARIO:CLAVE');
                $lines[] = "PROXYIPPORT{$L}=" . $cleanHost . ":" . ($servicePort ?? 8080);
                if ($s->type === 'PROXY') {
                    $testUrl = $s->web_url ?? '';
                    $low = strtolower($testUrl);
                    if ($testUrl && filter_var($testUrl, FILTER_VALIDATE_URL) && !str_contains($low, 'pfsense') && !str_contains($low, 'proxyr2')) {
                        $lines[] = "URLTESTSITE{$L}=" . $testUrl;
                    } else {
                        $lines[] = "URLTESTSITE{$L}=https://core.telegram.org/bots";
                    }
                } else {
                    $lines[] = "URLTESTSITE{$L}=" . ($s->web_url ?? 'https://google.com');
                }
                $lines[] = "NORMALESTATEMSG{$L}=\"" . addslashes($s->normal_state_msg ?? "✅ - \$NAMESERVICE{$L}") . "\"";
                $lines[] = "ERRORESTATEMSG{$L}=\"" . addslashes($s->error_state_msg ?? "❌ - \$NAMESERVICE{$L}") . "\"";
                $lines[] = "################################ FIN HOST ({$L}) ##################################";
                $lines[] = "";
            }

            $lines[] = "######################### SECCION DE CHEQUEO SEDES #########################";
            $sites = MonitoredSite::with('devices')->orderBy('sort_order')->get();
            foreach ($sites as $site) {
                $L = $site->letter;
                $lines[] = "################################### SITE ({$L}) ###################################";
                $lines[] = "NAMESITE{$L}=\"" . addslashes($site->name) . "\"";
                $lines[] = "IPSITE{$L}=" . ($site->ip ?? '0.0.0.0');
                $lines[] = "NORMALSITE{$L}=\"" . addslashes($site->normal_state_msg ?? "✅ - \$NAMESITE{$L}") . "\"";
                $lines[] = "ERRORSITE{$L}=\"" . addslashes($site->error_state_msg ?? "❌ - \$NAMESITE{$L}") . "\"";

                for ($i = 1; $i <= 8; $i++) {
                    $phone = $site->{"phone_{$i}"} ?? '';
                    $lines[] = "SITE{$L}TELEFONO{$i}=\"" . addslashes($phone) . "\"";
                }
                $lines[] = "SITE{$L}DIRECCION=\"" . addslashes($site->address ?? '') . "\"";

                foreach ($site->devices as $dev) {
                    $N = $dev->device_number;
                    $lines[] = "NAMESITE{$L}EQUIPO{$N}=\"" . addslashes($dev->name) . "\"";
                    $lines[] = "IPSITE{$L}EQUIPO{$N}=" . ($dev->is_active ? $dev->ip : '0.0.0.0');
                    $lines[] = "NORMALSITE{$L}EQUIPO{$N}=\"" . addslashes($dev->normal_state_msg ?? "✅ - \$NAMESITE{$L}EQUIPO{$N}") . "\"";
                    $lines[] = "ERRORSITE{$L}EQUIPO{$N}=\"" . addslashes($dev->error_state_msg ?? "❌ - \$NAMESITE{$L}EQUIPO{$N}") . "\"";
                }
                $lines[] = "################################ FIN SITE ({$L}) ##################################";
                $lines[] = "";
            }

            // 3. Sección Dispositivos Sede Valle Seco
            $lines[] = "################################################################################";
            $lines[] = "################### SECCION DISPOSITIVOS SEDE VALLE SECO ####################";
            $lines[] = "################################################################################";
            $lines[] = "# DISPOSITIVOS LOCALES DE LA SEDE VALLE SECO (10.20.23.0/24)";
            $lines[] = "# CHEQUEO MEDIANTE PING ICMP (FUNCIONES DE VERIFICACION UNIFICADAS)";
            $lines[] = "#";
            $lines[] = "";

            $netDevices = \App\Models\MonitoredNetworkDevice::orderBy('sort_order')->get();
            foreach ($netDevices as $nd) {
                $num = $nd->device_number;
                $lines[] = "## DISPOSITIVO {$num}";
                $lines[] = "DISPOSITIVO{$num}_NAME=\"" . addslashes($nd->name) . "\"";
                $lines[] = "DISPOSITIVO{$num}_IP=\"" . addslashes($nd->ip) . "\"";
                $lines[] = "DISPOSITIVO{$num}_MAC=\"" . addslashes($nd->mac ?? '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_DATOS=\"" . addslashes($nd->vendor_data ?? '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_ACCESS=\"" . addslashes($nd->access_type ?? 'SIN SOPORTE') . "\"";
                $lines[] = "DISPOSITIVO{$num}_PORT=\"" . addslashes($nd->access_port ? (string)$nd->access_port : '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_MODELO=\"" . addslashes($nd->model ?? '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_SERIAL=\"" . addslashes($nd->serial ?? '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_PUERTOS=\"" . addslashes($nd->ports ?? '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_NOTAS=\"" . addslashes($nd->notes ?? '') . "\"";
                $lines[] = "DISPOSITIVO{$num}_NORMAL=\"✅ \$DISPOSITIVO{$num}_NAME\"";
                $lines[] = "DISPOSITIVO{$num}_ERROR=\"❌ \$DISPOSITIVO{$num}_NAME\"";
                $lines[] = "";
            }

            @file_put_contents($confPath, implode("\n", $lines) . "\n");

            // 2. Construir bot.conf proxies
            $proxies = MonitoredProxy::orderBy('letter')->get();
            $pLines = [];
            $pLines[] = "# ==============================================================================";
            $pLines[] = "# 🌐 CONFIGURACIÓN DE PROXIES: bot.conf";
            $pLines[] = "# Sincronizado automáticamente desde el Panel Web ATIT Valle Seco";
            $pLines[] = "# ==============================================================================";
            foreach ($proxies as $p) {
                $L = $p->letter;
                $pLines[] = "NAMEPROXY{$L}=\"" . addslashes($p->name) . "\"";
                $pLines[] = "IPADDRPORTPROXY{$L}=" . ($p->is_active ? $p->ip_port : '');
                $pLines[] = "USERPASSWDPROXY{$L}=" . ($p->auth_userpass ?? '');
            }
            @file_put_contents($botConfPath, implode("\n", $pLines) . "\n");

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    public function triggerSyncManual(): RedirectResponse
    {
        $this->exportToConfigFiles();
        return back()->with('success', 'Archivos de configuración exportados y sincronizados correctamente.');
    }

    public function triggerScanNow(): JsonResponse
    {
        $this->exportToConfigFiles();
        $pythonScript = '/scripts/telegram-admin-bot/monitor/monitor_web_sync.py';
        $pythonBin = '/scripts/telegram-admin-bot/venv/bin/python';

        if (file_exists($pythonScript) && file_exists($pythonBin)) {
            $result = Process::run("{$pythonBin} {$pythonScript}");
            return response()->json([
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Script de escaneo Python no encontrado.',
        ], 404);
    }
}
