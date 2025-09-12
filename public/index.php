<?php
// Minimal health + router without 3rd-party

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Health check on "/"
if ($path === '/' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;
}

// GraphQL endpoint (POST /graphql)
if ($path === '/graphql') {
    if ($method !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        echo "Method Not Allowed";
        exit;
    }

    // autoload ONLY when needed
    require_once __DIR__ . '/../vendor/autoload.php';

    // call your controller (same class as досега)
    echo App\Controller\GraphQL::handle();
    exit;
}

// Fallback 404
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found";
