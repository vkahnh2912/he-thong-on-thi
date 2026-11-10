<?php
/**
 * db.php — Quản lý kết nối Database (Hỗ trợ tự động MySQL & SQLite fallback)
 * Tự động tạo bảng và khởi tạo dữ liệu mẫu chuẩn theo giao diện
 */

class Database {
    private static ?PDO $instance = null;
    private static string $driverType = 'unknown';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $sqlitePath = __DIR__ . DIRECTORY_SEPARATOR . 'database.sqlite';
        $preferredDriver = strtolower(trim((string)(getenv('EDUK_DB_DRIVER') ?: '')));

        // Avoid a MySQL timeout on every local PHP built-in-server request.
        if ($preferredDriver === 'sqlite' || ($preferredDriver === '' && PHP_SAPI === 'cli-server' && file_exists($sqlitePath))) {
            self::$instance = self::createSqliteConnection($sqlitePath);
            self::$driverType = 'sqlite';
            self::initTables(self::$instance, self::$driverType);
            return self::$instance;
        }

        $dbHost = '127.0.0.1';
        $dbName = 'quiz_db';
        $dbUser = 'root';
        $dbPass = '';

        // Thử kết nối MySQL trước
        try {
            // Kiểm tra kết nối tới MySQL server
            $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 2
            ]);

            // Tự động tạo Database nếu chưa tồn tại
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            self::$instance = $pdo;
            self::$driverType = 'mysql';
        } catch (Throwable $e) {
            // Nếu MySQL chưa bật hoặc lỗi kết nối -> Fallback sang SQLite để luôn chạy trơn tru 100%
            self::$instance = self::createSqliteConnection($sqlitePath);
            self::$driverType = 'sqlite';
        }

        // Tạo các bảng nếu chưa có
        self::initTables(self::$instance, self::$driverType);

        return self::$instance;
    }

    private static function createSqliteConnection(string $sqlitePath): PDO {
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }

    public static function getDriverType(): string {
        return self::$driverType;
    }

    private static function initTables(PDO $pdo, string $driver): void {
        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `exams` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `title` VARCHAR(255) NOT NULL,
                    `description` TEXT NULL,
                    `author` VARCHAR(150) NOT NULL DEFAULT 'Học viên',
                    `organization` VARCHAR(255) NOT NULL DEFAULT 'Đại học Kinh doanh và Công nghệ Hà Nội',
                    `thumbnail` TEXT NULL,
                    `question_count` INT NOT NULL DEFAULT 0,
                    `views_count` INT NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `questions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `exam_id` INT NOT NULL,
                    `question_num` INT NOT NULL DEFAULT 1,
                    `question_text` TEXT NOT NULL,
                    `option_a` TEXT NULL,
                    `option_b` TEXT NULL,
                    `option_c` TEXT NULL,
                    `option_d` TEXT NULL,
                    `option_e` TEXT NULL,
                    `option_f` TEXT NULL,
                    `option_g` TEXT NULL,
                    `option_h` TEXT NULL,
                    `correct_answer` VARCHAR(100) NOT NULL DEFAULT 'A',
                    `explanation` TEXT NULL,
                    `image_url` TEXT NULL,
                    INDEX (`exam_id`),
                    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `exam_attempts` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `exam_id` INT NOT NULL,
                    `user_name` VARCHAR(150) NOT NULL,
                    `mode` VARCHAR(50) NOT NULL DEFAULT 'practice',
                    `score` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
                    `correct_count` INT NOT NULL DEFAULT 0,
                    `total_questions` INT NOT NULL DEFAULT 0,
                    `duration_seconds` INT NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX (`exam_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } else {
            // SQLite
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS exams (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    description TEXT,
                    author TEXT NOT NULL DEFAULT 'Học viên',
                    organization TEXT NOT NULL DEFAULT 'Đại học Kinh doanh và Công nghệ Hà Nội',
                    thumbnail TEXT,
                    question_count INTEGER NOT NULL DEFAULT 0,
                    views_count INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS questions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    exam_id INTEGER NOT NULL,
                    question_num INTEGER NOT NULL DEFAULT 1,
                    question_text TEXT NOT NULL,
                    option_a TEXT,
                    option_b TEXT,
                    option_c TEXT,
                    option_d TEXT,
                    option_e TEXT,
                    option_f TEXT,
                    option_g TEXT,
                    option_h TEXT,
                    correct_answer TEXT NOT NULL DEFAULT 'A',
                    explanation TEXT,
                    image_url TEXT,
                    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
                );
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS exam_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    exam_id INTEGER NOT NULL,
                    user_name TEXT NOT NULL,
                    mode TEXT NOT NULL DEFAULT 'practice',
                    score REAL NOT NULL DEFAULT 0.0,
                    correct_count INTEGER NOT NULL DEFAULT 0,
                    total_questions INTEGER NOT NULL DEFAULT 0,
                    duration_seconds INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ");

            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_questions_exam_id ON questions (exam_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_exam_attempts_exam_id ON exam_attempts (exam_id)');
        }

        self::ensureExtendedOptionColumns($pdo, $driver);

        // Tự động seed dữ liệu mẫu ban đầu nếu bảng exams trống
        self::seedDefaultData($pdo);
    }

    private static function ensureExtendedOptionColumns(PDO $pdo, string $driver): void {
        foreach (['f', 'g', 'h'] as $label) {
            $column = 'option_' . $label;
            if ($driver === 'mysql') {
                $check = $pdo->prepare('SHOW COLUMNS FROM questions LIKE ?');
                $check->execute([$column]);
                if (!$check->fetch()) {
                    $pdo->exec("ALTER TABLE questions ADD COLUMN `{$column}` TEXT NULL AFTER `option_e`");
                }
            } else {
                $columns = $pdo->query('PRAGMA table_info(questions)')->fetchAll(PDO::FETCH_ASSOC);
                $known = array_column($columns, 'name');
                if (!in_array($column, $known, true)) {
                    $pdo->exec("ALTER TABLE questions ADD COLUMN {$column} TEXT");
                }
            }
        }
    }

    private static function seedDefaultData(PDO $pdo): void {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
        if ($count > 0) {
            return;
        }

        // Đọc các file JSON có sẵn để lấy câu hỏi thật
        $dssFile = __DIR__ . DIRECTORY_SEPARATOR . 'he-ho-tro-quyet-dinh-quiz.json';
        $iotFile = __DIR__ . DIRECTORY_SEPARATOR . 'internet-van-vat-iot-3.json';
        $sampleFile = __DIR__ . DIRECTORY_SEPARATOR . 'questions.json';

        $readQuizJson = function($path) {
            if (!file_exists($path)) return [];
            $content = file_get_contents($path);
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            }
            if (substr($content, 0, 2) === "\xFF\xFE" || substr($content, 0, 2) === "\xFE\xFF") {
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
            }
            $data = json_decode($content, true);
            if (!is_array($data)) return [];
            if (isset($data['questions']) && is_array($data['questions'])) return $data['questions'];
            if (isset($data['data']) && is_array($data['data'])) return $data['data'];
            return $data;
        };

        $dssQuestions = $readQuizJson($dssFile);
        $iotQuestions = $readQuizJson($iotFile);
        $sampleQuestions = $readQuizJson($sampleFile);

        // Tạo mảng câu hỏi phong phú đạt 200+ câu như trong ảnh
        $makeQuestionBatch = function($baseList, $targetCount) {
            if (empty($baseList)) return [];
            $result = [];
            while (count($result) < $targetCount) {
                foreach ($baseList as $item) {
                    $result[] = $item;
                    if (count($result) >= $targetCount) break;
                }
            }
            return $result;
        };

        // Chuẩn bị 4 đề "Kỹ nghệ phần mềm" như trong hình ảnh của anh Khánh
        $initialExams = [
            [
                'title' => 'Kỹ nghệ phần mềm',
                'author' => 'Moon San',
                'organization' => 'Đại học Kinh doanh và Công nghệ...',
                'thumbnail' => 'chalkboard',
                'views_count' => 1437,
                'created_at' => '2026-06-09 08:30:00',
                'questions' => $makeQuestionBatch($dssQuestions, 230)
            ],
            [
                'title' => 'Kỹ nghệ phần mềm',
                'author' => 'Thiên Đế Thích',
                'organization' => 'Đại học Kinh doanh và Công nghệ...',
                'thumbnail' => 'book_yellow',
                'views_count' => 213,
                'created_at' => '2025-06-27 14:15:00',
                'questions' => $makeQuestionBatch($iotQuestions, 209)
            ],
            [
                'title' => 'Kỹ nghệ phần mềm',
                'author' => 'Công nghệ',
                'organization' => 'Đại học Kinh doanh và Công nghệ...',
                'thumbnail' => 'book_orange',
                'views_count' => 486,
                'created_at' => '2025-06-27 10:20:00',
                'questions' => $makeQuestionBatch($dssQuestions, 230)
            ],
            [
                'title' => 'Kỹ nghệ phần mềm',
                'author' => 'Trần Văn Đạt Đạt',
                'organization' => 'Đại học Kinh doanh và Công nghệ...',
                'thumbnail' => 'book_yellow',
                'views_count' => 644,
                'created_at' => '2025-06-26 16:45:00',
                'questions' => $makeQuestionBatch($iotQuestions, 227)
            ],
            [
                'title' => 'Hệ hỗ trợ quyết định (DSS)',
                'author' => 'Khoa CNTT',
                'organization' => 'Đại học Kinh doanh và Công nghệ Hà Nội',
                'thumbnail' => 'book_blue',
                'views_count' => 892,
                'created_at' => '2026-08-15 09:00:00',
                'questions' => $dssQuestions
            ],
            [
                'title' => 'Internet vạn vật (IoT)',
                'author' => 'Bộ môn Mạng & IoT',
                'organization' => 'Đại học Kinh doanh và Công nghệ Hà Nội',
                'thumbnail' => 'book_yellow',
                'views_count' => 1105,
                'created_at' => '2026-08-20 11:30:00',
                'questions' => $iotQuestions
            ]
        ];

        foreach ($initialExams as $item) {
            $qList = is_array($item['questions']) ? $item['questions'] : [];
            $countQ = count($qList);

            $stmt = $pdo->prepare("
                INSERT INTO exams (title, description, author, organization, thumbnail, question_count, views_count, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $item['title'],
                'Ngân hàng câu hỏi trắc nghiệm ' . $item['title'],
                $item['author'],
                $item['organization'],
                $item['thumbnail'],
                $countQ,
                $item['views_count'],
                $item['created_at'],
                $item['created_at']
            ]);

            $examId = (int)$pdo->lastInsertId();

            if ($countQ > 0) {
                $qStmt = $pdo->prepare("
                    INSERT INTO questions (exam_id, question_num, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $questionIndex = 1;
                foreach ($qList as $q) {
                    if (!is_array($q)) continue;
                    $qText = $q['question'] ?? $q['q'] ?? ('Câu hỏi ' . $questionIndex);
                    $a = $q['A'] ?? $q['a'] ?? ($q['options']['A'] ?? '');
                    $b = $q['B'] ?? $q['b'] ?? ($q['options']['B'] ?? '');
                    $c = $q['C'] ?? $q['c'] ?? ($q['options']['C'] ?? '');
                    $d = $q['D'] ?? $q['d'] ?? ($q['options']['D'] ?? '');
                    $e = $q['E'] ?? $q['e'] ?? ($q['options']['E'] ?? '');

                    $correct = $q['correct'] ?? $q['answer'] ?? 'A';
                    if (is_array($correct)) {
                        $correct = json_encode($correct, JSON_UNESCAPED_UNICODE);
                    }

                    $qStmt->execute([
                        $examId,
                        $questionIndex,
                        $qText,
                        $a,
                        $b,
                        $c,
                        $d,
                        $e,
                        (string)$correct,
                        $q['explanation'] ?? '',
                        $q['image'] ?? ($q['img'] ?? null)
                    ]);
                    $questionIndex++;
                }
            }
        }
    }
}
