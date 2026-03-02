<?php

declare(strict_types=1);

function slugify(string $text): string
{
    $text = trim($text);
    $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    $text = preg_replace('/[^\pL\pN]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    if ($text === '') {
        return 'tag';
    }

    return $text;
}

function escape_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function can_view_recipe_public(PDO $pdo, int $recipeId): bool
{
    $stmt = $pdo->prepare(
        'SELECT r.id FROM recipes r WHERE r.id = :recipeId AND r.is_deleted = 0 LIMIT 1'
    );
    $stmt->execute([':recipeId' => $recipeId]);
    return (bool) $stmt->fetch();
}

function can_view_recipe(PDO $pdo, array $user, int $recipeId): bool
{
    $stmt = $pdo->prepare(
        'SELECT r.id
         FROM recipes r
         LEFT JOIN recipe_internal_shares s ON s.recipe_id = r.id AND s.shared_with_party_id = :partyIdShare
         WHERE r.id = :recipeId
           AND r.is_deleted = 0
           AND (r.owner_party_id = :partyIdOwner OR s.id IS NOT NULL)
         LIMIT 1'
    );
    $stmt->execute([
        ':partyIdShare' => (int) $user['party_id'],
        ':partyIdOwner' => (int) $user['party_id'],
        ':recipeId' => $recipeId,
    ]);

    return (bool) $stmt->fetch();
}

function can_edit_recipe(PDO $pdo, array $user, int $recipeId): bool
{
    $stmt = $pdo->prepare(
        'SELECT r.id
         FROM recipes r
         LEFT JOIN recipe_internal_shares s ON s.recipe_id = r.id AND s.shared_with_party_id = :partyIdShare
         WHERE r.id = :recipeId
           AND r.is_deleted = 0
           AND (
             r.owner_party_id = :partyIdOwner
             OR (s.id IS NOT NULL AND s.permission = "edit")
           )
         LIMIT 1'
    );
    $stmt->execute([
        ':partyIdShare' => (int) $user['party_id'],
        ':partyIdOwner' => (int) $user['party_id'],
        ':recipeId' => $recipeId,
    ]);

    return (bool) $stmt->fetch();
}

function normalize_day_time(?string $dayTime): ?string
{
    if (!is_string($dayTime)) {
        return null;
    }

    $value = trim($dayTime);
    if (in_array($value, ['fruehstueck', 'mittag', 'abend', 'snack', 'suesses', 'gebaeck', 'getraenk', 'beilage'], true)) {
        return $value;
    }

    return null;
}

function split_csv_filter(mixed $value): array
{
    if (!is_string($value)) {
        return [];
    }

    $items = array_map('trim', explode(',', $value));
    $items = array_values(array_filter($items, static fn(string $x): bool => $x !== ''));
    return array_slice($items, 0, 12);
}

function list_parties(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT p.id, p.name,
                (SELECT COUNT(*) FROM recipes r WHERE r.owner_party_id = p.id AND r.is_deleted = 0) AS recipe_count
         FROM parties p
         ORDER BY p.name ASC'
    );
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'recipe_count' => (int) $row['recipe_count'],
    ], $rows);
}

function list_my_recipes(PDO $pdo, array $user): array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.title, r.description, r.day_time, r.prep_minutes, r.cook_minutes,
                r.kcal_per_serving, r.visibility, r.updated_at,
                (SELECT COUNT(*) FROM recipe_favorites f WHERE f.recipe_id = r.id) AS favorite_count
         FROM recipes r
         WHERE r.owner_party_id = :partyId
           AND r.is_deleted = 0
         ORDER BY r.updated_at DESC
         LIMIT 200'
    );
    $stmt->execute([':partyId' => (int) $user['party_id']]);
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'day_time' => (string) ($row['day_time'] ?? ''),
        'prep_minutes' => $row['prep_minutes'] !== null ? (int) $row['prep_minutes'] : null,
        'cook_minutes' => $row['cook_minutes'] !== null ? (int) $row['cook_minutes'] : null,
        'kcal_per_serving' => $row['kcal_per_serving'] !== null ? (float) $row['kcal_per_serving'] : null,
        'visibility' => (string) $row['visibility'],
        'updated_at' => (string) $row['updated_at'],
        'favorite_count' => (int) $row['favorite_count'],
    ], $rows);
}

