<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BannedIp;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Fail2banService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBanController extends Controller
{
    public function index(): View
    {
        $bannedUsers = User::where('is_active', false)
            ->orderBy('banned_at', 'desc')
            ->get();

        $bannedIps = BannedIp::with('user')
            ->orderBy('banned_at', 'desc')
            ->get();

        $fail2banStatus = Fail2banService::getStatus();
        $fail2banJails = Fail2banService::getAllJailsDetails();

        return view('admin.bans.index', compact(
            'bannedUsers',
            'bannedIps',
            'fail2banStatus',
            'fail2banJails'
        ));
    }

    public function unbanUser(User $user): RedirectResponse
    {
        $user->update([
            'is_active' => true,
            'ban_reason' => null,
            'banned_at' => null,
        ]);

        AuditService::logCustom(
            event: 'updated',
            module: 'users',
            entityName: 'Usuario',
            entityLabel: $user->name,
            description: "Usuario {$user->name} reactivado y desbaneado"
        );

        return redirect()->route('admin.bans.index')
            ->with('success', "El usuario {$user->name} ({$user->email}) ha sido desbaneado y reactivado exitosamente.");
    }

    public function unbanIp(int $id): RedirectResponse
    {
        $ban = BannedIp::findOrFail($id);
        $ip = $ban->ip_address;
        $ban->delete();

        AuditService::logCustom(
            event: 'deleted',
            module: 'security',
            entityName: 'Lista Negra IP',
            entityLabel: $ip,
            description: "IP {$ip} removida de la lista negra de la aplicación"
        );

        return redirect()->route('admin.bans.index')
            ->with('success', "La dirección IP {$ip} ha sido removida de la lista negra de la aplicación.");
    }

    public function unbanAll(int $userId): RedirectResponse
    {
        $user = User::findOrFail($userId);
        $user->update([
            'is_active' => true,
            'ban_reason' => null,
            'banned_at' => null,
        ]);

        $deletedCount = BannedIp::where('user_id', $user->id)->delete();

        AuditService::logCustom(
            event: 'updated',
            module: 'users',
            entityName: 'Usuario',
            entityLabel: $user->name,
            description: "Usuario {$user->name} reactivado y {$deletedCount} IPs removidas de la lista negra"
        );

        return redirect()->route('admin.bans.index')
            ->with('success', "El usuario {$user->name} ha sido reactivado y {$deletedCount} IP(s) asociadas han sido desbloqueadas.");
    }

    public function banIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
            'apply_firewall' => ['nullable', 'string'],
            'jail' => ['nullable', 'string'],
        ]);

        $ip = $validated['ip_address'];
        if (in_array($ip, ['127.0.0.1', '::1', '10.20.23.221', '10.20.23.252', '10.20.23.1'], true) || str_starts_with($ip, '127.') || str_starts_with($ip, '10.20.23.')) {
            return redirect()->route('admin.bans.index')
                ->with('error', 'No se permite banear direcciones IP del cluster o red local corporativa.');
        }

        // 1. Registro en lista negra de aplicación
        BannedIp::firstOrCreate(
            ['ip_address' => $ip],
            [
                'reason' => $validated['reason'] ?: 'Bloqueo manual por el Administrador',
                'user_id' => auth()->id(),
                'banned_at' => now(),
            ]
        );

        // 2. Si se solicitó bloqueo en Firewall (Fail2ban)
        $firewallMsg = "";
        if (!empty($validated['apply_firewall'])) {
            $targetJail = $validated['jail'] ?: 'all';
            $f2bResult = Fail2banService::banIp($ip, $targetJail);
            if ($f2bResult['success']) {
                $firewallMsg = " y bloqueada en el firewall perimetral ({$targetJail})";
                AuditService::logCustom(
                    event: 'ip_banned',
                    module: 'security',
                    entityName: 'Fail2ban Firewall',
                    entityLabel: $ip,
                    description: "IP {$ip} bloqueada manualmente en Fail2ban ({$targetJail}): " . ($validated['reason'] ?: 'Bloqueo manual')
                );
            }
        }

        AuditService::logCustom(
            event: 'created',
            module: 'security',
            entityName: 'Lista Negra IP',
            entityLabel: $ip,
            description: "IP {$ip} añadida a la lista negra por el administrador"
        );

        return redirect()->route('admin.bans.index')
            ->with('success', "La dirección IP {$ip} ha sido añadida a la lista negra{$firewallMsg}.");
    }

    public function unbanFail2banIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'jail' => ['required', 'string'],
        ]);

        $res = Fail2banService::unbanIp($validated['ip_address'], $validated['jail']);

        if ($res['success']) {
            AuditService::logCustom(
                event: 'ip_unbanned',
                module: 'security',
                entityName: 'Fail2ban Firewall',
                entityLabel: $validated['ip_address'],
                description: "IP {$validated['ip_address']} desbloqueada manualmente de Fail2ban ({$validated['jail']})"
            );
            return redirect()->route('admin.bans.index')->with('success', $res['message']);
        }

        return redirect()->route('admin.bans.index')->with('error', $res['message']);
    }

    public function banFail2banIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'jail' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $res = Fail2banService::banIp($validated['ip_address'], $validated['jail']);

        if ($res['success']) {
            AuditService::logCustom(
                event: 'ip_banned',
                module: 'security',
                entityName: 'Fail2ban Firewall',
                entityLabel: $validated['ip_address'],
                description: "IP {$validated['ip_address']} bloqueada manualmente en Fail2ban ({$validated['jail']}): " . ($validated['reason'] ?: 'Manual')
            );
            return redirect()->route('admin.bans.index')->with('success', $res['message']);
        }

        return redirect()->route('admin.bans.index')->with('error', $res['message']);
    }

    public function reloadFail2ban(): RedirectResponse
    {
        $res = Fail2banService::reload();

        if ($res['success']) {
            AuditService::logCustom(
                event: 'updated',
                module: 'security',
                entityName: 'Fail2ban',
                entityLabel: 'Motor Perimetral',
                description: "Reglas de Fail2ban recargadas exitosamente desde la interfaz web"
            );
            return redirect()->route('admin.bans.index')->with('success', $res['message']);
        }

        return redirect()->route('admin.bans.index')->with('error', $res['message']);
    }
}
