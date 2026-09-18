<?php

namespace Database\Seeders;

use App\Models\OuiVendor;
use Illuminate\Database\Seeder;

class OuiVendorsSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            // Cisco Systems
            ['oui_prefix' => '00:00:0C', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:01:42', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:01:43', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:01:C7', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:01:C9', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:02:B9', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:02:BA', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:08:E3', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:13:C4', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:14:1C', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:1E:13', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:1E:14', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:24:97', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:26:0B', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:27:0D', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '40:55:39', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => '70:10:5C', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => 'CC:D5:39', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],
            ['oui_prefix' => 'F4:0F:1B', 'vendor_name' => 'Cisco Systems, Inc.', 'country' => 'US'],

            // HP / Hewlett Packard Enterprise
            ['oui_prefix' => '00:01:E6', 'vendor_name' => 'Hewlett Packard Enterprise', 'country' => 'US'],
            ['oui_prefix' => '00:02:A5', 'vendor_name' => 'Hewlett Packard Enterprise', 'country' => 'US'],
            ['oui_prefix' => '00:08:02', 'vendor_name' => 'Hewlett Packard Enterprise', 'country' => 'US'],
            ['oui_prefix' => '00:1E:0B', 'vendor_name' => 'HP Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:25:B3', 'vendor_name' => 'HP Inc.', 'country' => 'US'],
            ['oui_prefix' => '24:BE:05', 'vendor_name' => 'Hewlett Packard Enterprise', 'country' => 'US'],
            ['oui_prefix' => '3C:D9:2B', 'vendor_name' => 'HP Inc.', 'country' => 'US'],
            ['oui_prefix' => '70:5A:0F', 'vendor_name' => 'HP Inc.', 'country' => 'US'],
            ['oui_prefix' => '9C:8E:99', 'vendor_name' => 'HP Inc.', 'country' => 'US'],
            ['oui_prefix' => 'EC:B1:D7', 'vendor_name' => 'HP Inc. (ProDesk Workstation)', 'country' => 'US'],


            // Dell
            ['oui_prefix' => '00:14:22', 'vendor_name' => 'Dell Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:1E:4F', 'vendor_name' => 'Dell Inc.', 'country' => 'US'],
            ['oui_prefix' => '18:66:DA', 'vendor_name' => 'Dell Inc.', 'country' => 'US'],
            ['oui_prefix' => '74:86:7A', 'vendor_name' => 'Dell Inc.', 'country' => 'US'],
            ['oui_prefix' => 'F8:DB:88', 'vendor_name' => 'Dell Inc.', 'country' => 'US'],

            // VMware / Virtualización
            ['oui_prefix' => '00:05:69', 'vendor_name' => 'VMware, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:0C:29', 'vendor_name' => 'VMware, Inc.', 'country' => 'US'],
            ['oui_prefix' => '00:50:56', 'vendor_name' => 'VMware, Inc.', 'country' => 'US'],
            ['oui_prefix' => '52:54:00', 'vendor_name' => 'QEMU / KVM Virtual NIC', 'country' => 'US'],

            // MikroTik
            ['oui_prefix' => '00:0C:42', 'vendor_name' => 'MikroTik', 'country' => 'LV'],
            ['oui_prefix' => '48:8F:5A', 'vendor_name' => 'MikroTik', 'country' => 'LV'],
            ['oui_prefix' => '64:D1:54', 'vendor_name' => 'MikroTik', 'country' => 'LV'],
            ['oui_prefix' => '74:4D:28', 'vendor_name' => 'MikroTik', 'country' => 'LV'],
            ['oui_prefix' => 'B8:69:F4', 'vendor_name' => 'MikroTik', 'country' => 'LV'],

            // Ubiquiti Networks
            ['oui_prefix' => '00:27:22', 'vendor_name' => 'Ubiquiti Networks Inc.', 'country' => 'US'],
            ['oui_prefix' => '24:A4:3C', 'vendor_name' => 'Ubiquiti Networks Inc.', 'country' => 'US'],
            ['oui_prefix' => '68:D7:9A', 'vendor_name' => 'Ubiquiti Networks Inc.', 'country' => 'US'],
            ['oui_prefix' => 'B4:FB:E4', 'vendor_name' => 'Ubiquiti Networks Inc.', 'country' => 'US'],

            // Intel
            ['oui_prefix' => '00:1B:21', 'vendor_name' => 'Intel Corporate', 'country' => 'US'],
            ['oui_prefix' => '00:1E:67', 'vendor_name' => 'Intel Corporate', 'country' => 'US'],
            ['oui_prefix' => '68:05:CA', 'vendor_name' => 'Intel Corporate', 'country' => 'US'],
            ['oui_prefix' => '84:A9:38', 'vendor_name' => 'Intel Corporate', 'country' => 'US'],

            // Realtek Semiconductor
            ['oui_prefix' => '00:E0:4C', 'vendor_name' => 'Realtek Semiconductor Corp.', 'country' => 'TW'],
            ['oui_prefix' => '52:54:4C', 'vendor_name' => 'Realtek Semiconductor Corp.', 'country' => 'TW'],

            // TP-Link
            ['oui_prefix' => '14:CC:20', 'vendor_name' => 'TP-Link Technologies Co., Ltd.', 'country' => 'CN'],
            ['oui_prefix' => '50:C7:BF', 'vendor_name' => 'TP-Link Technologies Co., Ltd.', 'country' => 'CN'],
            ['oui_prefix' => '60:32:B1', 'vendor_name' => 'TP-Link Technologies Co., Ltd.', 'country' => 'CN'],

            // Fortinet
            ['oui_prefix' => '00:09:0F', 'vendor_name' => 'Fortinet, Inc.', 'country' => 'US'],
            ['oui_prefix' => '70:4C:A5', 'vendor_name' => 'Fortinet, Inc.', 'country' => 'US'],

            // Apple
            ['oui_prefix' => '00:03:93', 'vendor_name' => 'Apple, Inc.', 'country' => 'US'],
            ['oui_prefix' => 'AC:DE:48', 'vendor_name' => 'Apple, Inc.', 'country' => 'US'],
            ['oui_prefix' => 'F0:18:98', 'vendor_name' => 'Apple, Inc.', 'country' => 'US'],

            // APC / Schneider Electric (UPS)
            ['oui_prefix' => '00:C0:B7', 'vendor_name' => 'American Power Conversion (APC)', 'country' => 'US'],

            // Huawei
            ['oui_prefix' => '00:18:82', 'vendor_name' => 'Huawei Technologies Co., Ltd.', 'country' => 'CN'],
            ['oui_prefix' => '00:25:9E', 'vendor_name' => 'Huawei Technologies Co., Ltd.', 'country' => 'CN'],
        ];

        foreach ($vendors as $v) {
            OuiVendor::updateOrCreate(
                ['oui_prefix' => $v['oui_prefix']],
                $v
            );
        }
    }
}
