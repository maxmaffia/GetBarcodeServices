<?php

namespace GShop\Http;

class Request
{
    private $method;
    private $path;
    private $query;
    private $headers;
    private $rawBody;
    private $jsonBody;

    public function __construct(string $method, string $path, array $query, array $headers, string $rawBody)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
        $this->jsonBody = null;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = $_GET;

        // Fallback routing quando mod_rewrite non e disponibile:
        // /gshop/index.php?route=/gshop/api/health
        if (isset($query['route']) && is_string($query['route']) && $query['route'] !== '') {
            $path = $query['route'];
            unset($query['route']);
        }

        return new self($method, $path, $query, self::readHeaders(), file_get_contents('php://input') ?: '');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function header(string $name): ?string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $needle) {
                return $value;
            }
        }

        return null;
    }

    public function jsonBody(): ?array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        if (trim($this->rawBody) === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($this->rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        $this->jsonBody = $decoded;
        return $this->jsonBody;
    }

    private static function readHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : [];
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
