<?php
require 'src/bootstrap.php';
use GShop\Router;
use GShop\Http\Request;

$router = new Router();
$router->add('GET', '/gshop/api/data/{entity}', function($req, $p) { 
    echo json_encode(['router_match' => true, 'entity' => $p['entity']], JSON_PRETTY_PRINT);
});
$matched = $router->dispatch(new Request('GET', '/gshop/api/data/tipi', [], [], ''));
echo $matched ? 'ROUTER_MATCHED' : 'NOT_MATCHED', PHP_EOL;
?>