function list_recipes_by_party(PDO $pdo, int $partyId, array $filters = []): array
{
    $query = trim((string) ($filters['q'] ?? ''));
    $dayTime = normalize_day_time($filters['day_time'] ?? null);

    $params = [':partyId' => $partyId];
    $sql = 'SELECT DISTINCT r.id, r.title, r.description, r.day_time, r.kcal_per_serving, r.protein_g_per_serving, r.carbs_g_per_serving, r.fat_g_per_serving, r.prep_minutes, r.cook_minutes, r.visibility, r.updated_at, r.owner_party_id, p.name AS owner_party_name
            FROM recipes r
            INNER JOIN parties p ON p.id = r.owner_party_id
            WHERE r.is_deleted = 0
              AND r.owner_party_id = :partyId';

    if ($query !== '') {
        $sql .= ' AND (r.title LIKE :q OR r.description LIKE :qDesc
            OR EXISTS (SELECT 1 FROM recipe_ingredients ri WHERE ri.recipe_id = r.id AND ri.ingredient_name LIKE :qIng)
            OR EXISTS (SELECT 1 FROM recipe_tags rt INNER JOIN tags t ON t.id = rt.tag_id WHERE rt.recipe_id = r.id AND t.label LIKE :qTag))';
        $likeQ = '%' . $query . '%';
        $params[':q'] = $likeQ;
        $params[':qDesc'] = $likeQ;
        $params[':qIng'] = $likeQ;
        $params[':qTag'] = $likeQ;
    }

    if ($dayTime !== null) {
        $sql .= ' AND r.day_time = :dayTime';
        $params[':dayTime'] = $dayTime;
    }

    $sql .= ' ORDER BY r.updated_at DESC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'day_time' => (string) ($row['day_time'] ?? ''),
        'kcal_per_serving' => $row['kcal_per_serving'] !== null ? (float) $row['kcal_per_serving'] : null,
        'protein_g_per_serving' => $row['protein_g_per_serving'] !== null ? (float) $row['protein_g_per_serving'] : null,
        'carbs_g_per_serving' => $row['carbs_g_per_serving'] !== null ? (float) $row['carbs_g_per_serving'] : null,
        'fat_g_per_serving' => $row['fat_g_per_serving'] !== null ? (float) $row['fat_g_per_serving'] : null,
        'prep_minutes' => $row['prep_minutes'] !== null ? (int) $row['prep_minutes'] : null,
        'cook_minutes' => $row['cook_minutes'] !== null ? (int) $row['cook_minutes'] : null,
        'visibility' => (string) $row['visibility'],
        'updated_at' => (string) $row['updated_at'],
        'owner_party_name' => (string) $row['owner_party_name'],
        'is_owner' => false,
    ], $rows);
}

