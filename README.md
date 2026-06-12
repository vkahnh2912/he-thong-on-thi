# 📚 Quiz Master - Website Ôn Tập Và Thi Trắc Nghiệm

## Giới thiệu

 là một website ôn tập và thi trắc nghiệm được xây dựng bằng **HTML, CSS và JavaScript thuần**. Người dùng chỉ cần tải file đề thi dạng JSON lên hệ thống để bắt đầu làm bài.

Website hỗ trợ chấm điểm tự động, hiển thị đáp án đúng sai bằng màu sắc trực quan và thống kê kết quả sau khi hoàn thành bài thi.

---

## ✨ Chức năng chính

### 🔐 Đăng nhập

* Đăng nhập bằng tên người dùng và mật khẩu.
* Chuyển đến trang chọn đề thi sau khi đăng nhập thành công.

### 📂 Tải đề thi

* Hỗ trợ tải file đề thi định dạng JSON.
* và pdf , word ( nhưng đang lỗi)
* Tự động đọc dữ liệu câu hỏi.

### 📝 Làm bài thi

* Hiển thị từng câu hỏi cùng 4 đáp án A, B, C, D.
* Chọn đáp án bằng radio button hoặc nút bấm.
* Nếu trả lời đúng:

  * Đáp án chuyển sang màu xanh lá.
* Nếu trả lời sai:

  * Đáp án đã chọn chuyển sang màu đỏ.
  * Đáp án đúng được tô màu xanh.

### ⏮️ ⏭️ Điều hướng

* Chuyển câu trước.
* Chuyển câu tiếp theo.
* Hiển thị số câu hiện tại.
* Thanh tiến trình làm bài.

### 📊 Chấm điểm tự động

Sau khi nhấn **Nộp bài**, hệ thống sẽ hiển thị:

* Tổng số câu hỏi.
* Số câu đúng.
* Số câu sai.
* Điểm theo thang 10.
* Tỷ lệ chính xác (%).

### 🔄 Làm lại bài

* Cho phép làm lại bài thi.
* Quay về trang chủ.

---

## 🛠 Công nghệ sử dụng

* HTML5
* CSS3
* JavaScript (Vanilla JS)
* Font Awesome

---

## 📁 Cấu trúc thư mục

```bash
QuizMaster/
│
├── index.html          # Trang đăng nhập
├── select.html         # Chọn đề thi
├── quiz.html           # Trang làm bài
├── result.html         # Trang kết quả
│
├── css/
│   └── style.css
│
├── js/
│   └── script.js
│
├── data/
│   └── questions.json
│
└── README.md
```

---

## 📄 Cấu trúc file đề thi JSON

Ví dụ:

```json
[
  {
    "question": "HTML là gì?",
    "A": "Ngôn ngữ lập trình",
    "B": "Ngôn ngữ đánh dấu",
    "C": "Hệ điều hành",
    "D": "Cơ sở dữ liệu",
    "correct": "B"
  },
  {
    "question": "CSS dùng để làm gì?",
    "A": "Thiết kế giao diện",
    "B": "Lưu dữ liệu",
    "C": "Quản lý cơ sở dữ liệu",
    "D": "Tạo hệ điều hành",
    "correct": "A"
  }
]
```

---

## 🚀 Cách chạy dự án

### Bước 1

Clone project:

```bash
git clone https://github.com/your-username/QuizMaster.git
```

### Bước 2

Mở thư mục dự án bằng Visual Studio Code.

### Bước 3

Cài đặt extension:

* Live Server

### Bước 4

Chuột phải vào file:

```bash
index.html
```

Chọn:

```bash
Open with Live Server
```

---

## 📸 Giao diện

* Trang đăng nhập
* Trang chọn đề thi
* Trang làm bài
* Trang kết quả

---

## 📌 Tính năng dự kiến phát triển

* Đồng hồ đếm ngược.
* Lưu lịch sử làm bài.
* Chế độ tối (Dark Mode).
* Bảng xếp hạng điểm số.
* Đăng ký tài khoản.
* Lưu dữ liệu bằng Local Storage hoặc Database.

---

## 👨‍💻 Tác giả

**Vũ Trọng Khánh**

Sinh viên Công nghệ Thông tin

---

