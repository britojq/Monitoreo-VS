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
        return !empty($this->name) &&
               !empty($this->cedula) &&
               !empty($this->personal_number) &&
               !empty($this->phone);
    }

    public function bannedIps()
    {
        return $this->hasMany(BannedIp::class, 'user_id');
    }
}