function list_recent_by_party(PDO $pdo, int $partyId, int $limit = 8): array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.title, r.description, r.day_time, r.prep_minutes, r.cook_minutes,
                r.kcal_per_serving, r.image_url, r.created_at, p.name AS owner_party_name
         FROM recipes r
         INNER JOIN parties p ON p.id = r.owner_party_id
         WHERE r.is_deleted = 0
           AND r.owner_party_id = :partyId
         ORDER BY r.created_at DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':partyId', $partyId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function list_recipes(PDO $pdo, array $user, array $filters = []): array
{
    $query = trim((string) ($filters['q'] ?? ''));
    $ingredients = split_csv_filter($filters['ingredients'] ?? '');
    $tags = split_csv_filter($filters['tags'] ?? '');
    $dayTime = normalize_day_time($filters['day_time'] ?? null);
    $maxMinutesRaw = (string) ($filters['max_minutes'] ?? '');
    $maxMinutes = is_numeric($maxMinutesRaw) ? (int) $maxMinutesRaw : null;
    $maxKcalRaw = (string) ($filters['max_kcal'] ?? '');
    $maxKcal = is_numeric($maxKcalRaw) ? (float) $maxKcalRaw : null;
    $minProteinRaw = (string) ($filters['min_protein'] ?? '');
    $minProtein = is_numeric($minProteinRaw) ? (float) $minProteinRaw : null;
    $maxCarbsRaw = (string) ($filters['max_carbs'] ?? '');
    $maxCarbs = is_numeric($maxCarbsRaw) ? (float) $maxCarbsRaw : null;
    $maxFatRaw = (string) ($filters['max_fat'] ?? '');
    $maxFat = is_numeric($maxFatRaw) ? (float) $maxFatRaw : null;

    $params = [
        ':partyIdShare' => (int) $user['party_id'],
        ':partyIdOwner' => (int) $user['party_id'],
    ];

    $sql = 'SELECT DISTINCT r.id, r.title, r.description, r.day_time, r.kcal_per_serving, r.protein_g_per_serving, r.carbs_g_per_serving, r.fat_g_per_serving, r.prep_minutes, r.cook_minutes, r.visibility, r.updated_at, r.owner_party_id, p.name AS owner_party_name
            FROM recipes r
            INNER JOIN parties p ON p.id = r.owner_party_id
            LEFT JOIN recipe_internal_shares s ON s.recipe_id = r.id AND s.shared_with_party_id = :partyIdShare
            WHERE r.is_deleted = 0
              AND (r.owner_party_id = :partyIdOwner OR s.id IS NOT NULL)';

    if ($query !== '') {
        $sql .= ' AND (r.title LIKE :q OR r.description LIKE :qDesc
            OR EXISTS (SELECT 1 FROM recipe_ingredients ri WHERE ri.recipe_id = r.id AND ri.ingredient_name LIKE :qIng)
            OR EXISTS (SELECT 1 FROM recipe_tags rt INNER JOIN tags t ON t.id = rt.tag_id WHERE rt.recipe_id = r.id AND t.label LIKE :qTag))';
        $likeQ = '%' . $query . '%';
        $params[':q'] = $likeQ;
        $params[':qDesc'] = $likeQ;
        $params[':qIng'] = $likeQ;
        $params[':qTag'] = $likeQ;
    }

    if ($dayTime !== null) {
        $sql .= ' AND r.day_time = :dayTime';
        $params[':dayTime'] = $dayTime;
    }

    if ($maxMinutes !== null && $maxMinutes > 0) {
        $sql .= ' AND (COALESCE(r.prep_minutes, 0) + COALESCE(r.cook_minutes, 0)) <= :maxMinutes';
        $params[':maxMinutes'] = $maxMinutes;
    }

    if ($maxKcal !== null && $maxKcal > 0) {
        $sql .= ' AND r.kcal_per_serving IS NOT NULL AND r.kcal_per_serving <= :maxKcal';
        $params[':maxKcal'] = $maxKcal;
    }

    if ($minProtein !== null && $minProtein >= 0) {
        $sql .= ' AND r.protein_g_per_serving IS NOT NULL AND r.protein_g_per_serving >= :minProtein';
        $params[':minProtein'] = $minProtein;
    }

    if ($maxCarbs !== null && $maxCarbs >= 0) {
        $sql .= ' AND r.carbs_g_per_serving IS NOT NULL AND r.carbs_g_per_serving <= :maxCarbs';
        $params[':maxCarbs'] = $maxCarbs;
    }

    if ($maxFat !== null && $maxFat >= 0) {
        $sql .= ' AND r.fat_g_per_serving IS NOT NULL AND r.fat_g_per_serving <= :maxFat';
        $params[':maxFat'] = $maxFat;
    }

    foreach ($ingredients as $idx => $ingredient) {
        $nameKey = ':ingredient' . $idx;
        $sql .= ' AND EXISTS (
            SELECT 1 FROM recipe_ingredients ri
            WHERE ri.recipe_id = r.id
              AND ri.ingredient_name LIKE ' . $nameKey . '
        )';
        $params[$nameKey] = '%' . $ingredient . '%';
    }

    foreach ($tags as $idx => $tag) {
        $labelKey = ':tagLabel' . $idx;
        $slugKey = ':tagSlug' . $idx;
        $sql .= ' AND EXISTS (
            SELECT 1
            FROM recipe_tags rt
            INNER JOIN tags t ON t.id = rt.tag_id
            WHERE rt.recipe_id = r.id
              AND (t.label LIKE ' . $labelKey . ' OR t.slug = ' . $slugKey . ')
        )';
        $params[$labelKey] = '%' . $tag . '%';
        $params[$slugKey] = slugify($tag);
    }

    $sql .= ' ORDER BY r.updated_at DESC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function (array $row) use ($user): array {
        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'day_time' => (string) ($row['day_time'] ?? ''),
            'kcal_per_serving' => $row['kcal_per_serving'] !== null ? (float) $row['kcal_per_serving'] : null,
            'protein_g_per_serving' => $row['protein_g_per_serving'] !== null ? (float) $row['protein_g_per_serving'] : null,
            'carbs_g_per_serving' => $row['carbs_g_per_serving'] !== null ? (float) $row['carbs_g_per_serving'] : null,
            'fat_g_per_serving' => $row['fat_g_per_serving'] !== null ? (float) $row['fat_g_per_serving'] : null,
            'prep_minutes' => $row['prep_minutes'] !== null ? (int) $row['prep_minutes'] : null,
            'cook_minutes' => $row['cook_minutes'] !== null ? (int) $row['cook_minutes'] : null,
            'visibility' => (string) $row['visibility'],
            'updated_at' => (string) $row['updated_at'],
            'owner_party_name' => (string) $row['owner_party_name'],
            'is_owner' => (int) $row['owner_party_id'] === (int) $user['party_id'],
        ];
    }, $rows);
}

