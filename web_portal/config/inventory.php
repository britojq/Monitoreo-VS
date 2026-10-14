<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token Criptográfico de Agente GLPI
    |--------------------------------------------------------------------------
    | Clave pre-compartida requerida en las cabeceras HTTP de los agentes GLPI
    | para autorizar la ingestión de inventario de hardware y software.
    */
    'glpi_token' => env('GLPI_AGENT_TOKEN', 'ValleSeco_GLPI_Token_2026_Secure'),

    /*
    |--------------------------------------------------------------------------
    | Registro Automático de Nuevos Equipos
    |--------------------------------------------------------------------------
    | Si está en true, los equipos descubiertos por el agente que no existan
    | previamente en la base de datos se registran en estado 'unauthorized'
    | a la espera de aprobación del administrador.
    */
    'auto_register' => env('INVENTORY_AUTO_REGISTER', true),

    /*
    |--------------------------------------------------------------------------
    | Alertas a Telegram Privado del Administrador
    |--------------------------------------------------------------------------
    | Emite notificaciones en tiempo real al chat confidencial del Owner
    | ante nuevos activos o modificaciones de hardware (cambios de RAM/disco).
    */
    'notify_telegram' => env('INVENTORY_NOTIFY_TELEGRAM', true),
];
