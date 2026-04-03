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
        $limit = (int) ($body['limit'] ?? 999999);

        try {
            $result = $this->service->refreshMasterdata($this->pdo, $filters, $limit);
            Response::json([
                'status' => 'ok',
                'message' => 'masterdata.csv aggiornato',
                'rows' => $result['rows'],
                'file' => $result['filename'],
                'download_url' => '/gshop/api/palmari/masterdata/file',
                'filters' => $result['filters'],
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore refresh masterdata', 'details' => $e->getMessage()], 500);
        }
    }

    public function downloadMasterdata(Request $request): void
    {
        $meta = $this->service->resolveMasterdataFile();
        if ($meta === null) {
            Response::json(['error' => 'masterdata.csv non trovato'], 404);
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

        while (($row = fgetcsv($fp, 0, ';', '"', '\\')) !== false) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = $row[$index] ?? '';
            }

            $json = json_encode($assoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                fclose($fp);
                Response::json(['error' => 'Errore conversione JSON'], 500);
                return;
            }

            if (!$first) {
                echo ',';
            }

            echo $json;
            $first = false;
        }

        echo ']';
        fclose($fp);
    }
}
