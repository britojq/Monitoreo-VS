<?php

namespace App\Services\Cluster;

use App\Contracts\ClusterVerifierInterface;

class ClusterVerifierService implements ClusterVerifierInterface
{
    protected InstallerIntegrityInspector $installerInspector;
    protected GlpiAgentCensusCollector $agentCollector;
    protected RuntimeParityInspector $parityInspector;

    public function __construct(
        InstallerIntegrityInspector $installerInspector,
        GlpiAgentCensusCollector $agentCollector,
        RuntimeParityInspector $parityInspector
    ) {
        $this->installerInspector = $installerInspector;
        $this->agentCollector = $agentCollector;
        $this->parityInspector = $parityInspector;
    }

    /**
     * Inspección integral de salud, paridad, agentes e instaladores.
     */
    public function verifyAll(): array
    {
        $installers = $this->verifyInstallers();
        $agents = $this->verifyAgents();
        $parity = $this->verifyParity();

        $isHealthy = (
            $parity['system_services']['core_active'] &&
            $installers['all_present'] &&
            $parity['migrations']['is_synchronized']
        );

        return [
            'status' => $isHealthy ? 'healthy' : 'warning',
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'core_services_ok' => $parity['system_services']['core_active'],
                'all_installers_present' => $installers['all_present'],
                'migrations_synced' => $parity['migrations']['is_synchronized'],
                'git_is_clean' => $parity['git']['is_clean'],
                'glpi_active_agents' => $agents['glpi_reporting_24h'],
            ],
            'installers' => $installers,
            'glpi_agents' => $agents,
            'runtime_parity' => $parity,
        ];
    }

    /**
     * Inspecciona la integridad física y hashes SHA-256 de los instaladores.
     */
    public function verifyInstallers(): array
    {
        return $this->installerInspector->inspect();
    }

    /**
     * Inspecciona el censo, estado y telemetría de agentes GLPI.
     */
    public function verifyAgents(): array
    {
        return $this->agentCollector->collect();
    }

    /**
     * Inspecciona la paridad de Git, base de datos y servicios de sistema.
     */
    public function verifyParity(): array
    {
        return $this->parityInspector->inspect();
    }
}
