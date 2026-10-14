<?php

namespace App\Services\Qr;

use App\Contracts\QrCodeGeneratorInterface;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Servicio para la generación de códigos QR de alta fidelidad.
 * Cumple con SOLID y permite inyección desacoplada en controladores y generadores PDF.
 */
class QrCodeGeneratorService implements QrCodeGeneratorInterface
{
    /**
     * Genera un código QR en formato SVG.
     */
    public function generateSvg(string $data, int $size = 180): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($data);
    }

    /**
     * Genera un código QR como Data URI en Base64 listo para incrustar en HTML y DomPDF.
     */
    public function generateDataUri(string $data, int $size = 180): string
    {
        $svg = $this->generateSvg($data, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
