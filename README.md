# 🎓 Hệ Thống Ôn Thi Trắc Nghiệm Trực Tuyến HUBT (`Edu`)

> **Tác giả**: **Vũ Trọng Khánh**  
> **GitHub**: [https://github.com/vkahnh2912/edu](https://github.com/vkahnh2912/edu) | [https://github.com/vkahnh2912/he-thong-on-thi](https://github.com/vkahnh2912/he-thong-on-thi)

---

## 📌 1. Giới Thiệu Dự Án

**Hệ thống Ôn thi Trắc nghiệm (`Edu`)** là nền tảng web hỗ trợ sinh viên ôn tập, luyện đề trắc nghiệm nhiều môn học (Công nghệ Java, Lập trình C/C++ nâng cao, Cơ sở dữ liệu hướng đối tượng, Điện toán đám mây, IoT, Hệ hỗ trợ quyết định...), hỗ trợ chấm điểm tự động, xem lại lịch sử bài làm, nhập đề thông minh từ file PDF/JSON và xuất đề thi ra PDF.

### ✨ Tính Năng Nổi Bật
- **Luyện thi theo chế độ linh hoạt**: Làm bài theo từng phần, làm toàn bộ ngân hàng câu hỏi hoặc trộn đề ngẫu nhiên có bấm giờ.
- **Lưu trữ Cơ sở dữ liệu SQLite (`database.sqlite`)**: Lưu ngân hàng môn học, câu hỏi (kèm hình ảnh minh họa và định dạng code), đáp án và lịch sử làm bài.
- **Công cụ Bóc tách Đề thi từ PDF (`parse_pdf_*.py`, `smart_parser.js`)**: Tự động nhận diện câu hỏi, đáp án đúng được tô màu đỏ/in đậm và trích xuất hình ảnh từ file PDF đề cương.
- **Xuất Đề Thi & Đáp Án ra PDF (`export_exam.php`)**: Định dạng chuẩn đẹp để in ấn hoặc lưu trữ ngoại tuyến.

---

## 🛠️ 2. Yêu Cầu Cài Đặt Môi Trường (Environment Requirements)

Để chạy đầy đủ cả giao diện Web, Backend API PHP/SQLite và công cụ tách đề PDF, máy tính cần cài đặt:

| Thành phần | Phiên bản khuyến nghị | Công dụng |
| :--- | :--- | :--- |
| **PHP** | `>= 8.0` (hoặc XAMPP / Laragon) | Chạy Web Server (`index.php`, `quiz.php`, `api.php`) và kết nối SQLite |
| **PHP Extensions** | `pdo_sqlite`, `sqlite3`, `mbstring`, `gd` | Mở trong file `php.ini` để đọc/ghi file `database.sqlite` và xử lý ảnh |
| **Python** *(Tùy chọn)* | `>= 3.9+` | Dùng khi chạy các script tách đề tự động từ file `.pdf` (`parse_pdf_tool.py`) |
| **Thư viện Python** *(Tùy chọn)* | `pymupdf` (`fitz`) | Cài bằng lệnh `pip install pymupdf` để đọc màu chữ và trích xuất ảnh từ PDF |
| **Trình duyệt Web** | Google Chrome / Microsoft Edge | Truy cập hệ thống làm bài và xuất file PDF |

---

## ⚙️ 3. Hướng Dẫn Cài Đặt & Khởi Chạy

### Bước 1: Clone dự án từ GitHub
```bash
git clone https://github.com/vkahnh2912/edu.git
cd edu
```

### Bước 2: Cài đặt thư viện Python (nếu dùng tính năng bóc tách đề từ PDF)
```bash
pip install pymupdf
```

### Bước 3: Khởi động Web Server nội bộ
- **Cách 1 (Nhanh nhất trên Windows)**: Nhấp đúp chuột chạy file **`start_server.bat`** có sẵn trong thư mục dự án.
- **Cách 2 (Chạy qua Terminal / PowerShell)**:
  ```bash
  php -S localhost:8000
  ```

### Bước 4: Mở trên Trình duyệt
Truy cập địa chỉ:
- **Bản đầy đủ có Backend PHP & SQLite**: [http://localhost:8000/index.php](http://localhost:8000/index.php)
- **Bản tĩnh HTML/JS nhanh**: [http://localhost:8000/index.html](http://localhost:8000/index.html)

---

## 📖 4. Hướng Dẫn Sử Dụng Nhanh

1. **Chọn môn học & đề thi**:
   - Tại trang chủ (`index.php` hoặc `select.html`), chọn môn học muốn ôn tập trong danh sách.
2. **Làm bài trắc nghiệm (`quiz.php` / `quiz.html`)**:
   - Chọn đáp án cho từng câu hỏi, có thể chuyển nhanh giữa các câu ở bảng danh sách câu hỏi bên cạnh.
   - Bấm **Nộp bài** để hệ thống chấm điểm tự động.
3. **Xem kết quả & Đáp án chi tiết (`result.php` / `review.php`)**:
   - Xem tổng điểm, số câu đúng/sai, thời gian hoàn thành và xem giải thích/đáp án đúng cho từng câu.
4. **Thêm / Nhập đề thi mới & Xuất PDF**:
   - Sử dụng giao diện quản lý trên trang chủ để import đề từ file `.json` / `.pdf`, hoặc bấm **Xuất PDF (`export_exam.php`)** để tải đề thi về máy.

---

## 📂 5. Cấu Trúc File Chính Trong Dự Án

```text
edu/
├── index.php / index.html       # Trang chủ danh sách môn học & quản lý ngân hàng đề
├── select.html                  # Giao diện chọn chế độ ôn tập / kiểm tra
├── quiz.php / quiz.html         # Giao diện làm bài thi trắc nghiệm
├── result.php / result.html     # Giao diện thống kê kết quả sau khi nộp bài
├── review.php / review.html     # Giao diện xem lại chi tiết đáp án đúng/sai
├── api.php                      # REST API xử lý lưu/tải đề thi và lịch sử làm bài
├── db.php                       # Kết nối cơ sở dữ liệu SQLite (database.sqlite)
├── database.sqlite              # File cơ sở dữ liệu SQLite chứa toàn bộ môn học & câu hỏi
├── export_exam.php              # Xuất đề thi ra định dạng in / PDF
├── smart_parser.js              # Bộ phân tích cú pháp câu hỏi thông minh trên trình duyệt
├── parse_pdf_tool.py            # Công cụ Python bóc tách câu hỏi & hình ảnh từ file PDF
├── start_server.bat             # Script khởi động nhanh PHP Server trên Windows
├── style.css / exams.css        # Giao diện CSS hiện đại, hỗ trợ hiển thị công thức & khối code
└── uploads/                     # Thư mục lưu trữ hình ảnh câu hỏi trích xuất từ đề thi
```
