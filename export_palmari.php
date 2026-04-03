<?php

declare(strict_types=1);

require __DIR__ . '/gshop/src/bootstrap.php';

use GShop\Database\SqlServerConnection;
use GShop\Service\PalmariExportService;

$config = require __DIR__ . '/gshop/config.php';
$sqlConfig = $config['sqlserver'] ?? [];

$filters = [
    'modarticolo' => trim((string) ($_GET['modarticolo'] ?? '')),
    'moddescr' => trim((string) ($_GET['moddescr'] ?? '')),
    'modstag' => trim((string) ($_GET['modstag'] ?? '')),
    'modnumer' => trim((string) ($_GET['modnumer'] ?? '')),
    'modmar' => trim((string) ($_GET['modmar'] ?? '')),
    'modcat' => trim((string) ($_GET['modcat'] ?? '')),
    'modaltezza' => trim((string) ($_GET['modaltezza'] ?? '')),
    'moddis' => trim((string) ($_GET['moddis'] ?? '')),
    'modpel' => trim((string) ($_GET['modpel'] ?? '')),
    'modpro' => trim((string) ($_GET['modpro'] ?? '')),
    'modforn' => trim((string) ($_GET['modforn'] ?? '')),
    'limit' => (int) ($_GET['limit'] ?? 200),
];

if ($filters['limit'] < 1) {
    $filters['limit'] = 200;
}
if ($filters['limit'] > 999999) {
    $filters['limit'] = 999999;
}

$doExport = isset($_GET['export']) && (string) $_GET['export'] === '1';
$error = null;
$profileInfo = null;
$rows = [];

$options = [
    'stagioni' => [],
    'numerazioni' => [],
    'marchi' => [],
    'categorie' => [],
    'tipi' => [],
    'discipline' => [],
    'pellami' => [],
    'reparti' => [],
    'fornitori' => [],
];

function toUtf8Value($value)
{
    if (!is_string($value)) {
        return $value;
    }

    if (mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }

    return mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
}

function toUtf8Row(array $row): array
{
    $out = [];
    foreach ($row as $k => $v) {
        $out[$k] = toUtf8Value($v);
    }
    return $out;
}

function loadOptions(PDO $pdo, string $table, string $codeField, string $descrField): array
{
    $sql = sprintf(
        'SELECT [%s] AS code, [%s] AS descr FROM %s ORDER BY [%s]',
        $codeField,
        $descrField,
        $table,
        $descrField
    );

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll() ?: [];

    $result = [];
    foreach ($data as $row) {
        $code = trim((string) ($row['code'] ?? ''));
        $descr = trim((string) toUtf8Value((string) ($row['descr'] ?? '')));
        if ($code === '') {
            continue;
        }
        $result[] = ['code' => $code, 'descr' => $descr];
    }

    return $result;
}

function fetchModelli(PDO $pdo, array $filters, bool $forExport): array
{
    $sql = "
        SELECT
            m.ModArticolo AS Codice,
            m.ModDescr AS Descrizione,
            m.ModPrzacq AS PrezzoAcquisto,
            m.ModPrzven1 AS PrezzoVendita1,
            m.ModPrzven2 AS PrezzoVendita2,
            m.ModPrzven3 AS PrezzoVendita3,
            m.ModPrzven4 AS PrezzoVendita4,
            m.ModPrzven5 AS PrezzoVendita5,
            s.StaDescr AS Stagione,
            n.NumDescr AS Taglie,
            ma.Mardescr AS Brand,
            c.CatDescr AS Categoria,
            t.TipoDescr AS Tipologia,
            d.DisDescr AS Disciplina,
            p.PelDescr AS Materiale,
            r.RepDescr AS Reparto,
            f.ForDescr AS Fornitore
        FROM modelli m
        LEFT JOIN numerazioni n ON m.ModNumer = n.NumCode
        LEFT JOIN categorie c ON m.ModCat = c.CatCode
        LEFT JOIN tipi t ON m.ModAltezza = t.TipoCode
        LEFT JOIN Marchi ma ON m.ModMar = ma.MarCode
        LEFT JOIN discipline d ON m.ModDis = d.DisCode
        LEFT JOIN fornitori f ON m.ModForn = f.ForCode
        LEFT JOIN reparti r ON m.ModPro = r.RepCode
        LEFT JOIN pellami p ON m.ModPel = p.PelCode
        LEFT JOIN stagioni s ON m.ModStag = s.StaCode
    ";

    $where = [];
    $params = [];

    if ($filters['modarticolo'] !== '') {
        $where[] = 'm.ModArticolo LIKE :modarticolo';
        $params[':modarticolo'] = '%' . $filters['modarticolo'] . '%';
    }
    if ($filters['moddescr'] !== '') {
        $where[] = 'm.ModDescr LIKE :moddescr';
        $params[':moddescr'] = '%' . $filters['moddescr'] . '%';
    }

    $map = [
        'modstag' => 'm.ModStag',
        'modnumer' => 'm.ModNumer',
        'modmar' => 'm.ModMar',
        'modcat' => 'm.ModCat',
        'modaltezza' => 'm.ModAltezza',
        'moddis' => 'm.ModDis',
        'modpel' => 'm.ModPel',
        'modpro' => 'm.ModPro',
        'modforn' => 'm.ModForn',
    ];

    foreach ($map as $key => $field) {
        if (($filters[$key] ?? '') === '') {
            continue;
        }
        $param = ':' . $key;
        $where[] = $field . ' = ' . $param;
        $params[$param] = $filters[$key];
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY m.ModArticolo ASC';

    if (!$forExport) {
        $sql .= ' OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY';
    }

    $stmt = $pdo->prepare($sql);

    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_STR);
    }

    if (!$forExport) {
        $stmt->bindValue(':limit', (int) $filters['limit'], PDO::PARAM_INT);
    }

    $stmt->execute();

    $data = $stmt->fetchAll() ?: [];
    return array_map('toUtf8Row', $data);
}

