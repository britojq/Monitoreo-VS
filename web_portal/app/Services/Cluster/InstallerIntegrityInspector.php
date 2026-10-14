<?php

namespace App\Services\Cluster;

class InstallerIntegrityInspector
{
    /**
     * Lista oficial de instaladores autorizados del repositorio local.
     */
    protected array $trackedFiles = [
        'GLPI-Agent-x86.msi',
        'GLPI-Agent-x64.msi',
        'glpi-agent_all.deb',
        'glpi-linux.sh',
        'glpi-win.ps1',
        'glpi-switch.sh',
        'glpi-switch.ps1',
    ];

    /**
     * Inspecciona todos los binarios y scripts en la carpeta de instaladores.
     *
     * @return array
     */
    public function inspect(): array
    {
        $dir = $this->resolveInstallersPath();
        $results = [];
        $allPresent = true;

        foreach ($this->trackedFiles as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            $exists = file_exists($filePath) && is_readable($filePath);

            if (!$exists) {
                $allPresent = false;
                $results[$file] = [
                    'filename' => $file,
                    'exists' => false,
                    'size_bytes' => 0,
                    'sha256' => null,
                    'modified_at' => null,
                    'download_url' => url('/instaladores/' . $file),
                ];
                continue;
            }

            $size = @filesize($filePath) ?: 0;
            $sha256 = @hash_file('sha256', $filePath) ?: null;
            $mtime = @filemtime($filePath);
            $modifiedAt = $mtime ? date('Y-m-d H:i:s', $mtime) : null;

            $results[$file] = [
                'filename' => $file,
                'exists' => true,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'sha256' => $sha256,
                'modified_at' => $modifiedAt,
                'download_url' => url('/instaladores/' . $file),
            ];
        }

        return [
            'directory_path' => $dir,
            'total_tracked' => count($this->trackedFiles),
            'present_count' => count(array_filter($results, fn($r) => $r['exists'])),
            'all_present' => $allPresent,
            'files' => $results,
        ];
    }

    /**
     * Resuelve la ruta autoritativa del directorio de instaladores.
     */
    protected function resolveInstallersPath(): string
    {
        $candidates = [
            public_path('instaladores'),
            '/var/www/monitoreo/public/instaladores',
            base_path('public/instaladores'),
            '/scripts/telegram-admin-bot/web_portal/public/instaladores',
        ];

        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                return realpath($cand) ?: $cand;
            }
        }

        return public_path('instaladores');
    }

    /**
     * Formatea bytes en cadena legible para humanos.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
