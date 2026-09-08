<?php

namespace Database\Seeders;

use App\Models\MonitoredProxy;
use App\Models\MonitoredService;
use App\Models\MonitoredSite;
use App\Models\MonitoredSiteDevice;
use Illuminate\Database\Seeder;

class InitialMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. SERVICIOS CORPORATIVOS ---
        
        MonitoredService::updateOrCreate(
            ['letter' => 'A'],
            [
                'name' => 'OTRS',
                'type' => 'WEB',
                'host_ip' => '10.18.32.6',
                'web_url' => 'http://gsatit.corpoelec.com.ve/otrs/index.pl',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEA',
                'error_state_msg' => '❌ - $NAMESERVICEA',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'B'],
            [
                'name' => 'INTRANET CORPOELEC',
                'type' => 'WEB',
                'host_ip' => '10.16.2.28',
                'web_url' => 'http://intranet.corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEB',
                'error_state_msg' => '❌ - $NAMESERVICEB',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'C'],
            [
                'name' => 'CORREO WEB',
                'type' => 'WEB',
                'host_ip' => '10.250.34.67',
                'web_url' => 'http://correo.corpoelec.com.ve//login.php?phpgw_forward=%2Findex.php',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEC',
                'error_state_msg' => '❌ - $NAMESERVICEC',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'D'],
            [
                'name' => 'SAO',
                'type' => 'WEB',
                'host_ip' => '10.96.0.136',
                'web_url' => 'https://saocorp.corpoelec.com.ve:3002/login?service=https%3A%2F%2Fsaocorp.corpoelec.com.ve%3A6200%2F',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICED',
                'error_state_msg' => '❌ - $NAMESERVICED',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'E'],
            [
                'name' => 'SURGE',
                'type' => 'WEB',
                'host_ip' => '10.16.2.62',
                'web_url' => 'http://surge.corpoelec.com.ve/gestion-incidencias-cliente-web/ui/FrmTablero.html',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEE',
                'error_state_msg' => '❌ - $NAMESERVICEE',
                'is_active' => true,
                'sort_order' => 4,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'F'],
            [
                'name' => 'FACTURA DIGITAL',
                'type' => 'WEB',
                'host_ip' => '10.16.2.28',
                'web_url' => 'http://facturadigital.corpoelec.com.ve/',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEF',
                'error_state_msg' => '❌ - $NAMESERVICEF',
                'is_active' => true,
                'sort_order' => 5,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'G'],
            [
                'name' => 'SISTEMA AUTOGESTION DE CLAVES',
                'type' => 'WEB',
                'host_ip' => '10.16.2.22',
                'web_url' => 'http://cambioclaves.corpoelec.com.ve/gc/',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEG',
                'error_state_msg' => '❌ - $NAMESERVICEG',
                'is_active' => true,
                'sort_order' => 6,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'H'],
            [
                'name' => 'CAMBIO DE CLAVE CORREO (claver1)" #DESACTIVAR PARA REPORTE',
                'type' => 'WEB',
                'host_ip' => '10.16.2.22',
                'web_url' => 'https://claver1-sb.corpoelec.com.ve/',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEH',
                'error_state_msg' => '❌ - $NAMESERVICEH',
                'is_active' => true,
                'sort_order' => 7,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'I'],
            [
                'name' => 'LDAPADMIN Corporativo',
                'type' => 'WEB',
                'host_ip' => '10.2.28.64',
                'web_url' => 'https://ldapadmin.corpoelec.com.ve/htdocs/index.php',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEI',
                'error_state_msg' => '❌ - $NAMESERVICEI',
                'is_active' => true,
                'sort_order' => 8,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'J'],
            [
                'name' => 'Sistema de Incidencia CARABOBO',
                'type' => 'OTRO',
                'host_ip' => '10.18.32.33',
                'web_url' => 'https://T4WINP33-SIAR.ev.com',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEJ',
                'error_state_msg' => '❌ - $NAMESERVICEJ',
                'is_active' => true,
                'sort_order' => 9,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'K'],
            [
                'name' => 'SAP - CORPOELEC Produccion R3',
                'type' => 'OTRO',
                'host_ip' => '10.100.94.230',
                'web_url' => '10.100.94.230',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEK',
                'error_state_msg' => '❌ - $NAMESERVICEK',
                'is_active' => true,
                'sort_order' => 10,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'L'],
            [
                'name' => 'LDAP CARABOBO',
                'type' => 'LDAP',
                'host_ip' => '10.16.2.24',
                'web_url' => 'ldapr2-t4.corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEL',
                'error_state_msg' => '❌ - $NAMESERVICEL',
                'is_active' => true,
                'sort_order' => 11,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'M'],
            [
                'name' => '(DNS PRIMARIO CARABOBO - ARAGUA)',
                'type' => 'DNS',
                'host_ip' => '100.1.1.16',
                'web_url' => '100.1.1.16',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEM',
                'error_state_msg' => '❌ - $NAMESERVICEM',
                'is_active' => true,
                'sort_order' => 12,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'N'],
            [
                'name' => '(DNS SECUNDARIO CARABOBO - ARAGUA)',
                'type' => 'WEB',
                'host_ip' => '10.100.94.230',
                'web_url' => 'https://ldapr2-002.ev.com:10000/',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEN',
                'error_state_msg' => '❌ - $NAMESERVICEN',
                'is_active' => true,
                'sort_order' => 13,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'O'],
            [
                'name' => 'DHCP CARABOBO - ARAGUA',
                'type' => 'DHCP',
                'host_ip' => '10.18.32.38',
                'web_url' => 't4linp38.corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEO',
                'error_state_msg' => '❌ - $NAMESERVICEO',
                'is_active' => true,
                'sort_order' => 14,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'P'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'proxyr2-t4.corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'A1746281:Carabobo01*',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEP',
                'error_state_msg' => '❌ - $NAMESERVICEP',
                'is_active' => true,
                'sort_order' => 15,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'Q'],
            [
                'name' => 'Pfsense - CARABOBO (119)',
                'type' => 'PROXY',
                'host_ip' => '10.20.0.119',
                'web_url' => 'https://core.telegram.org/bots',
                'port' => 8080,
                'credentials' => 'A1746281:Abrito2026.*',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEQ',
                'error_state_msg' => '❌ - $NAMESERVICEQ',
                'is_active' => true,
                'sort_order' => 16,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'R'],
            [
                'name' => 'Pfsense - CARABOBO (89)',
                'type' => 'PROXY',
                'host_ip' => '10.20.0.89',
                'web_url' => 'https://core.telegram.org/bots',
                'port' => 8080,
                'credentials' => 'A1746281:Abrito2026.*',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICER',
                'error_state_msg' => '❌ - $NAMESERVICER',
                'is_active' => true,
                'sort_order' => 17,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'S'],
            [
                'name' => 'Pfsense - Valle Seco (65)',
                'type' => 'PROXY',
                'host_ip' => '10.20.23.65',
                'web_url' => 'https://core.telegram.org/bots',
                'port' => 8080,
                'credentials' => 'jbrito:Octubre2022.',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICES',
                'error_state_msg' => '❌ - $NAMESERVICES',
                'is_active' => true,
                'sort_order' => 18,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'T'],
            [
                'name' => 'Servidor de Correo CARABOBO (thunderbird)',
                'type' => 'SMTP',
                'host_ip' => '10.18.32.6',
                'web_url' => 'mailr2-t4.corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICET',
                'error_state_msg' => '❌ - $NAMESERVICET',
                'is_active' => true,
                'sort_order' => 19,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'U'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'corpoelec.com.ve',
                'port' => 993,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEU',
                'error_state_msg' => '❌ - $NAMESERVICEU',
                'is_active' => true,
                'sort_order' => 20,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'V'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEV',
                'error_state_msg' => '❌ - $NAMESERVICEV',
                'is_active' => true,
                'sort_order' => 21,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'W'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEW',
                'error_state_msg' => '❌ - $NAMESERVICEW',
                'is_active' => true,
                'sort_order' => 22,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'X'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEX',
                'error_state_msg' => '❌ - $NAMESERVICEX',
                'is_active' => true,
                'sort_order' => 23,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'Y'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEY',
                'error_state_msg' => '❌ - $NAMESERVICEY',
                'is_active' => true,
                'sort_order' => 24,
            ]
        );
        MonitoredService::updateOrCreate(
            ['letter' => 'Z'],
            [
                'name' => 'NO CONFIGURADO',
                'type' => 'OTRO',
                'host_ip' => '0.0.0.0',
                'web_url' => 'corpoelec.com.ve',
                'port' => 25,
                'credentials' => 'USUARIO:CLAVE',
                'check_interface' => 'eno1',
                'dns_test_domain' => 'intranet.corpoelec.com.ve',
                'normal_state_msg' => '✅ - $NAMESERVICEZ',
                'error_state_msg' => '❌ - $NAMESERVICEZ',
                'is_active' => true,
                'sort_order' => 25,
            ]
        );

        // --- 2. SEDES Y EQUIPOS ---
        
        $siteA = MonitoredSite::updateOrCreate(
            ['letter' => 'A'],
            [
                'name' => 'CIAU VALLE SECO',
                'ip' => '10.20.23.1',
                'phone_1' => '0242-3610061',
                'phone_2' => '0242-3613722 (ABBA)',
                'phone_3' => '0242-3612410',
                'phone_4' => '0242-3614625',
                'phone_5' => '0242-3616717',
                'phone_6' => '0242-3610539',
                'phone_7' => '0242-3612140',
                'phone_8' => '0242-3612140',
                'address' => 'Urb. Colinas de Valle Seco, final Av. Bolívar. Edificio CALIFE., Puerto Cabello - Edo Carabobo',
                'normal_state_msg' => '✅ $NAMESITEA',
                'error_state_msg' => '❌ $NAMESITEA',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 1],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 2],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 3],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 4],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 5],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteA->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEAEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEAEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteB = MonitoredSite::updateOrCreate(
            ['letter' => 'B'],
            [
                'name' => 'CIAU CAMPO ALEGRE',
                'ip' => '10.20.107.194',
                'phone_1' => '0242 - 3611044',
                'phone_2' => '0242 - 3618442',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'Urb. Rancho Grande, Calle N° 29, C.C. Campo Alegre, PB, Local N° 1 y 2 B., Puerto Cabello - Edo Carabobo',
                'normal_state_msg' => '✅ $NAMESITEB',
                'error_state_msg' => '❌ $NAMESITEB',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 1],
                [
                    'name' => 'JEFE OFICINA',
                    'ip' => '10.20.107.229',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 2],
                [
                    'name' => 'TAQUILLA 01',
                    'ip' => '10.20.107.223',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 3],
                [
                    'name' => 'TAQUILLA 02',
                    'ip' => '10.20.107.228',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 4],
                [
                    'name' => 'IMPRESORA RED',
                    'ip' => '10.20.107.201',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 5],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteB->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEBEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEBEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteC = MonitoredSite::updateOrCreate(
            ['letter' => 'C'],
            [
                'name' => 'CIAU CONSOLIDADO',
                'ip' => '10.20.107.131',
                'phone_1' => '0242 - 3618047',
                'phone_2' => '0242 - 3619584',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'Calle Mariño, C.C. Consolidado, Edificio Planta Alta y Baja, Local N° 1., Puerto Cabello - Edo Carabobo',
                'normal_state_msg' => '✅ $NAMESITEC',
                'error_state_msg' => '❌ $NAMESITEC',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 1],
                [
                    'name' => 'JEFE OFICINA',
                    'ip' => '10.20.107.154',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 2],
                [
                    'name' => 'TAQUILLA 01',
                    'ip' => '10.20.107.150',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 3],
                [
                    'name' => 'TAQUILLA 02',
                    'ip' => '10.20.107.151',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 4],
                [
                    'name' => 'ATU 01',
                    'ip' => '10.20.107.153',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 5],
                [
                    'name' => 'IMPRESORA RED',
                    'ip' => '10.20.107.136',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteC->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITECEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITECEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteD = MonitoredSite::updateOrCreate(
            ['letter' => 'D'],
            [
                'name' => 'CIAU PASEO MARIÑO',
                'ip' => '10.20.107.131',
                'phone_1' => '0242 - 3618706',
                'phone_2' => '0242 - 3611317',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'NO CONFIGURADO',
                'normal_state_msg' => '✅ $NAMESITED',
                'error_state_msg' => '❌ $NAMESITED',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 1],
                [
                    'name' => 'TAQUILLA 01',
                    'ip' => '10.20.107.152',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 2],
                [
                    'name' => 'TAQUILLA 02',
                    'ip' => '10.20.107.148',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 3],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 4],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 5],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteD->id, 'device_number' => 8],
                [
                    'name' => 'IMPRESORA RED',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEDEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEDEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteE = MonitoredSite::updateOrCreate(
            ['letter' => 'E'],
            [
                'name' => 'CIAU MORON',
                'ip' => '10.20.106.193',
                'phone_1' => '0242 - 3724250',
                'phone_2' => '0242 - 3720540',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'Edificio CORPOELEC, Calle San José con calle Miranda Diagonal a casa de la Cultura, Moron - Edo Carabobo',
                'normal_state_msg' => '✅ $NAMESITEE',
                'error_state_msg' => '❌ $NAMESITEE',
                'is_active' => true,
                'sort_order' => 4,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 1],
                [
                    'name' => 'JEFE OFICINA',
                    'ip' => '10.20.106.241',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 2],
                [
                    'name' => 'TAQUILLA 01',
                    'ip' => '10.20.106.240',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 3],
                [
                    'name' => 'TAQUILLA 02',
                    'ip' => '10.20.106.239',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 4],
                [
                    'name' => 'TAQUILLA 03',
                    'ip' => '10.20.106.238',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 5],
                [
                    'name' => 'ATU 01',
                    'ip' => '10.20.106.242',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 6],
                [
                    'name' => 'IMPRESORA RED',
                    'ip' => '10.20.106.253',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteE->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEEEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEEEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteF = MonitoredSite::updateOrCreate(
            ['letter' => 'F'],
            [
                'name' => 'CIAU -  Guaicamacuto',
                'ip' => '0.0.0.0',
                'phone_1' => '0242 - 3645902',
                'phone_2' => '0242 - 3645980',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'Dirección:Av la paz C C Guaicamacuto Nro M14B Cumboto Norte, Puerto Cabello Edo Carabobo',
                'normal_state_msg' => '✅ $NAMESITEF',
                'error_state_msg' => '❌ $NAMESITEF',
                'is_active' => true,
                'sort_order' => 5,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 1],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 2],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 3],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 4],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 5],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteF->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEFEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEFEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteG = MonitoredSite::updateOrCreate(
            ['letter' => 'G'],
            [
                'name' => 'CORDINACION TRASMISION',
                'ip' => '10.20.27.65',
                'phone_1' => '0242-3621429',
                'phone_2' => 'NO CONFIGURADO',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'NO CONFIGURADO',
                'normal_state_msg' => '✅ $NAMESITEG',
                'error_state_msg' => '❌ $NAMESITEG',
                'is_active' => true,
                'sort_order' => 6,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 1],
                [
                    'name' => 'EQUIPO 01',
                    'ip' => '10.20.27.81',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 2],
                [
                    'name' => 'EQUIPO 02',
                    'ip' => '10.20.27.84',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 3],
                [
                    'name' => 'EQUIPO 03',
                    'ip' => '10.20.27.84',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 4],
                [
                    'name' => 'IMPRESORA HP2420',
                    'ip' => '10.20.27.90',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 5],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteG->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEGEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEGEQUIPO8',
                    'is_active' => true,
                ]
            );
        $siteH = MonitoredSite::updateOrCreate(
            ['letter' => 'H'],
            [
                'name' => 'Subestación Valle Seco',
                'ip' => '0.0.0.0',
                'phone_1' => '0242-3620513',
                'phone_2' => 'NO CONFIGURADO',
                'phone_3' => 'NO CONFIGURADO',
                'phone_4' => 'NO CONFIGURADO',
                'phone_5' => 'NO CONFIGURADO',
                'phone_6' => 'NO CONFIGURADO',
                'phone_7' => 'NO CONFIGURADO',
                'phone_8' => 'NO CONFIGURADO',
                'address' => 'Urb. Colinas de Valle Seco, final Av. Bolívar',
                'normal_state_msg' => '✅ $NAMESITEH',
                'error_state_msg' => '❌ $NAMESITEH',
                'is_active' => true,
                'sort_order' => 7,
            ]
        );

            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 1],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO1',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO1',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 2],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO2',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO2',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 3],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO3',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO3',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 4],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO4',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO4',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 5],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO5',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO5',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 6],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO6',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO6',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 7],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO7',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO7',
                    'is_active' => true,
                ]
            );
            MonitoredSiteDevice::updateOrCreate(
                ['monitored_site_id' => $siteH->id, 'device_number' => 8],
                [
                    'name' => 'NO CONFIGURADO',
                    'ip' => '0.0.0.0',
                    'normal_state_msg' => '✅ $NAMESITEHEQUIPO8',
                    'error_state_msg' => '❌ $NAMESITEHEQUIPO8',
                    'is_active' => true,
                ]
            );

        // --- 3. PROXIES CORPORATIVOS ---
        
        MonitoredProxy::updateOrCreate(
            ['letter' => 'A'],
            [
                'name' => 'Squid - Dansguardian - CARABOBO',
                'ip_port' => '10.20.0.89:8080',
                'auth_userpass' => 'A1746281:Abrito2026.*',
                'test_url' => 'https://core.telegram.org/bots',
                'is_active' => true,
            ]
        );
        MonitoredProxy::updateOrCreate(
            ['letter' => 'B'],
            [
                'name' => 'Squid - Dansguardian - VALLE SECO',
                'ip_port' => '10.20.23.65:8080',
                'auth_userpass' => 'jbrito:Octubre2022.',
                'test_url' => 'https://core.telegram.org/bots',
                'is_active' => true,
            ]
        );
        MonitoredProxy::updateOrCreate(
            ['letter' => 'C'],
            [
                'name' => 'PFsense - CARABOBO (119)',
                'ip_port' => '10.20.0.119:8080',
                'auth_userpass' => 'A1746281:Abrito2026.*',
                'test_url' => 'https://core.telegram.org/bots',
                'is_active' => true,
            ]
        );
        MonitoredProxy::updateOrCreate(
            ['letter' => 'D'],
            [
                'name' => 'PFsense - CARABOBO (89)',
                'ip_port' => '10.20.0.89:8080',
                'auth_userpass' => 'A1746281:Abrito2026.*',
                'test_url' => 'https://core.telegram.org/bots',
                'is_active' => true,
            ]
        );
    }
}
