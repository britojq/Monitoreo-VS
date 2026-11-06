<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Ldap\LdapConnectionManager;
use App\Services\Ldap\LdapDirectorySearchService;
use App\Services\Ldap\LdapUserSynchronizerAction;

/**
 * Fachada delgada para el subsistema de Directorio Activo (LDAP).
 * Orquesta la gestión de conexión, autenticación de usuarios y búsqueda en directorio.
 */
class LdapAuthService
{
    protected LdapConnectionManager $connectionManager;
    protected LdapUserSynchronizerAction $synchronizerAction;
    protected LdapDirectorySearchService $searchService;

    public function __construct(
        ?LdapConnectionManager $connectionManager = null,
        ?LdapUserSynchronizerAction $synchronizerAction = null,
        ?LdapDirectorySearchService $searchService = null
    ) {
        $this->connectionManager = $connectionManager ?? app(LdapConnectionManager::class);
        $this->synchronizerAction = $synchronizerAction ?? app(LdapUserSynchronizerAction::class);
        $this->searchService = $searchService ?? app(LdapDirectorySearchService::class);
    }

    public function getConfig(): array
    {
        return $this->connectionManager->getConfig();
    }

    public function isEnabled(): bool
    {
        return $this->connectionManager->isEnabled();
    }

    public function updateConfig(array $attributes, ?string $updatedBy = null): bool
    {
        return $this->connectionManager->updateConfig($attributes, $updatedBy);
    }

    public function resolveBaseDn($conn, ?string $configuredBaseDn = null): string
    {
        return $this->connectionManager->resolveBaseDn($conn, $configuredBaseDn);
    }

    public function getDomainsFromBaseDn(string $baseDn): array
    {
        return $this->connectionManager->getDomainsFromBaseDn($baseDn);
    }

    public function testConnection(
        ?string $host = null,
        ?int $port = null,
        ?string $baseDn = null,
        ?string $bindDn = null,
        ?string $bindPassword = null
    ): array {
        return $this->connectionManager->testConnection($host, $port, $baseDn, $bindDn, $bindPassword);
    }

    public function getDecryptedBindPassword(): ?string
    {
        return $this->connectionManager->getDecryptedBindPassword();
    }

    public function getConnection(?string $customHost = null, ?int $customPort = null)
    {
        return $this->connectionManager->getConnection($customHost, $customPort);
    }

    public function authenticate(string $username, string $password): array
    {
        return $this->synchronizerAction->authenticate($username, $password);
    }

    public function searchUsers(string $term): array
    {
        return $this->searchService->searchUsers($term);
    }

    public function findUserByUid(string $uid): ?array
    {
        return $this->searchService->findUserByUid($uid);
    }

    public function searchEmployees(string $query, int $limit = 10): array
    {
        return $this->searchService->searchEmployees($query, $limit);
    }
}
