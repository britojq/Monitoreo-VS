<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request, AuthenticateUserAction $action): RedirectResponse
    {
        $result = $action->execute($request);

        if (!$result['success']) {
            return back()->withErrors(['login' => $result['message']])->onlyInput('login');
        }

        return $this->safeRedirectToIntended($request);
    }

    protected function safeRedirectToIntended(Request $request): RedirectResponse
    {
        $intended = $request->session()->get('url.intended');
        if ($intended) {
            $path = parse_url((string) $intended, PHP_URL_PATH) ?? '';
            if (
                str_contains($path, '/live') ||
                str_contains($path, '/api/') ||
                str_contains($path, '/ajax') ||
                str_contains($path, '/history') ||
                str_contains($path, '/diff') ||
                str_contains($path, '/preview') ||
                str_ends_with($path, '.json') ||
                str_ends_with($path, '.js') ||
                str_ends_with($path, '.css')
            ) {
                $request->session()->forget('url.intended');
            }
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            AuditService::logLogout($user, $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión cerrada exitosamente.');
    }
}
