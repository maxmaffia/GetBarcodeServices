<?php
return [
    'api_key' => '_9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J',
    'sqlserver' => [
        'host' => 'localhost',
        'port' => 1433,
        'database' => 'ANGOLO',
        'username' => 'sa',
        'password' => 'afragola2006',
        // Valori possibili: "sql" oppure "integrated".
        'auth_mode' => 'sql',
        // Valori possibili: "auto", "sqlsrv", "odbc".
        'connection' => 'auto',
        'odbc_driver' => 'ODBC Driver 18 for SQL Server',
        'trust_server_certificate' => true,
    ],
    'export' => [
        'base_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'exports',
        // Cartella e nome file usati dal servizio schedulato masterdata.
        'masterdata_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'scheduled',
        'masterdata_filename' => 'masterdata.csv',
    ],
];
