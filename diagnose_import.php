<?php
/**
 * Diagnostic cho pipeline import local.
 *
 * An toan mac dinh:
 * - --url chi kiem tra HTTP status/content-type/effective URL, khong luu hay in body.
 * - --file doc JSON do nguoi dung cung cap, chi in thong ke schema, khong in noi dung cau hoi.
 * - --import chi hoat dong voi --file va tao backup database truoc khi goi API local.
 *
 * Vi du:
 *   php diagnose_import.php --url="https://example.test/quiz"
 *   php diagnose_import.php --file="E:\\ASUS\\Desktop\\edu\\input.json"
 *   php diagnose_import.php --file="input.json" --title="De test" --import
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function argValue(array $argv, string $name): ?string {
    $prefix = $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) return substr($arg, strlen($prefix));
    }
    return null;
}

function questionLike(mixed $value): bool {
    if (!is_array($value) || array_is_list($value)) return false;
    foreach (['question', 'q', 'question_text', 'text', 'content'] as $key) {
        if (isset($value[$key]) && is_scalar($value[$key])) return true;
    }
    return isset($value['title'], $value['options']) && is_scalar($value['title']) && is_array($value['options']);
}

function questionList(mixed $value): array {
    if (is_array($value) && array_is_list($value)) {
        if (array_filter($value, 'questionLike')) return $value;
        $flattened = [];
        foreach ($value as $item) {
            if (!is_array($item)) continue;
            foreach (['questions', 'data', 'quiz', 'result', 'payload'] as $key) {
                if (array_key_exists($key, $item)) {
                    $flattened = array_merge($flattened, questionList($item[$key]));
                }
            }
        }
        return $flattened;
    }

    if (!is_array($value)) return [];
    foreach (['questions', 'data', 'quiz', 'result', 'payload'] as $key) {
        if (!array_key_exists($key, $value)) continue;
        $found = questionList($value[$key]);
        if ($found) return $found;
    }
    return questionLike($value) ? [$value] : [];
}

function scalarValue(array $row, array $keys): string {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $row) || !is_scalar($row[$key])) continue;
        $value = trim((string)$row[$key]);
        if ($value !== '') return $value;
    }
    return '';
}

function optionValue(array $row, string $label): string {
    $lower = strtolower($label);
    $hasIndexedAnswer = isset($row['o']) && is_array($row['o']) && isset($row['a']) && is_numeric($row['a']);
    $directKeys = [$label, 'option_' . $lower];
    if (!($label === 'A' && $hasIndexedAnswer)) $directKeys[] = $lower;
    $direct = scalarValue($row, $directKeys);
    if ($direct !== '') return $direct;

    $options = $row['options'] ?? ($row['o'] ?? null);
    if (!is_array($options)) return '';
    $nested = scalarValue($options, [$label, $lower, 'option_' . $lower]);
    if ($nested !== '') return $nested;

    if (array_is_list($options)) {
        $item = $options[ord($label) - ord('A')] ?? null;
        if (is_scalar($item)) return trim((string)$item);
        if (is_array($item)) return scalarValue($item, ['text', 'value', 'content', 'label']);
    }
    return '';
}

function reportForQuestions(array $questions): array {
    $valid = 0;
    $withImages = 0;
    $withIds = 0;
    $optionCounts = array_fill_keys(range('A', 'H'), 0);
    $correctCounts = [];
    $errors = [];
    $normalizedSamples = [];

    foreach ($questions as $index => $row) {
        if (!is_array($row)) {
            $errors[] = ['index' => $index + 1, 'reason' => 'record_not_object'];
            continue;
        }
        $text = scalarValue($row, ['question', 'q', 'title', 'question_text', 'text', 'content']);
        $optionTotal = 0;
        foreach (range('A', 'H') as $label) {
            if (optionValue($row, $label) !== '') {
                $optionCounts[$label]++;
                $optionTotal++;
            }
        }
        $correct = strtoupper(scalarValue($row, [
            'correct', 'answer', 'key', 'correct_answer', 'correctAnswer', 'answer_key'
        ]));
        if (isset($row['correct']) && is_array($row['correct'])) {
            $correct = implode(',', array_map(static fn($v) => strtoupper(trim((string)$v)), $row['correct']));
        } elseif ($correct === '' && isset($row['a']) && is_numeric($row['a']) && isset($row['o']) && is_array($row['o'])) {
            $correct = chr(ord('A') + (int)$row['a']);
        }
        if ($correct !== '') $correctCounts[$correct] = ($correctCounts[$correct] ?? 0) + 1;
        if (isset($row['id']) || isset($row['question_id'])) $withIds++;
        $image = scalarValue($row, ['image', 'img', 'image_url', 'imageUrl']);
        if ($image !== '') $withImages++;

        if (count($normalizedSamples) < 3) {
            $sampleOptions = [];
            foreach (range('A', 'H') as $label) {
                $value = optionValue($row, $label);
                if ($value !== '') $sampleOptions[$label] = $value;
            }
            $normalizedSamples[] = [
                'question' => $text,
                'options' => $sampleOptions,
                'correct_answer' => $correct,
                'image_url' => $image !== '' ? $image : null
            ];
        }

        if ($text !== '' && $optionTotal >= 2) {
            $valid++;
        } else {
            $errors[] = [
                'index' => $index + 1,
                'reason' => $text === '' ? 'missing_question_text' : 'fewer_than_two_options'
            ];
        }
    }

    return [
        'records' => count($questions),
        'valid_records' => $valid,
        'invalid_records' => count($errors),
        'ids' => $withIds,
        'images' => $withImages,
        'option_counts' => $optionCounts,
        'total_options' => array_sum($optionCounts),
        'correct_records' => array_sum($correctCounts),
        'correct_answer_counts' => $correctCounts,
        'normalized_samples' => $normalizedSamples,
        'validation_errors_sample' => array_slice($errors, 0, 10)
    ];
}

function httpStatusOnly(string $url): array {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'invalid_url'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'EduLocal-Diagnostic/1.0'
        ]);
        curl_exec($ch);
        $error = curl_error($ch);
        $result = [
            'ok' => $error === '',
            'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'content_type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null,
            'effective_url' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url
        ];
        if ($error !== '') $result['error'] = $error;
        curl_close($ch);
        return $result;
    }

    $headers = @get_headers($url, true);
    if (!$headers) return ['ok' => false, 'error' => 'no_response'];
    $statusLine = is_array($headers) ? (string)($headers[0] ?? '') : '';
    preg_match('/\s(\d{3})\s/', $statusLine, $match);
    $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? null;
    return [
        'ok' => true,
        'status' => isset($match[1]) ? (int)$match[1] : null,
        'content_type' => is_array($contentType) ? end($contentType) : $contentType,
        'effective_url' => $url
    ];
}

$url = argValue($argv, '--url');
$file = argValue($argv, '--file');
$title = argValue($argv, '--title') ?: 'Imported local JSON';
$doImport = in_array('--import', $argv, true);

$report = [
    'pipeline' => 'local-file -> parse -> validate' . ($doImport ? ' -> local-api-import' : ''),
    'project' => __DIR__,
    'url_check' => null,
    'input' => null,
    'schema' => null,
    'database' => null,
    'import' => null
];

if ($url !== null) {
    $report['url_check'] = httpStatusOnly($url);
}

if ($file !== null) {
    $path = realpath($file);
    if ($path === false || !is_file($path)) {
        fwrite(STDERR, "File not found: {$file}\n");
        exit(2);
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $report['input'] = ['file' => $path, 'bytes' => strlen($raw), 'format' => 'invalid_json', 'error' => json_last_error_msg()];
    } else {
        $questions = questionList($data);
        $report['input'] = [
            'file' => $path,
            'bytes' => strlen($raw),
            'format' => 'json',
            'top_level' => is_array($data) ? (array_is_list($data) ? 'array' : 'object') : gettype($data)
        ];
        $report['schema'] = reportForQuestions($questions);
    }
}

if ($file === null && $url === null) {
    fwrite(STDERR, "Usage: php diagnose_import.php --file=INPUT.json [--title=TITLE] [--import]\n       php diagnose_import.php --url=URL\n");
    exit(2);
}

if ($doImport) {
    if ($file === null || empty($report['schema']) || $report['schema']['valid_records'] === 0) {
        $report['import'] = ['ok' => false, 'error' => 'import_requires_valid_local_file'];
    } else {
        $dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'database.sqlite';
        $backup = $dbPath . '.bak-' . date('Ymd-His') . '-diagnose-import';
        if (!copy($dbPath, $backup)) {
            $report['import'] = ['ok' => false, 'error' => 'database_backup_failed'];
        } else {
            $payload = json_encode([
                'title' => $title,
                'author' => 'Học viên',
                'organization' => 'Đại học Kinh doanh và Công nghệ Hà Nội',
                'thumbnail' => 'chalkboard',
                'questions' => questionList(json_decode(file_get_contents(realpath($file)), true))
            ], JSON_UNESCAPED_UNICODE);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 30,
                    'ignore_errors' => true
                ]
            ]);
            $response = @file_get_contents('http://127.0.0.1:8000/api.php?action=upload_exam', false, $context);
            $decoded = $response !== false ? json_decode($response, true) : null;
            $report['import'] = [
                'ok' => is_array($decoded) && !empty($decoded['success']),
                'backup' => $backup,
                'api_response' => is_array($decoded)
                    ? ['success' => $decoded['success'] ?? false, 'question_count' => $decoded['question_count'] ?? null]
                    : ['raw_response_available' => $response !== false]
            ];
        }
    }
}

$pdo = Database::getConnection();
$report['database'] = [
    'driver' => Database::getDriverType(),
    'exams' => (int)$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
    'questions' => (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn()
];

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
