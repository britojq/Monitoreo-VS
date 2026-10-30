<?php

declare(strict_types=1);

namespace App\Services\System;

/**
 * Servicio canónico de resolución de rutas dinámicas del ecosistema S.O.N.A.R.
 * 
 * Desacopla a Laravel de rutas rígidas "hardcoded" en el sistema de archivos.
 * Resuelve dinámicamente las rutas hacia el repositorio unificado, entorno virtual de Python
 * y scripts de monitoreo, garantizando portabilidad y resiliencia.
 */
class SonarPathService
{
    /**
     * Obtiene la ruta raíz canónica del ecosistema S.O.N.A.R. (/opt/sonar).
     */
    public static function baseDir(): string
    {
        // Si existe la variable de entorno, tiene prioridad
        $envBase = env('SONAR_BASE_DIR');
        if (!empty($envBase) && is_dir((string) $envBase)) {
            return (string) $envBase;
        }

        // Resolución dinámica relativa desde web_portal (base_path('..'))
        $resolved = realpath(base_path('..'));
        if ($resolved !== false && is_dir($resolved)) {
            return $resolved;
        }

        return '/opt/sonar';
    }

    /**
     * Obtiene la ruta del ejecutable Python del entorno virtual.
     */
    public static function pythonBinary(): string
    {
        $custom = env('SONAR_PYTHON_BIN');
        if (!empty($custom) && is_executable((string) $custom)) {
            return (string) $custom;
        }

        $venvPy = self::baseDir() . '/venv/bin/python';
        if (is_executable($venvPy)) {
            return $venvPy;
        }

        $venvPy3 = self::baseDir() . '/venv/bin/python3';
        if (is_executable($venvPy3)) {
            return $venvPy3;
        }

        return '/opt/sonar/venv/bin/python';
    }

    /**
     * Obtiene la ruta absoluta de un script dentro del subsistema de monitoreo (/opt/sonar/monitor/...).
     */
    public static function monitorScript(string $scriptName): string
    {
        return self::baseDir() . '/monitor/' . ltrim($scriptName, '/');
    }

    /**
     * Obtiene la ruta de un script o herramienta dentro de tools (/opt/sonar/tools/...).
     */
    public static function toolScript(string $toolName): string
    {
        return self::baseDir() . '/tools/' . ltrim($toolName, '/');
    }

    /**
     * Obtiene la ruta del directorio o archivo de la bóveda de hardware (/opt/sonar/system/vault/...).
     */
    public static function vaultPath(string $subpath = ''): string
    {
        $base = self::baseDir() . '/system/vault';
        return empty($subpath) ? $base : $base . '/' . ltrim($subpath, '/');
    }

    /**
     * Obtiene la ruta del portal web Laravel (/opt/sonar/web_portal).
     */
    public static function webDir(string $subpath = ''): string
    {
        $base = base_path();
        return empty($subpath) ? $base : $base . '/' . ltrim($subpath, '/');
    }
}
