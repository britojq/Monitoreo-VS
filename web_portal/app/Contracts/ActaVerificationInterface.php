<?php

namespace App\Contracts;

use App\Models\InventoryAssignment;

/**
 * Contrato para la firma criptográfica y verificación forense de Actas de TI.
 * Principio S (Single Responsibility) y D (Dependency Inversion).
 */
interface ActaVerificationInterface
{
    /**
     * Genera la firma criptográfica (HMAC-SHA256) de integridad del acta.
     *
     * @param InventoryAssignment $assignment
     * @return string Firma hexadecimal segura.
     */
    public function generateSignature(InventoryAssignment $assignment): string;

    /**
     * Obtiene la URL completa del endpoint de verificación pública corporativa.
     *
     * @param InventoryAssignment $assignment
     * @return string URL pública verificable.
     */
    public function getVerificationUrl(InventoryAssignment $assignment): string;

    /**
     * Valida si una firma dada coincide con la integridad de los datos del acta en MariaDB.
     *
     * @param InventoryAssignment $assignment
     * @param string $signature
     * @return bool
     */
    public function verifySignature(InventoryAssignment $assignment, string $signature): bool;

    /**
     * Recupera el censo detallado de la asignación para la vista pública de validación.
     *
     * @param string $code Código de asignación (ej: UINF-4.05-0012)
     * @return array|null Datos consolidados o null si no existe.
     */
    public function getPublicVerificationData(string $code): ?array;
}
