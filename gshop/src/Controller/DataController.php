<?php

namespace GShop\Controller;

use GShop\Dto\Registry;
use GShop\Http\Request;
use GShop\Http\Response;
use GShop\Repository\GenericTableRepository;
use RuntimeException;

class DataController
{
    private $registry;
    private $repository;

    public function __construct(Registry $registry, GenericTableRepository $repository)
    {
        $this->registry = $registry;
        $this->repository = $repository;
    }

    public function list(Request $request, array $params): void
    {
        $entity = strtolower((string) ($params['entity'] ?? ''));
        $entityConfig = $this->registry->get($entity);

        if ($entityConfig === null) {
            Response::json(['error' => 'Entita non registrata', 'entity' => $entity], 404);
            return;
        }

        $query = $request->query();
        $options = [
            'sort' => $query['sort'] ?? null,
            'order' => $query['order'] ?? 'ASC',
            'limit' => $query['limit'] ?? 100,
            'offset' => $query['offset'] ?? 0,
        ];

        unset($query['sort'], $query['order'], $query['limit'], $query['offset']);

        try {
            $rows = $this->repository->fetchByFilters($entityConfig, $query, $options);
        } catch (RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 400);
            return;
        } catch (\Throwable $e) {
            Response::json(['error' => 'Errore interno durante la query', 'details' => $e->getMessage()], 500);
            return;
        }

        Response::json([
            'entity' => $entity,
            'count' => count($rows),
            'data' => $rows,
        ]);
    }
}
