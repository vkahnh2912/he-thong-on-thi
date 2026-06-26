/**
 * script.js — Hàm tiện ích dùng chung cho hệ thống ôn tập & thi trắc nghiệm
 *
 * localStorage keys:
 *   quizUser      — tên đăng nhập
 *   quizData      — danh sách câu hỏi
 *   quizAnswers   — đáp án đã chọn
 *   quizResult    — kết quả bài thi
 *   quizExamName  — tên đề thi
 *   quizMode      — 'practice' | 'exam'
 *   quizTimeLimit — số phút (0 = không giới hạn)
 *   quizStartTime — timestamp bắt đầu làm bài
 *   quizHistory   — mảng lịch sử làm bài
 */

function formatNumber(num) {
  return Number(num).toFixed(2).replace(/\.00$/, '');
}

function clearQuizSession() {
  localStorage.removeItem('quizData');
  localStorage.removeItem('quizAnswers');
  localStorage.removeItem('quizResult');
  localStorage.removeItem('quizExamName');
  localStorage.removeItem('quizMode');
  localStorage.removeItem('quizTimeLimit');
  localStorage.removeItem('quizStartTime');
}

function requireLogin() {
  const user = localStorage.getItem('quizUser');
  if (!user) {
    window.location.href = 'index.html';
    return null;
  }
  return user;
}

function getQuizMode() {
  return localStorage.getItem('quizMode') || 'practice';
}

function isExamMode() {
  return getQuizMode() === 'exam';
}

function shuffleArray(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function shuffleQuestionOptions(q) {
  // Chỉ lấy những đáp án thực sự tồn tại trong câu hỏi (hỗ trợ cả 2-3-4-5 lựa chọn, kể cả E)
  const letters = ['A', 'B', 'C', 'D', 'E'].filter(l => q[l] !== undefined && q[l] !== null && q[l] !== '');
  const entries = letters.map(l => ({ text: q[l], isCorrect: l === q.correct }));
  const shuffled = shuffleArray(entries);

  // Giữ nguyên toàn bộ field khác của câu hỏi gốc (image, number, section, ...)
  const newQ = { ...q };
  let newCorrect = '';
  letters.forEach((letter, idx) => {
    newQ[letter] = shuffled[idx].text;
    if (shuffled[idx].isCorrect) newCorrect = letter;
  });
  newQ.correct = newCorrect;
  return newQ;
}

function formatDuration(seconds) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function saveQuizHistory(entry) {
  const history = JSON.parse(localStorage.getItem('quizHistory') || '[]');
  history.unshift({
    ...entry,
    date: new Date().toISOString(),
    user: localStorage.getItem('quizUser') || 'Khách'
  });
  if (history.length > 20) history.length = 20;
  localStorage.setItem('quizHistory', JSON.stringify(history));
}

function getQuizHistory() {
  return JSON.parse(localStorage.getItem('quizHistory') || '[]');
}

function calculateResult(questions, userAnswers) {
  const total = questions.length;
  let correctCount = 0;
  questions.forEach((q, i) => {
    if (userAnswers[i] === q.correct) correctCount++;
  });
  return {
    total,
    correct: correctCount,
    wrong: total - correctCount,
    score: (correctCount / total * 10).toFixed(2),
    percent: (correctCount / total * 100).toFixed(1)
  };
}

function submitQuiz(questions, userAnswers) {
  const result = calculateResult(questions, userAnswers);

  const startTime = localStorage.getItem('quizStartTime');
  if (startTime) {
    result.duration = Math.floor((Date.now() - Number(startTime)) / 1000);
  }

  localStorage.setItem('quizResult', JSON.stringify(result));
  localStorage.setItem('quizAnswers', JSON.stringify(userAnswers));

  saveQuizHistory({
    examName: localStorage.getItem('quizExamName') || 'Đề thi',
    mode: getQuizMode(),
    ...result
  });

  window.location.href = 'result.html';
}