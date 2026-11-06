<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BotMessageTemplate;
use App\Models\User;

/**
 * Política de autorización para Plantillas de Mensajería del Bot.
 * Cumple con OWASP A01:2021 (Broken Access Control) y matriz RBAC de S.O.N.A.R.
 */
class BotTemplatePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('telegram.templates') || $user->hasPermission('telegram.dispatch');
    }

    public function view(User $user, ?BotMessageTemplate $template = null): bool
    {
        return $user->hasPermission('telegram.templates') || $user->hasPermission('telegram.dispatch');
    }

    public function update(User $user, ?BotMessageTemplate $template = null): bool
    {
        return $user->hasPermission('telegram.templates');
    }
}
