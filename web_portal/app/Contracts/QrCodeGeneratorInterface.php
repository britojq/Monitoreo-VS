<?php

namespace App\Contracts;

/**
 * Contrato para la generación agnóstica de códigos QR.
 * Principio D (Dependency Inversion) e I (Interface Segregation).
 */
interface QrCodeGeneratorInterface
{
    /**
     * Genera un código QR en formato SVG.
     *
     * @param string $data Contenido o URL codificada.
     * @param int $size Tamaño en píxeles.
     * @return string Marcado XML/SVG puro.
     */
    public function generateSvg(string $data, int $size = 180): string;

    /**
     * Genera un código QR como Data URI en Base64 (PNG o SVG) listo para incrustar en HTML/PDF.
     *
     * @param string $data Contenido o URL codificada.
     * @param int $size Tamaño en píxeles.
     * @return string Cadena data:image/svg+xml;base64,... o data:image/png;base64,...
     */
    public function generateDataUri(string $data, int $size = 180): string;
}
