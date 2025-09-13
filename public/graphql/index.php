<?php
// graphql/index.php
declare(strict_types=1);

use GraphQL\GraphQL;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;

ini_set('display_errors', '0');
error_reporting(E_ALL);

/**
 * --- CORS ---

 */
$allowedOrigins = [
    'http://localhost:5173',
    'https://scweb-shop.netlify.app',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Apollo-Require-Preflight');
header('Access-Control-Max-Age: 86400'); // кеширај preflight 24h

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

/**
 * --- Autoload ---
 */
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

/**
 * --- Schema ---
 */
$schemaFile = __DIR__ . '/schema.php';
$schema = require $schemaFile;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';


if ($method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "GraphQL endpoint ready. Use POST with JSON body to query.\n";
    exit;
}


if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

/**
 * --- Read JSON input ---
 */
$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);


if ($raw !== '' && $input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid JSON body: ' . json_last_error_msg()]);
    exit;
}

$query     = $input['query']         ?? '';
$variables = $input['variables']     ?? null;
$operation = $input['operationName'] ?? null;

if (!is_string($query) || $query === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing or invalid GraphQL query']);
    exit;
}


if (!($schema instanceof Schema)) {
    error_log('graphql/index.php: $schema is not instance of GraphQL\\Type\\Schema');
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode(['errors' => [['message' => 'Internal server error']]]);
    exit;
}

// Debug  ENV (APP_DEBUG=1 или DEBUG=1)
$debugEnv = getenv('APP_DEBUG') ?: getenv('DEBUG');
$debugOn  = is_string($debugEnv) && ($debugEnv === '1' || strtolower($debugEnv) === 'true');

try {
    $result = GraphQL::executeQuery(
        $schema,
        $query,
        null,
        null,
        is_array($variables) ? $variables : null,
        is_string($operation) ? $operation : null
    );

    $debugFlags = $debugOn
        ? (DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE)
        : DebugFlag::NONE;

    $output = $result->toArray($debugFlags);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($output, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    
    error_log($e);

    $payload = ['errors' => [['message' => 'Internal server error']]];
    if ($debugOn) {
        
        $payload['errors'][0]['message'] = $e->getMessage();
    }

    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
