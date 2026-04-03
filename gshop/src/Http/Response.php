<?php

namespace GShop\Http;

class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            error_log('ERROR: json_encode failed - ' . json_last_error_msg());
            echo json_encode(['error' => 'JSON encoding failed']);
            return;
        }
        echo $json;
    }

    public static function fileDownload(string $absolutePath, string $downloadName, string $contentType = 'text/csv'): void
    {
        if (!is_file($absolutePath)) {
            self::json(['error' => 'File non trovato'], 404);
            return;
        }

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');

        $fp = fopen($absolutePath, 'rb');
        if ($fp === false) {
            self::json(['error' => 'Impossibile aprire il file'], 500);
            return;
        }

        fpassthru($fp);
        fclose($fp);
    }
}
