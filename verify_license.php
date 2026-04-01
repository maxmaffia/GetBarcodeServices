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
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

if (!isset($data['licensecode'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing licensecode field']);
    exit;
}

$licensecode = $data['licensecode'];
$license_file = __DIR__ . DIRECTORY_SEPARATOR . 'license.txt';

// Verifica se il file license.txt esiste
if (!file_exists($license_file)) {
    http_response_code(200);
    echo json_encode(['licensecheck' => false]);
    exit;
}

try {
    // Leggi il contenuto del file license.txt
    $license_content = file_get_contents($license_file);
    
    if ($license_content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read license file']);
        exit;
    }
    
    // Rimuovi eventuali spazi bianchi e newline dal contenuto
    $license_content = trim($license_content);
    
    // Converti il contenuto in base64
    $license_base64 = base64_encode($license_content);
    
    // Confronta con il licensecode ricevuto
    if ($licensecode === $license_base64) {
        // Licenza corretta: cancella il file
        unlink($license_file);
        http_response_code(200);
        echo json_encode(['licensecheck' => true]);
    } else {
        // Licenza non corretta
        http_response_code(200);
        echo json_encode(['licensecheck' => false]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
