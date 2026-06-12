/**
 * script.js
 * File JavaScript chung (dùng chung cho toàn bộ dự án).
 * Hiện tại logic chính được viết trực tiếp trong từng trang (index.html,
 * select.html, quiz.html, result.html) để dễ theo dõi và chỉnh sửa.
 *
 * File này được giữ lại để:
 *  - Mở rộng thêm các hàm tiện ích chung trong tương lai
 *  - Đảm bảo cấu trúc thư mục đúng theo yêu cầu đề bài
 */

// ===== Các key dùng trong localStorage (tham khảo) =====
// quizUser    : tên đăng nhập của người dùng hiện tại
// quizData    : danh sách câu hỏi (JSON) của đề thi đã tải
// quizAnswers : mảng đáp án người dùng đã chọn cho từng câu
// quizResult  : kết quả bài thi (số câu đúng/sai, điểm, %)

// ===== Hàm tiện ích: định dạng số thành chuỗi có 1-2 chữ số thập phân =====
function formatNumber(num) {
  return Number(num).toFixed(2).replace(/\.00$/, '');
}

// ===== Hàm tiện ích: xóa toàn bộ dữ liệu bài làm (giữ lại tài khoản đăng nhập) =====
function clearQuizSession() {
  localStorage.removeItem('quizData');
  localStorage.removeItem('quizAnswers');
  localStorage.removeItem('quizResult');
}
