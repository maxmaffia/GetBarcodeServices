<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Accetta solo richieste POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['result' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Legge e decodifica il body JSON
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['result' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// Validazione campi obbligatori
$id_azienda = isset($input['id_azienda']) ? (int) $input['id_azienda'] : null;
$azienda    = isset($input['azienda'])    ? trim((string) $input['azienda']) : null;
$server     = isset($input['server'])     ? trim((string) $input['server'])  : null;

if (empty($id_azienda) || $id_azienda <= 0 || $azienda === '' || $azienda === null
    || $server === '' || $server === null) {
    http_response_code(400);
    echo json_encode(['result' => false, 'error' => 'Missing or invalid fields: id_azienda, azienda, server']);
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

    // UPSERT: se id_azienda esiste aggiorna, altrimenti inserisce
    $sql = "
        INSERT INTO `aziende` (`id_azienda`, `azienda`, `server`)
        VALUES (:id_azienda, :azienda, :server)
        ON DUPLICATE KEY UPDATE
            `azienda` = VALUES(`azienda`),
            `server`  = VALUES(`server`),
            `updated_at` = NOW()
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_azienda' => $id_azienda,
        ':azienda'    => $azienda,
        ':server'     => $server,
    ]);

    echo json_encode(['result' => true]);

} catch (PDOException $e) {
    error_log('[services/index.php] DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['result' => false]);
}
