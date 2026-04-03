<?php

require __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use GShop\Controller\DataController;
use GShop\Controller\ExportController;
use GShop\Controller\HealthController;
use GShop\Controller\PalmariExportController;
use GShop\Database\SqlServerConnection;
use GShop\Dto\Registry;
use GShop\Http\Request;
use GShop\Http\Response;
use GShop\Repository\GenericTableRepository;
use GShop\Router;
use GShop\Service\ExportService;
use GShop\Service\PalmariExportService;

$request = Request::fromGlobals();

$db = new SqlServerConnection($config['sqlserver'] ?? []);
$registry = new Registry();
$exportBaseDir = $config['export']['base_dir'] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'exports');
$exportService = new ExportService($exportBaseDir);
$palmariExportService = new PalmariExportService(
    $exportBaseDir,
    $config['export']['masterdata_dir'] ?? null,
    (string) ($config['export']['masterdata_filename'] ?? 'masterdata.csv'),
    (int) ($config['export']['taglia_chars'] ?? 3),
    (int) ($config['export']['max_taglie_fallback'] ?? 60),
    (int) ($config['export']['masterdata_chunk_size'] ?? 50000)
);

$healthController = new HealthController($db);

$router = new Router();
$router->add('GET', '/gshop/api/health', function () use ($healthController): void {
    $healthController->check();
});

$router->add('GET', '/gshop/api/data/{entity}', function (Request $request, array $params) use ($db, $registry, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $repository = new GenericTableRepository($db->pdo());
        $dataController = new DataController($registry, $repository);
        $dataController->list($request, $params);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('POST', '/gshop/api/export/{entity}', function (Request $request, array $params) use ($db, $registry, $exportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $repository = new GenericTableRepository($db->pdo());
        $exportController = new ExportController($registry, $repository, $exportService);
        $exportController->generate($request, $params);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('GET', '/gshop/api/export/files/{date}/{filename}', function (Request $request, array $params) use ($db, $registry, $exportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $repository = new GenericTableRepository($db->pdo());
        $exportController = new ExportController($registry, $repository, $exportService);
        $exportController->download($params);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('POST', '/gshop/api/palmari/export', function (Request $request) use ($db, $palmariExportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $controller = new PalmariExportController($palmariExportService, $db->pdo());
        $controller->generate($request);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('GET', '/gshop/api/palmari/files/{exportId}', function (Request $request, array $params) use ($db, $palmariExportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        // Manteniamo dipendenza dal DB per coerenza con gli altri endpoint protetti.
        $db->pdo();
        $controller = new PalmariExportController($palmariExportService, $db->pdo());
        $controller->download($params);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('POST', '/gshop/api/palmari/masterdata/refresh', function (Request $request) use ($db, $palmariExportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $controller = new PalmariExportController($palmariExportService, $db->pdo());
        $controller->refreshMasterdata($request);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('POST', '/gshop/api/palmari/masterdata/profile', function (Request $request) use ($db, $palmariExportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $db->pdo();
        $controller = new PalmariExportController($palmariExportService, $db->pdo());
        $controller->saveMasterdataProfile($request);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('GET', '/gshop/api/palmari/masterdata/profile', function (Request $request) use ($db, $palmariExportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $db->pdo();
        $controller = new PalmariExportController($palmariExportService, $db->pdo());
        $controller->getMasterdataProfile();
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$router->add('GET', '/gshop/api/palmari/masterdata/file', function (Request $request) use ($db, $palmariExportService, $config): void {
    if (!authorize($request, $config)) {
        return;
    }
    try {
        $db->pdo();
        $controller = new PalmariExportController($palmariExportService, $db->pdo());
        $controller->downloadMasterdata($request);
    } catch (\Throwable $e) {
        Response::json(['error' => 'Errore connessione database', 'details' => $e->getMessage()], 500);
    }
});

$matched = $router->dispatch($request);
if (!$matched) {
    Response::json(['error' => 'Endpoint non trovato'], 404);
}

function authorize(Request $request, array $config): bool
{
    $expectedKey = (string) ($config['api_key'] ?? '');
    if ($expectedKey === '') {
        Response::json(['error' => 'API key non configurata'], 500);
        return false;
    }

    $provided = $request->header('X-Api-Key');
    if (!is_string($provided) || !hash_equals($expectedKey, $provided)) {
        Response::json(['error' => 'Non autorizzato'], 401);
        return false;
    }

    return true;
}
