<?php
/**
 * Barcodes Browser - Navigazione nella cartella barcodes
 */

// Parametri di configurazione
$baseDir = __DIR__ . '/barcodes';
$requestPath = '';
$editMode = false;
$fileContent = '';
$editFile = '';
$editError = '';
$editSuccess = false;

// Gestione salvataggio file (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_action']) && $_POST['_action'] === 'save') {
    $editFile = $_POST['file'] ?? '';
    $fileContent = $_POST['content'] ?? '';
    
    // Validazione percorso file
    $filePath = $baseDir . '/' . $editFile;
    $realPath = realpath($baseDir);
    $realFilePath = realpath($filePath);
    
    // Verifica che il file sia dentro baseDir e sia un file .txt
    if ($realFilePath === false || strpos($realFilePath, $realPath) !== 0 || !str_ends_with($realFilePath, '.txt')) {
        $editError = 'Accesso non consentito';
    } elseif (!is_file($realFilePath)) {
        $editError = 'File non trovato';
    } elseif (!is_writable($realFilePath)) {
        $editError = 'Permessi insufficienti per scrivere il file';
    } else {
        if (file_put_contents($realFilePath, $fileContent) !== false) {
            $editSuccess = true;
            $editError = 'File salvato con successo!';
        } else {
            $editError = 'Errore durante il salvataggio del file';
        }
    }
}

// Gestione modalità edit (GET)
if (!empty($_GET['edit'])) {
    $editFile = $_GET['edit'];
    
    // Validazione percorso file
    $filePath = $baseDir . '/' . $editFile;
    $realPath = realpath($baseDir);
    $realFilePath = realpath($filePath);
    
    // Verifica che il file sia dentro baseDir e sia un file .txt
    if ($realFilePath === false || strpos($realFilePath, $realPath) !== 0 || !str_ends_with($realFilePath, '.txt')) {
        $editError = 'Accesso non consentito';
    } elseif (!is_file($realFilePath)) {
        $editError = 'File non trovato';
    } else {
        $fileContent = file_get_contents($realFilePath);
        if ($fileContent === false) {
            $editError = 'Errore durante la lettura del file';
        } else {
            $editMode = true;
            // Estrai il percorso per il breadcrumb
            $requestPath = dirname($editFile);
            if ($requestPath === '.') {
                $requestPath = '';
            }
        }
    }
}

// Se non in modalità edit, procedi con la navigazione normale
if (!$editMode && empty($editError)) {
    // Validazione del parametro path
    if (!empty($_GET['path'])) {
        $requestPath = $_GET['path'];
        // Sanitizzazione: rimuovi .. per evitare directory traversal
        $requestPath = str_replace('..', '', $requestPath);
        $requestPath = trim($requestPath, '/');
    }
    
    $currentPath = $baseDir;
    if (!empty($requestPath)) {
        $currentPath = $baseDir . '/' . $requestPath;
    }
    
    // Validazione percorso (deve essere dentro baseDir)
    $realPath = realpath($currentPath);
    if ($realPath === false || strpos($realPath, realpath($baseDir)) !== 0) {
        $realPath = realpath($baseDir);
        $requestPath = '';
    }
}

