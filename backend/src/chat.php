<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function ai_provider(): string
{
    $configured = strtolower((string) env('AI_PROVIDER', ''));
    if (in_array($configured, ['openrouter', 'openai'], true)) {
        return $configured;
    }

    if (env('OPENROUTER_API_KEY', '') !== '') {
        return 'openrouter';
    }

    return 'openai';
}

function ai_model(string $provider): string
{
    $generic = env('AI_MODEL', '');
    if ($generic !== '') {
        return $generic;
    }

    if ($provider === 'openrouter') {
        return env('OPENROUTER_MODEL', 'openai/gpt-4o-mini') ?? 'openai/gpt-4o-mini';
    }

    return env('OPENAI_MODEL', 'gpt-4.1-mini') ?? 'gpt-4.1-mini';
}

function ai_api_key(string $provider): string
{
    if ($provider === 'openrouter') {
        $key = env('OPENROUTER_API_KEY', '');
        if ($key !== '') {
            return $key;
        }
    }

    return env('OPENAI_API_KEY', '') ?? '';
}

function ai_endpoint(string $provider): string
{
    if ($provider === 'openrouter') {
        return 'https://openrouter.ai/api/v1/chat/completions';
    }

    return 'https://api.openai.com/v1/chat/completions';
}

function ai_chat_request(string $systemPrompt, string $userPrompt, bool $jsonMode = false): ?string
{
    $provider = ai_provider();
    $apiKey = ai_api_key($provider);
    if ($apiKey === '') {
        return null;
    }

    $payload = [
        'model' => ai_model($provider),
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'temperature' => 0.7,
    ];

    if ($jsonMode) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    if ($provider === 'openrouter') {
        $referer = env('OPENROUTER_HTTP_REFERER', '');
        $title = env('OPENROUTER_APP_TITLE', '');
        if ($referer !== '') {
            $headers[] = 'HTTP-Referer: ' . $referer;
        }
        if ($title !== '') {
            $headers[] = 'X-Title: ' . $title;
        }
    }

    $ch = curl_init(ai_endpoint($provider));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => $jsonMode ? 60 : 30,
    ]);

    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $result === false || $status >= 400) {
        error_log("AI request failed: errno=$errno, status=$status, response=" . substr((string)$result, 0, 500));
        // Store debug info for callers that need it
        $GLOBALS['_ai_last_error'] = "HTTP $status, curl_errno=$errno, body=" . substr((string)$result, 0, 300);
        return null;
    }

    $data = json_decode($result, true);
    if (!is_array($data)) {
        $GLOBALS['_ai_last_error'] = "Invalid JSON response: " . substr((string)$result, 0, 300);
        return null;
    }

    $content = $data['choices'][0]['message']['content'] ?? null;
    if (is_string($content) && trim($content) !== '') {
        return trim($content);
    }

    if (is_array($content)) {
        $parts = [];
        foreach ($content as $part) {
            if (!is_array($part)) {
                continue;
            }

            $text = $part['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $parts[] = trim($text);
            }
        }

        if ($parts !== []) {
            return trim(implode("\n", $parts));
        }
    }

    $GLOBALS['_ai_last_error'] = "No content in response: " . substr(json_encode($data), 0, 300);
    return null;
}

function chat_reply(string $userMessage): string
{
    if (ai_api_key(ai_provider()) === '') {
        return 'Chatbot ist noch nicht konfiguriert. Bitte OPENROUTER_API_KEY oder OPENAI_API_KEY im Backend setzen.';
    }

    $reply = ai_chat_request(
        'Du bist ein hilfreicher Assistent für eine Familien-Rezeptsammlung. Antworte kurz und praktisch.',
        $userMessage
    );

    if (!is_string($reply) || $reply === '') {
        return 'Der Chatbot ist aktuell nicht erreichbar.';
    }

    return $reply;
}

