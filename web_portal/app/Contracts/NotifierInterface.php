<?php

namespace App\Contracts;

/**
 * Contrato formal para cualquier canal o despachador de notificaciones.
 * Cumple con el Principio de Segregación de Interfaces (I) e Inversión de Dependencias (D).
 */
interface NotifierInterface
{
    /**
     * Envía un mensaje formateado al destinatario correspondiente.
     *
     * @param string $recipient ID de usuario, chat ID o identificador del destino
     * @param string $message Mensaje formateado a enviar
     * @param string $format Formato de marcado ('HTML', 'Markdown', 'MarkdownV2', 'Plain')
     * @return bool True si el mensaje fue aceptado o entregado exitosamente
     */
    public function send(string $recipient, string $message, string $format = 'HTML'): bool;
}
