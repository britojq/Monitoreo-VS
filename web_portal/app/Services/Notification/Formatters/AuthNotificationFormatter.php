<?php

namespace App\Services\Notification\Formatters;

use App\Models\User;

/**
 * Formateador de mensajes para eventos de Autenticación, Seguridad y Accesos.
 * Responsabilidad Única (S): Construcción limpia de texto HTML para alertas de acceso.
 */
class AuthNotificationFormatter
{
    /**
     * Formatea el mensaje cuando un usuario se registra automáticamente vía LDAP (JIT).
     */
    public function formatLdapAutoRegistered(User $user, string $ip): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        $roleText = match(strtolower($user->role ?? 'operator')) {
            'admin', 'superadmin' => 'ADMIN',
            default => 'OPERADOR',
        };

        return "🔑 <b>INICIO DE SESIÓN (USUARIO LDAP)</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "👤 <b>Nombre:</b> " . htmlspecialchars($user->name ?: 'Desconocido') . "\n"
             . "🆔 <b>Usuario LDAP:</b> <code>" . htmlspecialchars($user->username ?? 'N/A') . "</code>\n"
             . "📧 <b>Email:</b> <code>" . htmlspecialchars($user->email ?: 'N/A') . "</code>\n"
             . "🏷️ <b>Rol:</b> " . $roleText . "\n"
             . "🌐 <b>IP de Conexión:</b> <code>{$ip}</code>\n"
             . "📅 <b>Fecha y Hora:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "ℹ️ <i>Acceso mediante credenciales corporativas LDAP autorizadas.</i>";
    }

    /**
     * Formatea el mensaje cuando un usuario inicia sesión en el portal.
     */
    public function formatUserLogin(User $user, string $ip, string $authType = 'local'): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        $isLdap = ($authType === 'ldap' || (method_exists($user, 'isLdapUser') && $user->isLdapUser()));

        $title = $isLdap ? "🔑 <b>INICIO DE SESIÓN (USUARIO LDAP)</b>" : "🔐 <b>INICIO DE SESIÓN (USUARIO LOCAL)</b>";
        $methodDesc = $isLdap 
            ? "Acceso mediante credenciales corporativas LDAP autorizadas."
            : "Acceso mediante credenciales locales asignadas por el Administrador.";

        $roleText = match(strtolower($user->role ?? 'operator')) {
            'admin', 'superadmin' => 'ADMIN',
            default => 'OPERADOR',
        };

        $msg = "{$title}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "👤 <b>Nombre:</b> " . htmlspecialchars($user->name ?: 'Desconocido') . "\n";

        if ($isLdap) {
            $msg .= "🆔 <b>Usuario LDAP:</b> <code>" . htmlspecialchars($user->username ?? 'N/A') . "</code>\n";
        } elseif (!empty($user->username)) {
            $msg .= "🆔 <b>Usuario:</b> <code>" . htmlspecialchars($user->username) . "</code>\n";
        }

        $msg .= "📧 <b>Email:</b> <code>" . htmlspecialchars($user->email ?: 'N/A') . "</code>\n"
             . "🏷️ <b>Rol:</b> " . $roleText . "\n"
             . "🌐 <b>IP de Conexión:</b> <code>{$ip}</code>\n"
             . "📅 <b>Fecha y Hora:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "ℹ️ <i>{$methodDesc}</i>";

        return $msg;
    }

    /**
     * Formatea el mensaje cuando se autoriza o crea un nuevo usuario.
     */
    public function formatUserCreatedOrAuthorized(User $user, string $adminName, string $origin = 'manual'): string
    {
        $now = now()->timezone('America/Caracas')->format('d/m/Y h:i:s A');
        $title = ($origin === 'ldap_preauth') 
            ? "👤 <b>ACCESO CONCEDIDO: USUARIO LDAP PRE-AUTORIZADO</b>" 
            : "👤 <b>ACCESO CONCEDIDO: NUEVO USUARIO LOCAL</b>";

        $msg = "{$title}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "🛡️ <b>Otorgado por Administrador:</b> " . htmlspecialchars($adminName) . "\n"
             . "👤 <b>Nombre:</b> " . htmlspecialchars($user->name) . "\n";

        if (!empty($user->username)) {
            $msg .= "🆔 <b>Usuario LDAP:</b> <code>" . htmlspecialchars($user->username) . "</code>\n";
        }

        $msg .= "📧 <b>Email:</b> " . htmlspecialchars($user->email) . "\n"
             . "🏷️ <b>Rol Asignado:</b> " . strtoupper($user->role) . "\n"
             . "📅 <b>Fecha y Hora:</b> {$now}\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "ℹ️ <i>El usuario ya puede ingresar al portal utilizando sus credenciales asignadas.</i>";

        return $msg;
    }
}
