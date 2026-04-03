<?php

namespace GShop\Database;

use PDO;
use PDOException;

class SqlServerConnection
{
    private $config;
    private $pdo;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->pdo = null;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = $this->config['host'] ?? 'localhost';
        $port = (int) ($this->config['port'] ?? 1433);
        $database = $this->config['database'] ?? '';
        $authMode = strtolower((string) ($this->config['auth_mode'] ?? 'sql'));
        $trustServerCertificate = !empty($this->config['trust_server_certificate']) ? '1' : '0';

        $connectionMode = strtolower((string) ($this->config['connection'] ?? 'auto'));

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $preferredDrivers = $this->preferredDrivers($connectionMode);
        $lastException = null;

        foreach ($preferredDrivers as $driver) {
            try {
                if ($driver === 'sqlsrv') {
                    $this->pdo = $this->connectWithSqlSrv($host, $port, $database, $authMode, $trustServerCertificate, $options);
                } elseif ($driver === 'odbc') {
                    $this->pdo = $this->connectWithOdbc($host, $port, $database, $authMode, $options);
                }

                if ($this->pdo instanceof PDO) {
                    return $this->pdo;
                }
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        if ($lastException instanceof PDOException) {
            throw new PDOException('Connessione SQL Server fallita: ' . $lastException->getMessage(), (int) $lastException->getCode());
        }

        throw new PDOException('Connessione SQL Server fallita: nessun driver PDO disponibile (sqlsrv/odbc)');

    }

    private function preferredDrivers(string $connectionMode): array
    {
        if ($connectionMode === 'sqlsrv') {
            return ['sqlsrv'];
        }
        if ($connectionMode === 'odbc') {
            return ['odbc'];
        }

        return ['sqlsrv', 'odbc'];
    }

    private function connectWithSqlSrv(string $host, int $port, string $database, string $authMode, string $trustServerCertificate, array $options): PDO
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s,%d;Database=%s;TrustServerCertificate=%s',
            $host,
            $port,
            $database,
            $trustServerCertificate
        );

        if ($authMode === 'integrated') {
            return new PDO($dsn, null, null, $options);
        }

        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        return new PDO($dsn, $username, $password, $options);
    }

    private function connectWithOdbc(string $host, int $port, string $database, string $authMode, array $options): PDO
    {
        $odbcDriver = (string) ($this->config['odbc_driver'] ?? 'ODBC Driver 18 for SQL Server');
        if ($odbcDriver === '') {
            $odbcDriver = 'ODBC Driver 18 for SQL Server';
        }

        $dsn = sprintf(
            'odbc:Driver={%s};Server=%s,%d;Database=%s;Encrypt=no;TrustServerCertificate=Yes',
            $odbcDriver,
            $host,
            $port,
            $database
        );

        if ($authMode === 'integrated') {
            return new PDO($dsn, null, null, $options);
        }

        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        return new PDO($dsn, $username, $password, $options);
    }

    public function testConnection(): array
    {
        try {
            $stmt = $this->pdo()->query('SELECT 1 AS ok');
            $row = $stmt->fetch();
            return ['ok' => isset($row['ok']) && (int) $row['ok'] === 1];
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