try {
    $connection = new SqlServerConnection($sqlConfig);
    $pdo = $connection->pdo();

    $options['stagioni'] = loadOptions($pdo, 'stagioni', 'StaCode', 'StaDescr');
    $options['numerazioni'] = loadOptions($pdo, 'numerazioni', 'NumCode', 'NumDescr');
    $options['marchi'] = loadOptions($pdo, 'Marchi', 'MarCode', 'Mardescr');
    $options['categorie'] = loadOptions($pdo, 'categorie', 'CatCode', 'CatDescr');
    $options['tipi'] = loadOptions($pdo, 'tipi', 'TipoCode', 'TipoDescr');
    $options['discipline'] = loadOptions($pdo, 'discipline', 'DisCode', 'DisDescr');
    $options['pellami'] = loadOptions($pdo, 'pellami', 'PelCode', 'PelDescr');
    $options['reparti'] = loadOptions($pdo, 'reparti', 'RepCode', 'RepDescr');
    $options['fornitori'] = loadOptions($pdo, 'fornitori', 'ForCode', 'ForDescr');

    if (hasSubmittedFilters($_GET)) {
        $profileFilters = $filters;
        unset($profileFilters['limit']);

        $masterdataService = new PalmariExportService(
            (string) ($config['export']['base_dir'] ?? (__DIR__ . '/gshop/exports')),
            $config['export']['masterdata_dir'] ?? null,
            (string) ($config['export']['masterdata_filename'] ?? 'masterdata.csv')
        );

        $savedProfile = $masterdataService->saveMasterdataProfile($profileFilters, (int) $filters['limit']);
        $profileInfo = [
            'ok' => true,
            'filters' => $savedProfile['filters'] ?? [],
            'limit' => (int) ($savedProfile['limit'] ?? (int) $filters['limit']),
        ];
    }

    $rows = fetchModelli($pdo, $filters, $doExport);

    if ($doExport) {
        $fileName = 'export_palmari_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Codice', 'Descrizione', 'PrezzoAcquisto', 'PrezzoVendita1', 'PrezzoVendita2', 'PrezzoVendita3', 'PrezzoVendita4', 'PrezzoVendita5', 'Stagione', 'Taglie', 'Brand', 'Categoria', 'Tipologia', 'Disciplina', 'Materiale', 'Reparto', 'Fornitore'], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['Codice'] ?? '',
                $row['Descrizione'] ?? '',
            $row['PrezzoAcquisto'] ?? '',
            $row['PrezzoVendita1'] ?? '',
            $row['PrezzoVendita2'] ?? '',
            $row['PrezzoVendita3'] ?? '',
            $row['PrezzoVendita4'] ?? '',
            $row['PrezzoVendita5'] ?? '',
                $row['Stagione'] ?? '',
                $row['Taglie'] ?? '',
                $row['Brand'] ?? '',
                $row['Categoria'] ?? '',
                $row['Tipologia'] ?? '',
                $row['Disciplina'] ?? '',
                $row['Materiale'] ?? '',
                $row['Reparto'] ?? '',
                $row['Fornitore'] ?? '',
            ], ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function selected(string $value, string $current): string
{
    return $value === $current ? 'selected' : '';
}

