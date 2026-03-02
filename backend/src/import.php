<?php

declare(strict_types=1);

require_once __DIR__ . '/chat.php';

function import_from_url(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Ungültige URL.');
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Nur HTTP/HTTPS URLs sind erlaubt.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'FamilyRecipeApp/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);

    $html = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $html === false || $status >= 400) {
        throw new RuntimeException('Webseite konnte nicht abgerufen werden (HTTP ' . $status . ').');
    }

    $text = html_to_text((string) $html);
    $text = substr($text, 0, 8000);

    return extract_recipe_via_ai($text);
}

function import_from_uploaded_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Datei-Upload fehlgeschlagen.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Datei darf maximal 5 MB groß sein.');
    }

    $name = (string) ($file['name'] ?? '');
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $extToMime = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'txt' => 'text/plain', 'md' => 'text/plain',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $mime = $extToMime[$ext] ?? (function_exists('mime_content_type') ? (mime_content_type($tmpPath) ?: '') : '');

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return extract_recipe_from_image($tmpPath, $mime ?: 'image/jpeg');
    }

    $text = '';

    if (in_array($ext, ['txt', 'md'], true)) {
        $text = file_get_contents($tmpPath) ?: '';
    } elseif ($ext === 'pdf') {
        $text = extract_text_from_pdf($tmpPath);
    } elseif ($ext === 'docx') {
        $text = extract_text_from_docx($tmpPath);
    } else {
        $text = file_get_contents($tmpPath) ?: '';
    }

    if (trim($text) === '') {
        throw new InvalidArgumentException('Konnte keinen Text aus der Datei extrahieren (' . $ext . ').');
    }

    // Sanitize: remove control chars and invalid UTF-8 that break JSON encoding
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
    if (function_exists('mb_convert_encoding')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    } else {
        $text = iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;
    }
    $text = substr($text, 0, 8000);
    return extract_recipe_via_ai($text);
}

function html_to_text(string $html): string
{
    $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html) ?? $html;
    $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html) ?? $html;
    $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html) ?? $html;
    $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html) ?? $html;
    $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html) ?? $html;

    $html = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>', '</div>'], "\n", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

    return trim($text);
}

function extract_text_from_pdf(string $path): string
{
    $pdftotext = 'pdftotext';
    $output = '';
    $returnCode = 1;

    if (function_exists('exec')) {
        exec(
            escapeshellcmd($pdftotext) . ' ' . escapeshellarg($path) . ' -',
            $lines,
            $returnCode
        );
        if ($returnCode === 0 && !empty($lines)) {
            return implode("\n", $lines);
        }
    }

    $raw = file_get_contents($path) ?: '';
    preg_match_all('/\((.*?)\)/', $raw, $matches);
    if (!empty($matches[1])) {
        return implode(' ', $matches[1]);
    }

    return '';
}

function extract_text_from_docx(string $path): string
{
    // Strategy 1: PHP ZipArchive extension
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml !== false && $xml !== '') {
                return docx_xml_to_text($xml);
            }
        }
    }

    // Strategy 2: Pure PHP ZIP parsing (no extensions needed)
    // DOCX is a ZIP file. We can parse the ZIP format manually to find word/document.xml
    $xml = zip_extract_file_pure_php($path, 'word/document.xml');
    if ($xml !== '') {
        return docx_xml_to_text($xml);
    }

    return '';
}

/**
 * Pure PHP ZIP file extraction — reads a single file from a ZIP archive
 * without requiring the ZipArchive extension.
 * Supports DEFLATE (method 8) and STORED (method 0) compression.
 */
