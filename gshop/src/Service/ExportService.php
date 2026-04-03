<?php

namespace GShop\Service;

use RuntimeException;

class ExportService
{
    private $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
    }

    public function exportRowsToCsv(string $entity, array $rows): array
    {
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $entity)) {
            throw new RuntimeException('Nome entita non valido');
        }

        $today = date('Ymd');
        $dir = $this->baseDir . DIRECTORY_SEPARATOR . $today;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossibile creare la directory export');
        }

        $filename = sprintf('%s_%s.csv', $entity, date('Ymd_His'));
        $absolutePath = $dir . DIRECTORY_SEPARATOR . $filename;

        $fp = fopen($absolutePath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Impossibile creare il file export');
        }

        if (!empty($rows)) {
            $headers = array_keys($rows[0]);
            fputcsv($fp, $headers, ';', '"', '\\');

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = array_key_exists($header, $row) ? $row[$header] : null;
                }
                fputcsv($fp, $line, ';', '"', '\\');
            }
        }

        fclose($fp);

        return [
            'date' => $today,
            'filename' => $filename,
            'relative_path' => $today . '/' . $filename,
            'absolute_path' => $absolutePath,
            'rows' => count($rows),
        ];
    }

    public function resolveExportFile(string $date, string $filename): ?string
    {
        if (!preg_match('/^\d{8}$/', $date)) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $filename)) {
            return null;
        }

        $path = $this->baseDir . DIRECTORY_SEPARATOR . $date . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }
}
