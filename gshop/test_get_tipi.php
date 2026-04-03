<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/gshop/api/data/tipi?limit=50&sort=TipoCode&order=ASC';
$_SERVER['HTTP_X_API_KEY'] = '_9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8080';
$_GET = ['limit'=>'50','sort'=>'TipoCode','order'=>'ASC'];

ob_start();
require 'index.php';
$output = ob_get_clean();

echo $output ?: 'EMPTY_OUTPUT';
?>
