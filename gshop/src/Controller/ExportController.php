<?php

namespace GShop\Controller;

use GShop\Dto\Registry;
use GShop\Http\Request;
use GShop\Http\Response;
use GShop\Repository\GenericTableRepository;
use GShop\Service\ExportService;
use RuntimeException;

class ExportController
{
    private $registry;
    private $repository;
    private $exportService;

    public function __construct(Registry $registry, GenericTableRepository $repository, ExportService $exportService)
    {
        $this->registry = $registry;
        $this->repository = $repository;
        $this->exportService = $exportService;
    }

    public function generate(Request $request, array $params): void
    {
        $entity = strtolower((string) ($params['entity'] ?? ''));
        $entityConfig = $this->registry->get($entity);

        if ($entityConfig === null) {
            Response::json(['error' => 'Entita non registrata', 'entity' => $entity], 404);
            return;
        }

        $body = $request->jsonBody();
        if ($body === null) {
            Response::json(['error' => 'Body JSON non valido'], 400);
            return;
        }

        $filters = isset($body['filters']) && is_array($body['filters']) ? $body['filters'] : [];
        $options = [
            'sort' => $body['sort'] ?? null,
            'order' => $body['order'] ?? 'ASC',
            'limit' => $body['limit'] ?? 5000,
            'offset' => 0,
        ];

        try {
            $rows = $this->repository->fetchByFilters($entityConfig, $filters, $options);
            $result = $this->exportService->exportRowsToCsv($entity, $rows);

            Response::json([
                'status' => 'ok',
                'entity' => $entity,
                'rows' => $result['rows'],
                'file' => $result['relative_path'],
                'download_url' => '/gshop/api/export/files/' . $result['date'] . '/' . $result['filename'],
            ]);
        } catch (RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore interno durante export', 'details' => $e->getMessage()], 500);
        }
    }

    public function download(array $params): void
    {
        $date = (string) ($params['date'] ?? '');
        $filename = (string) ($params['filename'] ?? '');

        $absolutePath = $this->exportService->resolveExportFile($date, $filename);
        if ($absolutePath === null) {
            Response::json(['error' => 'File export non trovato'], 404);
            return;
        }

        Response::fileDownload($absolutePath, $filename, 'text/csv');
    }
}
