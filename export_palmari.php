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
];

$viewLimit = (int) ($_GET['limit'] ?? 200);
if ($viewLimit < 1) {
    $viewLimit = 200;
}
if ($viewLimit > 5000) {
    $viewLimit = 5000;
}

$generateMasterdata = isset($_GET['export']) && (string) $_GET['export'] === '1';
$error = null;
$profileInfo = null;
$exportInfo = null;
$totalRows = 0;
$masterdataRows = 0;
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

function fetchModelli(PDO $pdo, array $filters, int $viewLimit): array
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

    $sql .= ' OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY';

    $stmt = $pdo->prepare($sql);

    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', $viewLimit, PDO::PARAM_INT);

    $stmt->execute();

    $data = $stmt->fetchAll() ?: [];
    return array_map('toUtf8Row', $data);
}

function countModelli(PDO $pdo, array $filters): int
{
    $sql = 'SELECT COUNT(*) FROM modelli m';

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

    $stmt = $pdo->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_STR);
    }

    $stmt->execute();

    return (int) $stmt->fetchColumn();
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

    $profileFilters = $filters;
    $masterdataService = new PalmariExportService(
        (string) ($config['export']['base_dir'] ?? (__DIR__ . '/gshop/exports')),
        $config['export']['masterdata_dir'] ?? null,
        (string) ($config['export']['masterdata_filename'] ?? 'masterdata.csv'),
        (int) ($config['export']['taglia_chars'] ?? 3),
        (int) ($config['export']['max_taglie_fallback'] ?? 30),
        (int) ($config['export']['masterdata_chunk_size'] ?? 1000)
    );

    if (hasSubmittedFilterCriteria($_GET) || $generateMasterdata) {
        $savedProfile = $masterdataService->saveMasterdataProfile($profileFilters, null);
        $profileInfo = [
            'ok' => true,
            'filters' => $savedProfile['filters'] ?? [],
        ];
    }

    if ($generateMasterdata) {
        $exportInfo = $masterdataService->refreshMasterdata($pdo, $profileFilters, null);
    }

    $rows = fetchModelli($pdo, $filters, $viewLimit);
    $totalRows = countModelli($pdo, $filters);
    $masterdataRows = $exportInfo !== null
        ? (int) ($exportInfo['rows'] ?? 0)
        : $masterdataService->estimateMasterdataRows($pdo, $profileFilters, null);
}
catch (Throwable $e) {
    $error = $e->getMessage();
}

function selected(string $value, string $current): string
{
    return $value === $current ? 'selected' : '';
}

function hasSubmittedFilterCriteria(array $query): bool
{
    $keys = [
        'modarticolo', 'moddescr', 'modstag', 'modnumer', 'modmar',
        'modcat', 'modaltezza', 'moddis', 'modpel', 'modpro', 'modforn'
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        body { background: #f6f8fb; }
        .page-title { font-weight: 700; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
<?php $navActivePage = 'export-palmari'; include __DIR__ . '/_navbar.php'; ?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 page-title mb-0">Filtri modelli</h1>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger">
            Errore DB: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($profileInfo !== null && $error === null): ?>
        <div class="alert alert-info py-2">
            Profilo filtri masterdata aggiornato automaticamente con i filtri correnti.
        </div>
    <?php endif; ?>

    <?php if ($exportInfo !== null && $error === null): ?>
        <div class="alert alert-success">
            Refresh masterdata eseguito: righe esportate <?= (int) ($exportInfo['rows'] ?? 0) ?>, file creati <?= (int) ($exportInfo['files_count'] ?? 0) ?>, chunk size <?= (int) ($config['export']['masterdata_chunk_size'] ?? 1000) ?>.
            <?php if (!empty($exportInfo['files']) && is_array($exportInfo['files'])): ?>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <?php foreach ($exportInfo['files'] as $fileMeta): ?>
                        <?php $part = (int) ($fileMeta['index'] ?? 0); ?>
                        <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars('/gshop/index.php?route=/gshop/api/palmari/masterdata/file&part=' . $part, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($fileMeta['file'] ?? ('Parte ' . $part)), ENT_QUOTES, 'UTF-8') ?>
                            (<?= (int) ($fileMeta['rows'] ?? 0) ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                    <label class="form-label">Limite visualizzazione</label>
                    <input type="number" min="1" max="5000" name="limit" class="form-control" value="<?= $viewLimit ?>">
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
                <button type="submit" name="export" value="1" class="btn btn-success">Esegui refresh masterdata</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Risultati</strong>
            <span class="badge bg-primary">Anteprima: <?= count($rows) ?> / <?= $viewLimit ?></span>
        </div>
        <div class="card-body border-bottom py-2 small text-muted d-flex justify-content-between align-items-center">
            <span>Totale modelli filtrati: <?= $totalRows ?></span>
            <span>Righe masterdata attese: <?= $masterdataRows ?></span>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
