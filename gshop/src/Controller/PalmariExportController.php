<?php

namespace GShop\Controller;

use GShop\Http\Request;
use GShop\Http\Response;
use GShop\Service\PalmariExportService;
use PDO;

class PalmariExportController
{
    private $service;
    private $pdo;

    public function __construct(PalmariExportService $service, PDO $pdo)
    {
        $this->service = $service;
        $this->pdo = $pdo;
    }

    public function generate(Request $request): void
    {
        $body = $request->jsonBody();
        if ($body === null) {
            Response::json(['error' => 'Body JSON non valido'], 400);
            return;
        }

        $filters = isset($body['filters']) && is_array($body['filters']) ? $body['filters'] : [];
        $limit = (int) ($body['limit'] ?? 5000);

        try {
            $result = $this->service->createFilteredExport($this->pdo, $filters, $limit);
            Response::json([
                'status' => 'ok',
                'export_id' => $result['export_id'],
                'rows' => $result['rows'],
                'file' => $result['relative_path'],
                'download_url' => '/gshop/api/palmari/files/' . $result['export_id'],
                'filters' => $result['filters'],
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore export palmari', 'details' => $e->getMessage()], 500);
        }
    }

    public function download(array $params): void
    {
        $exportId = (string) ($params['exportId'] ?? '');
        $meta = $this->service->resolveExportById($exportId);

        if ($meta === null) {
            Response::json(['error' => 'Export non trovato'], 404);
            return;
        }

        $filename = (string) ($meta['filename'] ?? ('palmari_' . $exportId . '.csv'));
        $absolutePath = (string) ($meta['absolute_path'] ?? '');
        Response::fileDownload($absolutePath, $filename, 'text/csv');
    }

    public function refreshMasterdata(Request $request): void
    {
        $body = $request->jsonBody();
        if ($body === null) {
            Response::json(['error' => 'Body JSON non valido'], 400);
            return;
        }

        $filters = isset($body['filters']) && is_array($body['filters']) ? $body['filters'] : [];
        $hasLimit = array_key_exists('limit', $body);
        $limit = $hasLimit ? (int) $body['limit'] : null;
        $filtersSource = 'request';
        $profileSaved = false;

        if ($this->isEmptyFiltersPayload($filters)) {
            $profile = $this->service->getMasterdataProfile();
            $filters = $profile['filters'];
            if (!$hasLimit) {
                $limit = $profile['limit'];
            }
            $filtersSource = (string) ($profile['source'] ?? 'default');
        } else {
            try {
                $this->service->saveMasterdataProfile($filters, $hasLimit ? $limit : null);
                $profileSaved = true;
            } catch (\Throwable $e) {
                Response::json(['error' => 'Errore salvataggio profilo filtri', 'details' => $e->getMessage()], 500);
                return;
            }
        }

        $limit = $this->service->normalizeMasterdataLimit($limit);

        try {
            $result = $this->service->refreshMasterdata($this->pdo, $filters, $limit);
            $files = isset($result['files']) && is_array($result['files']) ? $result['files'] : [];
            $filesForResponse = [];
            foreach ($files as $fileMeta) {
                $fileName = (string) ($fileMeta['file'] ?? '');
                if ($fileName === '') {
                    continue;
                }
                $index = (int) ($fileMeta['index'] ?? 0);
                $filesForResponse[] = [
                    'file' => $fileName,
                    'rows' => (int) ($fileMeta['rows'] ?? 0),
                    'index' => $index,
                    'download_url' => '/gshop/api/palmari/masterdata/file?part=' . $index,
                ];
            }

            Response::json([
                'status' => 'ok',
                'message' => 'File masterdata aggiornati',
                'rows' => $result['rows'],
                'files_count' => (int) ($result['files_count'] ?? count($filesForResponse)),
                'files' => $filesForResponse,
                'download_url' => '/gshop/api/palmari/masterdata/file',
                'filters' => $result['filters'],
                'filters_source' => $filtersSource,
                'profile_saved' => $profileSaved,
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore refresh masterdata', 'details' => $e->getMessage()], 500);
        }
    }

    public function saveMasterdataProfile(Request $request): void
    {
        $body = $request->jsonBody();
        if ($body === null) {
            Response::json(['error' => 'Body JSON non valido'], 400);
            return;
        }

        $filters = isset($body['filters']) && is_array($body['filters']) ? $body['filters'] : [];
        $limit = array_key_exists('limit', $body) ? (int) $body['limit'] : null;

        try {
            $profile = $this->service->saveMasterdataProfile($filters, $limit);
            Response::json([
                'status' => 'ok',
                'message' => 'Profilo filtri masterdata salvato',
                'profile' => $profile,
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore salvataggio profilo filtri', 'details' => $e->getMessage()], 500);
        }
    }

    public function getMasterdataProfile(): void
    {
        try {
            $profile = $this->service->getMasterdataProfile();
            Response::json([
                'status' => 'ok',
                'profile' => $profile,
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore lettura profilo filtri', 'details' => $e->getMessage()], 500);
        }
    }

    public function downloadMasterdata(Request $request): void
    {
        $part = (int) ($request->query()['part'] ?? 0);
        if ($part > 0) {
            $meta = $this->service->resolveMasterdataFileByIndex($part);
        } else {
            // Compat legacy: se arriva file=masterdata_X.csv lo supportiamo ancora.
            $requestedFile = trim((string) ($request->query()['file'] ?? ''));
            $meta = $this->service->resolveMasterdataFileByName($requestedFile !== '' ? $requestedFile : null);
        }

        if ($meta === null) {
            Response::json(['error' => 'File masterdata non trovato'], 404);
            return;
        }

        $filename = (string) ($meta['filename'] ?? 'masterdata.csv');
        $absolutePath = (string) ($meta['absolute_path'] ?? '');
        $format = strtolower(trim((string) ($request->query()['format'] ?? 'csv')));

        if ($format === '' || $format === 'csv') {
            Response::fileDownload($absolutePath, $filename, 'text/csv');
            return;
        }

        if ($format !== 'json') {
            Response::json(['error' => 'Formato non supportato. Usa format=csv oppure format=json'], 400);
            return;
        }

        $this->streamMasterdataJson($absolutePath, preg_replace('/\.csv$/i', '.json', $filename) ?: 'masterdata.json');
    }

    private function streamMasterdataJson(string $absolutePath, string $downloadName): void
    {
        $fp = fopen($absolutePath, 'rb');
        if ($fp === false) {
            Response::json(['error' => 'Impossibile aprire il file'], 500);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');

        $headers = fgetcsv($fp, 0, ';', '"', '\\');
        if (!is_array($headers)) {
            fclose($fp);
            Response::json(['error' => 'Header CSV non valido'], 500);
            return;
        }

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
        }

        echo '[';
        $first = true;
        $skippedRows = 0;
        $csvLine = 1; // Header line.

        while (($row = fgetcsv($fp, 0, ';', '"', '\\')) !== false) {
            $csvLine++;
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = $row[$index] ?? '';
            }

            $json = json_encode($assoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $jsonError = $json === false ? json_last_error_msg() : null;
            if ($json === false) {
                $assoc = $this->sanitizeUtf8Array($assoc);
                $json = json_encode($assoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($json === false) {
                    $skippedRows++;
                    $this->logMasterdataJsonSkip(
                        $absolutePath,
                        $csvLine,
                        (string) $jsonError,
                        json_last_error_msg(),
                        $assoc
                    );
                    continue;
                }
            }

            if (!$first) {
                echo ',';
            }

            echo $json;
            $first = false;
        }

        echo ']';
        if ($skippedRows > 0) {
            header('X-Masterdata-Skipped-Rows: ' . $skippedRows);
        }
        fclose($fp);
    }

    private function logMasterdataJsonSkip(string $csvPath, int $line, string $firstError, string $secondError, array $row): void
    {
        $logPath = dirname($csvPath) . DIRECTORY_SEPARATOR . 'masterdata_json_errors.log';

        $entry = [
            'timestamp' => date('c'),
            'csv_file' => basename($csvPath),
            'csv_line' => $line,
            'first_encode_error' => $firstError,
            'second_encode_error' => $secondError,
            // Snapshot ridotto per facilitare debug senza appesantire il log.
            'row_preview' => mb_substr(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '', 0, 500),
        ];

        $lineText = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"error":"log_encode_failed"}';
        @file_put_contents($logPath, $lineText . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeUtf8Array(array $input): array
    {
        $out = [];

        foreach ($input as $key => $value) {
            if (!is_string($value)) {
                $out[$key] = $value;
                continue;
            }

            if (mb_check_encoding($value, 'UTF-8')) {
                $out[$key] = $value;
                continue;
            }

            $out[$key] = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }

        return $out;
    }

    private function isEmptyFiltersPayload(array $filters): bool
    {
        if (empty($filters)) {
            return true;
        }

        foreach ($filters as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
