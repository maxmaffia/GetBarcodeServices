<?php
declare(strict_types=1);

$gshopPlugin = false;
$configPath  = __DIR__ . '/gshop/config.php';
if (is_file($configPath)) {
    $cfg = @include $configPath;
    if (is_array($cfg)) {
        $gshopPlugin = !empty($cfg['gshop_plugin']);
    }
}

$navActivePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GetBarcodes — Pannello di Amministrazione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f6f8fb;
        }
        main {
            flex: 1;
            padding-top: 2.5rem;
            padding-bottom: 2.5rem;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/_navbar.php'; ?>

<main>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="text-center mb-5">
                    <h1 class="display-5 fw-bold mb-2">Pannello di amministrazione</h1>
                    <p class="text-muted lead">Gestione completa di GetBarcodes. Seleziona una sezione.</p>
                </div>

                <?php if ($gshopPlugin): ?>
                <!-- Sezione GShop -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-grid-3x3-gap me-2"></i><strong>GShop</strong>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <a href="gshop_admin.php" class="btn btn-outline-success btn-lg">
                                <i class="bi bi-grid me-2"></i>Area GShop
                            </a>
                            <a href="gshop_settings.php" class="btn btn-outline-success btn-lg">
                                <i class="bi bi-sliders me-2"></i>Impostazioni GShop
                            </a>
                            <a href="export_palmari.php" class="btn btn-outline-success btn-lg">
                                <i class="bi bi-file-earmark-arrow-down me-2"></i>Export Palmari
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sezione strumenti core -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-tools me-2"></i><strong>Strumenti</strong>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <a href="ipupdate_ui.php" class="btn btn-outline-primary btn-lg">
                                <i class="bi bi-server me-2"></i>Configurazione IP
                            </a>
                            <a href="barcodes_browser.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-folder me-2"></i>Gestione Barcodes
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!$gshopPlugin): ?>
                <p class="text-muted small text-center mt-4">
                    <i class="bi bi-info-circle me-1"></i>
                    Il plugin GShop è disabilitato. Per abilitarlo imposta <code>gshop_plugin = true</code> in <code>gshop/config.php</code>.
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
