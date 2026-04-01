<?php
require_once __DIR__ . '/ipupdate_config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Recupera l'IP locale principale della macchina.
 */
function resolveLocalIp(): string
{
    // Primo tentativo: risoluzione hostname locale
    $hostnameIp = gethostbyname(gethostname());
    if (filter_var($hostnameIp, FILTER_VALIDATE_IP) && strpos($hostnameIp, '127.') !== 0) {
        return $hostnameIp;
    }

    // Secondo tentativo: socket UDP (non invia traffico reale, serve a capire l'interfaccia in uso)
    if (function_exists('socket_create')) {
        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock !== false) {
            @socket_connect($sock, '8.8.8.8', 53);
            @socket_getsockname($sock, $ip);
            @socket_close($sock);

            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

$payload = [
    'id_azienda' => IPUPDATE_ID_AZIENDA,
    'azienda'    => IPUPDATE_AZIENDA,
    'server'     => resolveLocalIp(),
];

$endpoint = 'https://www.godrop.me/services/';
$jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

if ($jsonBody === false) {
    http_response_code(500);
    echo json_encode([
        'result' => false,
        'error'  => 'Payload encoding failed',
    ]);
    exit;
}

$result = false;
$error  = null;

// Invio preferenziale via cURL
if (function_exists('curl_init')) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonBody),
        ],
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError    = curl_error($ch);
    curl_close($ch);

    if ($responseBody !== false && $httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($responseBody, true);
        $result  = is_array($decoded) && !empty($decoded['result']);
    } else {
        $error = $curlError !== '' ? $curlError : ('HTTP ' . $httpCode);
    }
} else {
    // Fallback con stream context
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n"
                       . 'Content-Length: ' . strlen($jsonBody) . "\r\n",
            'content' => $jsonBody,
            'timeout' => 15,
        ],
    ]);

    $responseBody = @file_get_contents($endpoint, false, $context);

    if ($responseBody !== false) {
        $decoded = json_decode($responseBody, true);
        $result  = is_array($decoded) && !empty($decoded['result']);
    } else {
        $error = 'Unable to contact endpoint';
    }
}

http_response_code($result ? 200 : 500);
echo json_encode([
    'result'  => $result,
    'payload' => $payload,
    'error'   => $error,
]);