function recipe_details(PDO $pdo, int $recipeId): ?array
{
    $baseStmt = $pdo->prepare(
        'SELECT r.id, r.owner_party_id, r.created_by_user_id, r.title, r.description, r.day_time, r.kcal_per_serving, r.protein_g_per_serving, r.carbs_g_per_serving, r.fat_g_per_serving, r.servings, r.prep_minutes, r.cook_minutes,
                r.image_url, r.visibility, r.created_at, r.updated_at, p.name AS owner_party_name
         FROM recipes r
         INNER JOIN parties p ON p.id = r.owner_party_id
         WHERE r.id = :id AND r.is_deleted = 0
         LIMIT 1'
    );
    $baseStmt->execute([':id' => $recipeId]);
    $base = $baseStmt->fetch();
    if (!is_array($base)) {
        return null;
    }

    $ingStmt = $pdo->prepare(
        'SELECT ingredient_name, quantity, unit, note
         FROM recipe_ingredients
         WHERE recipe_id = :id
         ORDER BY line_no ASC'
    );
    $ingStmt->execute([':id' => $recipeId]);
    $ingredients = $ingStmt->fetchAll() ?: [];

    $stepsStmt = $pdo->prepare(
        'SELECT step_no, instruction, image_url
         FROM recipe_steps
         WHERE recipe_id = :id
         ORDER BY step_no ASC'
    );
    $stepsStmt->execute([':id' => $recipeId]);
    $steps = $stepsStmt->fetchAll() ?: [];

    $tagsStmt = $pdo->prepare(
        'SELECT t.label
         FROM recipe_tags rt
         INNER JOIN tags t ON t.id = rt.tag_id
         WHERE rt.recipe_id = :id
         ORDER BY t.label ASC'
    );
    $tagsStmt->execute([':id' => $recipeId]);
    $tagsRaw = $tagsStmt->fetchAll() ?: [];
    $tags = array_map(static fn(array $t): string => (string) $t['label'], $tagsRaw);

    return [
        'id' => (int) $base['id'],
        'owner_party_id' => (int) $base['owner_party_id'],
        'owner_party_name' => (string) $base['owner_party_name'],
        'created_by_user_id' => (int) $base['created_by_user_id'],
        'title' => (string) $base['title'],
        'description' => (string) ($base['description'] ?? ''),
        'day_time' => (string) ($base['day_time'] ?? ''),
        'kcal_per_serving' => $base['kcal_per_serving'] !== null ? (float) $base['kcal_per_serving'] : null,
        'protein_g_per_serving' => $base['protein_g_per_serving'] !== null ? (float) $base['protein_g_per_serving'] : null,
        'carbs_g_per_serving' => $base['carbs_g_per_serving'] !== null ? (float) $base['carbs_g_per_serving'] : null,
        'fat_g_per_serving' => $base['fat_g_per_serving'] !== null ? (float) $base['fat_g_per_serving'] : null,
        'servings' => $base['servings'] !== null ? (int) $base['servings'] : null,
        'prep_minutes' => $base['prep_minutes'] !== null ? (int) $base['prep_minutes'] : null,
        'cook_minutes' => $base['cook_minutes'] !== null ? (int) $base['cook_minutes'] : null,
        'image_url' => (string) ($base['image_url'] ?? ''),
        'visibility' => (string) $base['visibility'],
        'created_at' => (string) $base['created_at'],
        'updated_at' => (string) $base['updated_at'],
        'ingredients' => $ingredients,
        'steps' => $steps,
        'tags' => $tags,
    ];
}

