<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Whitelist de Direcciones IP de Administración (OWASP A05:2021)
    |--------------------------------------------------------------------------
    | Lista de direcciones IP inmunes a bloqueos de seguridad automáticos.
    | Se obtiene exclusivamente desde la variable de entorno SECURITY_WHITELIST_IPS
    | para evitar exponer IPs corporativas privadas en el código fuente.
    |
    */
    'whitelist_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SECURITY_WHITELIST_IPS', '127.0.0.1,::1'))
    ))),
];
