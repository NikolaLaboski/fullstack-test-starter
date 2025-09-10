<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // GraphQL endpoint
    $r->post('/graphql', fn($vars) => App\Controller\GraphQL::handle());
});

// normalize path (decode + trim control chars and trailing slashes except root)
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = rtrim($path, " \t\n\r\0\x0B"); // strip trailing whitespace/control
if ($path === '') { 
    $path = '/'; 
}

$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $path);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ✅ Health check: Railway will ping "/" → return OK
        if ($path === '/') {
            header('Content-Type: text/plain; charset=utf-8');
            echo "OK";
            break;
        }
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found";
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        http_response_code(405);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Method Not Allowed";
        break;

    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        echo $handler($vars);
        break;
}
