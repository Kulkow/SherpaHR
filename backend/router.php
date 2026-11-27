<?php

declare(strict_types=1);

use ProjectDesk\Http\BaseController;
use ProjectDesk\Http\ControllerFactory;
use ProjectDesk\Http\FrontController;
use ProjectDesk\Http\Router;

require __DIR__ . '/vendor/autoload.php';

$router = new Router();
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = $router->pathFromUri(is_string($uri) ? $uri : '');

if ($router->isApiPath($path)) {
    BaseController::handle(static function () use ($router, $path): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $controller = new FrontController($router, new ControllerFactory());
        $controller->dispatch(is_string($method) ? $method : '', $path);
    });

    return;
}

$root = realpath(__DIR__ . '/../public');
$file = $root ? realpath($root . $path) : false;
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
if ($root && is_file($root . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root . '/index.html');
    return;
}
http_response_code(404);
echo 'Frontend is not built. See docs/development.md.';
