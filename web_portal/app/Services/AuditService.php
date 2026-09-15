<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Atributos técnicos o sensibles que se excluyen de la auditoría de diferencias
     */
    protected static array $ignoredAttributes = [
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * Mapeo de nombres técnicos de columnas a etiquetas legibles en español
     */
    protected static array $fieldLabels = [
        'name' => 'Nombre',
        'letter' => 'ID / Nomenclatura',
        'type' => 'Tipo de Protocolo',
        'scope' => 'Ámbito',
        'host_ip' => 'Host IP',
        'ip' => 'Dirección IP',
        'web_url' => 'URL Web',
        'port' => 'Puerto',
        'access_port' => 'Puerto de Acceso',
        'access_type' => 'Tipo de Acceso',
        'credentials' => 'Credenciales / Token',
        'check_interface' => 'Interfaz de Red',
        'dns_test_domain' => 'Dominio de Prueba DNS',
        'normal_state_msg' => 'Mensaje Estado Normal',
        'error_state_msg' => 'Mensaje Estado Fallo',
        'is_active' => 'Estado Activo',
        'sort_order' => 'Orden de Posición',
        'phone_1' => 'Teléfono 1',
        'phone_2' => 'Teléfono 2',
        'phone_3' => 'Teléfono 3',
        'phone_4' => 'Teléfono 4',
        'address' => 'Dirección',
        'mac' => 'Dirección MAC',
        'model' => 'Modelo',
        'serial' => 'Número de Serie',
        'vendor_data' => 'Fabricante / Vendor',
        'ports' => 'Especificación de Puertos',
        'notes' => 'Observaciones / Notas',
        'device_number' => 'Número de Dispositivo',
        'role' => 'Rol de Usuario',
        'email' => 'Correo Electrónico',
        'username' => 'Nombre de Usuario',
        'department' => 'Departamento',
        'title' => 'Cargo',
        'ban_reason' => 'Motivo de Suspensión',
        'banned_at' => 'Fecha de Suspensión',
    ];

    public static function logLogin(User $user, Request $request, string $authType = 'local'): AuditLog
    {
        $typeLabel = $authType === 'ldap' ? 'LDAP Institucional' : 'Local Administrativo';
        
        return AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'event' => 'login',
            'module' => 'auth',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'entity_name' => 'Autenticación',
            'entity_label' => $user->name,
            'description' => "Inicio de sesión exitoso ({$typeLabel}) del usuario [{$user->name}] desde la IP {$request->ip()}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => null,
            'new_values' => [
                'login_type' => $authType,
                'username' => $user->username ?: $user->email,
            ],
            'changed_fields' => null,
        ]);
    }

    public static function logLogout(User $user, Request $request): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'event' => 'logout',
            'module' => 'auth',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'entity_name' => 'Autenticación',
            'entity_label' => $user->name,
            'description' => "Cierre de sesión del usuario [{$user->name}]",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => null,
            'new_values' => null,
            'changed_fields' => null,
        ]);
    }

    public static function logLoginFailed(string $loginValue, Request $request, string $reason): AuditLog
    {
        return AuditLog::create([
            'user_id' => null,
            'user_name' => $loginValue,
            'user_email' => filter_var($loginValue, FILTER_VALIDATE_EMAIL) ? $loginValue : null,
            'user_role' => null,
            'event' => 'login_failed',
            'module' => 'auth',
            'auditable_type' => null,
            'auditable_id' => null,
            'entity_name' => 'Seguridad',
            'entity_label' => $loginValue,
            'description' => "Intento fallido de inicio de sesión para el identificador [{$loginValue}]. Motivo: {$reason}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => null,
            'new_values' => [
                'attempted_login' => $loginValue,
                'reason' => $reason,
            ],
            'changed_fields' => null,
        ]);
    }

    /**
     * Registrar un evento de auditoría personalizado (seguridad, términos, etc.)
     */
    public static function logCustom(
        string $event,
        string $module,
        string $entityName,
        string $entityLabel,
        string $description,
        ?Model $auditable = null,
        ?array $newValues = null,
        ?array $oldValues = null,
        ?Request $request = null,
        ?User $user = null
    ): AuditLog {
        $req = $request ?? request();
        $authUser = $user ?? Auth::user();

        return AuditLog::create([
            'user_id' => $authUser?->id,
            'user_name' => $authUser?->name ?? 'Sistema',
            'user_email' => $authUser?->email,
            'user_role' => $authUser?->role,
            'event' => $event,
            'module' => $module,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'entity_name' => $entityName,
            'entity_label' => $entityLabel,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => null,
            'ip_address' => $req ? $req->ip() : '127.0.0.1',
            'user_agent' => $req ? $req->userAgent() : null,
        ]);
    }

    public static function log(
        string $event,
        string $module,
        string $entityName,
        string $entityLabel,
        string $description,
        ?Model $auditable = null,
        ?array $newValues = null,
        ?array $oldValues = null,
        ?Request $request = null,
        ?User $user = null
    ): AuditLog {
        return static::logCustom($event, $module, $entityName, $entityLabel, $description, $auditable, $newValues, $oldValues, $request, $user);
    }

    public static function logModelCreated(Model $model): ?AuditLog
    {
        $user = Auth::user();
        $request = request();
        $ip = $request ? $request->ip() : '127.0.0.1';
        $agent = $request ? $request->userAgent() : 'System';

        $module = self::determineModule($model);
        $entityName = self::determineEntityName($model);
        $entityLabel = self::determineEntityLabel($model);

        $attributes = collect($model->getAttributes())
            ->except(self::$ignoredAttributes)
            ->toArray();

        $userName = $user ? $user->name : 'Sistema Automático';

        return AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'user_name' => $userName,
            'user_email' => $user ? $user->email : null,
            'user_role' => $user ? $user->role : 'system',
            'event' => 'created',
            'module' => $module,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'entity_name' => $entityName,
            'entity_label' => $entityLabel,
            'description' => "El usuario {$userName} agregó el nuevo {$entityName} [{$entityLabel}]",
            'old_values' => null,
            'new_values' => $attributes,
            'changed_fields' => array_keys($attributes),
            'ip_address' => $ip,
            'user_agent' => $agent,
        ]);
    }

    public static function logModelUpdated(Model $model): ?AuditLog
    {
        $dirty = $model->getDirty();
        $dirty = collect($dirty)->except(self::$ignoredAttributes)->toArray();

        if (empty($dirty)) {
            return null;
        }

        $user = Auth::user();
        $request = request();
        $ip = $request ? $request->ip() : '127.0.0.1';
        $agent = $request ? $request->userAgent() : 'System';

        $module = self::determineModule($model);
        $entityName = self::determineEntityName($model);
        $entityLabel = self::determineEntityLabel($model, true);

        $oldValues = [];
        $newValues = [];
        $changedLabels = [];

        foreach ($dirty as $field => $newVal) {
            $oldVal = $model->getOriginal($field);
            $oldValues[$field] = $oldVal;
            $newValues[$field] = $newVal;
            $changedLabels[] = self::$fieldLabels[$field] ?? $field;
        }

        $userName = $user ? $user->name : 'Sistema Automático';
        $fieldsStr = implode(', ', $changedLabels);

        // Detectar si fue exclusivamente un cambio de estado (toggle)
        $isToggleOnly = count($dirty) === 1 && isset($dirty['is_active']);
        $event = $isToggleOnly ? 'toggled' : 'updated';

        if ($isToggleOnly) {
            $statusText = $newValues['is_active'] ? 'ACTIVÓ' : 'PAUSÓ / DESACTIVÓ';
            $description = "El usuario {$userName} {$statusText} el {$entityName} [{$entityLabel}]";
        } else {
            $description = "El usuario {$userName} modificó el {$entityName} [{$entityLabel}] cambiando: {$fieldsStr}";
        }

        return AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'user_name' => $userName,
            'user_email' => $user ? $user->email : null,
            'user_role' => $user ? $user->role : 'system',
            'event' => $event,
            'module' => $module,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'entity_name' => $entityName,
            'entity_label' => $entityLabel,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => array_keys($dirty),
            'ip_address' => $ip,
            'user_agent' => $agent,
        ]);
    }

    public static function logModelDeleted(Model $model): ?AuditLog
    {
        $user = Auth::user();
        $request = request();
        $ip = $request ? $request->ip() : '127.0.0.1';
        $agent = $request ? $request->userAgent() : 'System';

        $module = self::determineModule($model);
        $entityName = self::determineEntityName($model);
        $entityLabel = self::determineEntityLabel($model);

        $attributes = collect($model->getAttributes())
            ->except(self::$ignoredAttributes)
            ->toArray();

        $userName = $user ? $user->name : 'Sistema Automático';

        return AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'user_name' => $userName,
            'user_email' => $user ? $user->email : null,
            'user_role' => $user ? $user->role : 'system',
            'event' => 'deleted',
            'module' => $module,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'entity_name' => $entityName,
            'entity_label' => $entityLabel,
            'description' => "El usuario {$userName} eliminó el {$entityName} [{$entityLabel}]",
            'old_values' => $attributes,
            'new_values' => null,
            'changed_fields' => array_keys($attributes),
            'ip_address' => $ip,
            'user_agent' => $agent,
        ]);
    }

    public static function determineModule(Model $model): string
    {
        $class = class_basename($model);
        return match($class) {
            'MonitoredService' => 'services',
            'MonitoredSite' => 'sites',
            'MonitoredSiteDevice', 'MonitoredNetworkDevice' => 'devices',
            'MonitoredProxy' => 'proxies',
            'User' => 'users',
            default => 'system',
        };
    }

    public static function determineEntityName(Model $model): string
    {
        $class = class_basename($model);
        return match($class) {
            'MonitoredService' => 'Servicio',
            'MonitoredSite' => 'Sede',
            'MonitoredSiteDevice', 'MonitoredNetworkDevice' => 'Dispositivo / Equipo',
            'MonitoredProxy' => 'Proxy',
            'User' => 'Usuario',
            default => 'Registro',
        };
    }

    public static function determineEntityLabel(Model $model, bool $preferOriginal = false): string
    {
        if ($preferOriginal) {
            if ($orig = $model->getOriginal('name')) {
                return $orig;
            }
            if ($orig = $model->getOriginal('ip')) {
                return $orig;
            }
            if ($orig = $model->getOriginal('username')) {
                return $orig;
            }
            if ($orig = $model->getOriginal('email')) {
                return $orig;
            }
        }
        if (isset($model->name) && $model->name) {
            return $model->name;
        }
        if (isset($model->ip) && $model->ip) {
            return $model->ip;
        }
        if (isset($model->username) && $model->username) {
            return $model->username;
        }
        if (isset($model->email) && $model->email) {
            return $model->email;
        }
        return '#' . $model->getKey();
    }

    public static function getFieldLabel(string $field): string
    {
        return self::$fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