function sync_recipe_children(PDO $pdo, int $recipeId, array $payload): void
{
    $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = :id')->execute([':id' => $recipeId]);
    $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = :id')->execute([':id' => $recipeId]);
    $pdo->prepare('DELETE FROM recipe_tags WHERE recipe_id = :id')->execute([':id' => $recipeId]);

    $ingredients = is_array($payload['ingredients'] ?? null) ? $payload['ingredients'] : [];
    $line = 1;
    $insIng = $pdo->prepare(
        'INSERT INTO recipe_ingredients (recipe_id, line_no, ingredient_name, quantity, unit, note)
         VALUES (:recipeId, :lineNo, :name, :quantity, :unit, :note)'
    );
    foreach ($ingredients as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['ingredient_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $quantity = $item['quantity'] ?? null;
        $quantity = is_numeric($quantity) ? (float) $quantity : null;
        $unit = trim((string) ($item['unit'] ?? ''));
        $note = trim((string) ($item['note'] ?? ''));
        $insIng->execute([
            ':recipeId' => $recipeId,
            ':lineNo' => $line++,
            ':name' => $name,
            ':quantity' => $quantity,
            ':unit' => $unit !== '' ? $unit : null,
            ':note' => $note !== '' ? $note : null,
        ]);
    }

    $steps = is_array($payload['steps'] ?? null) ? $payload['steps'] : [];
    $stepNo = 1;
    $insStep = $pdo->prepare(
        'INSERT INTO recipe_steps (recipe_id, step_no, instruction, image_url)
         VALUES (:recipeId, :stepNo, :instruction, :imageUrl)'
    );
    foreach ($steps as $item) {
        if (!is_array($item)) {
            continue;
        }
        $instruction = trim((string) ($item['instruction'] ?? ''));
        if ($instruction === '') {
            continue;
        }
        $imageUrl = trim((string) ($item['image_url'] ?? ''));
        $insStep->execute([
            ':recipeId' => $recipeId,
            ':stepNo' => $stepNo++,
            ':instruction' => $instruction,
            ':imageUrl' => $imageUrl !== '' ? $imageUrl : null,
        ]);
    }

    $tags = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];
    $findTag = $pdo->prepare('SELECT id FROM tags WHERE slug = :slug LIMIT 1');
    $insertTag = $pdo->prepare('INSERT INTO tags (slug, label) VALUES (:slug, :label)');
    $attachTag = $pdo->prepare('INSERT INTO recipe_tags (recipe_id, tag_id) VALUES (:recipeId, :tagId)');
    foreach ($tags as $rawTag) {
        $label = trim((string) $rawTag);
        if ($label === '') {
            continue;
        }
        $slug = slugify($label);
        $findTag->execute([':slug' => $slug]);
        $tag = $findTag->fetch();
        if (is_array($tag)) {
            $tagId = (int) $tag['id'];
        } else {
            $insertTag->execute([
                ':slug' => $slug,
                ':label' => $label,
            ]);
            $tagId = (int) $pdo->lastInsertId();
        }
        $attachTag->execute([
            ':recipeId' => $recipeId,
            ':tagId' => $tagId,
        ]);
    }
}

