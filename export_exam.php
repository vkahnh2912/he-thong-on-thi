<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanQuestionTextForExport(string $text): string {
    $cleaned = preg_replace(
        '/^(?:(?:Câu\s*(?:số)?\s*\d+|\d+)[\s.\:\)\/\-]+)+/iu',
        '',
        $text
    );

    return trim($cleaned ?? $text);
}

/**
 * Khôi phục xuống dòng cho các đoạn code đã bị PDF/import dồn thành một dòng.
 * Quy tắc này đồng bộ với formatQuestionText() trong quiz.php để bản in
 * không bị khác với màn hình ôn tập.
 */
function formatQuestionTextForExport(string $text): string {
    $value = cleanQuestionTextForExport($text);
    if ($value === '') {
        return '';
    }

    $looksLikeCode = preg_match(
        '/(?:#include\b|\b(?:int|void|float|double|char|bool)\s+main\s*\(|\btypedef\s+struct\b|\b(?:static\s+void|private\s+void|public\s+(?:class|void))|SqlConnection|SqlCommand|Console\.|dataGridView|\b(?:cout|cin|printf|scanf|strlen|sqrt|acos)\b|\b(?:if|for|while|switch)\s*\(|\bclass\s+\w+\s*\{)/iu',
        $value
    ) === 1;
    if (!$looksLikeCode) {
        return $value;
    }

    $value = preg_replace('/\s+(?=\d+\s*(?::|\.(?!\d)))/u', "\n", $value) ?? $value;
    $value = preg_replace('/(^|\n)(\d+\s*(?::|\.(?!\d)))(?=\S)/mu', "$1$2 ", $value) ?? $value;
    $value = preg_replace(
        '/(?<!\d):\s+(?=(?:#include|const|int|float|double|char|bool|typedef|using|class|struct|for|while|if|switch|static|public|private)\b)/iu',
        ":\n",
        $value
    ) ?? $value;
    $value = preg_replace('/\b(public|private|protected):\s+/iu', "$1:\n", $value) ?? $value;

    // Chỉ tách header đầu tiên; cụm "Lệnh #include..." ở phần hỏi phía sau
    // không được coi là một block code mới.
    $value = preg_replace('/\s+(?=#include\b)/iu', "\n", $value, 1) ?? $value;
    $value = preg_replace(
        '/#include\s+(?=(?:int|void|float|double|char|bool|typedef|using|class|struct)\b)/iu',
        "#include <iostream.h>\n",
        $value
    ) ?? $value;
    $value = preg_replace(
        '/(#include\s*(?:<[^>\n]+>|["\'][^"\n]+["\']))\s+(?=(?:#include|int|void|float|double|char|bool|typedef|using|class|struct)\b)/iu',
        "$1\n",
        $value
    ) ?? $value;
    $value = preg_replace(
        '/(\b(?:int|void|float|double|char|bool)\s+main\s*\(\s*(?:void)?\s*\))\s+(?=(?:int|float|double|char|bool|long|short|string)\s+\w)/iu',
        "$1 {\n",
        $value
    ) ?? $value;
    $value = preg_replace(
        '/(\b(?:int|void|float|double|char|bool)\s+main\s*\(\s*(?:void)?\s*\))\s+(?=[^{])/iu',
        "$1\n",
        $value
    ) ?? $value;
    $value = preg_replace(
        '/(\})\s*(?=(?:#include|typedef|class|struct|int|void|public|private|protected)\b)/u',
        "$1\n",
        $value
    ) ?? $value;

    $formatted = '';
    $quote = null;
    $escaped = false;
    $parenDepth = 0;
    $bracketDepth = 0;
    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    foreach ($characters as $character) {
        if ($quote !== null) {
            $formatted .= $character;
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($character === '"' || $character === "'") {
            $quote = $character;
            $formatted .= $character;
        } elseif ($character === '(') {
            $parenDepth++;
            $formatted .= $character;
        } elseif ($character === ')') {
            $parenDepth = max(0, $parenDepth - 1);
            $formatted .= $character;
        } elseif ($character === '[') {
            $bracketDepth++;
            $formatted .= $character;
        } elseif ($character === ']') {
            $bracketDepth = max(0, $bracketDepth - 1);
            $formatted .= $character;
        } elseif ($character === ';') {
            $formatted .= ';';
            if ($parenDepth === 0 && $bracketDepth === 0) {
                $formatted .= "\n";
            }
        } elseif ($character === '{') {
            $formatted .= "{\n";
        } elseif ($character === '}') {
            $formatted .= "\n}\n";
        } else {
            $formatted .= $character;
        }
    }

    $normalized = $formatted;
    $normalized = preg_replace(
        '/\?\s+(?=(?:static|private|public|class|int|if|for|while|SqlConnection|dataGridView)\b)/iu',
        "?\n",
        $normalized
    ) ?? $normalized;
    $normalized = preg_replace('/[ \t]+\n/u', "\n", $normalized) ?? $normalized;
    $normalized = preg_replace('/\n[ \t]+/u', "\n", $normalized) ?? $normalized;
    $normalized = preg_replace(
        '/\{\s*\n\s*([^{}\n]*,[^{}\n]*)\s*\n\s*\}/u',
        '{ $1 }',
        $normalized
    ) ?? $normalized;
    $normalized = preg_replace('/\n\s*,\s*\n?\s*\{/u', ",\n{", $normalized) ?? $normalized;
    $normalized = preg_replace('/\}\s*\n\s*;\s*\n/u', "};\n", $normalized) ?? $normalized;
    $normalized = preg_replace("/\n{3,}/u", "\n\n", $normalized) ?? $normalized;
    $normalized = trim($normalized);

    $indentLevel = 0;
    $output = [];
    $lines = preg_split('/\r\n|\r|\n/', $normalized) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        if (str_starts_with($trimmed, '}')) {
            $indentLevel = max(0, $indentLevel - 1);
        }
        $output[] = str_repeat('  ', $indentLevel) . $trimmed;
        $openCount = substr_count($trimmed, '{');
        $closeCount = substr_count($trimmed, '}');
        $indentLevel = max(0, $indentLevel + $openCount - $closeCount);
    }

    return trim(preg_replace("/\n{2,}/u", "\n", implode("\n", $output)) ?? implode("\n", $output));
}

