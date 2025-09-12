<?php
// Minimal router + health + GraphQL entry

// 1) Health on "/"
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path    = rawurldecode($rawPath);

// Strip control chars & spaces
$path = rtrim($path, " \t\n\r\0\x0B");
// Remove trailing slashes except root
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// CORS for frontend (add your domain(s) here)
$allowed = ['https://scweb-shop.netlify.app', 'http://localhost:5173'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Apollo-Require-Preflight');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($path === '/' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;
}

// 2) GraphQL route: accepts "/graphql" AND "/graphql/"
if ($path === '/graphql') {
    if ($method === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "GraphQL endpoint ready. Use POST with JSON body.";
        exit;
    }
    if ($method !== 'POST') {
        header('Allow: POST, GET, OPTIONS');
        http_response_code(405);
        echo "Method Not Allowed";
        exit;
    }

    // Load autoloader only when we actually hit GraphQL
    require_once __DIR__ . '/../vendor/autoload.php';

    // Call your existing controller
    echo App\Controller\GraphQL::handle();
    exit;
}

// 3) Fallback 404 (also prints the normalized path for debug)
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not Found: {$path}";