// Leggi contenuto della cartella (solo se non in modalità edit)
$items = [];
if (!$editMode && empty($editError)) {
    $items = @scandir($realPath);
    if ($items === false) {
        $items = [];
    }
    
    // Separa file e cartelle
    $folders = [];
    $files = [];
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $itemPath = $realPath . '/' . $item;
        $itemSize = 0;
        $itemDate = filemtime($itemPath);
        
        if (is_dir($itemPath)) {
            $folders[] = [
                'name' => $item,
                'path' => (!empty($requestPath) ? $requestPath . '/' : '') . $item,
                'date' => $itemDate,
            ];
        } else {
            $itemSize = filesize($itemPath);
            $files[] = [
                'name' => $item,
                'path' => (!empty($requestPath) ? $requestPath . '/' : '') . $item,
                'size' => $itemSize,
                'date' => $itemDate,
            ];
        }
    }
    
    // Ordina
    usort($folders, fn($a, $b) => strcasecmp($b['name'], $a['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    
    // Merge mantenendo cartelle prima dei file
    $items = array_merge($folders, $files);
}

// Funzione helper per formattare la dimensione
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Funzione helper per formattare la data
function formatDate($timestamp) {
    return date('d/m/Y H:i', $timestamp);
}

// Breadcrumb
function getBreadcrumb($path) {
    $parts = explode('/', trim($path, '/'));
    $breadcrumb = [['name' => 'barcodes', 'path' => '']];
    $currentPath = '';
    
    foreach ($parts as $part) {
        if (!empty($part)) {
            $currentPath .= ($currentPath ? '/' : '') . $part;
            $breadcrumb[] = ['name' => $part, 'path' => $currentPath];
        }
    }
    
    return $breadcrumb;
}

$breadcrumb = getBreadcrumb($requestPath);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigazione Barcodes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
            padding: 2rem 0;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .breadcrumb {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .file-table {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .file-row {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.2s;
        }
        .file-row:hover {
            background: #f8f9fa;
        }
        .file-row:last-child {
            border-bottom: none;
        }
        .file-row-left {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
        }
        .file-icon {
            font-size: 1.5rem;
            width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .file-info {
            flex: 1;
            min-width: 0;
        }
        .file-name {
            font-weight: 500;
            word-break: break-all;
            color: #212529;
        }
        .file-details {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .folder-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
        .folder-link:hover {
            text-decoration: underline;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .editor-container {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .editor-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
        }
        .editor-header h5 {
            margin: 0;
            color: #212529;
            font-weight: 600;
        }
        .editor-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        textarea.editor {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.95rem;
            line-height: 1.5;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            width: 100%;
            min-height: 400px;
            resize: vertical;
        }
        textarea.editor:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .alert-success, .alert-danger {
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-file-earmark-text me-2"></i>Barcodes
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin.html">
                            <i class="bi bi-house me-1"></i>Pannello principale
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <div class="container">
            <?php if ($editMode): ?>
                <!-- Editor Mode -->
                <div class="editor-container">
                    <div class="editor-header">
                        <h5>
                            <i class="bi bi-pencil-square me-2"></i>Modifica file: <?= htmlspecialchars(basename($editFile)) ?>
                        </h5>
                        <small class="text-muted">
                            <i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($editFile) ?>
                        </small>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="_action" value="save">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($editFile) ?>">
                        
                        <textarea name="content" class="editor" required><?= htmlspecialchars($fileContent) ?></textarea>
                        
                        <div class="editor-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Salva
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Torna alla cartella
                            </a>
                        </div>
                    </form>
                </div>
            <?php elseif (!empty($editError)): ?>
                <!-- Error Mode -->
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($editError) ?>
                </div>
                <div class="text-center">
                    <a href="?" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-1"></i>Torna indietro
                    </a>
                </div>
            <?php elseif ($editSuccess): ?>
                <!-- Success Mode -->
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($editError) ?>
                </div>
                <div class="editor-container">
                    <div class="editor-header">
                        <h5>
                            <i class="bi bi-pencil-square me-2"></i>Modifica file: <?= htmlspecialchars(basename($editFile)) ?>
                        </h5>
                        <small class="text-muted">
                            <i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($editFile) ?>
                        </small>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="_action" value="save">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($editFile) ?>">
                        
                        <textarea name="content" class="editor" required><?= htmlspecialchars($fileContent) ?></textarea>
                        
                        <div class="editor-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Salva
                            </button>
                            <a href="<?= $requestPath ? '?path=' . urlencode($requestPath) : '?' ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Torna alla cartella
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Browse Mode -->
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <?php foreach ($breadcrumb as $index => $crumb): ?>
                            <?php if ($index === count($breadcrumb) - 1): ?>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <i class="bi bi-folder me-1"></i><?= htmlspecialchars($crumb['name']) ?>
                                </li>
                            <?php else: ?>
                                <li class="breadcrumb-item">
                                    <a href="?path=<?= urlencode($crumb['path']) ?>" class="text-decoration-none">
                                        <i class="bi bi-folder me-1"></i><?= htmlspecialchars($crumb['name']) ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <!-- File List -->
                <div class="file-table">
                    <?php if (empty($items)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>Questa cartella è vuota</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php if (array_key_exists('path', $item) && in_array($item, $folders)): ?>
                                <!-- Cartella -->
                                <div class="file-row">
                                    <div class="file-row-left">
                                        <div class="file-icon text-primary">
                                            <i class="bi bi-folder-fill"></i>
                                        </div>
                                        <div class="file-info">
                                            <a href="?path=<?= urlencode($item['path']) ?>" class="folder-link">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <div class="file-details">
                                                Cartella • Modificato: <?= formatDate($item['date']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- File -->
                                <div class="file-row">
                                    <div class="file-row-left">
                                        <div class="file-icon text-secondary">
                                            <i class="bi bi-file-earmark"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>
                                            <div class="file-details">
                                                <?= formatBytes($item['size']) ?> • Modificato: <?= formatDate($item['date']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (str_ends_with($item['name'], '.txt')): ?>
                                        <div>
                                            <a href="?edit=<?= urlencode($item['path']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil me-1"></i>Modifica
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4">
                    <a href="admin.html" class="btn btn-outline-primary">
                        <i class="bi bi-house me-1"></i>Torna al pannello principale
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