function fallback_home_ideas(int $hour): array
{
    if ($hour < 11) {
        return [
            [
                'title' => 'Warum Frühstück wichtig ist',
                'text' => 'Ein ausgewogenes Frühstück hilft, konzentriert in den Tag zu starten und Heißhunger später zu reduzieren.',
                'action' => 'Tipps für schnelles Familienfrühstück',
            ],
            [
                'title' => 'Wusstest du schon? Haferflocken',
                'text' => 'Hafer liefert Ballaststoffe, die lange satt machen und sich gut mit Obst und Joghurt kombinieren lassen.',
                'action' => '3 Ideen mit Haferflocken',
            ],
            [
                'title' => 'Morgen-Routine in 10 Minuten',
                'text' => 'Wenn Zutaten am Vorabend vorbereitet sind, klappt ein gesundes Frühstück auch an stressigen Tagen.',
                'action' => 'Checkliste für den Vorabend',
            ],
        ];
    }

    if ($hour < 17) {
        return [
            [
                'title' => 'Mittag leicht und nahrhaft',
                'text' => 'Kombiniere Eiweiß, Gemüse und komplexe Kohlenhydrate für langanhaltende Energie am Nachmittag.',
                'action' => 'Schnelle Lunch-Ideen',
            ],
            [
                'title' => 'Küchen-Shortcut des Tages',
                'text' => 'Schneide gleich die doppelte Menge Gemüse und nutze den Rest später für Abendessen oder Snack.',
                'action' => 'Meal-Prep Mini-Plan',
            ],
            [
                'title' => 'Wusstest du schon? Hülsenfrüchte',
                'text' => 'Linsen und Bohnen sind günstig, proteinreich und vielseitig in Salaten, Suppen und Pfannengerichten.',
                'action' => '2 familienfreundliche Linsenrezepte',
            ],
        ];
    }

    return [
        [
            'title' => 'Entspanntes Abendessen planen',
            'text' => 'Einfache Gerichte mit 5 bis 7 Zutaten reduzieren Stress und funktionieren auch unter der Woche.',
            'action' => 'Ideen für Feierabendgerichte',
        ],
        [
            'title' => 'Wusstest du schon? Gewürze',
            'text' => 'Mit Gewürzen wie Paprika, Kreuzkümmel und Oregano kannst du mit denselben Grundzutaten stark variieren.',
            'action' => 'Gewürz-Kombis für Alltag',
        ],
        [
            'title' => 'Kochmodus für Familien',
            'text' => 'Teile Aufgaben: eine Person schnippelt, eine kocht, eine deckt den Tisch. So geht es deutlich schneller.',
            'action' => 'Aufgabenteilung in 3 Schritten',
        ],
    ];
}

function home_ideas(int $hour, string $locale = 'de-DE'): array
{
    $hour = max(0, min(23, $hour));

    $json = ai_chat_request(
        'Du erstellst Startseiten-Inhalte für eine Familien-Rezept-App. Antworte ausschließlich mit gültigem JSON.',
        sprintf(
            'Erzeuge 3 kurze, motivierende Karten für Uhrzeit %d:00 (%s). Ausgabeformat exakt: {"items":[{"title":"...","text":"...","action":"..."}]}. Sprache: Deutsch. Themen: Essen, Kochen, Gesundheit, Familienalltag.',
            $hour,
            $locale
        ),
        true
    );

    if (!is_string($json) || $json === '') {
        return fallback_home_ideas($hour);
    }

    $decoded = json_decode($json, true);
    $items = is_array($decoded) ? ($decoded['items'] ?? null) : null;
    if (!is_array($items) || $items === []) {
        return fallback_home_ideas($hour);
    }

    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = trim((string) ($item['title'] ?? ''));
        $body = trim((string) ($item['text'] ?? ''));
        $action = trim((string) ($item['action'] ?? ''));

        if ($title === '' || $body === '') {
            continue;
        }

        $normalized[] = [
            'title' => $title,
            'text' => $body,
            'action' => $action,
        ];
    }

    if ($normalized === []) {
        return fallback_home_ideas($hour);
    }

    return array_slice($normalized, 0, 3);
}