function hasSubmittedFilters(array $query): bool
{
    $keys = [
        'modarticolo', 'moddescr', 'modstag', 'modnumer', 'modmar',
        'modcat', 'modaltezza', 'moddis', 'modpel', 'modpro', 'modforn', 'limit'
    ];

    foreach ($keys as $key) {
        if (array_key_exists($key, $query)) {
            return true;
        }
    }

    return false;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export per palmari</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
        .page-title { font-weight: 700; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin.html">GetBarcodes - Export per palmari</a>
    </div>
</nav>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 page-title mb-0">Filtri modelli</h1>
        <a href="admin.html" class="btn btn-outline-secondary">Torna ad Admin</a>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger">
            Errore DB: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($profileInfo !== null && $error === null): ?>
        <div class="alert alert-info py-2">
            Profilo filtri masterdata aggiornato automaticamente (limit: <?= (int) $profileInfo['limit'] ?>).
        </div>
    <?php endif; ?>

    <form method="get" class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Codice articolo</label>
                    <input type="text" name="modarticolo" class="form-control" value="<?= htmlspecialchars($filters['modarticolo'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Descrizione</label>
                    <input type="text" name="moddescr" class="form-control" value="<?= htmlspecialchars($filters['moddescr'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Limite righe</label>
                    <input type="number" min="1" max="999999" name="limit" class="form-control" value="<?= (int) $filters['limit'] ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Stagione</label>
                    <select name="modstag" class="form-select">
                        <option value="">Tutte</option>
                        <?php foreach ($options['stagioni'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modstag']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Taglie</label>
                    <select name="modnumer" class="form-select">
                        <option value="">Tutte</option>
                        <?php foreach ($options['numerazioni'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modnumer']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Brand</label>
                    <select name="modmar" class="form-select">
                        <option value="">Tutti</option>
                        <?php foreach ($options['marchi'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modmar']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Categoria</label>
                    <select name="modcat" class="form-select">
                        <option value="">Tutte</option>
                        <?php foreach ($options['categorie'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modcat']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipologia</label>
                    <select name="modaltezza" class="form-select">
                        <option value="">Tutte</option>
                        <?php foreach ($options['tipi'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modaltezza']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Disciplina</label>
                    <select name="moddis" class="form-select">
                        <option value="">Tutte</option>
                        <?php foreach ($options['discipline'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['moddis']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Materiale</label>
                    <select name="modpel" class="form-select">
                        <option value="">Tutti</option>
                        <?php foreach ($options['pellami'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modpel']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Reparto</label>
                    <select name="modpro" class="form-select">
                        <option value="">Tutti</option>
                        <?php foreach ($options['reparti'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modpro']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fornitore</label>
                    <select name="modforn" class="form-select">
                        <option value="">Tutti</option>
                        <?php foreach ($options['fornitori'] as $o): ?>
                            <option value="<?= htmlspecialchars($o['code'], ENT_QUOTES, 'UTF-8') ?>" <?= selected($o['code'], $filters['modforn']) ?>>
                                <?= htmlspecialchars($o['descr'] . ' (' . $o['code'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Applica filtri</button>
                <a href="export_palmari.php" class="btn btn-outline-secondary">Reset filtri</a>
                <button type="submit" name="export" value="1" class="btn btn-success">Crea file export CSV</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Risultati</strong>
            <span class="badge bg-primary">Righe: <?= count($rows) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Codice</th>
                        <th>Descrizione</th>
                        <th>PrzAcq</th>
                        <th>PrzVen1</th>
                        <th>PrzVen2</th>
                        <th>PrzVen3</th>
                        <th>PrzVen4</th>
                        <th>PrzVen5</th>
                        <th>Stagione</th>
                        <th>Taglie</th>
                        <th>Brand</th>
                        <th>Categoria</th>
                        <th>Tipologia</th>
                        <th>Disciplina</th>
                        <th>Materiale</th>
                        <th>Reparto</th>
                        <th>Fornitore</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="17" class="text-center py-4 text-muted">Nessun risultato</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['Codice'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Descrizione'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['PrezzoAcquisto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['PrezzoVendita1'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['PrezzoVendita2'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['PrezzoVendita3'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['PrezzoVendita4'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['PrezzoVendita5'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Stagione'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Taglie'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Brand'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Tipologia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Disciplina'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Materiale'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Reparto'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['Fornitore'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
