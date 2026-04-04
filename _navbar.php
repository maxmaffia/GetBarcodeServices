<?php
/**
 * _navbar.php — Barra di navigazione condivisa.
 *
 * Variabile opzionale da impostare prima dell'include:
 *   $navActivePage (string) — identifica la pagina corrente per l'highlight del link.
 *   Valori: 'dashboard' | 'gshop' | 'gshop-settings' | 'export-palmari' | 'ipconfig' | 'barcodes'
 */

$_navCfgPath     = __DIR__ . '/gshop/config.php';
$_navGshopPlugin = false;
if (is_file($_navCfgPath)) {
    $_navCfg = @include $_navCfgPath;
    if (is_array($_navCfg)) {
        $_navGshopPlugin = !empty($_navCfg['gshop_plugin']);
    }
}
$_navActive = $navActivePage ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="admin.php">
            <i class="bi bi-upc-scan me-2"></i>GetBarcodes
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link<?= $_navActive === 'dashboard' ? ' active' : '' ?>" href="admin.php">
                        <i class="bi bi-house me-1"></i>Dashboard
                    </a>
                </li>
                <?php if ($_navGshopPlugin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= str_starts_with($_navActive, 'gshop') ? ' active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-grid-3x3-gap me-1"></i>GShop
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item<?= $_navActive === 'gshop' ? ' active' : '' ?>" href="gshop_admin.php">
                                <i class="bi bi-grid me-2"></i>Area GShop
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item<?= $_navActive === 'gshop-settings' ? ' active' : '' ?>" href="gshop_settings.php">
                                <i class="bi bi-sliders me-2"></i>Impostazioni GShop
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item<?= $_navActive === 'export-palmari' ? ' active' : '' ?>" href="export_palmari.php">
                                <i class="bi bi-file-earmark-arrow-down me-2"></i>Export Palmari
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link<?= $_navActive === 'ipconfig' ? ' active' : '' ?>" href="ipupdate_ui.php">
                        <i class="bi bi-server me-1"></i>Config. IP
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $_navActive === 'barcodes' ? ' active' : '' ?>" href="barcodes_browser.php">
                        <i class="bi bi-folder me-1"></i>Barcodes
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
