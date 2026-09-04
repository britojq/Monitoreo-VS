<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BannedIp;
use App\Models\User;
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

        return view('admin.bans.index', compact('bannedUsers', 'bannedIps'));
    }

    public function unbanUser(User $user): RedirectResponse
    {
        $user->update([
            'is_active' => true,
            'ban_reason' => null,
            'banned_at' => null,
        ]);

        return redirect()->route('admin.bans.index')
            ->with('success', "El usuario {$user->name} ({$user->email}) ha sido desbaneado y reactivado exitosamente.");
    }

    public function unbanIp(int $id): RedirectResponse
    {
        $ban = BannedIp::findOrFail($id);
        $ip = $ban->ip_address;
        $ban->delete();

        return redirect()->route('admin.bans.index')
            ->with('success', "La dirección IP {$ip} ha sido removida de la lista negra y desbloqueada.");
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

        return redirect()->route('admin.bans.index')
            ->with('success', "El usuario {$user->name} ha sido reactivado y {$deletedCount} IP(s) asociadas han sido desbloqueadas.");
    }

    public function banIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        BannedIp::firstOrCreate(
            ['ip_address' => $validated['ip_address']],
            [
                'reason' => $validated['reason'] ?: 'Bloqueo manual por el Administrador',
                'user_id' => auth()->id(),
                'banned_at' => now(),
            ]
        );

        return redirect()->route('admin.bans.index')
            ->with('success', "La dirección IP {$validated['ip_address']} ha sido añadida a la lista negra.");
    }
}
