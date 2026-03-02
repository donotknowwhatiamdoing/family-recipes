<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/http.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/chat.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$normalizedPath = rtrim((string) $path, '/');
if ($normalizedPath === '') {
    $normalizedPath = '/';
}

$healthPaths = ['/api/health', '/health'];
$chatPaths = ['/api/chat', '/chat'];
$insightsPaths = ['/api/insights', '/insights'];

if (in_array($normalizedPath, $healthPaths, true) && $method === 'GET') {
    try {
        $pdo = db();
        $dbVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

        json_response([
            'ok' => true,
            'service' => 'family-recipes-api',
            'database' => 'connected',
            'db_version' => $dbVersion,
        ]);
        exit;
    } catch (Throwable $e) {
        json_response([
            'ok' => false,
            'service' => 'family-recipes-api',
            'database' => 'error',
            'error' => $e->getMessage(),
        ], 500);
        exit;
    }
}

if (in_array($normalizedPath, $chatPaths, true) && $method === 'POST') {
    $body = read_json_body();
    $message = trim((string) ($body['message'] ?? ''));

    if ($message === '') {
        json_response([
            'ok' => false,
            'error' => 'message ist erforderlich',
        ], 422);
        exit;
    }

    $answer = chat_reply($message);
    json_response([
        'ok' => true,
        'reply' => $answer,
    ]);
    exit;
}

if (in_array($normalizedPath, $insightsPaths, true) && $method === 'GET') {
    $hour = (int) ($_GET['hour'] ?? date('G'));
    $locale = (string) ($_GET['locale'] ?? 'de-DE');

    $items = home_ideas($hour, $locale);
    json_response([
        'ok' => true,
        'items' => $items,
    ]);
    exit;
}

json_response([
    'ok' => false,
    'error' => 'Not found',
], 404);
