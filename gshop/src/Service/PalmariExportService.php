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

    public function __construct(string $baseDir, ?string $masterdataDir = null, string $masterdataFilename = 'masterdata.csv')
    {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->jobsDir = $this->baseDir . DIRECTORY_SEPARATOR . 'palmari_jobs';
        $this->masterdataDir = rtrim((string) ($masterdataDir ?: ($this->baseDir . DIRECTORY_SEPARATOR . 'scheduled')), DIRECTORY_SEPARATOR);
        $this->masterdataFilename = $masterdataFilename !== '' ? $masterdataFilename : 'masterdata.csv';
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
        if ($limit < 1) {
            $limit = 999999;
        }
        if ($limit > 2000000) {
            $limit = 2000000;
        }

        $normalized = $this->normalizeFilters($filters);

        if (!is_dir($this->masterdataDir) && !mkdir($this->masterdataDir, 0755, true) && !is_dir($this->masterdataDir)) {
            throw new RuntimeException('Impossibile creare la cartella masterdata');
        }

        $absolutePath = $this->masterdataDir . DIRECTORY_SEPARATOR . $this->masterdataFilename;

        $stmt = $this->executeQuery($pdo, $normalized, $limit);
        $rowCount = $this->writeCsvFromStatement($absolutePath, $stmt);

        $metadata = [
            'created_at' => date('c'),
            'filters' => $normalized,
            'limit' => $limit,
            'rows' => $rowCount,
            'filename' => $this->masterdataFilename,
            'absolute_path' => $absolutePath,
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
        $absolutePath = $this->masterdataDir . DIRECTORY_SEPARATOR . $this->masterdataFilename;
        if (!is_file($absolutePath)) {
            return null;
        }

        return [
            'filename' => $this->masterdataFilename,
            'absolute_path' => $absolutePath,
        ];
    }

    private function executeQuery(PDO $pdo, array $filters, int $limit): \PDOStatement
    {
        $sql = $this->baseSql();

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

        $sql .= ' ORDER BY RTRIM(m.ModArticolo) ASC, CAST(LTRIM(b.ModCorriPosTaglia) AS INT) ASC';
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

    private function baseSql(): string
    {
        return "
            SELECT
                b.ModCorriBarcode AS Barcode,
                RTRIM(m.ModArticolo) AS Codice,
                m.ModDescr AS Descrizione,
                RTRIM(SUBSTRING(n.NumTaglie, (CAST(LTRIM(b.ModCorriPosTaglia) AS INT) - 1) * 3 + 1, 3)) AS Taglia,
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
