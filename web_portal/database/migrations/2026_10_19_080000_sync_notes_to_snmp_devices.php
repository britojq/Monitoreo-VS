<?php

use App\Models\MonitoredNetworkDevice;
use App\Models\SnmpDevice;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $netDevices = MonitoredNetworkDevice::whereNotNull('notes')->where('notes', '!=', '')->get();

        foreach ($netDevices as $nd) {
            SnmpDevice::where(function ($query) use ($nd) {
                $query->where('network_device_id', $nd->id)
                    ->orWhere('ip_address', $nd->ip);
            })->where(function ($q) {
                $q->whereNull('notes')->orWhere('notes', '');
            })->update([
                'notes' => $nd->notes,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert operation required to preserve technical notes
    }
};
