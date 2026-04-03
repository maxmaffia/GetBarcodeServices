<?php

declare(strict_types=1);

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'gshop' . DIRECTORY_SEPARATOR . 'config.php';
$error = null;
$success = null;

if (!is_file($configPath)) {
    http_response_code(500);
    echo 'File config non trovato: ' . htmlspecialchars($configPath, ENT_QUOTES, 'UTF-8');
    exit;
}

$config = require $configPath;
if (!is_array($config)) {
    http_response_code(500);
    echo 'Formato config non valido';
    exit;
}

function postString(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function postInt(string $key, int $default): int
{
    $value = (int) ($_POST[$key] ?? $default);
    return $value;
}

function normalizePositive(int $value, int $fallback): int
{
    return $value > 0 ? $value : $fallback;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = [
        'api_key' => postString('api_key', (string) ($config['api_key'] ?? '')),
        'sqlserver' => [
            'host' => postString('sql_host', (string) ($config['sqlserver']['host'] ?? 'localhost')),
            'port' => normalizePositive(postInt('sql_port', (int) ($config['sqlserver']['port'] ?? 1433)), 1433),
            'database' => postString('sql_database', (string) ($config['sqlserver']['database'] ?? '')),
            'username' => postString('sql_username', (string) ($config['sqlserver']['username'] ?? '')),
            'password' => postString('sql_password', (string) ($config['sqlserver']['password'] ?? '')),
            'auth_mode' => postString('sql_auth_mode', (string) ($config['sqlserver']['auth_mode'] ?? 'sql')),
            'connection' => postString('sql_connection', (string) ($config['sqlserver']['connection'] ?? 'auto')),
            'odbc_driver' => postString('sql_odbc_driver', (string) ($config['sqlserver']['odbc_driver'] ?? 'ODBC Driver 18 for SQL Server')),
            'trust_server_certificate' => isset($_POST['sql_trust_server_certificate']),
        ],
        'export' => [
            'base_dir' => postString('export_base_dir', (string) ($config['export']['base_dir'] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'gshop' . DIRECTORY_SEPARATOR . 'exports'))),
            'masterdata_dir' => postString('export_masterdata_dir', (string) ($config['export']['masterdata_dir'] ?? '')),
            'masterdata_filename' => postString('export_masterdata_filename', (string) ($config['export']['masterdata_filename'] ?? 'masterdata.csv')),
            'taglia_chars' => normalizePositive(postInt('export_taglia_chars', (int) ($config['export']['taglia_chars'] ?? 3)), 3),
            'max_taglie_fallback' => normalizePositive(postInt('export_max_taglie_fallback', (int) ($config['export']['max_taglie_fallback'] ?? 30)), 30),
            'masterdata_chunk_size' => normalizePositive(postInt('export_masterdata_chunk_size', (int) ($config['export']['masterdata_chunk_size'] ?? 50000)), 50000),
        ],
    ];

    $php = "<?php\nreturn " . var_export($new, true) . ";\n";
    $written = @file_put_contents($configPath, $php);
    if ($written === false) {
        $error = 'Errore durante il salvataggio del file config.php';
    } else {
        $success = 'Configurazione salvata con successo';
        $config = $new;
    }
}

$sql = $config['sqlserver'] ?? [];
$exp = $config['export'] ?? [];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GShop - Impostazioni</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
        .card { border-radius: 12px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin.html">GetBarcodes - GShop Settings</a>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="gshop_admin.html">Area GShop</a>
            <a class="btn btn-outline-light btn-sm" href="admin.html">Dashboard</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="mb-3">
        <h1 class="h3 mb-1">Impostazioni GShop</h1>
        <p class="text-muted mb-0">Modifica tutti i parametri presenti in gshop/config.php</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($success !== null): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>API</strong></div>
                <div class="card-body">
                    <label class="form-label">API Key</label>
                    <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars((string) ($config['api_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>SQL Server</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Host</label>
                            <input type="text" name="sql_host" class="form-control" value="<?= htmlspecialchars((string) ($sql['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Porta</label>
                            <input type="number" min="1" name="sql_port" class="form-control" value="<?= (int) ($sql['port'] ?? 1433) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Database</label>
                            <input type="text" name="sql_database" class="form-control" value="<?= htmlspecialchars((string) ($sql['database'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="sql_username" class="form-control" value="<?= htmlspecialchars((string) ($sql['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="text" name="sql_password" class="form-control" value="<?= htmlspecialchars((string) ($sql['password'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Auth Mode</label>
                            <select name="sql_auth_mode" class="form-select">
                                <?php $authMode = (string) ($sql['auth_mode'] ?? 'sql'); ?>
                                <option value="sql" <?= $authMode === 'sql' ? 'selected' : '' ?>>sql</option>
                                <option value="integrated" <?= $authMode === 'integrated' ? 'selected' : '' ?>>integrated</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Connection</label>
                            <?php $conn = (string) ($sql['connection'] ?? 'auto'); ?>
                            <select name="sql_connection" class="form-select">
                                <option value="auto" <?= $conn === 'auto' ? 'selected' : '' ?>>auto</option>
                                <option value="sqlsrv" <?= $conn === 'sqlsrv' ? 'selected' : '' ?>>sqlsrv</option>
                                <option value="odbc" <?= $conn === 'odbc' ? 'selected' : '' ?>>odbc</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Trust Cert</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="sql_trust_server_certificate" id="sql_trust_server_certificate" <?= !empty($sql['trust_server_certificate']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sql_trust_server_certificate">true</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">ODBC Driver</label>
                            <input type="text" name="sql_odbc_driver" class="form-control" value="<?= htmlspecialchars((string) ($sql['odbc_driver'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Export Masterdata</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Base Dir</label>
                            <input type="text" name="export_base_dir" class="form-control" value="<?= htmlspecialchars((string) ($exp['base_dir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Masterdata Dir</label>
                            <input type="text" name="export_masterdata_dir" class="form-control" value="<?= htmlspecialchars((string) ($exp['masterdata_dir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Masterdata Filename</label>
                            <input type="text" name="export_masterdata_filename" class="form-control" value="<?= htmlspecialchars((string) ($exp['masterdata_filename'] ?? 'masterdata.csv'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Taglia Chars</label>
                            <input type="number" min="1" name="export_taglia_chars" class="form-control" value="<?= (int) ($exp['taglia_chars'] ?? 3) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Taglie Fallback</label>
                            <input type="number" min="1" name="export_max_taglie_fallback" class="form-control" value="<?= (int) ($exp['max_taglie_fallback'] ?? 30) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Masterdata Chunk Size</label>
                            <input type="number" min="1" name="export_masterdata_chunk_size" class="form-control" value="<?= (int) ($exp['masterdata_chunk_size'] ?? 50000) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Salva configurazione</button>
            <a href="gshop_settings.php" class="btn btn-outline-secondary">Ricarica</a>
        </div>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
