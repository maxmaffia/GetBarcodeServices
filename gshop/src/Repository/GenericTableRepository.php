<?php

namespace GShop\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

class GenericTableRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function fetchByFilters(array $entityConfig, array $filters, array $options = []): array
    {
        $table = $entityConfig['table'] ?? '';
        $fields = $entityConfig['fields'] ?? [];

        if (!is_string($table) || $table === '' || !is_array($fields) || empty($fields)) {
            throw new RuntimeException('Configurazione entita non valida');
        }

        $normalizedFields = $this->normalizeFieldList($fields);
        $tableSql = $this->quotedTable($table);

        $whereParts = [];
        $bindings = [];
        $filterable = $normalizedFields;

        foreach ($filters as $field => $value) {
            if (!is_string($field) || !array_key_exists($field, $filterable)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $param = ':f_' . count($bindings);
            $whereParts[] = $this->quoteIdentifier($field) . ' = ' . $param;
            $bindings[$param] = $value;
        }

        $sort = (string) ($options['sort'] ?? ($entityConfig['default_sort'] ?? array_key_first($normalizedFields)));
        if (!array_key_exists($sort, $normalizedFields)) {
            $sort = array_key_first($normalizedFields);
        }

        $order = strtoupper((string) ($options['order'] ?? 'ASC'));
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'ASC';
        }

        $limit = (int) ($options['limit'] ?? 100);
        if ($limit < 1) {
            $limit = 100;
        }
        if ($limit > 20000) {
            $limit = 20000;
        }

        $offset = (int) ($options['offset'] ?? 0);
        if ($offset < 0) {
            $offset = 0;
        }

        $select = implode(', ', array_map([$this, 'quoteIdentifier'], array_keys($normalizedFields)));

        $sql = 'SELECT ' . $select . ' FROM ' . $tableSql;
        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }
        $sql .= ' ORDER BY ' . $this->quoteIdentifier($sort) . ' ' . $order;
        $sql .= ' OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY';

        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $bindings);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        
        // Convert ODBC data to UTF-8
        return count($rows) > 0 ? array_map([$this, 'convertRowToUtf8'], $rows) : [];
    }

    private function convertRowToUtf8(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                // Try to detect if value is Latin-1 and convert to UTF-8
                $result[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function bindValues(PDOStatement $stmt, array $bindings): void
    {
        foreach ($bindings as $param => $value) {
            if (is_int($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($param, (string) $value, PDO::PARAM_STR);
        }
    }

    private function normalizeFieldList(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (!is_string($field) || !$this->isValidIdentifier($field)) {
                continue;
            }
            $result[$field] = true;
        }

        if (empty($result)) {
            throw new RuntimeException('Nessun campo valido per questa entita');
        }

        return $result;
    }

    private function quotedTable(string $table): string
    {
        $parts = explode('.', $table);
        if (count($parts) < 1 || count($parts) > 2) {
            throw new RuntimeException('Nome tabella non valido');
        }

        $quoted = [];
        foreach ($parts as $part) {
            if (!$this->isValidIdentifier($part)) {
                throw new RuntimeException('Nome tabella non valido');
            }
            $quoted[] = $this->quoteIdentifier($part);
        }

        return implode('.', $quoted);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!$this->isValidIdentifier($identifier)) {
            throw new RuntimeException('Identificatore SQL non valido');
        }

        return '[' . $identifier . ']';
    }

    private function isValidIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }
}
