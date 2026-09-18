<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OuiVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'oui_prefix',
        'vendor_name',
        'country',
    ];

    /**
     * Resuelve el nombre del fabricante a partir de la dirección MAC.
     */
    public static function lookup(?string $mac): ?string
    {
        if (empty($mac)) {
            return null;
        }

        // Normalizar MAC (remover separadores y tomar primeros 6 hex)
        $cleanMac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
        if (strlen($cleanMac) < 6) {
            return null;
        }

        $prefix = substr($cleanMac, 0, 2) . ':' . substr($cleanMac, 2, 2) . ':' . substr($cleanMac, 4, 2);

        $vendor = static::where('oui_prefix', $prefix)->value('vendor_name');
        return $vendor ?: 'Fabricante Desconocido';
    }
}
