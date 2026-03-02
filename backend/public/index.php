<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/http.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/chat.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/recipes.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = db();
ensure_auth_schema($pdo);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$normalizedPath = rtrim($path, '/');
if ($normalizedPath === '') {
    $normalizedPath = '/';
}

try {
    if (in_array($normalizedPath, ['/api/health', '/health'], true) && $method === 'GET') {
        $dbVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        json_response([
            'ok' => true,
            'service' => 'family-recipes-api',
            'database' => 'connected',
            'db_version' => $dbVersion,
        ]);
        exit;
    }

    // --- Public: Parties ---
    if (in_array($normalizedPath, ['/api/parties', '/parties'], true) && $method === 'GET') {
        json_response(['ok' => true, 'items' => list_parties($pdo)]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/auth/register', '/auth/register'], true) && $method === 'POST') {
        $result = register_user($pdo, read_json_body());
        json_response(['ok' => true] + $result, 201);
        exit;
    }

    if (in_array($normalizedPath, ['/api/auth/login', '/auth/login'], true) && $method === 'POST') {
        $result = login_user($pdo, read_json_body());
        json_response(['ok' => true] + $result);
        exit;
    }

    if (in_array($normalizedPath, ['/api/me', '/me'], true) && $method === 'GET') {
        $user = require_user($pdo);
        json_response([
            'ok' => true,
            'user' => [
                'id' => (int) $user['id'],
                'party_id' => (int) $user['party_id'],
                'party_name' => (string) $user['party_name'],
                'email' => (string) $user['email'],
                'display_name' => (string) $user['display_name'],
                'role' => (string) $user['role'],
            ],
        ]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/chat', '/chat'], true) && $method === 'POST') {
        $body = read_json_body();
        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            throw new InvalidArgumentException('message ist erforderlich.');
        }
        json_response(['ok' => true, 'reply' => chat_reply($message)]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/insights', '/insights'], true) && $method === 'GET') {
        $hour = (int) ($_GET['hour'] ?? date('G'));
        $locale = (string) ($_GET['locale'] ?? 'de-DE');
        json_response(['ok' => true, 'items' => home_ideas($hour, $locale)]);
        exit;
    }

    // --- Auth required: Favorites ---
    if (in_array($normalizedPath, ['/api/recipes/favorites', '/recipes/favorites'], true) && $method === 'GET') {
        $user = require_user($pdo);
        json_response(['ok' => true, 'items' => list_favorites($pdo, $user)]);
        exit;
    }

    // --- Auth required: My Recipes ---
    if (in_array($normalizedPath, ['/api/recipes/mine', '/recipes/mine'], true) && $method === 'GET') {
        $user = require_user($pdo);
        json_response(['ok' => true, 'items' => list_my_recipes($pdo, $user)]);
        exit;
    }

    // --- Public: Recent recipes (by party_id) ---
    if (in_array($normalizedPath, ['/api/recipes/recent', '/recipes/recent'], true) && $method === 'GET') {
        $partyIdParam = (string) ($_GET['party_id'] ?? '');
        if ($partyIdParam !== '' && is_numeric($partyIdParam)) {
            json_response(['ok' => true, 'items' => list_recent_by_party($pdo, (int) $partyIdParam)]);
            exit;
        }
        $user = require_user($pdo);
        json_response(['ok' => true, 'items' => list_recent_recipes($pdo, $user)]);
        exit;
    }

    if (preg_match('#^/(api/)?recipes/(\d+)/favorite$#', $normalizedPath, $m) === 1) {
        $recipeId = (int) $m[2];
        $user = require_user($pdo);
        if ($method === 'POST') {
            add_favorite($pdo, $user, $recipeId);
            json_response(['ok' => true]);
            exit;
        }
        if ($method === 'DELETE') {
            remove_favorite($pdo, $user, $recipeId);
            json_response(['ok' => true]);
            exit;
        }
    }

    // --- Recipes list: public with party_id, or auth ---
    if (in_array($normalizedPath, ['/api/recipes', '/recipes'], true) && $method === 'GET') {
        $partyIdParam = (string) ($_GET['party_id'] ?? '');
        $filters = [
            'q' => (string) ($_GET['q'] ?? ''),
            'ingredients' => (string) ($_GET['ingredients'] ?? ''),
            'tags' => (string) ($_GET['tags'] ?? ''),
            'day_time' => (string) ($_GET['day_time'] ?? ''),
            'max_minutes' => (string) ($_GET['max_minutes'] ?? ''),
            'max_kcal' => (string) ($_GET['max_kcal'] ?? ''),
            'min_protein' => (string) ($_GET['min_protein'] ?? ''),
            'max_carbs' => (string) ($_GET['max_carbs'] ?? ''),
            'max_fat' => (string) ($_GET['max_fat'] ?? ''),
        ];

        if ($partyIdParam !== '' && is_numeric($partyIdParam)) {
            json_response(['ok' => true, 'items' => list_recipes_by_party($pdo, (int) $partyIdParam, $filters)]);
            exit;
        }

        $user = require_user($pdo);
        json_response(['ok' => true, 'items' => list_recipes($pdo, $user, $filters)]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/recipes/search-options', '/recipes/search-options'], true) && $method === 'GET') {
        $user = require_user($pdo);
        json_response(['ok' => true, 'options' => search_options($pdo, $user)]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/recipes', '/recipes'], true) && $method === 'POST') {
        $user = require_user($pdo);
        $recipeId = create_recipe($pdo, $user, read_json_body());
        $recipe = recipe_details($pdo, $recipeId);
        json_response(['ok' => true, 'item' => $recipe], 201);
        exit;
    }

    if (preg_match('#^/(api/)?recipes/(\d+)$#', $normalizedPath, $m) === 1) {
        $recipeId = (int) $m[2];

        if ($method === 'GET') {
            // Public: any non-deleted recipe is viewable
            if (!can_view_recipe_public($pdo, $recipeId)) {
                json_response(['ok' => false, 'error' => 'Nicht gefunden'], 404);
                exit;
            }
            $recipe = recipe_details($pdo, $recipeId);
            if (!is_array($recipe)) {
                json_response(['ok' => false, 'error' => 'Nicht gefunden'], 404);
                exit;
            }
            json_response(['ok' => true, 'item' => $recipe]);
            exit;
        }

        $user = require_user($pdo);

        if ($method === 'PUT' || $method === 'POST') {
            update_recipe($pdo, $user, $recipeId, read_json_body());
            $recipe = recipe_details($pdo, $recipeId);
            json_response(['ok' => true, 'item' => $recipe]);
            exit;
        }

        if ($method === 'DELETE') {
            delete_recipe($pdo, $user, $recipeId);
            json_response(['ok' => true]);
            exit;
        }
    }

    if (preg_match('#^/(api/)?recipes/(\d+)/share-internal$#', $normalizedPath, $m) === 1 && $method === 'POST') {
        $recipeId = (int) $m[2];
        $user = require_user($pdo);
        $body = read_json_body();
        $targetPartyId = (int) ($body['party_id'] ?? 0);
        $permission = (string) ($body['permission'] ?? 'view');
        if ($targetPartyId <= 0) {
            throw new InvalidArgumentException('party_id ist erforderlich.');
        }
        create_internal_share($pdo, $user, $recipeId, $targetPartyId, $permission);
        json_response(['ok' => true]);
        exit;
    }

    if (preg_match('#^/(api/)?recipes/(\d+)/share-public$#', $normalizedPath, $m) === 1 && $method === 'POST') {
        $recipeId = (int) $m[2];
        $user = require_user($pdo);
        $body = read_json_body();
        $expiresAtRaw = trim((string) ($body['expires_at'] ?? ''));
        $expiresAt = $expiresAtRaw !== '' ? $expiresAtRaw : null;
        $token = create_public_link($pdo, $user, $recipeId, $expiresAt);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = $scheme . '://' . $host . '/api/public/' . $token;
        json_response([
            'ok' => true,
            'token' => $token,
            'url' => $url,
        ]);
        exit;
    }

    // Public: Print view
    if (preg_match('#^/(api/)?recipes/(\d+)/print$#', $normalizedPath, $m) === 1 && $method === 'GET') {
        $recipeId = (int) $m[2];
        if (!can_view_recipe_public($pdo, $recipeId)) {
            json_response(['ok' => false, 'error' => 'Nicht gefunden'], 404);
            exit;
        }
        $recipe = recipe_details($pdo, $recipeId);
        if (!is_array($recipe)) {
            json_response(['ok' => false, 'error' => 'Nicht gefunden'], 404);
            exit;
        }
        html_response(recipe_print_html($recipe));
        exit;
    }

    if (preg_match('#^/(api/)?public/([a-f0-9]{64})$#', $normalizedPath, $m) === 1 && $method === 'GET') {
        $token = (string) $m[2];
        $recipe = recipe_by_public_token($pdo, $token);
        if (!is_array($recipe)) {
            json_response(['ok' => false, 'error' => 'Nicht gefunden oder Link abgelaufen'], 404);
            exit;
        }
        json_response(['ok' => true, 'item' => $recipe]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/import/url', '/import/url'], true) && $method === 'POST') {
        $user = require_user($pdo);
        require_once dirname(__DIR__) . '/src/import.php';
        $body = read_json_body();
        $url = trim((string) ($body['url'] ?? ''));
        if ($url === '') {
            throw new InvalidArgumentException('url ist erforderlich.');
        }
        $recipe = import_from_url($url);
        json_response(['ok' => true, 'recipe' => $recipe]);
        exit;
    }

    if (in_array($normalizedPath, ['/api/import/file', '/import/file'], true) && $method === 'POST') {
        $user = require_user($pdo);
        require_once dirname(__DIR__) . '/src/import.php';
        if (!isset($_FILES['file'])) {
            throw new InvalidArgumentException('file ist erforderlich.');
        }
        $recipe = import_from_uploaded_file($_FILES['file']);
        json_response(['ok' => true, 'recipe' => $recipe]);
        exit;
    }

    json_response(['ok' => false, 'error' => 'Not found'], 404);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 403);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
