<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON', 'error_code' => 'INVALID_JSON']);
    exit;
}

if (!isset($data['scanned_codes'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing scanned_codes field', 'error_code' => 'MISSING_FIELD']);
    exit;
}

if (!isset($data['list_name']) || !is_string($data['list_name']) || trim($data['list_name']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid list_name field', 'error_code' => 'MISSING_FIELD']);
    exit;
}

$codes = $data['scanned_codes'];
if (!is_array($codes)) {
    http_response_code(400);
    echo json_encode(['error' => 'scanned_codes must be a list of strings', 'error_code' => 'INVALID_TYPE']);
    exit;
}

// Verifica che tutti gli elementi siano stringhe
foreach ($codes as $code) {
    if (!is_string($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'scanned_codes must be a list of strings', 'error_code' => 'INVALID_TYPE']);
        exit;
    }
}

if (empty($codes)) {
    http_response_code(400);
    echo json_encode(['error' => 'scanned_codes list is empty', 'error_code' => 'EMPTY_LIST']);
    exit;
}

try {
    $today = date('Ymd');
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'barcodes' . DIRECTORY_SEPARATOR . $today;

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new Exception('Failed to create directory');
    }

    // Sanifica il nome file rimuovendo caratteri non sicuri
    $listName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($data['list_name']));
    $filename = $listName . '.txt';
    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

    $content = implode("\n", $codes) . "\n";

    if (file_put_contents($filepath, $content) === false) {
        throw new Exception('Failed to write file');
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'file' => $today . '/' . $filename]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'error_code' => 'FILE_WRITE_ERROR']);
}
?>
