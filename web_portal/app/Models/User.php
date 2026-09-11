<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'academic_title',
        'cedula',
        'personal_number',
        'phone',
        'username',
        'email',
        'avatar',
        'password',
        'role',
        'is_active',
        'ban_reason',
        'banned_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $appends = [
        'avatar_url',
        'full_title_name',
        'atit_signature',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'banned_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'SuperAdmin';
    }

    public function isLdapUser(): bool
    {
        return !empty($this->username);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!empty($this->avatar)) {
            if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
                return $this->avatar;
            }
            if (file_exists(public_path('storage/' . $this->avatar))) {
                return asset('storage/' . $this->avatar);
            }
            if (file_exists(public_path($this->avatar))) {
                return asset($this->avatar);
            }
            return asset('storage/' . $this->avatar);
        }
        return null;
    }

    public function getFullTitleNameAttribute(): string
    {
        $title = trim($this->academic_title ?? '');
        $name = trim($this->name ?? '');

        // Filtrar placeholders genéricos para que jamás se impriman en el reporte
        if (preg_match('/^(grado\s*acad[eé]mico|t[ií]tulo(\s*acad[eé]mico)?|n\/a|none|null)$/i', $title)) {
            $title = '';
        }

        if (!empty($title) && !str_starts_with(strtolower($name), strtolower($title))) {
            return $title . ' ' . $name;
        }
        return $name;
    }

    public function getAtitSignatureAttribute(): string
    {
        $name = $this->full_title_name;
        $ci = $this->cedula ?? '';
        $personal = $this->personal_number ?? '';
        $phone = $this->phone ?? '';

        return "**Personal de  ATIT:**\n" .
               $name . "\n" .
               "C.I: " . $ci . "\n" .
               "N° Personal: " . $personal . "\n" .
               "📱Tlf: " . $phone;
    }

    public function hasCompleteAtitProfile(): bool
    {
        $name = trim($this->name ?? '');
        $ci = trim($this->cedula ?? '');
        $personal = trim($this->personal_number ?? '');
        $phone = trim($this->phone ?? '');

        // No permitir nombres genéricos de rol ni datos ficticios de prueba
        $isGenericName = in_array(strtolower($name), ['operador', 'operator', 'admin', 'administrador', 'usuario', 'user']);
        $isDummyCi = in_array($ci, ['1234567890', '12345678', '0']);

        return !empty($name) &&
               !$isGenericName &&
               !empty($ci) &&
               !$isDummyCi &&
               !empty($personal) &&
               !empty($phone);
    }

    public function bannedIps()
    {
        return $this->hasMany(BannedIp::class, 'user_id');
    }
}
