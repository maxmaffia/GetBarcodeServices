<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$apiKeyFromHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKeyFromHeader === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^Bearer\s+(.+)$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $apiKeyFromHeader = trim($matches[1]);
    }
}

if ($apiKeyFromHeader !== SERVICES_API_KEY) {
    http_response_code(401);
    echo json_encode(['result' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['result' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['result' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// Supporta sia idazienda che id_azienda
$idAzienda = null;
if (isset($input['idazienda'])) {
    $idAzienda = (int) $input['idazienda'];
} elseif (isset($input['id_azienda'])) {
    $idAzienda = (int) $input['id_azienda'];
}

if (empty($idAzienda) || $idAzienda <= 0) {
    http_response_code(400);
    echo json_encode(['result' => false, 'error' => 'Missing or invalid field: idazienda']);
    exit;
}

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $stmt = $pdo->prepare('SELECT `server` FROM `aziende` WHERE `id_azienda` = :id_azienda LIMIT 1');
    $stmt->execute([':id_azienda' => $idAzienda]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['result' => false, 'error' => 'Azienda non trovata']);
        exit;
    }

    echo json_encode([
        'result'     => true,
        'id_azienda' => $idAzienda,
        'server'     => (string) $row['server'],
    ]);

} catch (PDOException $e) {
    error_log('[services/getip.php] DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['result' => false]);
}
