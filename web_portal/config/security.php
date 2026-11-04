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

    /*
    |--------------------------------------------------------------------------
    | Whitelist de Direcciones IP de Clúster (OWASP A02:2021 / SEC-002)
    |--------------------------------------------------------------------------
    | Lista de IPs autorizadas para comunicarse con los endpoints /api/cluster/*.
    | Se obtiene desde la variable de entorno SECURITY_CLUSTER_ALLOWED_IPS.
    |
    */
    'cluster_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SECURITY_CLUSTER_ALLOWED_IPS', ''))
    ))),
];
