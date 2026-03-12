<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function auth_allowed_party_names(): array
{
    return [
        'Toni & Gudrun',
        'Gabi & Thomas',
        'Terry',
        'CT & Petra',
        'Toto & Maren',
        'Steffi & Dirk',
    ];
}

function canonical_party_name(string $rawName): ?string
{
    $needle = function_exists('mb_strtolower') ? mb_strtolower(trim($rawName)) : strtolower(trim($rawName));
    foreach (auth_allowed_party_names() as $name) {
        $candidate = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
        if ($candidate === $needle) {
            return $name;
        }
    }
    return null;
}

function ensure_auth_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_tokens (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NULL,
          last_used_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_auth_tokens_token_hash (token_hash),
          KEY idx_auth_tokens_user_id (user_id),
          CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function parse_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || $header === '') {
        return '';
    }

    if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $m) !== 1) {
        return '';
    }

    return trim((string) $m[1]);
}

function hash_token(string $token): string
{
    return hash('sha256', $token);
}

function create_auth_token(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash_token($token);

    $stmt = $pdo->prepare(
        'INSERT INTO auth_tokens (user_id, token_hash, expires_at, last_used_at) VALUES (:uid, :hash, NULL, NOW())'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':hash' => $hash,
    ]);

    return $token;
}

function user_from_token(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $hash = hash_token($token);
    $stmt = $pdo->prepare(
        'SELECT u.id, u.party_id, u.email, u.display_name, u.role, u.is_active, p.name AS party_name
         FROM auth_tokens t
         INNER JOIN users u ON u.id = t.user_id
         INNER JOIN parties p ON p.id = u.party_id
         WHERE t.token_hash = :hash
           AND u.is_active = 1
           AND (t.expires_at IS NULL OR t.expires_at >= NOW())
         LIMIT 1'
    );
    $stmt->execute([':hash' => $hash]);
    $user = $stmt->fetch();

    if (!is_array($user)) {
        return null;
    }

    $touch = $pdo->prepare('UPDATE auth_tokens SET last_used_at = NOW() WHERE token_hash = :hash');
    $touch->execute([':hash' => $hash]);

    return $user;
}

function require_user(PDO $pdo): array
{
    $token = parse_bearer_token();
    $user = user_from_token($pdo, $token);
    if (!is_array($user)) {
        throw new RuntimeException('Nicht authentifiziert.');
    }

    return $user;
}

function optional_user(PDO $pdo): ?array
{
    $token = parse_bearer_token();
    if ($token === '') {
        return null;
    }
    $user = user_from_token($pdo, $token);
    return is_array($user) ? $user : null;
}

function register_user(PDO $pdo, array $payload): array
{
    $emailRaw = trim((string) ($payload['email'] ?? ''));
    $email = function_exists('mb_strtolower') ? mb_strtolower($emailRaw) : strtolower($emailRaw);
    $password = (string) ($payload['password'] ?? '');
    $displayName = trim((string) ($payload['display_name'] ?? ''));
    $partyNameInput = trim((string) ($payload['party_name'] ?? ''));
    $partyName = canonical_party_name($partyNameInput);

    if ($email === '' || $password === '' || $displayName === '' || $partyNameInput === '') {
        throw new InvalidArgumentException('email, password, display_name und party_name sind erforderlich.');
    }
    if ($partyName === null) {
        throw new InvalidArgumentException('Bitte eine der 6 verfügbaren Familien auswählen.');
    }

    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Passwort muss mindestens 8 Zeichen lang sein.');
    }

    $existingStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existingStmt->execute([':email' => $email]);
    if ($existingStmt->fetch()) {
        throw new InvalidArgumentException('E-Mail ist bereits registriert.');
    }

    $pdo->beginTransaction();
    try {
        $partyStmt = $pdo->prepare('SELECT id FROM parties WHERE name = :name LIMIT 1');
        $partyStmt->execute([':name' => $partyName]);
        $party = $partyStmt->fetch();

        if (is_array($party)) {
            $partyId = (int) $party['id'];
        } else {
            $insertParty = $pdo->prepare('INSERT INTO parties (name) VALUES (:name)');
            $insertParty->execute([':name' => $partyName]);
            $partyId = (int) $pdo->lastInsertId();
        }

        $role = 'editor';
        $partyUserCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE party_id = :partyId');
        $partyUserCountStmt->execute([':partyId' => $partyId]);
        $count = (int) $partyUserCountStmt->fetchColumn();
        if ($count === 0) {
            $role = 'owner';
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insertUser = $pdo->prepare(
            'INSERT INTO users (party_id, email, password_hash, display_name, role) VALUES (:partyId, :email, :hash, :displayName, :role)'
        );
        $insertUser->execute([
            ':partyId' => $partyId,
            ':email' => $email,
            ':hash' => $passwordHash,
            ':displayName' => $displayName,
            ':role' => $role,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $token = create_auth_token($pdo, $userId);
        $pdo->commit();

        return [
            'token' => $token,
            'user' => [
                'id' => $userId,
                'party_id' => $partyId,
                'party_name' => $partyName,
                'email' => $email,
                'display_name' => $displayName,
                'role' => $role,
            ],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function login_user(PDO $pdo, array $payload): array
{
    $emailRaw = trim((string) ($payload['email'] ?? ''));
    $email = function_exists('mb_strtolower') ? mb_strtolower($emailRaw) : strtolower($emailRaw);
    $password = (string) ($payload['password'] ?? '');

    if ($email === '' || $password === '') {
        throw new InvalidArgumentException('email und password sind erforderlich.');
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.party_id, u.email, u.password_hash, u.display_name, u.role, u.is_active, p.name AS party_name
         FROM users u
         INNER JOIN parties p ON p.id = u.party_id
         WHERE u.email = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!is_array($user) || (int) $user['is_active'] !== 1) {
        throw new InvalidArgumentException('Ungültige Zugangsdaten.');
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        throw new InvalidArgumentException('Ungültige Zugangsdaten.');
    }

    $token = create_auth_token($pdo, (int) $user['id']);

    return [
        'token' => $token,
        'user' => [
            'id' => (int) $user['id'],
            'party_id' => (int) $user['party_id'],
            'party_name' => (string) $user['party_name'],
            'email' => (string) $user['email'],
            'display_name' => (string) $user['display_name'],
            'role' => (string) $user['role'],
        ],
    ];
}