function zip_extract_file_pure_php(string $zipPath, string $targetFile): string
{
    $fh = fopen($zipPath, 'rb');
    if ($fh === false) {
        return '';
    }

    $result = '';

    while (!feof($fh)) {
        $sig = fread($fh, 4);
        if ($sig === false || strlen($sig) < 4) {
            break;
        }

        // Local file header signature: PK\x03\x04
        if ($sig !== "PK\x03\x04") {
            break;
        }

        $header = fread($fh, 26);
        if ($header === false || strlen($header) < 26) {
            break;
        }

        $data = unpack('vversion/vflags/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', $header);
        if ($data === false) {
            break;
        }

        $fileName = fread($fh, $data['nameLen']);
        if ($data['extraLen'] > 0) {
            fread($fh, $data['extraLen']);
        }

        // Handle data descriptor (bit 3 of flags)
        $compSize = $data['compSize'];
        $uncompSize = $data['uncompSize'];

        if ($fileName === $targetFile && $compSize > 0) {
            $compressed = fread($fh, $compSize);
            if ($compressed === false) {
                break;
            }

            if ($data['method'] === 0) {
                // STORED — no compression
                $result = $compressed;
            } elseif ($data['method'] === 8) {
                // DEFLATE
                $decompressed = @gzinflate($compressed);
                if ($decompressed !== false) {
                    $result = $decompressed;
                }
            }
            break;
        }

        // Skip this file's data
        if ($compSize > 0) {
            fseek($fh, $compSize, SEEK_CUR);
        }
    }

    fclose($fh);
    return $result;
}

function docx_xml_to_text(string $xml): string
{
    // Extract text from <w:t> tags, preserving paragraph breaks
    // Each <w:p> is a paragraph, each <w:t> is a text run within a paragraph
    $result = '';
    // Split by paragraph boundaries
    $paragraphs = preg_split('/<w:p[\s>]/u', $xml) ?: [$xml];
    foreach ($paragraphs as $para) {
        // Extract all <w:t> text runs in this paragraph
        if (preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $para, $matches)) {
            $line = implode('', $matches[1]);
            if (trim($line) !== '') {
                $result .= trim($line) . "\n";
            }
        }
    }
    return trim($result);
}

function extract_recipe_via_ai(string $text): array
{
    // Verify text produces valid JSON before sending
    $testPayload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
    if ($testPayload === false) {
        $text = preg_replace('/[^\x20-\x7E\xC0-\xFF\n\r\t]/u', '', $text) ?? $text;
    }

    $systemPrompt = 'Du bist ein erfahrener Koch und Ernährungsberater. Deine Aufgabe ist es, aus beliebigen Texten Rezeptdaten zu extrahieren und sinnvoll zu ergänzen. Der Text kann Tippfehler, informellen Stil oder unstrukturierte Beschreibungen enthalten — extrahiere trotzdem so viel wie möglich. Antworte ausschließlich mit gültigem JSON.';
    $userPrompt = 'Extrahiere das Rezept aus dem folgenden Text und ergänze fehlende Angaben mit fundierten Schätzungen:

Regeln:
- Extrahiere alle Zutaten mit Menge und Einheit (g, ml, TL, EL, Stück, Prise, etc.)
- Formuliere die Zubereitungsschritte klar und prägnant um
- Schätze Portionen falls nicht angegeben (typische Haushaltsgröße: 4)
- Schätze Zubereitungs- und Kochzeit in Minuten (Zubereitungszeit = aktives Arbeiten, Kochzeit = Ofen/Herd/Ruhezeit)
- Schätze Nährwerte pro Portion basierend auf den Zutaten (kcal, Protein, Kohlenhydrate, Fett in Gramm)
- Vergib 2-4 passende Tags (z.B. "Brot", "Backen", "Vegetarisch", "Schnell", "Familienrezept")
- Wähle day_time: "fruehstueck", "mittag", "snack", "abend", "suesses", "gebaeck", "getraenk", "beilage" oder null
- Schreibe eine kurze, appetitliche Beschreibung (1-2 Sätze)

JSON-Format:
{"title":"...","description":"...","day_time":null,"servings":4,"prep_minutes":15,"cook_minutes":30,"kcal_per_serving":350,"protein_g_per_serving":10,"carbs_g_per_serving":45,"fat_g_per_serving":12,"tags":["..."],"ingredients":[{"ingredient_name":"...","quantity":1,"unit":"g"}],"steps":[{"instruction":"..."}]}

Hier ist der Text:

' . $text;

    $json = ai_chat_request($systemPrompt, $userPrompt, true);

    if (!is_string($json) || $json === '') {
        // Check if it's a config issue or an API error
        $provider = ai_provider();
        $apiKey = ai_api_key($provider);
        if ($apiKey === '') {
            throw new RuntimeException('KI-API-Key nicht konfiguriert. Bitte OPENROUTER_API_KEY oder OPENAI_API_KEY im Backend setzen.');
        }
        $detail = $GLOBALS['_ai_last_error'] ?? 'unbekannt';
        throw new RuntimeException('KI-Extraktion fehlgeschlagen: ' . $detail);
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('KI-Antwort ist kein gültiges JSON: ' . substr($json, 0, 300));
    }
    if (empty($data['title']) || $data['title'] === 'Kein Rezept gefunden') {
        throw new RuntimeException('KI konnte kein Rezept extrahieren. Text-Anfang: ' . substr($text, 0, 200));
    }

    return normalize_imported_recipe($data);
}

