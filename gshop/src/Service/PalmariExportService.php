<?php

namespace GShop\Service;

use PDO;
use RuntimeException;

class PalmariExportService
{
    private $baseDir;
    private $jobsDir;
    private $masterdataDir;
    private $masterdataFilename;
    private $tagliaChars;
    private $maxTaglieFallback;
    private $masterdataChunkSize;
    private $currentMaxTaglie;

    public function __construct(string $baseDir, ?string $masterdataDir = null, string $masterdataFilename = 'masterdata.csv', int $tagliaChars = 3, int $maxTaglieFallback = 60, int $masterdataChunkSize = 50000)
    {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->jobsDir = $this->baseDir . DIRECTORY_SEPARATOR . 'palmari_jobs';
        $this->masterdataDir = rtrim((string) ($masterdataDir ?: ($this->baseDir . DIRECTORY_SEPARATOR . 'scheduled')), DIRECTORY_SEPARATOR);
        $this->masterdataFilename = $masterdataFilename !== '' ? $masterdataFilename : 'masterdata.csv';
        $this->tagliaChars = $tagliaChars > 0 ? $tagliaChars : 3;
        $this->maxTaglieFallback = $maxTaglieFallback > 0 ? $maxTaglieFallback : 60;
        $this->masterdataChunkSize = $masterdataChunkSize > 0 ? $masterdataChunkSize : 50000;
        $this->currentMaxTaglie = $this->maxTaglieFallback;
    }

    public function createFilteredExport(PDO $pdo, array $filters, int $limit = 5000): array
    {
        if ($limit < 1) {
            $limit = 5000;
        }
        if ($limit > 50000) {
            $limit = 50000;
        }

        $normalized = $this->normalizeFilters($filters);

        $today = date('Ymd');
        $dir = $this->baseDir . DIRECTORY_SEPARATOR . $today;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossibile creare la directory export palmari');
        }

        if (!is_dir($this->jobsDir) && !mkdir($this->jobsDir, 0755, true) && !is_dir($this->jobsDir)) {
            throw new RuntimeException('Impossibile creare la directory metadata export');
        }

        $exportId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = 'palmari_' . $exportId . '.csv';
        $absolutePath = $dir . DIRECTORY_SEPARATOR . $filename;

        $stmt = $this->executeQuery($pdo, $normalized, $limit);
        $rowCount = $this->writeCsvFromStatement($absolutePath, $stmt);

        $metadata = [
            'export_id' => $exportId,
            'created_at' => date('c'),
            'filters' => $normalized,
            'limit' => $limit,
            'rows' => $rowCount,
            'date' => $today,
            'filename' => $filename,
            'relative_path' => $today . '/' . $filename,
            'absolute_path' => $absolutePath,
        ];

