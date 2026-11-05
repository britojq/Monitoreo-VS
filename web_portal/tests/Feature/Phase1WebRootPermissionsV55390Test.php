<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class Phase1WebRootPermissionsV55390Test extends TestCase
{
    /**
     * SEC-18 (CWE-732 / CWE-276): Verificar que los archivos críticos del directorio
     * público posean permisos estrictos 0644 (lectura y escritura solo para propietario).
     */
    public function test_web_root_critical_files_have_strict_0644_permissions(): void
    {
        $criticalFiles = [
            public_path('index.php'),
            public_path('.htaccess'),
            public_path('robots.txt'),
            public_path('favicon.ico'),
        ];

        foreach ($criticalFiles as $filePath) {
            $this->assertFileExists($filePath, "El archivo crítico no existe: {$filePath}");

            $perms = fileperms($filePath);
            $octal = substr(sprintf('%o', $perms), -3);

            $this->assertSame(
                '644',
                $octal,
                "El archivo {$filePath} debe tener permisos 0644, se detectó: {$octal}"
            );

            // Verificación a nivel de bits: ni grupo ni otros deben tener permiso de escritura
            $this->assertSame(0, $perms & 0022, "El archivo {$filePath} no debe ser escribible por grupo ni otros.");
        }
    }

    /**
     * SEC-18: Verificar que la raíz web pública y sus subdirectorios tengan permisos 0755
     * y que no exista ningún archivo con permisos de escritura pública (world-writable, 0777).
     */
    public function test_web_root_has_no_world_writable_files(): void
    {
        $publicDir = public_path();
        $this->assertDirectoryExists($publicDir);

        $dirPerms = fileperms($publicDir);
        $dirOctal = substr(sprintf('%o', $dirPerms), -3);
        $this->assertSame('755', $dirOctal, "El directorio {$publicDir} debe tener permisos 0755.");

        // Inspeccionar recursivamente todos los archivos de public/
        $directoryIterator = new \RecursiveDirectoryIterator($publicDir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        $worldWritableFiles = [];
        foreach ($iterator as $item) {
            if ($item->isFile() && !$item->isLink()) {
                $perms = fileperms($item->getPathname());
                if (($perms & 0002) !== 0) {
                    $worldWritableFiles[] = $item->getPathname() . ' (' . substr(sprintf('%o', $perms), -3) . ')';
                }
            }
        }

        $this->assertEmpty(
            $worldWritableFiles,
            'Se detectaron archivos world-writable en la raíz web: ' . implode(', ', $worldWritableFiles)
        );
    }

    /**
     * SEC-18: Verificar que el árbol de Git rastree los archivos de bootstrapping
     * como no ejecutables (modo 100644 en lugar de 100755).
     */
    public function test_git_index_tracks_web_root_files_as_non_executable(): void
    {
        $baseRepo = base_path('..');
        $output = [];
        $returnVar = 0;
        exec("cd {$baseRepo} && git ls-files --stage web_portal/public/.htaccess web_portal/public/index.php web_portal/public/robots.txt web_portal/public/favicon.ico", $output, $returnVar);

        $this->assertSame(0, $returnVar, 'Fallo al ejecutar git ls-files');
        $this->assertNotEmpty($output, 'git ls-files no devolvió resultados');

        foreach ($output as $line) {
            $this->assertStringStartsWith('100644', $line, "El archivo en Git no está rastreado como 100644: {$line}");
        }
    }

    /**
     * Garantía de Inmutabilidad Criptográfica: Verificar que el archivo de auditoría
     * docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.53.90.md se mantenga intacto.
     */
    public function test_audit_report_hash_remains_strictly_immutable(): void
    {
        $auditReport = base_path('../docs/REPORTE_AUDITORIA_SEGURIDAD_ARQUITECTURA_v5.53.90.md');
        $this->assertFileExists($auditReport);

        $expectedHash = 'baa62332f165f5b2dbd9390ddbfbf3ff9d8a8cff7b25c14dd2cbc7dc88f6f247';
        $actualHash = hash_file('sha256', $auditReport);

        $this->assertSame(
            $expectedHash,
            $actualHash,
            'CRÍTICO: El archivo de auditoría ha sido alterado o manipulado.'
        );
    }
}
