<?php

namespace GShop\Dto;

class Registry
{
    private $entities;

    public function __construct()
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'entities.php';
        $loaded = is_file($path) ? require $path : [];
        $this->entities = is_array($loaded) ? $loaded : [];
    }

    public function get(string $entity): ?array
    {
        $key = strtolower(trim($entity));
        if ($key === '' || !isset($this->entities[$key]) || !is_array($this->entities[$key])) {
            return null;
        }

        return $this->entities[$key];
    }

    public function all(): array
    {
        return $this->entities;
    }
}