function answerLabels($rawAnswer): array {
    $decoded = json_decode((string)$rawAnswer, true);
    $values = is_array($decoded) ? $decoded : [$rawAnswer];
    $labels = [];

    foreach ($values as $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $candidate = strtoupper(trim((string)$value));
        if ($candidate === '') {
            continue;
        }

        // Hỗ trợ cả A, A/B, A,B và chuỗi nhiều đáp án như AB.
        $parts = preg_split('/[,;|\/\s]+/', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts as $part) {
            if (preg_match('/^[A-H]+$/', $part)) {
                foreach (str_split($part) as $label) {
                    $labels[$label] = true;
                }
            }
        }
    }

    return array_keys($labels);
}

function formatAnswer($rawAnswer): string {
    $labels = answerLabels($rawAnswer);
    return $labels ? implode(', ', $labels) : '-';
}

$examId = (int)($_GET['exam_id'] ?? $_GET['id'] ?? 0);
if ($examId <= 0) {
    http_response_code(400);
    exit('ID đề thi không hợp lệ.');
}

try {
    $pdo = Database::getConnection();

    $examStmt = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
    $examStmt->execute([$examId]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        http_response_code(404);
        exit('Không tìm thấy đề thi.');
    }

    $questionStmt = $pdo->prepare(
        'SELECT question_num, question_text, image_url, option_a, option_b, option_c, option_d, option_e, option_f, option_g, option_h, correct_answer
         FROM questions WHERE exam_id = ? ORDER BY question_num ASC, id ASC'
    );
    $questionStmt->execute([$examId]);
    $questions = $questionStmt->fetchAll();
} catch (Throwable $error) {
    http_response_code(500);
    exit('Không thể tải đề thi: ' . e($error->getMessage()));
}

