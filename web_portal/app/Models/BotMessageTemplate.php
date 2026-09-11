<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotMessageTemplate extends Model
{
    use HasFactory;

    protected $table = 'bot_message_templates';

    protected $fillable = [
        'template_key',
        'title',
        'header_text',
        'sub_header',
        'legend_text',
        'impact_statement',
        'default_signature',
        'slogan',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Obtener plantilla por clave (ej. 'servicios', 'sedes')
     */
    public static function getByKey(string $key): ?self
    {
        return static::where('template_key', $key)->first();
    }

    /**
     * Devuelve las plantillas predeterminadas de fábrica
     */
    public static function getDefaultFactoryValues(string $key): ?array
    {
        $defaults = [
            'servicios' => [
                'template_key' => 'servicios',
                'title' => 'Servicios Corporativos (Carabobo - Valle Seco)',
                'header_text' => "<b>GERENCIA DE ATIT REGIÓN CENTRAL</b>\n<b>DIVISIÓN DE ATIT CARABOBO</b>\n<b>DEPARTAMENTO DE  INFRAESTRUCTURA TECNOLÓGICA - SERVIDORES.</b>\n<b>Lugar:</b> Puerto Cabello - (Valle Seco)\n<b>Coordinación:</b> Infraestructura Tecnológica - Servidores.",
                'sub_header' => '<b>ESTATUS DE SERVICIOS CORPORATIVOS (CARABOBO - VALLE SECO)</b>',
                'legend_text' => "<b>Leyenda:</b>\n✅ Operativo.\n⚠️ Advertencia.\n❌ Fallas.",
                'impact_statement' => 'Impacto al SEN: Monitorear los servidores de la Región Central, permite detectar fallas a tiempo  que puedan ocasionar la imposibilidad de los servicios corporativos que son parte del SEN.',
                'default_signature' => "<b>Personal de  ATIT:</b>\nIng. José A. Brito H\nC.I: 11746281\nN° Personal: 144306\n📱Tlf: 02423602039",
                'slogan' => '<b>⚡️ATIT Somos la Voz, Comando y Control de SEN, Nadie se Cansa ⚡️</b>',
                'is_active' => true,
            ],
            'sedes' => [
                'template_key' => 'sedes',
                'title' => 'Sedes y Enlaces (CIAU Eje Costero)',
                'header_text' => "<b>GERENCIA DE ATIT REGIÓN CENTRAL</b>\n<b>DIVISIÓN DE ATIT CARABOBO</b>\n<b>DEPARTAMENTO DE  INFRAESTRUCTURA TECNOLÓGICA</b>\n<b>Lugar:</b> Puerto Cabello - (Valle Seco)\n<b>Coordinación:</b> Infraestructura Tecnológica - Servidores.",
                'sub_header' => '<b>ESTATUS DE CIAU EJE COSTERO</b>',
                'legend_text' => "<b>Leyenda:</b>\n✅ Operativo.\n⚠️ Advertencia.\n❌ Fallas.",
                'impact_statement' => 'Impacto al SEN: Monitorear los servidores de la Región Central, permite detectar fallas a tiempo  que puedan ocasionar la imposibilidad de los servicios corporativos que son parte del SEN.',
                'default_signature' => "<b>Personal de  ATIT:</b>\nIng. José A. Brito H\nC.I: 11746281\nN° Personal: 144306\n📱Tlf: 02423602039",
                'slogan' => '<b>⚡️ATIT Somos la Voz, Comando y Control de SEN, Nadie se Cansa ⚡️</b>',
                'is_active' => true,
            ],
            'debug_servicios' => [
                'template_key' => 'debug_servicios',
                'title' => 'Depuración de Servicios (Debug Completo)',
                'header_text' => "<b>REPORTE TÉCNICO DE DEPURACIÓN DE SERVICIOS</b>",
                'sub_header' => null,
                'legend_text' => "<b>Leyenda:</b>\n✅ Operativo.\n⚠️ Advertencia.\n❌ Fallas.",
                'impact_statement' => null,
                'default_signature' => null,
                'slogan' => null,
                'is_active' => true,
            ],
            'debug_sedes' => [
                'template_key' => 'debug_sedes',
                'title' => 'Depuración de Sedes (Debug Completo)',
                'header_text' => "<b>REPORTE TÉCNICO DE DEPURACIÓN DE SEDES Y ENLACES</b>",
                'sub_header' => null,
                'legend_text' => "<b>Leyenda:</b>\n✅ Operativo.\n⚠️ Advertencia.\n❌ Fallas.",
                'impact_statement' => null,
                'default_signature' => null,
                'slogan' => null,
                'is_active' => true,
            ],
        ];

        return $defaults[$key] ?? null;
    }

    /**
     * Restaura los valores de fábrica para esta plantilla
     */
    public function resetToDefault(?string $user = null): bool
    {
        $defaults = static::getDefaultFactoryValues($this->template_key);
        if (!$defaults) {
            return false;
        }

        $defaults['updated_by'] = $user ?: 'Restauración de Fábrica';
        return $this->update($defaults);
    }
}