function extract_recipe_from_image(string $path, string $mime): array
{
    $provider = ai_provider();
    $apiKey = ai_api_key($provider);
    if ($apiKey === '') {
        throw new RuntimeException('KI-API-Key nicht konfiguriert.');
    }

    $imageData = base64_encode(file_get_contents($path) ?: '');
    if ($imageData === '') {
        throw new InvalidArgumentException('Bilddatei konnte nicht gelesen werden.');
    }

    $mimeType = $mime ?: 'image/jpeg';

    $payload = [
        'model' => ai_model($provider),
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Du extrahierst Rezeptdaten aus Bildern. Antworte ausschließlich mit gültigem JSON.',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Extrahiere das Rezept aus diesem Bild. Ausgabeformat:
{"title":"...","description":"...","day_time":null,"servings":null,"prep_minutes":null,"cook_minutes":null,"kcal_per_serving":null,"protein_g_per_serving":null,"carbs_g_per_serving":null,"fat_g_per_serving":null,"tags":["..."],"ingredients":[{"ingredient_name":"...","quantity":null,"unit":""}],"steps":[{"instruction":"..."}]}',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $mimeType . ';base64,' . $imageData,
                        ],
                    ],
                ],
            ],
        ],
        'temperature' => 0.3,
        'response_format' => ['type' => 'json_object'],
    ];

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
        CURLOPT_TIMEOUT => 60,
    ]);

    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $result === false || $status >= 400) {
        throw new RuntimeException('Bild-Extraktion fehlgeschlagen. Nutze ein Vision-fähiges Modell.');
    }

    $data = json_decode($result, true);
    $content = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($content)) {
        throw new RuntimeException('KI konnte das Bild nicht verarbeiten.');
    }

    $recipe = json_decode($content, true);
    if (!is_array($recipe) || empty($recipe['title'])) {
        throw new RuntimeException('KI konnte kein gültiges Rezept aus dem Bild extrahieren.');
    }

    return normalize_imported_recipe($recipe);
}

function normalize_imported_recipe(array $data): array
{
    $ingredients = [];
    foreach (($data['ingredients'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $name = trim((string) ($item['ingredient_name'] ?? ''));
        if ($name === '') continue;
        $ingredients[] = [
            'ingredient_name' => $name,
            'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : null,
            'unit' => trim((string) ($item['unit'] ?? '')),
        ];
    }

    $steps = [];
    foreach (($data['steps'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $instruction = trim((string) ($item['instruction'] ?? ''));
        if ($instruction === '') continue;
        $steps[] = ['instruction' => $instruction];
    }

    $tags = [];
    foreach (($data['tags'] ?? []) as $tag) {
        $label = trim((string) $tag);
        if ($label !== '') $tags[] = $label;
    }

    return [
        'title' => trim((string) ($data['title'] ?? '')),
        'description' => trim((string) ($data['description'] ?? '')),
        'day_time' => $data['day_time'] ?? null,
        'servings' => is_numeric($data['servings'] ?? null) ? (int) $data['servings'] : null,
        'prep_minutes' => is_numeric($data['prep_minutes'] ?? null) ? (int) $data['prep_minutes'] : null,
        'cook_minutes' => is_numeric($data['cook_minutes'] ?? null) ? (int) $data['cook_minutes'] : null,
        'kcal_per_serving' => is_numeric($data['kcal_per_serving'] ?? null) ? (float) $data['kcal_per_serving'] : null,
        'protein_g_per_serving' => is_numeric($data['protein_g_per_serving'] ?? null) ? (float) $data['protein_g_per_serving'] : null,
        'carbs_g_per_serving' => is_numeric($data['carbs_g_per_serving'] ?? null) ? (float) $data['carbs_g_per_serving'] : null,
        'fat_g_per_serving' => is_numeric($data['fat_g_per_serving'] ?? null) ? (float) $data['fat_g_per_serving'] : null,
        'tags' => $tags,
        'ingredients' => $ingredients,
        'steps' => $steps,
    ];
}
