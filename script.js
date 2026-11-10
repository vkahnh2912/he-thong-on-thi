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
    window.location.href = 'index.php';
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

function getCorrectLabels(q) {
  const rawValues = Array.isArray(q.correct) ? q.correct : [q.correct];
  const labels = [];
  rawValues.forEach(value => {
    const matches = String(value || '').toUpperCase().match(/[A-H]/g) || [];
    matches.forEach(label => {
      if (!labels.includes(label)) labels.push(label);
    });
  });
  return labels;
}

function getAnswerLabels(answer) {
  if (answer === null || answer === undefined) return [];
  return Array.isArray(answer) ? answer : [answer];
}

function sameLabels(a, b) {
  const left = getAnswerLabels(a).slice().sort().join('|');
  const right = getAnswerLabels(b).slice().sort().join('|');
  return left !== '' && left === right;
}

function isCorrectAnswer(answer, q) {
  return sameLabels(answer, getCorrectLabels(q));
}

function shuffleQuestionOptions(q) {
  const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'].filter(l => q[l] !== undefined && q[l] !== null && q[l] !== '');
  if (letters.length < 2) return { ...q };

  // order[destinationIndex] = nhãn cũ chứa nội dung chuyển tới vị trí mới.
  const order = shuffleArray(letters);
  if (order.every((oldLabel, index) => oldLabel === letters[index])) {
    [order[0], order[1]] = [order[1], order[0]];
  }

  const newQ = { ...q };
  const oldToNew = {};
  letters.forEach((letter, idx) => {
    const oldLabel = order[idx];
    newQ[letter] = q[oldLabel];
    oldToNew[oldLabel] = letter;
  });

  // Ánh xạ khóa theo nội dung cũ -> vị trí mới, tuyệt đối không chọn lại ngẫu nhiên.
  const newCorrect = getCorrectLabels(q)
    .map(oldLabel => oldToNew[oldLabel] || oldLabel)
    .filter((label, index, list) => list.indexOf(label) === index);
  if (newCorrect.length > 0) {
    newQ.correct = newCorrect.length > 1 ? newCorrect : newCorrect[0];
  }
  newQ.multipleAnswers = newCorrect.length > 1;
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
    if (isCorrectAnswer(userAnswers[i], q)) correctCount++;
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

  window.location.href = 'result.php';
}
