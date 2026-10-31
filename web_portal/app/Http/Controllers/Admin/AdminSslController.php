<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Ssl\FetchSslCertificatesAction;
use App\Actions\Ssl\RecheckSslCertificateAction;
use App\Actions\Ssl\StoreSslCertificateAction;
use App\Contracts\Services\ClusterConfigInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ssl\StoreSslCertificateRequest;
use App\Models\AuditLog;
use App\Models\SslCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSslController extends Controller
{
    public function __construct(
        protected ClusterConfigInterface $cluster,
    ) {}

    public function index(Request $request, FetchSslCertificatesAction $action): View
    {
        $this->authorize('view', SslCertificate::class);
        return view('admin.ssl.index', $action->execute($request));
    }

    public function show(int $id, FetchSslCertificatesAction $action): JsonResponse
    {
        $this->authorize('view', SslCertificate::class);
        return response()->json($action->getDetails($id));
    }

    public function store(StoreSslCertificateRequest $request, StoreSslCertificateAction $action): RedirectResponse
    {
        $this->authorize('manage', SslCertificate::class);
        if ($this->cluster->isSlave()) {
            return back()->with('error', "Acción Bloqueada: Servidor en modo ESCLAVO. Registre en MASTER ({$this->cluster->getMasterApiUrl()}).");
        }
        $cert = $action->execute($request->validated(), $request->user(), $request->ip(), $request->userAgent());
        return redirect()->route('admin.ssl.index')->with('success', "Dominio '{$cert->domain}' agregado e inspeccionado.");
    }

    public function recheck(Request $request, int $id, RecheckSslCertificateAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('recheck', SslCertificate::class);
        $cert = $action->recheckSingle($id, $request->user(), $request->ip(), $request->userAgent());
        return $request->wantsJson() ? response()->json(['success' => true, 'certificate' => $cert]) : back()->with('success', "Certificado '{$cert->domain}' re-inspeccionado.");
    }

    public function recheckAll(Request $request, RecheckSslCertificateAction $action): RedirectResponse
    {
        $this->authorize('recheck', SslCertificate::class);
        $action->recheckAll($request->user(), $request->ip(), $request->userAgent());
        return back()->with('success', 'Todos los certificados SSL han sido re-inspeccionados.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorize('manage', SslCertificate::class);
        $cert = SslCertificate::findOrFail($id);
        $domain = $cert->domain;
        $cert->delete();
        AuditLog::log('deleted', 'ssl', "Certificado '{$domain}' eliminado", SslCertificate::class, $id, 'Certificado SSL', $domain);
        return back()->with('success', "Certificado para '{$domain}' eliminado del monitoreo.");
    }
}