$createdAt = !empty($exam['created_at']) ? strtotime((string)$exam['created_at']) : false;
$formattedDate = $createdAt ? date('d/m/Y', $createdAt) : '-';
$title = (string)$exam['title'];
$organization = trim((string)($exam['organization'] ?? ''));
$author = trim((string)($exam['author'] ?? ''));
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#4f46e5">
  <title><?= e($title) ?> - Bản xem PDF</title>
  <style>
    :root {
      color-scheme: light;
      --primary: #4f46e5;
      --primary-dark: #3730a3;
      --ink: #172033;
      --muted: #64748b;
      --line: #dbe3ef;
      --paper: #ffffff;
      --page-bg: #eef2ff;
    }

    * { box-sizing: border-box; }

    html { background: var(--page-bg); }

    body {
      margin: 0;
      background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 55%, #e0e7ff 100%);
      color: var(--ink);
      font-family: Arial, "Noto Sans", sans-serif;
      line-height: 1.55;
      -webkit-text-size-adjust: 100%;
    }

    .print-toolbar {
      position: sticky;
      top: 0;
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 12px max(16px, env(safe-area-inset-right)) 12px max(16px, env(safe-area-inset-left));
      background: rgba(255, 255, 255, 0.94);
      border-bottom: 1px solid rgba(148, 163, 184, 0.35);
      box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
      backdrop-filter: blur(12px);
    }

    .toolbar-note {
      color: var(--muted);
      font-size: 13px;
    }

    .print-button {
      min-height: 44px;
      padding: 10px 18px;
      border: 0;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--primary), #7c3aed);
      color: #fff;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      white-space: nowrap;
    }

    .paper {
      width: min(100% - 32px, 900px);
      margin: 28px auto 48px;
      padding: 42px 52px;
      background: var(--paper);
      border: 1px solid rgba(148, 163, 184, 0.32);
      border-radius: 16px;
      box-shadow: 0 20px 55px rgba(30, 41, 59, 0.14);
    }

    .exam-header {
      padding-bottom: 22px;
      border-bottom: 2px solid var(--primary);
    }

    .eyebrow {
      margin: 0 0 8px;
      color: var(--primary);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 1.2px;
      text-transform: uppercase;
    }

    h1 {
      margin: 0 0 12px;
      color: #0f172a;
      font-size: clamp(24px, 4vw, 34px);
      line-height: 1.2;
      overflow-wrap: anywhere;
    }

    .meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 20px;
      color: var(--muted);
      font-size: 13px;
    }

    .summary {
      margin: 22px 0 28px;
      padding: 12px 16px;
      border: 1px solid #c7d2fe;
      border-radius: 10px;
      background: #eef2ff;
      color: #3730a3;
      font-size: 13px;
    }

    .question {
      margin: 0 0 24px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .question-heading {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 8px;
      font-size: 15px;
      font-weight: 700;
    }

    .question-number {
      flex: 0 0 auto;
      min-width: 34px;
      padding: 3px 7px;
      border-radius: 7px;
      background: var(--primary);
      color: #fff;
      font-size: 12px;
      text-align: center;
    }

    .question-text {
      min-width: 0;
      font-weight: 600;
      overflow-wrap: anywhere;
      white-space: pre-wrap;
    }

    .question-image {
      display: block;
      max-width: min(100%, 620px);
      max-height: 420px;
      margin: 10px 0 12px 44px;
      border: 1px solid var(--line);
      border-radius: 8px;
      object-fit: contain;
      background: #fff;
    }

    .options {
      display: grid;
      gap: 5px;
      margin: 0 0 0 44px;
    }

    .option {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      min-width: 0;
      color: #334155;
      font-size: 14px;
    }

    .correct-option {
      padding: 7px 9px;
      border: 1px solid #fca5a5;
      border-radius: 7px;
      background: #fff1f2;
      color: #b91c1c;
      font-weight: 700;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .correct-option .option-label,
    .correct-option .option-text {
      color: #b91c1c;
    }

    .correct-badge {
      flex: 0 0 auto;
      margin-left: auto;
      padding: 2px 6px;
      border-radius: 999px;
      background: #dc2626;
      color: #fff;
      font-size: 10px;
      letter-spacing: 0.25px;
      white-space: nowrap;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .option-label {
      flex: 0 0 22px;
      font-weight: 700;
      color: var(--primary-dark);
    }

    .option-text {
      min-width: 0;
      overflow-wrap: anywhere;
      white-space: pre-wrap;
    }

    .answer-key {
      margin-top: 34px;
      padding-top: 24px;
      border-top: 2px solid var(--line);
      break-before: page;
      page-break-before: always;
    }

    .answer-key h2 {
      margin: 0 0 16px;
      color: #0f172a;
      font-size: 22px;
    }

    .answer-grid {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 8px;
    }

    .answer-item {
      padding: 8px 6px;
      border: 1px solid var(--line);
      border-radius: 7px;
      background: #f8fafc;
      font-size: 13px;
      text-align: center;
    }

    .answer-item-correct {
      border-color: #fca5a5;
      background: #fff1f2;
      color: #b91c1c;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .paper-footer {
      margin-top: 34px;
      padding-top: 14px;
      border-top: 1px solid var(--line);
      color: var(--muted);
      font-size: 11px;
      text-align: center;
    }

    @media (max-width: 600px) {
      .print-toolbar {
        align-items: stretch;
        flex-direction: column;
        gap: 8px;
      }

      .print-button { width: 100%; }

      .paper {
        width: 100%;
        margin: 0;
        padding: 26px 18px 34px;
        border: 0;
        border-radius: 0;
        box-shadow: none;
      }

      .options { margin-left: 0; }
      .answer-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @page {
      size: A4;
      margin: 14mm 12mm 16mm;
    }

    @media print {
      html, body { background: #fff; }
      .print-toolbar { display: none; }
      .paper {
        width: auto;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
      }
      .exam-header { padding-top: 0; }
      .question { margin-bottom: 17px; }
      .answer-key { margin-top: 20px; }
    }
  </style>
</head>
<body>
  <div class="print-toolbar">
    <div class="toolbar-note">Trên máy tính chọn Lưu thành PDF. Trên điện thoại chọn In/Chia sẻ rồi lưu PDF để xem offline.</div>
    <button type="button" class="print-button" onclick="window.print()">In / Lưu thành PDF</button>
  </div>

  <main class="paper">
    <header class="exam-header">
      <p class="eyebrow">EduK Quiz - Ngân hàng đề thi</p>
      <h1><?= e($title) ?></h1>
      <div class="meta">
        <?php if ($author !== ''): ?><span>Tác giả: <?= e($author) ?></span><?php endif; ?>
        <?php if ($organization !== ''): ?><span>Đơn vị: <?= e($organization) ?></span><?php endif; ?>
        <span>Ngày tạo: <?= e($formattedDate) ?></span>
      </div>
    </header>

    <div class="summary">
      Có <?= count($questions) ?> câu hỏi. Phần đáp án tra cứu được đặt ở cuối tài liệu.
    </div>

    <section aria-label="Danh sách câu hỏi">
      <?php foreach ($questions as $index => $question): ?>
        <?php
          $questionNumber = (int)($question['question_num'] ?? ($index + 1));
          $options = [
              'A' => $question['option_a'] ?? '',
              'B' => $question['option_b'] ?? '',
              'C' => $question['option_c'] ?? '',
              'D' => $question['option_d'] ?? '',
              'E' => $question['option_e'] ?? '',
              'F' => $question['option_f'] ?? '',
              'G' => $question['option_g'] ?? '',
              'H' => $question['option_h'] ?? '',
          ];
          $correctLabels = answerLabels($question['correct_answer'] ?? '');
        ?>
        <article class="question">
          <div class="question-heading">
            <span class="question-number">Câu <?= $questionNumber ?></span>
            <div class="question-text"><?= e(formatQuestionTextForExport((string)$question['question_text'])) ?></div>
          </div>
          <?php if (trim((string)($question['image_url'] ?? '')) !== ''): ?>
            <img class="question-image" src="<?= e((string)$question['image_url']) ?>" alt="Hình minh họa câu <?= $questionNumber ?>">
          <?php endif; ?>
          <div class="options">
            <?php foreach ($options as $label => $optionText): ?>
              <?php if (trim((string)$optionText) !== ''): ?>
                <div class="option<?= in_array($label, $correctLabels, true) ? ' correct-option' : '' ?>">
                  <span class="option-label"><?= $label ?>.</span>
                  <span class="option-text"><?= e((string)$optionText) ?></span>
                  <?php if (in_array($label, $correctLabels, true)): ?>
                    <span class="correct-badge">ĐÁP ÁN ĐÚNG</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="answer-key" aria-label="Đáp án">
      <h2>Đáp án tra cứu</h2>
      <div class="answer-grid">
        <?php foreach ($questions as $index => $question): ?>
          <?php $questionNumber = (int)($question['question_num'] ?? ($index + 1)); ?>
          <div class="answer-item answer-item-correct">Câu <?= $questionNumber ?>: <strong><?= e(formatAnswer($question['correct_answer'] ?? '')) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </section>

    <footer class="paper-footer">Tài liệu được xuất từ EduK Quiz để xem offline trên điện thoại.</footer>
  </main>

  <script>
    window.addEventListener('load', function () {
      window.setTimeout(function () { window.print(); }, 350);
    });
  </script>
</body>
</html>
