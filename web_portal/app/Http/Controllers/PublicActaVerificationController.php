<?php

namespace App\Http\Controllers;

use App\Contracts\ActaVerificationInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador público para la verificación de autenticidad de Actas de TI y Pases de Movilización.
 * Permite a cualquier persona autorizada o auditor con lector QR comprobar la legitimidad del documento.
 */
class PublicActaVerificationController extends Controller
{
    public function __construct(
        protected ActaVerificationInterface $verificationService
    ) {}

    /**
     * Muestra la pantalla de validación forense y sello digital corporativo.
     */
    public function verify(Request $request, string $code): View
    {
        $cleanCode = strtoupper(trim($code));
        $data = $this->verificationService->getPublicVerificationData($cleanCode);

        if (!$data) {
            return view('public.acta_not_found', [
                'searchedCode' => $cleanCode,
            ]);
        }

        $providedSig = (string) $request->query('sig', '');
        $isValidSignature = true;

        if ($providedSig !== '') {
            $isValidSignature = $this->verificationService->verifySignature($data['assignment'], $providedSig);
        }

        return view('public.acta_verify', [
            'data' => $data,
            'isValidSignature' => $isValidSignature,
        ]);
    }
}
