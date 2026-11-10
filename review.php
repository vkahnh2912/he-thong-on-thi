<?php
/**
 * review.php — Trang Xem Lại Đáp Án Chi Tiết EduK
 */
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xem đáp án chi tiết - Hệ thống ôn tập & thi trắc nghiệm EduK</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="centered-page">

  <div class="card review-card" style="max-width:800px; width:100%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <a href="result.php" style="color:var(--text-muted); text-decoration:none; font-weight:700; font-size:14px;">
        <i class="fa-solid fa-arrow-left"></i> Quay lại kết quả
      </a>
      <button class="icon-btn" id="darkModeBtn" title="Chế độ tối/sáng">
        <i class="fa-solid fa-moon"></i>
      </button>
    </div>

    <h2><i class="fa-solid fa-list-check" style="color:var(--primary);"></i> Xem Đáp Án Chi Tiết</h2>

    <p id="reviewSummary" class="note" style="margin-bottom:20px; font-weight:600; font-size:15px; color:var(--primary);"></p>

    <div class="filter-row">
      <button class="filter-btn active" data-filter="all"><i class="fa-solid fa-layer-group"></i> Tất cả câu hỏi</button>
      <button class="filter-btn" data-filter="wrong"><i class="fa-solid fa-circle-xmark" style="color:var(--danger);"></i> Câu sai</button>
      <button class="filter-btn" data-filter="correct"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Câu đúng</button>
      <button class="filter-btn" data-filter="skipped"><i class="fa-solid fa-circle-question" style="color:var(--warning);"></i> Chưa trả lời</button>
    </div>

    <div id="reviewList"></div>

    <div class="btn-row" style="margin-top:24px;">
      <button class="btn secondary" id="backBtn">
        <i class="fa-solid fa-arrow-left"></i> Quay lại kết quả
      </button>
      <button class="btn" id="homeBtn">
        <i class="fa-solid fa-house"></i> Về danh sách đề thi
      </button>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
    // Dark mode
    const darkModeBtn = document.getElementById('darkModeBtn');
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

    const quizData = JSON.parse(localStorage.getItem('quizData') || 'null');
    const userAnswers = JSON.parse(localStorage.getItem('quizAnswers') || 'null');
    const result = JSON.parse(localStorage.getItem('quizResult') || 'null');

    if (!quizData || !userAnswers || !result) {
      window.location.href = 'index.php';
    }

    const examName = localStorage.getItem('quizExamName') || 'Đề thi';
    document.getElementById('reviewSummary').textContent =
      `${examName} — ${result.correct}/${result.total} câu đúng (${result.percent}%)`;

    let currentFilter = 'all';
    const reviewList = document.getElementById('reviewList');

    function answerLabels(answer) {
      if (answer === null || answer === undefined) return [];
      return Array.isArray(answer) ? answer : [answer];
    }
    function correctLabels(q) {
      return Array.isArray(q.correct) ? q.correct : [q.correct];
    }
    function escapeReviewHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
    function normalizeOptionText(value) {
      return String(value ?? '').replace(/\s+/g, ' ').trim();
    }
    function answerText(q, labels) {
      return labels.map(l => `<b>${escapeReviewHtml(l)}.</b> ${escapeReviewHtml(normalizeOptionText(q[l] || ''))}`).join('<br>');
    }
    function sameAnswer(answer, q) {
      const selected = answerLabels(answer).slice().sort().join('|');
      const correct = correctLabels(q).slice().sort().join('|');
      return selected !== '' && selected === correct;
    }

    function formatReviewQuestionText(text) {
      let value = String(text || '')
        .replace(/^(?:(?:Câu\s*(?:số)?\s*\d+|\d+)[\s.:\)\/\-]+)+/gi, '')
        .trim();
      if (!value) return '';

      const looksLikeCode = /(?:#include\b|\b(?:int|void|float|double|char|bool)\s+main\s*\(|\btypedef\s+struct\b|SqlConnection|SqlCommand|Console\.|dataGridView|\b(?:cout|cin|printf|scanf|strlen|sqrt|acos)\b|\b(?:if|for|while|switch)\s*\(|\bclass\s+\w+\s*\{)/i.test(value);
      if (!looksLikeCode) return value;

      value = value
        .replace(/\s+(?=\d+\s*(?::|\.(?!\d)))/g, '\n')
        .replace(/(^|\n)(\d+\s*(?::|\.(?!\d)))(?=\S)/gm, '$1$2 ')
        .replace(/(?<!\d):\s+(?=(?:#include|const|int|float|double|char|bool|typedef|using|class|struct|for|while|if|switch|static|public|private)\b)/ig, ':\n')
        .replace(/\b(public|private|protected):\s+/ig, '$1:\n')
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
          if (escaped) escaped = false;
          else if (char === '\\') escaped = true;
          else if (char === quote) quote = null;
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
        .replace(/\{\s*\n\s*([^{}\n]*,[^{}\n]*)\s*\n\s*\}/g, '{ $1 }')
        .replace(/\n\s*,\s*\n?\s*\{/g, ',\n{')
        .replace(/\}\s*\n\s*;\s*\n/g, '};\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();

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

    function getItemStatus(index) {
      const ans = userAnswers[index];
      const q = quizData[index];
      if (answerLabels(ans).length === 0) return 'skipped';
      return sameAnswer(ans, q) ? 'correct' : 'wrong';
    }

    function renderReview() {
      reviewList.innerHTML = '';
      quizData.forEach((q, i) => {
        const status = getItemStatus(i);
        if (currentFilter !== 'all' && currentFilter !== status) return;

        const ans = userAnswers[i];
        const selected = answerLabels(ans);
        const correct = correctLabels(q);

        const card = document.createElement('div');
        card.className = `review-item ${status}`;

        let statusBadge = '';
        if (status === 'correct') {
          statusBadge = '<span class="status-badge correct"><i class="fa-solid fa-circle-check"></i> Trả lời đúng</span>';
        } else if (status === 'wrong') {
          statusBadge = '<span class="status-badge wrong"><i class="fa-solid fa-circle-xmark"></i> Trả lời sai</span>';
        } else {
          statusBadge = '<span class="status-badge skipped"><i class="fa-solid fa-circle-question"></i> Chưa trả lời</span>';
        }

        const selectedHtml = selected.length > 0
          ? answerText(q, selected)
          : '<i style="color:var(--text-muted);">(Chưa chọn đáp án)</i>';
        const correctHtml = answerText(q, correct);

        let imgHtml = '';
        if (q.image) {
          imgHtml = `<div style="margin: 10px 0;"><img src="${escapeReviewHtml(q.image)}" style="max-width:100%; max-height:200px; border-radius:8px;" alt="Hình câu hỏi"></div>`;
        }

        let explanationHtml = '';
        if (q.explanation) {
          explanationHtml = `
            <div style="margin-top:10px; padding:10px; background:var(--primary-light); border-radius:6px; font-size:13px;">
              <b><i class="fa-solid fa-lightbulb" style="color:var(--primary);"></i> Giải thích:</b> ${escapeReviewHtml(q.explanation)}
            </div>
          `;
        }

        const questionHtml = escapeReviewHtml(formatReviewQuestionText(q.question));

        card.innerHTML = `
          <div class="review-item-header">
            <b>Câu ${i + 1}: ${questionHtml}</b>
            ${statusBadge}
          </div>
          ${imgHtml}
          <div class="review-ans user-ans">
            <span class="label">Đáp án của bạn:</span>
            <div>${selectedHtml}</div>
          </div>
          <div class="review-ans correct-ans">
            <span class="label">Đáp án chính xác:</span>
            <div>${correctHtml}</div>
          </div>
          ${explanationHtml}
        `;
        reviewList.appendChild(card);
      });
    }

    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        renderReview();
      });
    });

    document.getElementById('backBtn').addEventListener('click', () => {
      window.location.href = 'result.php';
    });

    document.getElementById('homeBtn').addEventListener('click', () => {
      window.location.href = 'index.php';
    });

    renderReview();
  </script>
</body>
</html>
