<?php
/**
 * result.php — Trang Kết Quả Bài Thi EduK
 */
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kết quả bài thi - Hệ thống ôn tập & thi trắc nghiệm EduK</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="centered-page">

  <div class="card" style="max-width:560px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <a href="index.php" style="color:var(--text-muted); text-decoration:none; font-weight:700; font-size:14px;">
        <i class="fa-solid fa-arrow-left"></i> Danh sách đề thi
      </a>
      <button class="icon-btn" id="darkModeBtn" title="Chế độ tối/sáng">
        <i class="fa-solid fa-moon"></i>
      </button>
    </div>

    <h2><i class="fa-solid fa-trophy" style="color:#eab308;"></i> Kết Quả Bài Thi</h2>

    <p id="examNameResult" class="note" style="margin-bottom:20px; font-weight:700; color:var(--primary); font-size:15px;"></p>

    <!-- Visual Score Circle -->
    <div class="score-badge-circle">
      <div class="score-num" id="scoreValue">0</div>
      <div class="score-denom">ĐIỂM / 10</div>
    </div>

    <div id="passBadge" style="text-align:center; margin-bottom:24px; font-weight:800; font-size:16px;"></div>

    <div class="result-summary">
      <div class="result-item correct">
        <i class="fa-solid fa-circle-check"></i>
        <div class="value" id="correctValue">0</div>
        <div class="label">Câu đúng</div>
      </div>

      <div class="result-item wrong">
        <i class="fa-solid fa-circle-xmark"></i>
        <div class="value" id="wrongValue">0</div>
        <div class="label">Câu sai</div>
      </div>

      <div class="result-item score">
        <i class="fa-solid fa-list-ol"></i>
        <div class="value" id="totalValue">0</div>
        <div class="label">Tổng số câu</div>
      </div>

      <div class="result-item percent">
        <i class="fa-solid fa-chart-pie"></i>
        <div class="value" id="percentValue">0%</div>
        <div class="label">Chính xác</div>
      </div>
    </div>

    <p id="durationInfo" class="note" style="display:none; background:var(--primary-light); padding:10px; border-radius:var(--radius-sm); margin-bottom:20px; font-weight:600;"></p>

    <div class="btn-row">
      <button class="btn" id="reviewBtn">
        <i class="fa-solid fa-list-check"></i> Xem Đáp Án Chi Tiết
      </button>
    </div>

    <div class="btn-row">
      <button class="btn secondary" id="retryBtn">
        <i class="fa-solid fa-rotate-right"></i> Làm Lại Bài Thi
      </button>
      <button class="btn secondary" id="retryWrongBtn">
        <i class="fa-solid fa-rotate-left"></i> Làm Lại Câu Sai
      </button>
    </div>

    <div class="btn-row">
      <button class="btn btn-outline" id="homeBtn">
        <i class="fa-solid fa-house"></i> Về Danh Sách Đề Thi
      </button>
      <button class="btn btn-outline" id="deleteAttemptBtn" style="color:var(--danger); border-color:rgba(239,68,68,0.4);" title="Xóa kết quả lần thi này khỏi hệ thống">
        <i class="fa-solid fa-trash-can"></i> Xóa Lần Thi Này
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

    const raw = localStorage.getItem('quizResult');
    if (!raw) {
      window.location.href = 'index.php';
    }

    const result = JSON.parse(raw);
    const examName = localStorage.getItem('quizExamName') || 'Đề thi trắc nghiệm';
    document.getElementById('examNameResult').textContent = examName;

    document.getElementById('scoreValue').textContent = result.score;
    document.getElementById('correctValue').textContent = result.correct;
    document.getElementById('wrongValue').textContent = result.wrong;
    document.getElementById('totalValue').textContent = result.total;
    document.getElementById('percentValue').textContent = result.percent + '%';

    const passBadge = document.getElementById('passBadge');
    const scoreNum = parseFloat(result.score);
    if (scoreNum >= 8.5) {
      passBadge.innerHTML = '<span style="color:var(--success);"><i class="fa-solid fa-medal"></i> Xuất sắc! Bạn nắm rất vững kiến thức.</span>';
    } else if (scoreNum >= 7.0) {
      passBadge.innerHTML = '<span style="color:var(--primary);"><i class="fa-solid fa-thumbs-up"></i> Khá giỏi! Kết quả rất tốt.</span>';
    } else if (scoreNum >= 5.0) {
      passBadge.innerHTML = '<span style="color:var(--warning);"><i class="fa-solid fa-circle-check"></i> Đạt yêu cầu! Cần ôn luyện thêm các câu sai.</span>';
    } else {
      passBadge.innerHTML = '<span style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Chưa đạt! Hãy làm lại các câu sai để ghi nhớ.</span>';
    }

    if (result.timeSpent !== undefined) {
      const durationInfo = document.getElementById('durationInfo');
      durationInfo.style.display = 'block';
      durationInfo.innerHTML = `<i class="fa-regular fa-clock"></i> Thời gian hoàn thành: <b>${formatDuration(result.timeSpent)}</b>`;
    }

    document.getElementById('reviewBtn').addEventListener('click', () => {
      window.location.href = 'review.php';
    });

    document.getElementById('retryBtn').addEventListener('click', () => {
      localStorage.removeItem('quizAnswers');
      localStorage.removeItem('quizResult');
      localStorage.removeItem('quizElapsed');
      const examId = localStorage.getItem('quizExamId');
      window.location.href = examId ? `quiz.php?exam_id=${examId}` : 'quiz.php';
    });

    document.getElementById('retryWrongBtn').addEventListener('click', () => {
      const quizData = JSON.parse(localStorage.getItem('quizData') || '[]');
      const userAnswers = JSON.parse(localStorage.getItem('quizAnswers') || '[]');
      const wrongQuestions = quizData.filter((q, i) => !isCorrectAnswer(userAnswers[i], q));

      if (wrongQuestions.length === 0) {
        alert('Chúc mừng! Bạn không làm sai câu nào.');
        return;
      }

      localStorage.setItem('quizData', JSON.stringify(wrongQuestions));
      localStorage.removeItem('quizAnswers');
      localStorage.removeItem('quizResult');
      localStorage.removeItem('quizElapsed');
      window.location.href = 'quiz.php';
    });

    document.getElementById('homeBtn').addEventListener('click', () => {
      window.location.href = 'index.php';
    });

    document.getElementById('deleteAttemptBtn').addEventListener('click', async () => {
      if (!confirm('Anh Khánh có chắc chắn muốn xóa kết quả lần làm bài này không?')) return;
      
      try {
        const user = localStorage.getItem('quizUser') || '';
        const res = await fetch(`api.php?action=get_history${user ? '&user_name=' + encodeURIComponent(user) : ''}`);
        const json = await res.json();
        if (json.success && json.data && json.data.length > 0) {
          const latestId = json.data[0].id;
          await fetch('api.php?action=delete_attempt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: latestId })
          });
        }
      } catch (err) {
        console.error('Lỗi khi xóa bài thi:', err);
      }

      localStorage.removeItem('quizAnswers');
      localStorage.removeItem('quizResult');
      localStorage.removeItem('quizElapsed');
      alert('Đã xóa kết quả lần thi thành công!');
      window.location.href = 'index.php';
    });
  </script>
</body>
</html>
