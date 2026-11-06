<?php

namespace App\Contracts;

interface ClusterVerifierInterface
{
    /**
     * Ejecuta una inspección integral de salud, paridad, agentes e instaladores.
     *
     * @return array
     */
    public function verifyAll(): array;

    /**
     * Inspecciona la integridad física y hashes SHA-256 de los instaladores.
     *
     * @return array
     */
    public function verifyInstallers(): array;

    /**
     * Inspecciona el censo, estado y telemetría de agentes GLPI.
     *
     * @return array
     */
    public function verifyAgents(): array;

    /**
     * Inspecciona la paridad de Git, base de datos y servicios de sistema.
     *
     * @return array
     */
    public function verifyParity(): array;
}