function create_recipe(PDO $pdo, array $user, array $payload): int
{
    $title = trim((string) ($payload['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title ist erforderlich.');
    }

    $description = trim((string) ($payload['description'] ?? ''));
    $dayTime = normalize_day_time((string) ($payload['day_time'] ?? ''));
    $kcal = $payload['kcal_per_serving'] ?? null;
    $protein = $payload['protein_g_per_serving'] ?? null;
    $carbs = $payload['carbs_g_per_serving'] ?? null;
    $fat = $payload['fat_g_per_serving'] ?? null;
    $servings = $payload['servings'] ?? null;
    $prep = $payload['prep_minutes'] ?? null;
    $cook = $payload['cook_minutes'] ?? null;
    $image = trim((string) ($payload['image_url'] ?? ''));
    $visibility = (string) ($payload['visibility'] ?? 'private');
    if (!in_array($visibility, ['private', 'internal', 'public_link'], true)) {
        $visibility = 'private';
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO recipes (owner_party_id, created_by_user_id, title, description, day_time, kcal_per_serving, protein_g_per_serving, carbs_g_per_serving, fat_g_per_serving, servings, prep_minutes, cook_minutes, image_url, visibility)
             VALUES (:partyId, :userId, :title, :description, :dayTime, :kcal, :protein, :carbs, :fat, :servings, :prep, :cook, :image, :visibility)'
        );
        $stmt->execute([
            ':partyId' => (int) $user['party_id'],
            ':userId' => (int) $user['id'],
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':dayTime' => $dayTime,
            ':kcal' => is_numeric($kcal) ? (float) $kcal : null,
            ':protein' => is_numeric($protein) ? (float) $protein : null,
            ':carbs' => is_numeric($carbs) ? (float) $carbs : null,
            ':fat' => is_numeric($fat) ? (float) $fat : null,
            ':servings' => is_numeric($servings) ? (int) $servings : null,
            ':prep' => is_numeric($prep) ? (int) $prep : null,
            ':cook' => is_numeric($cook) ? (int) $cook : null,
            ':image' => $image !== '' ? $image : null,
            ':visibility' => $visibility,
        ]);
        $recipeId = (int) $pdo->lastInsertId();

        sync_recipe_children($pdo, $recipeId, $payload);

        $pdo->commit();
        return $recipeId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function update_recipe(PDO $pdo, array $user, int $recipeId, array $payload): void
{
    if (!can_edit_recipe($pdo, $user, $recipeId)) {
        throw new RuntimeException('Keine Berechtigung zum Bearbeiten.');
    }

    $title = trim((string) ($payload['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title ist erforderlich.');
    }

    $description = trim((string) ($payload['description'] ?? ''));
    $dayTime = normalize_day_time((string) ($payload['day_time'] ?? ''));
    $kcal = $payload['kcal_per_serving'] ?? null;
    $protein = $payload['protein_g_per_serving'] ?? null;
    $carbs = $payload['carbs_g_per_serving'] ?? null;
    $fat = $payload['fat_g_per_serving'] ?? null;
    $servings = $payload['servings'] ?? null;
    $prep = $payload['prep_minutes'] ?? null;
    $cook = $payload['cook_minutes'] ?? null;
    $image = trim((string) ($payload['image_url'] ?? ''));
    $visibility = (string) ($payload['visibility'] ?? 'private');
    if (!in_array($visibility, ['private', 'internal', 'public_link'], true)) {
        $visibility = 'private';
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE recipes
             SET title = :title,
                 description = :description,
                 day_time = :dayTime,
                 kcal_per_serving = :kcal,
                 protein_g_per_serving = :protein,
                 carbs_g_per_serving = :carbs,
                 fat_g_per_serving = :fat,
                 servings = :servings,
                 prep_minutes = :prep,
                 cook_minutes = :cook,
                 image_url = :image,
                 visibility = :visibility
             WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':dayTime' => $dayTime,
            ':kcal' => is_numeric($kcal) ? (float) $kcal : null,
            ':protein' => is_numeric($protein) ? (float) $protein : null,
            ':carbs' => is_numeric($carbs) ? (float) $carbs : null,
            ':fat' => is_numeric($fat) ? (float) $fat : null,
            ':servings' => is_numeric($servings) ? (int) $servings : null,
            ':prep' => is_numeric($prep) ? (int) $prep : null,
            ':cook' => is_numeric($cook) ? (int) $cook : null,
            ':image' => $image !== '' ? $image : null,
            ':visibility' => $visibility,
            ':id' => $recipeId,
        ]);

        sync_recipe_children($pdo, $recipeId, $payload);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function delete_recipe(PDO $pdo, array $user, int $recipeId): void
{
    if (!can_edit_recipe($pdo, $user, $recipeId)) {
        throw new RuntimeException('Keine Berechtigung zum Löschen.');
    }

    $stmt = $pdo->prepare('UPDATE recipes SET is_deleted = 1 WHERE id = :id');
    $stmt->execute([':id' => $recipeId]);
}

function create_internal_share(PDO $pdo, array $user, int $recipeId, int $targetPartyId, string $permission): void
{
    if (!can_edit_recipe($pdo, $user, $recipeId)) {
        throw new RuntimeException('Keine Berechtigung zum Teilen.');
    }
    if (!in_array($permission, ['view', 'edit'], true)) {
        $permission = 'view';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO recipe_internal_shares (recipe_id, shared_with_party_id, permission, shared_by_user_id)
         VALUES (:recipeId, :targetPartyId, :permission, :userId)
         ON DUPLICATE KEY UPDATE permission = VALUES(permission), shared_by_user_id = VALUES(shared_by_user_id)'
    );
    $stmt->execute([
        ':recipeId' => $recipeId,
        ':targetPartyId' => $targetPartyId,
        ':permission' => $permission,
        ':userId' => (int) $user['id'],
    ]);
}

function create_public_link(PDO $pdo, array $user, int $recipeId, ?string $expiresAt): string
{
    if (!can_edit_recipe($pdo, $user, $recipeId)) {
        throw new RuntimeException('Keine Berechtigung zum Teilen.');
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        'INSERT INTO recipe_public_links (recipe_id, token, expires_at, is_active, created_by_user_id)
         VALUES (:recipeId, :token, :expiresAt, 1, :userId)'
    );
    $stmt->execute([
        ':recipeId' => $recipeId,
        ':token' => $token,
        ':expiresAt' => $expiresAt,
        ':userId' => (int) $user['id'],
    ]);

    return $token;
}

function recipe_by_public_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.id
         FROM recipe_public_links l
         INNER JOIN recipes r ON r.id = l.recipe_id
         WHERE l.token = :token
           AND l.is_active = 1
           AND r.is_deleted = 0
           AND (l.expires_at IS NULL OR l.expires_at >= NOW())
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return recipe_details($pdo, (int) $row['id']);
}

function search_options(PDO $pdo, array $user): array
{
    $partyId = (int) $user['party_id'];
    $accessJoin = 'INNER JOIN recipes r ON r.id = ri.recipe_id
                   LEFT JOIN recipe_internal_shares s ON s.recipe_id = r.id AND s.shared_with_party_id = :partyIdShare
                   WHERE r.is_deleted = 0
                     AND (r.owner_party_id = :partyIdOwner OR s.id IS NOT NULL)';

    $ingSql = 'SELECT DISTINCT ri.ingredient_name
               FROM recipe_ingredients ri
               ' . $accessJoin . '
               ORDER BY ri.ingredient_name ASC
               LIMIT 200';
    $ingStmt = $pdo->prepare($ingSql);
    $ingStmt->execute([
        ':partyIdShare' => $partyId,
        ':partyIdOwner' => $partyId,
    ]);
    $ingredients = array_values(array_map(static fn(array $row): string => (string) $row['ingredient_name'], $ingStmt->fetchAll() ?: []));

    $tagSql = 'SELECT DISTINCT t.label
               FROM tags t
               INNER JOIN recipe_tags rt ON rt.tag_id = t.id
               INNER JOIN recipes r ON r.id = rt.recipe_id
               LEFT JOIN recipe_internal_shares s ON s.recipe_id = r.id AND s.shared_with_party_id = :partyIdShare
               WHERE r.is_deleted = 0
                 AND (r.owner_party_id = :partyIdOwner OR s.id IS NOT NULL)
               ORDER BY t.label ASC
               LIMIT 120';
    $tagStmt = $pdo->prepare($tagSql);
    $tagStmt->execute([
        ':partyIdShare' => $partyId,
        ':partyIdOwner' => $partyId,
    ]);
    $tags = array_values(array_map(static fn(array $row): string => (string) $row['label'], $tagStmt->fetchAll() ?: []));

    return [
        'ingredients' => $ingredients,
        'tags' => $tags,
        'day_times' => [
            ['value' => 'fruehstueck', 'label' => 'Frühstück'],
            ['value' => 'mittag', 'label' => 'Mittag'],
            ['value' => 'abend', 'label' => 'Abend'],
            ['value' => 'snack', 'label' => 'Snack'],
            ['value' => 'suesses', 'label' => 'Süßes'],
            ['value' => 'gebaeck', 'label' => 'Gebäck & Kuchen'],
            ['value' => 'getraenk', 'label' => 'Getränk'],
            ['value' => 'beilage', 'label' => 'Beilage'],
        ],
    ];
}

function list_favorites(PDO $pdo, array $user, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.title, r.description, r.day_time, r.prep_minutes, r.cook_minutes,
                r.kcal_per_serving, r.image_url, p.name AS owner_party_name
         FROM recipe_favorites f
         INNER JOIN recipes r ON r.id = f.recipe_id
         INNER JOIN parties p ON p.id = r.owner_party_id
         WHERE f.user_id = :userId
           AND r.is_deleted = 0
         ORDER BY f.created_at DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':userId', (int) $user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function add_favorite(PDO $pdo, array $user, int $recipeId): void
{
    if (!can_view_recipe($pdo, $user, $recipeId)) {
        throw new RuntimeException('Kein Zugriff auf dieses Rezept.');
    }
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO recipe_favorites (user_id, recipe_id) VALUES (:userId, :recipeId)'
    );
    $stmt->execute([':userId' => (int) $user['id'], ':recipeId' => $recipeId]);
}

function remove_favorite(PDO $pdo, array $user, int $recipeId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM recipe_favorites WHERE user_id = :userId AND recipe_id = :recipeId'
    );
    $stmt->execute([':userId' => (int) $user['id'], ':recipeId' => $recipeId]);
}

function list_recent_recipes(PDO $pdo, array $user, int $limit = 8): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT r.id, r.title, r.description, r.day_time, r.prep_minutes, r.cook_minutes,
                r.kcal_per_serving, r.image_url, r.created_at, p.name AS owner_party_name
         FROM recipes r
         INNER JOIN parties p ON p.id = r.owner_party_id
         LEFT JOIN recipe_internal_shares s ON s.recipe_id = r.id AND s.shared_with_party_id = :partyIdShare
         WHERE r.is_deleted = 0
           AND (r.owner_party_id = :partyIdOwner OR s.id IS NOT NULL)
         ORDER BY r.created_at DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':partyIdShare', (int) $user['party_id'], PDO::PARAM_INT);
    $stmt->bindValue(':partyIdOwner', (int) $user['party_id'], PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function recipe_print_html(array $recipe): string
{
    $dayTimeMap = [
        'fruehstueck' => 'Frühstück',
        'mittag' => 'Mittag',
        'abend' => 'Abend',
        'snack' => 'Snack',
        'suesses' => 'Süßes',
        'gebaeck' => 'Gebäck & Kuchen',
        'getraenk' => 'Getränk',
        'beilage' => 'Beilage',
    ];
    $dayTimeLabel = $dayTimeMap[(string) ($recipe['day_time'] ?? '')] ?? '-';

    $title = escape_html((string) $recipe['title']);
    $description = escape_html((string) ($recipe['description'] ?? ''));

    $ingredientsHtml = '';
    foreach (($recipe['ingredients'] ?? []) as $ingredient) {
        $name = escape_html((string) ($ingredient['ingredient_name'] ?? ''));
        $quantity = $ingredient['quantity'] !== null ? escape_html((string) $ingredient['quantity']) . ' ' : '';
        $unit = escape_html((string) ($ingredient['unit'] ?? ''));
        $ingredientsHtml .= '<li>' . trim($quantity . $unit . ' ' . $name) . '</li>';
    }

    $stepsHtml = '';
    foreach (($recipe['steps'] ?? []) as $step) {
        $instruction = escape_html((string) ($step['instruction'] ?? ''));
        $stepsHtml .= '<li>' . $instruction . '</li>';
    }

    return '<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>' . $title . '</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
    h1 { margin: 0 0 8px; }
    .meta { color: #555; margin-bottom: 18px; }
    h2 { margin-top: 20px; }
    li { margin-bottom: 6px; line-height: 1.4; }
    @media print { body { margin: 0; } }
  </style>
</head>
<body>
  <h1>' . $title . '</h1>
  <div class="meta">Tageszeit: ' . escape_html($dayTimeLabel) . ' | Portionen: ' . escape_html((string) ($recipe['servings'] ?? '-')) . ' | Vorbereitungszeit: ' . escape_html((string) ($recipe['prep_minutes'] ?? '-')) . ' min | Kochzeit: ' . escape_html((string) ($recipe['cook_minutes'] ?? '-')) . ' min</div>
  <div class="meta">Nährwerte/Portion: kcal ' . escape_html((string) ($recipe['kcal_per_serving'] ?? '-')) . ' | Protein ' . escape_html((string) ($recipe['protein_g_per_serving'] ?? '-')) . ' g | Kohlenhydrate ' . escape_html((string) ($recipe['carbs_g_per_serving'] ?? '-')) . ' g | Fett ' . escape_html((string) ($recipe['fat_g_per_serving'] ?? '-')) . ' g</div>
  <p>' . $description . '</p>
  <h2>Zutaten</h2>
  <ul>' . $ingredientsHtml . '</ul>
  <h2>Zubereitung</h2>
  <ol>' . $stepsHtml . '</ol>
</body>
</html>';
}
