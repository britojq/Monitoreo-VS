<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanMonitoringSeeder extends Seeder
{
    /**
     * Seeder limpio y moderno que inicializa sedes y dispositivos reales
     * eliminando slots obsoletos, padding vacío ("NO CONFIGURADO") y nomenclaturas por letras.
     */
    public function run(): void
    {
        // 1. SEDES CORPORATIVAS REALES
        $sites = [
            [
                'id' => 1,
                'name' => 'CIAU VALLE SECO',
                'ip' => '10.20.23.1',
                'phone_1' => '0242-3610061',
                'phone_2' => '0242-3613722',
                'address' => 'Urb. Colinas de Valle Seco, final Av. Bolívar. Edificio CALIFE., Puerto Cabello',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 3,
                'name' => 'CIAU CONSOLIDADO',
                'ip' => '10.20.107.131',
                'phone_1' => '0242 - 3618047',
                'phone_2' => '0242 - 3619584',
                'address' => 'Calle Mariño, C.C. Consolidado, Edificio Planta Alta y Baja, Local N° 1., Puerto Cabello',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 4,
                'name' => 'CIAU PASEO MARIÑO',
                'ip' => '10.20.107.131',
                'phone_1' => '0242 - 3618706',
                'phone_2' => '0242 - 3611317',
                'address' => 'Calle Mariño, Edificio Paseo Mariño, Puerto Cabello',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'id' => 5,
                'name' => 'CIAU MORON',
                'ip' => '10.20.106.193',
                'phone_1' => '0242 - 3724250',
                'phone_2' => '0242 - 3720540',
                'address' => 'Edificio empresa, Calle San José con calle Miranda Diagonal a casa de la Cultura, Morón',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'id' => 7,
                'name' => 'CORDINACION TRASMISION',
                'ip' => '10.20.27.65',
                'phone_1' => '0242-3621429',
                'phone_2' => null,
                'address' => 'Subestación Planta Centro / Valle Seco, Área de Transmisión',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($sites as $siteData) {
            DB::table('monitored_sites')->updateOrInsert(
                ['id' => $siteData['id']],
                array_merge($siteData, [
                    'normal_state_msg' => "✅ - {$siteData['name']}",
                    'error_state_msg' => "❌ - {$siteData['name']}",
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        // 2. DISPOSITIVOS / EQUIPOS REALES POR SEDE
        $devices = [
            // Sede 1: Valle Seco
            [
                'monitored_site_id' => 1,
                'name' => 'ROUTER PRINCIPAL',
                'ip' => '10.20.23.1',
                'mac' => '00:1A:2B:3C:4D:5E',
                'vendor_data' => 'Cisco Systems',
                'access_type' => 'SSH',
                'access_port' => 22,
                'model' => 'Cisco 2901',
                'serial' => 'FGL15242ABC',
                'ports' => '3x GE, 4x EHWIC',
                'notes' => "Gateway principal de la sede Valle Seco.\nEnlace WAN corporativo.",
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 1,
                'name' => 'SW01 - Cisco Principal',
                'ip' => '10.20.23.2',
                'mac' => '70:69:79:A1:B2:C3',
                'vendor_data' => 'Cisco Systems',
                'access_type' => 'TELNET',
                'access_port' => 23,
                'model' => 'Catalyst 2960-X',
                'serial' => 'FCW1942A001',
                'ports' => '48x GE PoE+, 4x SFP',
                'notes' => "Puerto 47 cascada con SW2\nPuerto 48 enlace uplink router principal",
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 1,
                'name' => 'SW02 - Cisco Piso 1',
                'ip' => '10.20.23.4',
                'mac' => '70:69:79:A1:B2:C4',
                'vendor_data' => 'Cisco Systems',
                'access_type' => 'TELNET',
                'access_port' => 23,
                'model' => 'Catalyst 2960-X',
                'serial' => 'FCW1942A002',
                'ports' => '48x GE, 4x SFP',
                'notes' => "Puerto 47 cascada con SW1\nPuerto 48 cascada con SW3",
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 1,
                'name' => 'SW03 - Cisco Servidores',
                'ip' => '10.20.23.5',
                'mac' => '70:69:79:A1:B2:C5',
                'vendor_data' => 'Cisco Systems',
                'access_type' => 'TELNET',
                'access_port' => 23,
                'model' => 'Catalyst 2960-S',
                'serial' => 'FCW1942A003',
                'ports' => '24x GE, 2x SFP+',
                'notes' => "Conexión directa a servidores blade e infraestructura de monitoreo.",
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 1,
                'name' => 'ROUTER WIFI SIGECOM',
                'ip' => '10.20.23.64',
                'mac' => 'B4:EE:B4:88:99:AA',
                'vendor_data' => 'MikroTik / Ubiquiti',
                'access_type' => 'WEB',
                'access_port' => 80,
                'model' => 'RouterBOARD RB951Ui',
                'serial' => 'MT6829101',
                'ports' => '5x FE, WiFi 2.4GHz',
                'notes' => "Red WiFi para captura y lectura móvil de medidores SIGECOM.",
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 1,
                'name' => 'EQUIPO MONITOREO ATIT',
                'ip' => '10.20.23.252',
                'mac' => '00:0C:29:4F:8E:1A',
                'vendor_data' => 'VMware / Debian Linux',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'Servidor Virtual LAMP',
                'serial' => 'VM-ATIT-MON-2026',
                'ports' => '1x vNIC vmxnet3',
                'notes' => "Servidor de despliegue de monitoreo continuo, bots y panel web.",
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 1,
                'name' => 'SW04 - Hon Hai Acceso',
                'ip' => '10.20.23.232',
                'mac' => 'E4:1F:13:55:66:77',
                'vendor_data' => 'Hon Hai Precision',
                'access_type' => 'SIN SOPORTE',
                'access_port' => null,
                'model' => 'Foxconn Embedded Switch',
                'serial' => 'HH2019-902',
                'ports' => '16x FE',
                'notes' => 'Conmutador de periféricos y salas de lectura.',
                'is_active' => true,
            ],

            // Sede 3: Consolidado
            [
                'monitored_site_id' => 3,
                'name' => 'JEFE OFICINA',
                'ip' => '10.20.107.154',
                'mac' => '18:66:DA:33:44:11',
                'vendor_data' => 'Dell Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'OptiPlex 7050',
                'serial' => 'DL-OPT-7050-01',
                'ports' => '1x GE',
                'notes' => 'Estación de trabajo Jefatura CIAU Consolidado.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 3,
                'name' => 'TAQUILLA 01',
                'ip' => '10.20.107.150',
                'mac' => '18:66:DA:33:44:12',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-01',
                'ports' => '1x GE',
                'notes' => 'Atención comercial y recaudación Taquilla 01.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 3,
                'name' => 'TAQUILLA 02',
                'ip' => '10.20.107.151',
                'mac' => '18:66:DA:33:44:13',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-02',
                'ports' => '1x GE',
                'notes' => 'Atención comercial y recaudación Taquilla 02.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 3,
                'name' => 'ATU 01',
                'ip' => '10.20.107.153',
                'mac' => '18:66:DA:33:44:14',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-03',
                'ports' => '1x GE',
                'notes' => 'Área Técnica de Usuarios (ATU).',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 3,
                'name' => 'IMPRESORA RED',
                'ip' => '10.20.107.136',
                'mac' => '00:25:B3:99:88:77',
                'vendor_data' => 'Hewlett-Packard',
                'access_type' => 'WEB',
                'access_port' => 80,
                'model' => 'HP LaserJet Enterprise M605',
                'serial' => 'HPLJ-M605-01',
                'ports' => '1x Fast Ethernet',
                'notes' => 'Impresora compartida red CIAU Consolidado.',
                'is_active' => true,
            ],

            // Sede 4: Paseo Mariño
            [
                'monitored_site_id' => 4,
                'name' => 'JEFE OFICINA',
                'ip' => '10.20.107.155',
                'mac' => '18:66:DA:55:66:21',
                'vendor_data' => 'Dell Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'OptiPlex 7040',
                'serial' => 'DL-OPT-7040-01',
                'ports' => '1x GE',
                'notes' => 'Estación de trabajo Jefatura CIAU Paseo Mariño.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 4,
                'name' => 'TAQUILLA 01',
                'ip' => '10.20.107.148',
                'mac' => '18:66:DA:55:66:22',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G3',
                'serial' => 'HP-PD400-04',
                'ports' => '1x GE',
                'notes' => 'Atención comercial Taquilla 01 Paseo Mariño.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 4,
                'name' => 'TAQUILLA 02',
                'ip' => '10.20.107.152',
                'mac' => '18:66:DA:55:66:23',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G3',
                'serial' => 'HP-PD400-05',
                'ports' => '1x GE',
                'notes' => 'Atención comercial Taquilla 02 Paseo Mariño.',
                'is_active' => true,
            ],

            // Sede 5: Morón
            [
                'monitored_site_id' => 5,
                'name' => 'JEFE OFICINA',
                'ip' => '10.20.106.241',
                'mac' => '18:66:DA:77:88:31',
                'vendor_data' => 'Dell Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'OptiPlex 7050',
                'serial' => 'DL-OPT-7050-02',
                'ports' => '1x GE',
                'notes' => 'Estación de trabajo Jefatura CIAU Morón.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 5,
                'name' => 'TAQUILLA 01',
                'ip' => '10.20.106.240',
                'mac' => '18:66:DA:77:88:32',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-06',
                'ports' => '1x GE',
                'notes' => 'Recaudación Taquilla 01 CIAU Morón.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 5,
                'name' => 'TAQUILLA 02',
                'ip' => '10.20.106.239',
                'mac' => '18:66:DA:77:88:33',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-07',
                'ports' => '1x GE',
                'notes' => 'Recaudación Taquilla 02 CIAU Morón.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 5,
                'name' => 'TAQUILLA 03',
                'ip' => '10.20.106.238',
                'mac' => '18:66:DA:77:88:34',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-08',
                'ports' => '1x GE',
                'notes' => 'Recaudación Taquilla 03 CIAU Morón.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 5,
                'name' => 'ATU 01',
                'ip' => '10.20.106.242',
                'mac' => '18:66:DA:77:88:35',
                'vendor_data' => 'HP Inc.',
                'access_type' => 'VNC',
                'access_port' => 5900,
                'model' => 'HP ProDesk 400 G4',
                'serial' => 'HP-PD400-09',
                'ports' => '1x GE',
                'notes' => 'Área Técnica de Usuarios CIAU Morón.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 5,
                'name' => 'IMPRESORA RED',
                'ip' => '10.20.106.253',
                'mac' => '00:25:B3:77:66:55',
                'vendor_data' => 'Hewlett-Packard',
                'access_type' => 'WEB',
                'access_port' => 80,
                'model' => 'HP LaserJet Enterprise M605',
                'serial' => 'HPLJ-M605-02',
                'ports' => '1x Fast Ethernet',
                'notes' => 'Impresora de red CIAU Morón.',
                'is_active' => true,
            ],

            // Sede 7: Transmisión
            [
                'monitored_site_id' => 7,
                'name' => 'EQUIPO 01',
                'ip' => '10.20.27.81',
                'mac' => '24:B6:FD:11:22:01',
                'vendor_data' => 'Dell Inc.',
                'access_type' => 'SSH',
                'access_port' => 22,
                'model' => 'PowerEdge R340',
                'serial' => 'DL-PE-R340-01',
                'ports' => '2x GE',
                'notes' => 'Servidor telecontrol subestación Planta Centro / Transmisión.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 7,
                'name' => 'EQUIPO 02',
                'ip' => '10.20.27.82',
                'mac' => '24:B6:FD:11:22:02',
                'vendor_data' => 'Dell Inc.',
                'access_type' => 'SSH',
                'access_port' => 22,
                'model' => 'PowerEdge R340',
                'serial' => 'DL-PE-R340-02',
                'ports' => '2x GE',
                'notes' => 'Servidor secundario telecontrol Transmisión.',
                'is_active' => true,
            ],
            [
                'monitored_site_id' => 7,
                'name' => 'EQUIPO 03',
                'ip' => '10.20.27.83',
                'mac' => '24:B6:FD:11:22:03',
                'vendor_data' => 'Cisco Systems',
                'access_type' => 'TELNET',
                'access_port' => 23,
                'model' => 'Catalyst 2960',
                'serial' => 'FCW1942A009',
                'ports' => '24x FE',
                'notes' => 'Switch de comunicaciones Transmisión.',
                'is_active' => true,
            ],
        ];

        // 3. POBLAR Y SINCRONIZAR AMBAS TABLAS DE DISPOSITIVOS
        $deviceOrder = 1;
        $siteCounters = [];

        foreach ($devices as $d) {
            $siteId = $d['monitored_site_id'];
            $siteCounters[$siteId] = ($siteCounters[$siteId] ?? 0) + 1;
            $devNum = $siteCounters[$siteId];

            // Tabla monitored_site_devices
            DB::table('monitored_site_devices')->updateOrInsert(
                ['monitored_site_id' => $siteId, 'device_number' => $devNum],
                [
                    'ip' => $d['ip'],
                    'name' => $d['name'],
                    'mac' => $d['mac'],
                    'vendor_data' => $d['vendor_data'],
                    'access_type' => $d['access_type'],
                    'access_port' => $d['access_port'],
                    'model' => $d['model'],
                    'serial' => $d['serial'],
                    'ports' => $d['ports'],
                    'notes' => $d['notes'],
                    'normal_state_msg' => "✅ - {$d['name']}",
                    'error_state_msg' => "❌ - {$d['name']}",
                    'is_active' => $d['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Tabla monitored_network_devices
            DB::table('monitored_network_devices')->updateOrInsert(
                ['ip' => $d['ip']],
                [
                    'monitored_site_id' => $siteId,
                    'device_number' => $devNum,
                    'name' => $d['name'],
                    'mac' => $d['mac'],
                    'vendor_data' => $d['vendor_data'],
                    'access_type' => $d['access_type'],
                    'access_port' => $d['access_port'],
                    'model' => $d['model'],
                    'serial' => $d['serial'],
                    'ports' => $d['ports'],
                    'notes' => $d['notes'],
                    'normal_state_msg' => "✅ - {$d['name']}",
                    'error_state_msg' => "❌ - {$d['name']}",
                    'is_active' => $d['is_active'],
                    'sort_order' => $deviceOrder++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 4. SERVICIOS CORPORATIVOS Y REGIONALES REALES (SIN "NO CONFIGURADO")
        DB::table('monitored_services')
            ->where('name', 'like', '%NO CONFIGURADO%')
            ->orWhere('type', 'DESACTIVADO')
            ->orWhere('host_ip', '0.0.0.0')
            ->delete();

        $services = [
            ['id' => 1, 'letter' => 'A', 'name' => 'OTRS', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.18.32.6', 'web_url' => 'http://gsatit.empresa.com.ve/otrs/index.pl', 'port' => 25, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 0],
            ['id' => 2, 'letter' => 'B', 'name' => 'INTRANET empresa', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.16.2.28', 'web_url' => 'http://intranet.empresa.com.ve', 'port' => 25, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 1],
            ['id' => 3, 'letter' => 'C', 'name' => 'CORREO WEB', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.250.34.67', 'web_url' => 'http://correo.empresa.com.ve//login.php?phpgw_forward=%2Findex.php', 'port' => 25, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 2],
            ['id' => 4, 'letter' => 'D', 'name' => 'SAO', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.96.0.136', 'web_url' => 'https://saocorp.empresa.com.ve:3002/login?service=https%3A%2F%2Fsaocorp.empresa.com.ve%3A6200%2F', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 3],
            ['id' => 5, 'letter' => 'E', 'name' => 'SURGE', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.16.2.62', 'web_url' => 'http://surge.empresa.com.ve/gestion-incidencias-cliente-web/ui/FrmTablero.html', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 4],
            ['id' => 6, 'letter' => 'F', 'name' => 'FACTURA DIGITAL', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.16.2.28', 'web_url' => 'http://facturadigital.empresa.com.ve/', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 5],
            ['id' => 7, 'letter' => 'G', 'name' => 'SISTEMA AUTOGESTION DE CLAVES', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.16.2.22', 'web_url' => 'http://cambioclaves.empresa.com.ve/gc/', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 6],
            ['id' => 9, 'letter' => 'I', 'name' => 'LDAPADMIN Corporativo', 'type' => 'WEB', 'scope' => 'corporativo', 'host_ip' => '10.2.28.64', 'web_url' => 'https://ldapadmin.empresa.com.ve/htdocs/index.php', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 7],
            ['id' => 10, 'letter' => 'J', 'name' => 'Sistema de Incidencia CARABOBO', 'type' => 'OTRO', 'scope' => 'corporativo', 'host_ip' => '10.18.32.33', 'web_url' => 'https://T4WINP33-SIAR.ev.com', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 8],
            ['id' => 11, 'letter' => 'K', 'name' => 'SAP - empresa Produccion R3', 'type' => 'OTRO', 'scope' => 'corporativo', 'host_ip' => '10.100.94.230', 'web_url' => '10.100.94.230', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 9],
            ['id' => 12, 'letter' => 'L', 'name' => 'LDAP CARABOBO', 'type' => 'LDAP', 'scope' => 'regional', 'host_ip' => '10.20.0.22', 'web_url' => 'ldapr2-t4.empresa.com.ve', 'port' => 389, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 10],
            ['id' => 13, 'letter' => 'M', 'name' => '(DNS PRIMARIO CARABOBO - ARAGUA)', 'type' => 'DNS', 'scope' => 'regional', 'host_ip' => '100.1.1.16', 'web_url' => '100.1.1.16', 'port' => 53, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 11],
            ['id' => 14, 'letter' => 'N', 'name' => '(DNS SECUNDARIO CARABOBO - ARAGUA)', 'type' => 'WEB', 'scope' => 'regional', 'host_ip' => '10.100.94.230', 'web_url' => 'https://ldapr2-002.ev.com:10000/', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 12],
            ['id' => 15, 'letter' => 'O', 'name' => 'DHCP CARABOBO - ARAGUA', 'type' => 'DHCP', 'scope' => 'regional', 'host_ip' => '10.18.32.38', 'web_url' => 't4linp38.empresa.com.ve', 'port' => null, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 13],
            ['id' => 17, 'letter' => 'Q', 'name' => 'Pfsense - CARABOBO (119)', 'type' => 'PROXY', 'scope' => 'regional', 'host_ip' => '10.20.0.119', 'web_url' => 'https://core.telegram.org/bots', 'port' => 8080, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 14],
            ['id' => 18, 'letter' => 'R', 'name' => 'Pfsense - CARABOBO (89)', 'type' => 'PROXY', 'scope' => 'regional', 'host_ip' => '10.20.0.89', 'web_url' => 'https://core.telegram.org/bots', 'port' => 8080, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 15],
            ['id' => 19, 'letter' => 'S', 'name' => 'Pfsense - Valle Seco (65)', 'type' => 'PROXY', 'scope' => 'regional', 'host_ip' => '10.20.23.65', 'web_url' => 'https://core.telegram.org/bots', 'port' => 8080, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 16],
            ['id' => 20, 'letter' => 'T', 'name' => 'Servidor de Correo CARABOBO (thunderbird)', 'type' => 'SMTP', 'scope' => 'regional', 'host_ip' => '10.18.32.6', 'web_url' => 'mailr2-t4.empresa.com.ve', 'port' => 25, 'credentials' => 'USUARIO:CLAVE', 'check_interface' => 'eno1', 'dns_test_domain' => 'intranet.empresa.com.ve', 'is_active' => true, 'sort_order' => 17],
        ];

        // 4.1 Protección de URLs y dominios operativos reales:
        // Cargar URLs legítimas desde config/monitoreo.conf si está presente en el servidor
        $confServices = [];
        $confPaths = [
            base_path('../config/monitoreo.conf'),
            '/scripts/telegram-admin-bot/config/monitoreo.conf',
            base_path('config/monitoreo.conf')
        ];
        foreach ($confPaths as $cPath) {
            if (file_exists($cPath)) {
                $lines = @file($cPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim(trim($v), '"\'');
                    if (preg_match('/^(TYPESERVICE|NAMESERVICE|WEBSERVICE|IPSERVICE|TESTHOSTDNS)([A-Z])$/', $k, $m)) {
                        $var = $m[1];
                        $letter = $m[2];
                        $confServices[$letter][$var] = $v;
                    }
                }
                break;
            }
        }

        foreach ($services as $srv) {
            $letter = $srv['letter'] ?? '';
            // Si config/monitoreo.conf tiene valores reales, tienen precedencia
            if (isset($confServices[$letter]['WEBSERVICE']) && !empty($confServices[$letter]['WEBSERVICE'])) {
                $srv['web_url'] = $confServices[$letter]['WEBSERVICE'];
            }
            if (isset($confServices[$letter]['TESTHOSTDNS']) && !empty($confServices[$letter]['TESTHOSTDNS'])) {
                $srv['dns_test_domain'] = $confServices[$letter]['TESTHOSTDNS'];
            }
            if (isset($confServices[$letter]['NAMESERVICE']) && !empty($confServices[$letter]['NAMESERVICE'])) {
                $srv['name'] = $confServices[$letter]['NAMESERVICE'];
            }

            // Des-sanitización automática: si el seeder fue procesado para repositorio público con 'empresa'
            $srv['web_url'] = str_ireplace('empresa.com.ve', 'empresa.com.ve', $srv['web_url']);
            $srv['dns_test_domain'] = str_ireplace('empresa.com.ve', 'empresa.com.ve', $srv['dns_test_domain']);
            $srv['name'] = str_ireplace('empresa', 'empresa', $srv['name']);

            // Si la base de datos ya contiene un registro con URL operativa legítima, preservarla
            $existing = DB::table('monitored_services')->where('id', $srv['id'])->first();
            if ($existing) {
                if (!empty($existing->web_url) && !str_contains($existing->web_url, 'empresa.com.ve')) {
                    $srv['web_url'] = $existing->web_url;
                }
                if (!empty($existing->dns_test_domain) && !str_contains($existing->dns_test_domain, 'empresa.com.ve')) {
                    $srv['dns_test_domain'] = $existing->dns_test_domain;
                }
                if (!empty($existing->host_ip) && $existing->host_ip !== '0.0.0.0') {
                    $srv['host_ip'] = $existing->host_ip;
                }
                if (!empty($existing->credentials) && $existing->credentials !== 'USUARIO:CLAVE') {
                    $srv['credentials'] = $existing->credentials;
                }
            }

            // Para servicios tipo PROXY: si no tienen credenciales o son dummy, heredar de monitored_proxies
            if (($srv['type'] ?? '') === 'PROXY' && (empty($srv['credentials']) || $srv['credentials'] === 'USUARIO:CLAVE')) {
                $proxyHost = $srv['host_ip'] ?? '';
                $realProxy = DB::table('monitored_proxies')->where('ip_port', 'like', "{$proxyHost}:%")->first();
                if ($realProxy && !empty($realProxy->auth_userpass) && $realProxy->auth_userpass !== 'USUARIO:CLAVE') {
                    $srv['credentials'] = $realProxy->auth_userpass;
                }
            }

            DB::table('monitored_services')->updateOrInsert(
                ['id' => $srv['id']],
                array_merge($srv, [
                    'normal_state_msg' => "✅ - {$srv['name']}",
                    'error_state_msg' => "❌ - {$srv['name']}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
