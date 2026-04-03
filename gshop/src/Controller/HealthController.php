<?php

namespace GShop\Controller;

use GShop\Database\SqlServerConnection;
use GShop\Http\Response;

class HealthController
{
    private $db;

    public function __construct(SqlServerConnection $db)
    {
        $this->db = $db;
    }

    public function check(): void
    {
        $dbStatus = $this->db->testConnection();
        $statusCode = !empty($dbStatus['ok']) ? 200 : 500;

        Response::json([
            'service' => 'gshop-api',
            'date' => date('c'),
            'database' => $dbStatus,
        ], $statusCode);
    }
}