        $metaPath = $this->jobsDir . DIRECTORY_SEPARATOR . $exportId . '.json';
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($metaPath, $json) === false) {
            throw new RuntimeException('Impossibile salvare metadata export');
        }

        return $metadata;
    }

    public function resolveExportById(string $exportId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $exportId)) {
            return null;
        }

        $metaPath = $this->jobsDir . DIRECTORY_SEPARATOR . $exportId . '.json';
        if (!is_file($metaPath)) {
            return null;
        }

        $json = file_get_contents($metaPath);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $absolutePath = (string) ($data['absolute_path'] ?? '');
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return null;
        }

        return $data;
    }

    public function refreshMasterdata(PDO $pdo, array $filters, int $limit = 999999): array
    {
        $limit = $this->normalizeMasterdataLimit($limit);

        $normalized = $this->normalizeFilters($filters);

        if (!is_dir($this->masterdataDir) && !mkdir($this->masterdataDir, 0755, true) && !is_dir($this->masterdataDir)) {
            throw new RuntimeException('Impossibile creare la cartella masterdata');
        }

        $this->cleanupMasterdataChunks();

        $stmt = $this->executeQuery($pdo, $normalized, $limit);
        $chunkResult = $this->writeCsvChunksFromStatement($this->masterdataFilename, $stmt, $this->masterdataChunkSize);
        $rowCount = (int) ($chunkResult['rows'] ?? 0);
        $files = $chunkResult['files'] ?? [];

        $metadata = [
            'created_at' => date('c'),
            'filters' => $normalized,
            'limit' => $limit,
            'rows' => $rowCount,
            'files_count' => count($files),
            'files' => $files,
        ];

        $metaPath = $this->masterdataDir . DIRECTORY_SEPARATOR . 'masterdata.meta.json';
        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json !== false) {
            file_put_contents($metaPath, $json);
        }

        return $metadata;
    }

    public function resolveMasterdataFile(): ?array
    {
        return $this->resolveMasterdataFileByName(null);
    }

    public function resolveMasterdataFileByName(?string $requestedName): ?array
    {
        if (is_string($requestedName) && trim($requestedName) !== '') {
            $file = basename(trim($requestedName));
            if (!preg_match('/^[A-Za-z0-9._\-]+$/', $file)) {
                return null;
            }

            $absolutePath = $this->masterdataDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($absolutePath)) {
                return null;
            }

            return [
                'filename' => $file,
                'absolute_path' => $absolutePath,
            ];
        }

        $files = $this->listMasterdataChunks();
        if (empty($files)) {
            return null;
        }

        return [
            'filename' => $files[0],
            'absolute_path' => $this->masterdataDir . DIRECTORY_SEPARATOR . $files[0],
        ];
    }

    public function resolveMasterdataFileByIndex(int $index): ?array
    {
        $files = $this->listMasterdataChunks();
        if (empty($files)) {
            return null;
        }

        if ($index < 1) {
            $index = 1;
        }

        $offset = $index - 1;
        if (!isset($files[$offset])) {
            return null;
        }

        $filename = $files[$offset];
        return [
            'filename' => $filename,
            'absolute_path' => $this->masterdataDir . DIRECTORY_SEPARATOR . $filename,
        ];
    }

    public function saveMasterdataProfile(array $filters, int $limit = 999999): array
    {
        $normalized = $this->normalizeFilters($filters);
        $limit = $this->normalizeMasterdataLimit($limit);

        if (!is_dir($this->masterdataDir) && !mkdir($this->masterdataDir, 0755, true) && !is_dir($this->masterdataDir)) {
            throw new RuntimeException('Impossibile creare la cartella masterdata');
        }

        $profile = [
            'updated_at' => date('c'),
            'filters' => $normalized,
            'limit' => $limit,
        ];

        $json = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($this->masterdataProfilePath(), $json) === false) {
            throw new RuntimeException('Impossibile salvare il profilo filtri masterdata');
        }

        return $profile;
    }

    public function getMasterdataProfile(): array
    {
        $defaults = [
            'updated_at' => null,
            'filters' => $this->normalizeFilters([]),
            'limit' => 999999,
            'source' => 'default',
        ];

        $path = $this->masterdataProfilePath();
        if (!is_file($path)) {
            return $defaults;
        }

        $json = file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return $defaults;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $defaults;
        }

        return [
            'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            'filters' => $this->normalizeFilters(isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : []),
            'limit' => $this->normalizeMasterdataLimit((int) ($data['limit'] ?? 999999)),
            'source' => 'saved',
        ];
    }

    public function normalizeMasterdataLimit(int $limit): int
    {
        if ($limit < 1) {
            return 999999;
        }
        if ($limit > 2000000) {
            return 2000000;
        }
        return $limit;
    }

    private function masterdataProfilePath(): string
    {
        return $this->masterdataDir . DIRECTORY_SEPARATOR . 'masterdata.profile.json';
    }

    private function listMasterdataChunks(): array
    {
        $base = pathinfo($this->masterdataFilename, PATHINFO_FILENAME);
        $pattern = '/^' . preg_quote($base, '/') . '_(\d+)\.csv$/i';

        $files = [];
        $items = @scandir($this->masterdataDir);
        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            if (preg_match($pattern, $item, $m) === 1) {
                $files[] = ['name' => $item, 'index' => (int) $m[1]];
            }
        }

        usort($files, function (array $a, array $b): int {
            return $a['index'] <=> $b['index'];
        });

        return array_map(function (array $item): string {
            return $item['name'];
        }, $files);
    }

    private function cleanupMasterdataChunks(): void
    {
        $files = $this->listMasterdataChunks();
        foreach ($files as $file) {
            $path = $this->masterdataDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $legacy = $this->masterdataDir . DIRECTORY_SEPARATOR . $this->masterdataFilename;
        if (is_file($legacy)) {
            @unlink($legacy);
        }
    }

    private function executeQuery(PDO $pdo, array $filters, int $limit): \PDOStatement
    {
        $maxTaglie = $this->resolveMaxTaglie($pdo);
        $this->currentMaxTaglie = $maxTaglie;
        $sql = $this->baseSql($maxTaglie);

        $where = [];
        $params = [];

        if ($filters['modarticolo'] !== '') {
            $where[] = 'm.ModArticolo LIKE :modarticolo';
            $params[':modarticolo'] = '%' . $filters['modarticolo'] . '%';
        }
        if ($filters['moddescr'] !== '') {
            $where[] = 'm.ModDescr LIKE :moddescr';
            $params[':moddescr'] = '%' . $filters['moddescr'] . '%';
        }

        $map = [
            'modstag' => 'm.ModStag',
            'modnumer' => 'm.ModNumer',
            'modmar' => 'm.ModMar',
            'modcat' => 'm.ModCat',
            'modaltezza' => 'm.ModAltezza',
            'moddis' => 'm.ModDis',
            'modpel' => 'm.ModPel',
            'modpro' => 'm.ModPro',
            'modforn' => 'm.ModForn',
        ];

        foreach ($map as $key => $field) {
            if (($filters[$key] ?? '') === '') {
                continue;
            }
            $param = ':' . $key;
            $where[] = $field . ' = ' . $param;
            $params[$param] = $filters[$key];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY RTRIM(m.ModArticolo) ASC, TRY_CAST(LTRIM(b.ModCorriPosTaglia) AS INT) ASC';
        $sql .= ' OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY';

        $stmt = $pdo->prepare($sql);
        $stmt->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_FWDONLY);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    private function resolveMaxTaglie(PDO $pdo): int
    {
        $sql = "
            SELECT TOP 1 ParValore
            FROM ParametriNegozio
            WHERE ParRecord = 'GESTIONETAGLIE'
        ";

        try {
            $stmt = $pdo->query($sql);
            $value = $stmt !== false ? $stmt->fetchColumn() : false;
            $maxTaglie = (int) trim((string) $value);
            if ($maxTaglie > 0) {
                return $maxTaglie;
            }
        } catch (\Throwable $e) {
            // Fallback a valore configurato se la tabella/record non e disponibile.
        }

        return $this->maxTaglieFallback;
    }

    private function normalizeFilters(array $filters): array
    {
        $keys = [
            'modarticolo', 'moddescr', 'modstag', 'modnumer', 'modmar',
            'modcat', 'modaltezza', 'moddis', 'modpel', 'modpro', 'modforn'
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = trim((string) ($filters[$key] ?? ''));
        }

        return $out;
    }

    private function toUtf8Row(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (!is_string($v)) {
                $out[$k] = $v;
                continue;
            }

            if (mb_check_encoding($v, 'UTF-8')) {
                $out[$k] = $v;
                continue;
            }

            $out[$k] = mb_convert_encoding($v, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }

        return $out;
    }

    private function writeCsvFromStatement(string $absolutePath, \PDOStatement $stmt): int
    {
        $fp = fopen($absolutePath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Impossibile creare il file CSV palmari');
        }

        fwrite($fp, "\xEF\xBB\xBF");
        $headers = [
            'Barcode', 'Codice', 'Descrizione', 'Taglia',
            'PrezzoAcquisto', 'PrezzoVendita1', 'PrezzoVendita2', 'PrezzoVendita3',
            'PrezzoVendita4', 'PrezzoVendita5', 'Stagione', 'Brand', 'Categoria', 'Tipologia',
            'Disciplina', 'Materiale', 'Reparto', 'Fornitore'
        ];
        fputcsv($fp, $headers, ';', '"', '\\');

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row = $this->toUtf8Row($row);

            if ($this->shouldExpandRootBarcode($row)) {
                $numTaglieRaw = (string) ($row['NumTaglieRaw'] ?? '');
                for ($pos = 1; $pos <= $this->currentMaxTaglie; $pos++) {
                    $expanded = $this->buildExpandedBarcodeRow($row, $numTaglieRaw, $pos);
                    if ($this->isEmptyTaglia($expanded)) {
                        continue;
                    }
                    $line = [];
                    foreach ($headers as $header) {
                        $line[] = $expanded[$header] ?? '';
                    }
                    fputcsv($fp, $line, ';', '"', '\\');
                    $count++;
                }
                continue;
            }

            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($fp, $line, ';', '"', '\\');
            $count++;
        }

        fclose($fp);
        return $count;
    }

    private function writeCsvChunksFromStatement(string $baseFilename, \PDOStatement $stmt, int $chunkSize): array
    {
        $chunkSize = $chunkSize > 0 ? $chunkSize : 50000;
        $base = pathinfo($baseFilename, PATHINFO_FILENAME);
        $headers = [
            'Barcode', 'Codice', 'Descrizione', 'Taglia',
            'PrezzoAcquisto', 'PrezzoVendita1', 'PrezzoVendita2', 'PrezzoVendita3',
            'PrezzoVendita4', 'PrezzoVendita5', 'Stagione', 'Brand', 'Categoria', 'Tipologia',
            'Disciplina', 'Materiale', 'Reparto', 'Fornitore'
        ];

        $files = [];
        $totalRows = 0;
        $chunkRows = 0;
        $chunkIndex = 0;
        $fp = null;

        $openChunk = function () use (&$fp, &$chunkRows, &$chunkIndex, &$files, $base, $headers): void {
            $chunkIndex++;
            $chunkRows = 0;
            $filename = $base . '_' . $chunkIndex . '.csv';
            $absolutePath = $this->masterdataDir . DIRECTORY_SEPARATOR . $filename;
            $fp = fopen($absolutePath, 'wb');
            if ($fp === false) {
                throw new RuntimeException('Impossibile creare un file CSV masterdata chunk');
            }

            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $headers, ';', '"', '\\');

            $files[] = [
                'file' => $filename,
                'absolute_path' => $absolutePath,
                'rows' => 0,
                'index' => $chunkIndex,
            ];
        };

        $writeRow = function (array $data) use (&$fp, &$chunkRows, &$totalRows, &$files, $chunkSize, $headers, $openChunk): void {
            if ($fp === null || $chunkRows >= $chunkSize) {
                if ($fp !== null) {
                    fclose($fp);
                    $fp = null;
                }
                $openChunk();
            }

            $line = [];
            foreach ($headers as $header) {
                $line[] = $data[$header] ?? '';
            }

            fputcsv($fp, $line, ';', '"', '\\');
            $chunkRows++;
            $totalRows++;
            $files[count($files) - 1]['rows'] = $chunkRows;
        };

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row = $this->toUtf8Row($row);

            if ($this->shouldExpandRootBarcode($row)) {
                $numTaglieRaw = (string) ($row['NumTaglieRaw'] ?? '');
                for ($pos = 1; $pos <= $this->currentMaxTaglie; $pos++) {
                    $expanded = $this->buildExpandedBarcodeRow($row, $numTaglieRaw, $pos);
                    if ($this->isEmptyTaglia($expanded)) {
                        continue;
                    }
                    $writeRow($expanded);
                }
                continue;
            }

            $writeRow($row);
        }

        if ($fp !== null) {
            fclose($fp);
        }

        return [
            'rows' => $totalRows,
            'files' => $files,
        ];
    }

    private function isEmptyTaglia(array $row): bool
    {
        return trim((string) ($row['Taglia'] ?? '')) === '';
    }

    private function shouldExpandRootBarcode(array $row): bool
    {
        $barcode = trim((string) ($row['Barcode'] ?? ''));
        $posTaglia = trim((string) ($row['PosTagliaRaw'] ?? ''));

        return preg_match('/^\d{10}$/', $barcode) === 1 && $posTaglia === '';
    }

    private function buildExpandedBarcodeRow(array $row, string $numTaglieRaw, int $position): array
    {
        $base10 = trim((string) ($row['Barcode'] ?? ''));
        $pos2 = str_pad((string) $position, 2, '0', STR_PAD_LEFT);
        $ean12 = $base10 . $pos2;
        $checksum = $this->computeEan13ChecksumDigit($ean12);

        $expanded = $row;
        $expanded['Barcode'] = $ean12 . $checksum;
        $expanded['Taglia'] = $this->extractTagliaByPosition($numTaglieRaw, $position);

        return $expanded;
    }

    private function extractTagliaByPosition(string $numTaglieRaw, int $position): string
    {
        if ($position < 1 || $this->tagliaChars < 1) {
            return '';
        }

        $start = ($position - 1) * $this->tagliaChars;
        $chunk = substr($numTaglieRaw, $start, $this->tagliaChars);

        return trim((string) $chunk);
    }

    private function computeEan13ChecksumDigit(string $ean12): string
    {
        if (preg_match('/^\d{12}$/', $ean12) !== 1) {
            return '0';
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $ean12[$i];
            // EAN13: posizioni dispari peso 1, posizioni pari peso 3 (posizioni 1-based).
            $sum += ($i % 2 === 0) ? $digit : ($digit * 3);
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    private function baseSql(int $maxTaglie): string
    {
        $tagliaChars = $this->tagliaChars;
        $maxTaglie = $maxTaglie > 0 ? $maxTaglie : $this->maxTaglieFallback;

        return "
            SELECT
                b.ModCorriBarcode AS Barcode,
                NULLIF(LTRIM(RTRIM(b.ModCorriPosTaglia)), '') AS PosTagliaRaw,
                RTRIM(m.ModArticolo) AS Codice,
                m.ModDescr AS Descrizione,
                CASE
                    WHEN TRY_CAST(LTRIM(b.ModCorriPosTaglia) AS INT) BETWEEN 1 AND {$maxTaglie}
                    THEN RTRIM(SUBSTRING(n.NumTaglie, (TRY_CAST(LTRIM(b.ModCorriPosTaglia) AS INT) - 1) * {$tagliaChars} + 1, {$tagliaChars}))
                    ELSE ''
                END AS Taglia,
                n.NumTaglie AS NumTaglieRaw,
                m.ModPrzacq AS PrezzoAcquisto,
                m.ModPrzven1 AS PrezzoVendita1,
                m.ModPrzven2 AS PrezzoVendita2,
                m.ModPrzven3 AS PrezzoVendita3,
                m.ModPrzven4 AS PrezzoVendita4,
                m.ModPrzven5 AS PrezzoVendita5,
                s.StaDescr AS Stagione,
                ma.Mardescr AS Brand,
                c.CatDescr AS Categoria,
                t.TipoDescr AS Tipologia,
                d.DisDescr AS Disciplina,
                p.PelDescr AS Materiale,
                r.RepDescr AS Reparto,
                f.ForDescr AS Fornitore
            FROM ModCorris_Barcode b
            INNER JOIN modelli m ON RTRIM(b.ModCorriArticolo) = RTRIM(m.ModArticolo)
            LEFT JOIN numerazioni n ON m.ModNumer = n.NumCode
            LEFT JOIN categorie c ON m.ModCat = c.CatCode
            LEFT JOIN tipi t ON m.ModAltezza = t.TipoCode
            LEFT JOIN Marchi ma ON m.ModMar = ma.MarCode
            LEFT JOIN discipline d ON m.ModDis = d.DisCode
            LEFT JOIN fornitori f ON m.ModForn = f.ForCode
            LEFT JOIN reparti r ON m.ModPro = r.RepCode
            LEFT JOIN pellami p ON m.ModPel = p.PelCode
            LEFT JOIN stagioni s ON m.ModStag = s.StaCode
        ";
    }
}
