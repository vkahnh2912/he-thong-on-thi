<?php
/**
 * quiz.php — Trang Ôn tập & Thi trắc nghiệm EduK
 * Hỗ trợ Chế độ Ôn tập & Thi thật, âm thanh, tính giờ, giải thích, lưu kết quả vào Database
 */
require_once __DIR__ . '/db.php';
$examId = (int)($_GET['exam_id'] ?? ($_GET['id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Làm bài thi - Hệ thống ôn tập & thi trắc nghiệm EduK</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .progress-bar-wrap {
      width: 100%;
      height: 8px;
      background: var(--card-border);
      border-radius: var(--radius-full);
      overflow: hidden;
      margin: 12px 0;
    }
    
    .progress-bar-fill {
      height: 100%;
      width: 0%;
      background: var(--primary-gradient);
      border-radius: var(--radius-full);
      transition: width 0.3s ease;
    }

    .question-image-wrap {
      margin-bottom: 20px;
      text-align: center;
      cursor: pointer;
    }
    
    .question-image-wrap img {
      max-width: 100%;
      max-height: 300px;
      border-radius: var(--radius-md);
      border: 1px solid var(--card-border);
      transition: transform 0.2s ease;
    }
    
    .question-image-wrap img:hover {
      transform: scale(1.02);
    }
    
    .stats-row {
      display: flex;
      gap: 12px;
      margin-top: 14px;
    }
    
    .stat-pill {
      flex: 1;
      padding: 10px;
      border-radius: var(--radius-sm);
      text-align: center;
      font-size: 13px;
      font-weight: 700;
    }
    
    .stat-pill.correct { background: var(--success-light); color: var(--success); }
    .stat-pill.wrong { background: var(--danger-light); color: var(--danger); }
  </style>
</head>
<body>

  <!-- Top Navigation Bar -->
  <div class="topbar">
    <a href="index.php" class="brand">
      <i class="fa-solid fa-graduation-cap"></i>
      <span>EduK Quiz</span>
    </a>
    <div class="topbar-actions">
      <a href="index.php" class="btn btn-outline" style="padding:6px 14px; font-size:13px; text-decoration:none;">
        <i class="fa-solid fa-arrow-left"></i> Danh sách đề
      </a>
      <button class="icon-btn" id="darkModeBtn" title="Chế độ tối/sáng">
        <i class="fa-solid fa-moon"></i>
      </button>
      <button class="icon-btn" id="fullscreenBtn" title="Toàn màn hình">
        <i class="fa-solid fa-expand"></i>
      </button>
      <div class="avatar-badge">
        <div class="avatar" id="userAvatar">
          <i class="fa-solid fa-user-graduate"></i>
        </div>
        <span id="userNameDisplay">Học viên</span>
      </div>
    </div>
  </div>

  <!-- Layout 3 cột -->
  <div class="layout">

    <!-- Cột trái: Điều khiển & Tiến độ -->
    <div class="sidebar-left">
      <div class="panel">
        <h3 id="examTitle">Đề thi</h3>

        <div class="field-label">Chế độ bài làm</div>
        <div class="mode-value" id="modeValueDisplay" style="font-weight:700; color:var(--primary); font-size:15px;">
          <i class="fa-solid fa-book-open"></i> Ôn tập
        </div>

        <div class="divider"></div>

        <div class="field-label">Thời gian làm bài</div>
        <div class="timer-card">
          <div class="timer-value" id="timerValue">00 : 00 : 00</div>
        </div>

        <div class="divider"></div>

        <div class="field-label">Tự động chuyển câu</div>
        <select class="select-control" id="autoNextSelect">
          <option value="off">Tắt</option>
          <option value="on">Bật (sau khi chọn)</option>
        </select>

        <div class="divider"></div>

        <label class="toggle-row">
          <input type="checkbox" id="soundToggle" checked>
          <span>Âm thanh chọn đáp án</span>
        </label>

        <div class="side-btn-row">
          <button class="btn btn-outline" id="backBtn"><i class="fa-solid fa-arrow-left"></i> Trở về</button>
          <button class="btn secondary" id="retryWrongSideBtn"><i class="fa-solid fa-rotate-left"></i> Làm câu sai</button>
        </div>
      </div>

      <div class="panel" style="margin-top:20px;">
        <h3>
          <span>Tiến Độ Làm Bài</span>
          <span id="progressPercent" style="color:var(--primary); font-size:14px;">0%</span>
        </h3>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" id="progressBarFill"></div>
        </div>
        <div style="font-size:13px; color:var(--text-muted); font-weight:600; display:flex; justify-content:space-between; margin-top:6px;">
          <span>Đã hoàn thành:</span>
          <b id="sectionProgress" style="color:var(--text-main);">0/0</b>
        </div>

        <div class="stats-row" id="statsRow">
          <div class="stat-pill correct">
            <i class="fa-solid fa-circle-check"></i> Đúng: <span id="correctCount">0</span>
          </div>
          <div class="stat-pill wrong">
            <i class="fa-solid fa-circle-xmark"></i> Sai: <span id="wrongCount">0</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Cột giữa: Nội dung câu hỏi -->
    <div class="panel">
      <div class="question-panel-header">
        <div class="question-num">Câu <span id="currentNum">1</span></div>
        <div class="question-type-tag" id="questionTypeTag">Một đáp án</div>
      </div>

      <div class="question-text" id="questionText">Đang tải nội dung câu hỏi...</div>

      <div class="question-image-wrap" id="questionImageWrap" style="display:none;">
        <img id="questionImage" src="" alt="Hình minh họa">
      </div>

      <div class="options-list" id="optionsContainer"></div>

      <!-- Lời giải chi tiết (nếu có) -->
      <div id="explanationBox" style="display:none; margin-top:20px; padding:14px; background:var(--primary-light); border-left:4px solid var(--primary); border-radius:8px; font-size:14px;">
        <div style="font-weight:700; color:var(--primary); margin-bottom:4px;"><i class="fa-solid fa-lightbulb"></i> Giải thích:</div>
        <div id="explanationText"></div>
      </div>

      <div class="nav-bottom" style="display:flex; justify-content:space-between; gap:14px; margin-top:28px;">
        <button class="btn btn-outline" id="prevBtn" style="width:auto; padding:12px 24px;"><i class="fa-solid fa-arrow-left"></i> Câu Trước</button>
        <button class="btn" id="nextBtn" style="width:auto; padding:12px 24px;">Câu Sau <i class="fa-solid fa-arrow-right"></i></button>
      </div>
    </div>

    <!-- Cột phải: Mục lục câu hỏi -->
    <div class="panel">
      <h3>
        <span>Mục Lục Câu Hỏi</span>
        <i class="fa-solid fa-border-all" style="color:var(--text-muted); font-size:14px;"></i>
      </h3>
      <div class="qgrid" id="qgrid"></div>

      <div class="submit-wrap" style="margin-top:20px;">
        <button class="btn success" id="submitBtn" style="padding:14px; font-size:16px;">
          <i class="fa-solid fa-paper-plane"></i> NỘP BÀI THI
        </button>
      </div>
    </div>

  </div>

  <!-- Lightbox Modal -->
  <div class="lightbox-modal" id="imageLightbox">
    <img id="imageLightboxImg" src="" alt="Xem ảnh phóng to">
  </div>

  <audio id="correctSound" preload="auto">
    <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=" type="audio/wav">
  </audio>

  <script src="script.js?v=20261006-answer-labels-2"></script>
  <script>
    const paramExamId = <?= $examId ?>;
    const javaDataVersion = '20260930-text-clean-2';
    const openSourceDataVersion = '20261005-dedupe-1';
    const linkedExamDataVersion = '20261006-link-sync-1';
    let user = localStorage.getItem('quizUser') || 'Học viên';
    document.getElementById('userNameDisplay').textContent = user;

    // DOM refs
    const examTitle = document.getElementById('examTitle');
    const modeValueDisplay = document.getElementById('modeValueDisplay');
    const currentNumEl = document.getElementById('currentNum');
    const questionTypeTag = document.getElementById('questionTypeTag');
    const questionText = document.getElementById('questionText');
    const questionImageWrap = document.getElementById('questionImageWrap');
    const questionImage = document.getElementById('questionImage');
    const optionsContainer = document.getElementById('optionsContainer');
    const explanationBox = document.getElementById('explanationBox');
    const explanationText = document.getElementById('explanationText');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const qgrid = document.getElementById('qgrid');
    const submitBtn = document.getElementById('submitBtn');
    const sectionProgress = document.getElementById('sectionProgress');
    const progressBarFill = document.getElementById('progressBarFill');
    const progressPercent = document.getElementById('progressPercent');
    const correctCountEl = document.getElementById('correctCount');
    const wrongCountEl = document.getElementById('wrongCount');
    const statsRow = document.getElementById('statsRow');
    const timerValue = document.getElementById('timerValue');
    const autoNextSelect = document.getElementById('autoNextSelect');
    const soundToggle = document.getElementById('soundToggle');
    const darkModeBtn = document.getElementById('darkModeBtn');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const backBtn = document.getElementById('backBtn');
    const retryWrongSideBtn = document.getElementById('retryWrongSideBtn');
    const correctSound = document.getElementById('correctSound');

    // Chế độ tối
    function applyDarkMode(on) {
      document.body.classList.toggle('dark', on);
      if (darkModeBtn) darkModeBtn.innerHTML = on ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
      localStorage.setItem('quizDarkMode', on ? '1' : '0');
    }
    applyDarkMode(localStorage.getItem('quizDarkMode') === '1');
    if (darkModeBtn) {
      darkModeBtn.addEventListener('click', () => {
        applyDarkMode(!document.body.classList.contains('dark'));
      });
    }

    // Toàn màn hình
    if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen().catch(() => {});
        } else {
          document.exitFullscreen().catch(() => {});
        }
      });
    }

    let questions = [];
    let totalQuestions = 0;
    let examMode = false;
    let userAnswers = [];
    let currentIndex = 0;
    let elapsedSeconds = 0;
    let timerInterval = null;

    // Khởi tạo bài thi từ Server hoặc LocalStorage
    async function initQuizSession() {
      let cachedData = localStorage.getItem('quizData');
      const cachedExamId = localStorage.getItem('quizExamId');
      const cachedSessionId = localStorage.getItem('quizSessionId');
      const cachedDataVersion = localStorage.getItem('quizDataVersion');
      let cachedQuestions = null;
      try {
        cachedQuestions = cachedData ? JSON.parse(cachedData) : null;
      } catch (parseError) {
        cachedQuestions = null;
      }
      const hasBrokenJavaCache = paramExamId === 28
        && Array.isArray(cachedQuestions)
        && cachedQuestions.length > 0
        && cachedQuestions.some(question => !question.A || !question.B || !question.C || !question.D);
      const needsJavaDataRefresh = paramExamId === 28 && cachedDataVersion !== javaDataVersion;
      const needsOpenSourceDataRefresh = paramExamId === 30 && cachedDataVersion !== openSourceDataVersion;
      const needsLinkedExamDataRefresh = (paramExamId === 32 || paramExamId === 33) && cachedDataVersion !== linkedExamDataVersion;

      // Chỉ dùng cache khi nó thuộc đúng phiên vừa được launchQuiz tạo.
      // Cache cũ có thể vẫn chứa 296 câu dù database đã được cập nhật đủ 300.
      if (paramExamId > 0 && (!cachedData || cachedExamId != paramExamId || !cachedSessionId || hasBrokenJavaCache || needsJavaDataRefresh || needsOpenSourceDataRefresh || needsLinkedExamDataRefresh)) {
        try {
          const res = await fetch(`api.php?action=get_exam&id=${paramExamId}`);
          const json = await res.json();
          if (json.success) {
            const preserveSessionState = cachedExamId == paramExamId && Boolean(cachedSessionId);
            const preservedAnswers = preserveSessionState ? localStorage.getItem('quizAnswers') : null;
            const preservedResult = preserveSessionState ? localStorage.getItem('quizResult') : null;
            const preservedElapsed = preserveSessionState ? localStorage.getItem('quizElapsed') : null;
            questions = json.questions;
            localStorage.setItem('quizData', JSON.stringify(questions));
            localStorage.setItem('quizExamName', json.exam.title);
            localStorage.setItem('quizExamId', json.exam.id);
            if (!preserveSessionState) {
              localStorage.setItem('quizSessionId', `${Date.now()}-${Math.random().toString(36).slice(2)}`);
              localStorage.removeItem('quizAnswers');
              localStorage.removeItem('quizResult');
              localStorage.setItem('quizElapsed', '0');
            } else {
              if (preservedAnswers !== null) localStorage.setItem('quizAnswers', preservedAnswers);
              if (preservedResult !== null) localStorage.setItem('quizResult', preservedResult);
              if (preservedElapsed !== null) localStorage.setItem('quizElapsed', preservedElapsed);
            }
            if (paramExamId === 28) localStorage.setItem('quizDataVersion', javaDataVersion);
            if (paramExamId === 30) localStorage.setItem('quizDataVersion', openSourceDataVersion);
            if (paramExamId === 32 || paramExamId === 33) localStorage.setItem('quizDataVersion', linkedExamDataVersion);
            if (!localStorage.getItem('quizMode')) {
              localStorage.setItem('quizMode', 'practice');
            }
          } else {
            alert('Không tải được đề thi: ' + json.message);
            window.location.href = 'index.php';
            return;
          }
        } catch (err) {
          alert('Lỗi kết nối CSDL: ' + err.message);
          window.location.href = 'index.php';
          return;
        }
      } else if (cachedData) {
        questions = JSON.parse(cachedData);
      } else {
        window.location.href = 'index.php';
        return;
      }

      // Link mở trực tiếp không có phiên launch trước đó phải bắt đầu từ 0 giây.
      if (!localStorage.getItem('quizSessionId')) {
        localStorage.setItem('quizSessionId', `${Date.now()}-${Math.random().toString(36).slice(2)}`);
        localStorage.removeItem('quizAnswers');
        localStorage.removeItem('quizResult');
        localStorage.setItem('quizElapsed', '0');
      }

      totalQuestions = questions.length;
      examMode = isExamMode();
      const storedAnswers = JSON.parse(localStorage.getItem('quizAnswers') || 'null');
      userAnswers = Array.from({ length: totalQuestions }, (_, index) => (
        Array.isArray(storedAnswers) ? (storedAnswers[index] ?? null) : null
      ));

      // Cập nhật giao diện tiêu đề
      const storedExamName = localStorage.getItem('quizExamName');
      if (storedExamName) examTitle.textContent = storedExamName;

      if (examMode) {
        modeValueDisplay.innerHTML = '<i class="fa-solid fa-file-pen"></i> Thi thật';
        statsRow.style.display = 'none';
        retryWrongSideBtn.style.display = 'none';
      } else {
        modeValueDisplay.innerHTML = '<i class="fa-solid fa-book-open"></i> Ôn tập';
      }

      // Khởi động đồng hồ
      elapsedSeconds = parseInt(localStorage.getItem('quizElapsed') || '0', 10);
      timerValue.textContent = formatTime(elapsedSeconds);
      timerInterval = setInterval(() => {
        elapsedSeconds++;
        localStorage.setItem('quizElapsed', elapsedSeconds);
        timerValue.textContent = formatTime(elapsedSeconds);
      }, 1000);

      // Hiển thị câu hỏi đầu tiên
      renderQuestion(0);
    }

    function formatTime(totalSec) {
      const h = Math.floor(totalSec / 3600);
      const m = Math.floor((totalSec % 3600) / 60);
      const s = totalSec % 60;
      const pad = n => String(n).padStart(2, '0');
      return `${pad(h)} : ${pad(m)} : ${pad(s)}`;
    }

    function isMultipleQuestion(q) {
      return getCorrectLabels(q).length > 1 || q.multipleAnswers === true;
    }

    function isAnswered(answer) {
      return getAnswerLabels(answer).length > 0;
    }

    // Hàm làm sạch triệt để mọi tiền tố 'Câu X:', 'Câu số X:' để không bao giờ bị lặp 2 lần
    function cleanQuestionText(text) {
      if (!text) return '';
      return text.replace(/^(?:(?:Câu\s*(?:số)?\s*\d+|\d+)[\s.:\)\/\-]+)+/gi, '').trim();
    }

    // Giữ câu hỏi dễ đọc khi dữ liệu chứa đoạn code bị dồn thành một dòng.
    // Chỉ format chuỗi có dấu hiệu là code; không thay đổi nội dung đáp án/database.
    function formatQuestionText(text) {
      let value = cleanQuestionText(text);
      if (!value) return '';

      // Bộ đề C++ có nhiều đoạn bắt đầu bằng #include/int main/cout nhưng
      // PDF đã làm mất xuống dòng. Nhận diện đủ các dấu hiệu này để không
      // bỏ sót các câu giống câu 1, 2, 35-38, 177-180.
      const looksLikeCode = /(?:#include\b|\b(?:int|void|float|double|char|bool)\s+main\s*\(|\btypedef\s+struct\b|\b(?:static\s+void|private\s+void|public\s+(?:class|void))|SqlConnection|SqlCommand|Console\.|dataGridView|\b(?:cout|cin|printf|scanf|strlen|sqrt|acos)\b|\b(?:if|for|while|switch)\s*\(|\bclass\s+\w+\s*\{)/i.test(value);
      if (!looksLikeCode) return value;

      // Tách các câu lệnh đánh số: "1.... 2.... 3....".
      value = value
        .replace(/\s+(?=\d+\s*(?::|\.(?!\d)))/g, '\n')
        .replace(/(^|\n)(\d+\s*(?::|\.(?!\d)))(?=\S)/gm, '$1$2 ');
      value = value.replace(/(?<!\d):\s+(?=(?:#include|const|int|float|double|char|bool|typedef|using|class|struct|for|while|if|switch|static|public|private)\b)/ig, ':\n');
      value = value.replace(/\b(public|private|protected):\s+/ig, '$1:\n');

      // Một số PDF làm mất cả khoảng trắng quanh header. Khôi phục header
      // C++ phổ biến, đồng thời tách code ra khỏi phần dẫn/trailing prompt.
      value = value
        // Chỉ tách header đầu tiên; các cụm "Lệnh #include..." ở phần hỏi
        // phía sau không được coi là một block code mới.
        .replace(/\s+(?=#include\b)/i, '\n')
        .replace(/#include\s+(?=(?:int|void|float|double|char|bool|typedef|using|class|struct)\b)/ig, '#include <iostream.h>\n')
        .replace(/(#include\s*(?:<[^>\n]+>|["'][^"\n]+["']))\s+(?=(?:#include|int|void|float|double|char|bool|typedef|using|class|struct)\b)/ig, '$1\n')
        .replace(/(\b(?:int|void|float|double|char|bool)\s+main\s*\(\s*(?:void)?\s*\))\s+(?=(?:int|float|double|char|bool|long|short|string)\s+\w)/ig, '$1 {\n')
        .replace(/(\b(?:int|void|float|double|char|bool)\s+main\s*\(\s*(?:void)?\s*\))\s+(?=[^{])/ig, '$1\n')
        .replace(/(\})\s*(?=(?:#include|typedef|class|struct|int|void|public|private|protected)\b)/g, '$1\n');

      let formatted = '';
      let quote = null;
      let escaped = false;
      let parenDepth = 0;
      let bracketDepth = 0;
      for (const char of value) {
        if (quote !== null) {
          formatted += char;
          if (escaped) {
            escaped = false;
          } else if (char === '\\') {
            escaped = true;
          } else if (char === quote) {
            quote = null;
          }
          continue;
        }

        if (char === '"' || char === "'") {
          quote = char;
          formatted += char;
        } else if (char === '(') {
          parenDepth++;
          formatted += char;
        } else if (char === ')') {
          parenDepth = Math.max(0, parenDepth - 1);
          formatted += char;
        } else if (char === '[') {
          bracketDepth++;
          formatted += char;
        } else if (char === ']') {
          bracketDepth = Math.max(0, bracketDepth - 1);
          formatted += char;
        } else if (char === ';') {
          formatted += ';';
          // Không ngắt dòng các dấu ; bên trong phần đầu của for (...).
          if (parenDepth === 0 && bracketDepth === 0) formatted += '\n';
        } else if (char === '{') {
          formatted += '{\n';
        } else if (char === '}') {
          formatted += '\n}\n';
        } else {
          formatted += char;
        }
      }

      const normalized = formatted
        .replace(/\?\s+(?=(?:static|private|public|class|int|if|for|while|SqlConnection|dataGridView)\b)/ig, '?\n')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n[ \t]+/g, '\n')
        // Không để dấu phẩy của mảng và dấu ; đứng thành dòng riêng.
        .replace(/\{\s*\n\s*([^{}\n]*,[^{}\n]*)\s*\n\s*\}/g, '{ $1 }')
        .replace(/\n\s*,\s*\n?\s*\{/g, ',\n{')
        .replace(/\}\s*\n\s*;\s*\n/g, '};\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();

      // Thụt lề tối thiểu theo cặp ngoặc để đoạn code đọc như một khối chuẩn.
      let indentLevel = 0;
      return normalized.split('\n').map(line => {
        const trimmed = line.trim();
        if (!trimmed) return '';
        if (trimmed.startsWith('}')) indentLevel = Math.max(0, indentLevel - 1);
        const result = '  '.repeat(indentLevel) + trimmed;
        const openCount = (trimmed.match(/\{/g) || []).length;
        const closeCount = (trimmed.match(/\}/g) || []).length;
        indentLevel = Math.max(0, indentLevel + openCount - closeCount);
        return result;
      }).join('\n').replace(/\n{2,}/g, '\n').trim();
    }

    // Hiển thị câu hỏi
    function renderQuestion(index) {
      if (index < 0 || index >= totalQuestions) return;
      currentIndex = index;
      const q = questions[index];
      const multiple = isMultipleQuestion(q);

      currentNumEl.textContent = index + 1;
      questionTypeTag.textContent = multiple ? 'Nhiều đáp án' : 'Một đáp án';
      // Ở trên đã có badge tím 'Câu X', bên dưới hiển thị nội dung câu hỏi sạch sẽ, không bị lặp
      questionText.textContent = formatQuestionText(q.question);

      if (q.image) {
        questionImage.src = q.image;
        questionImageWrap.style.display = 'block';
      } else {
        questionImage.removeAttribute('src');
        questionImageWrap.style.display = 'none';
      }

      const answeredTotal = userAnswers.filter(isAnswered).length;
      sectionProgress.textContent = `${answeredTotal}/${totalQuestions}`;

      const percent = totalQuestions ? Math.round((answeredTotal / totalQuestions) * 100) : 0;
      progressBarFill.style.width = percent + '%';
      progressPercent.textContent = percent + '%';

      if (!examMode) {
        let correctSoFar = 0, wrongSoFar = 0;
        userAnswers.forEach((ans, i) => {
          if (!isAnswered(ans)) return;
          if (isCorrectAnswer(ans, questions[i])) correctSoFar++;
          else wrongSoFar++;
        });
        correctCountEl.textContent = correctSoFar;
        wrongCountEl.textContent = wrongSoFar;
      }

      // Xóa và vẽ lại các đáp án A-H
      optionsContainer.innerHTML = '';
      const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
      const userAnswer = userAnswers[index];
      const selectedLabels = getAnswerLabels(userAnswer);
      const correctLabels = getCorrectLabels(q);

      letters.forEach(letter => {
        if (!q[letter]) return;
        const row = document.createElement('div');
        row.className = 'option-row';
        // Render answer text as text nodes.  Java snippets often contain
        // <, >, &, quotes or line breaks; injecting them through innerHTML
        // makes the browser interpret part of the answer as markup.
        const radio = document.createElement('span');
        radio.className = 'option-radio';
        const optionText = document.createElement('span');
        optionText.className = 'option-text';
        const label = document.createElement('b');
        label.textContent = `${letter}.`;
        const optionValue = String(q[letter] ?? '').trim();
        optionText.append(label, document.createTextNode(` ${optionValue}`));
        row.append(radio, optionText);

        if (examMode) {
          if (selectedLabels.includes(letter)) row.classList.add('selected');
          row.addEventListener('click', () => selectAnswer(letter));
        } else if (isAnswered(userAnswer)) {
          if (!multiple) row.classList.add('locked');
          if (correctLabels.includes(letter)) {
            row.classList.add('correct');
          } else if (selectedLabels.includes(letter)) {
            row.classList.add('wrong');
          }
          if (multiple && !isCorrectAnswer(userAnswer, q)) {
            row.addEventListener('click', () => selectAnswer(letter));
          }
        } else {
          row.addEventListener('click', () => selectAnswer(letter));
        }

        optionsContainer.appendChild(row);
      });

      // Hiển thị lời giải nếu có và đã trả lời
      if (!examMode && isAnswered(userAnswer) && q.explanation) {
        explanationBox.style.display = 'block';
        explanationText.textContent = q.explanation;
      } else {
        explanationBox.style.display = 'none';
      }

      renderGrid();
      prevBtn.disabled = (index === 0);
      nextBtn.disabled = (index === totalQuestions - 1);
    }

    // Chọn đáp án
    function selectAnswer(letter) {
      const q = questions[currentIndex];
      if (isMultipleQuestion(q)) {
        const current = getAnswerLabels(userAnswers[currentIndex]);
        userAnswers[currentIndex] = current.includes(letter)
          ? current.filter(l => l !== letter)
          : current.concat(letter);
        if (userAnswers[currentIndex].length === 0) userAnswers[currentIndex] = null;
      } else {
        userAnswers[currentIndex] = letter;
      }
      localStorage.setItem('quizAnswers', JSON.stringify(userAnswers));

      if (soundToggle.checked) {
        correctSound.currentTime = 0;
        correctSound.play().catch(() => {});
      }

      renderQuestion(currentIndex);

      if (!isMultipleQuestion(q) && autoNextSelect.value === 'on' && currentIndex < totalQuestions - 1) {
        setTimeout(() => renderQuestion(currentIndex + 1), 400);
      }
    }

    // Lưới danh sách câu hỏi bên phải
    function renderGrid() {
      qgrid.innerHTML = '';
      let currentCell = null;
      for (let i = 0; i < totalQuestions; i++) {
        const cell = document.createElement('button');
        cell.className = 'qcell';
        cell.textContent = i + 1;
        if (isAnswered(userAnswers[i])) {
          if (examMode) {
            cell.classList.add('answered-neutral');
          } else {
            cell.classList.add(isCorrectAnswer(userAnswers[i], questions[i]) ? 'answered-correct' : 'answered-wrong');
          }
        }
        if (i === currentIndex) {
          cell.classList.add('current');
          currentCell = cell;
        }
        cell.addEventListener('click', () => renderQuestion(i));
        qgrid.appendChild(cell);
      }
      if (currentCell) {
        currentCell.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    }

    // Điều hướng câu trước / câu sau
    prevBtn.addEventListener('click', () => {
      if (currentIndex > 0) renderQuestion(currentIndex - 1);
    });
    nextBtn.addEventListener('click', () => {
      if (currentIndex < totalQuestions - 1) renderQuestion(currentIndex + 1);
    });

    // Lightbox phóng to ảnh
    const imageLightbox = document.getElementById('imageLightbox');
    const imageLightboxImg = document.getElementById('imageLightboxImg');
    questionImage.addEventListener('click', () => {
      if (!questionImage.src) return;
      imageLightboxImg.src = questionImage.src;
      imageLightbox.classList.add('open');
    });
    imageLightbox.addEventListener('click', () => {
      imageLightbox.classList.remove('open');
    });

    // Phím tắt mũi tên trái/phải
    document.addEventListener('keydown', (e) => {
      const tag = document.activeElement.tagName;
      if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;

      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        if (currentIndex > 0) renderQuestion(currentIndex - 1);
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        if (currentIndex < totalQuestions - 1) renderQuestion(currentIndex + 1);
      }
    });

    backBtn.addEventListener('click', () => {
      if (confirm('Bạn có muốn quay về trang danh sách đề thi không?')) {
        window.location.href = 'index.php';
      }
    });

    retryWrongSideBtn.addEventListener('click', () => {
      const wrongIndexes = userAnswers
        .map((a, i) => (isAnswered(a) && !isCorrectAnswer(a, questions[i])) ? i : -1)
        .filter(i => i !== -1);

      if (wrongIndexes.length === 0) {
        alert('Tuyệt vời! Hiện tại chưa có câu nào sai để làm lại.');
        return;
      }

      const wrongQuestions = wrongIndexes.map(i => questions[i]);
      localStorage.setItem('quizData', JSON.stringify(wrongQuestions));
      localStorage.removeItem('quizAnswers');
      localStorage.removeItem('quizElapsed');
      localStorage.setItem('quizSessionId', `${Date.now()}-${Math.random().toString(36).slice(2)}`);
      location.reload();
    });

    // Nộp bài thi
    submitBtn.addEventListener('click', async () => {
      const unanswered = userAnswers.filter(a => !isAnswered(a)).length;
      if (unanswered > 0) {
        const confirmSubmit = confirm(`Bạn còn ${unanswered} câu chưa trả lời. Bạn có chắc chắn muốn nộp bài ngay?`);
        if (!confirmSubmit) return;
      }

      clearInterval(timerInterval);

      let correctCount = 0;
      questions.forEach((q, i) => {
        if (isCorrectAnswer(userAnswers[i], q)) correctCount++;
      });

      const wrongCount = totalQuestions - correctCount;
      const score = (correctCount / totalQuestions * 10).toFixed(2);
      const percent = (correctCount / totalQuestions * 100).toFixed(1);

      const result = {
        total: totalQuestions,
        correct: correctCount,
        wrong: wrongCount,
        score: score,
        percent: percent,
        timeSpent: elapsedSeconds
      };

      const currentExamId = localStorage.getItem('quizExamId') || paramExamId || 0;
      const examName = localStorage.getItem('quizExamName') || 'Đề thi';
      const mode = localStorage.getItem('quizMode') || 'practice';

      // Lưu kết quả vào CSDL Database qua API
      try {
        await fetch('api.php?action=save_attempt', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            exam_id: currentExamId,
            user_name: user,
            mode: mode,
            score: score,
            correct_count: correctCount,
            total_questions: totalQuestions,
            duration_seconds: elapsedSeconds
          })
        });
      } catch (err) {
        console.error('Lỗi lưu điểm lên server:', err);
      }

      // Lưu vào LocalStorage
      saveQuizHistory({
        user: user,
        examName: examName,
        mode: mode,
        score: score,
        correct: correctCount,
        total: totalQuestions,
        date: new Date().toISOString()
      });

      localStorage.setItem('quizResult', JSON.stringify(result));
      localStorage.removeItem('quizElapsed');
      localStorage.removeItem('quizSessionId');
      window.location.href = 'result.php';
    });

    // Khởi chạy khi load trang
    initQuizSession();
  </script>
</body>
</html>
