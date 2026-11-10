<?php
/**
 * index.php — Hệ thống Ôn tập & Thi trắc nghiệm EduK
 * Giao diện Thẻ đề thi chuẩn theo hình ảnh + Menu Danh sách đề & Tải đề lên
 */

require_once __DIR__ . '/db.php';
$pdo = Database::getConnection();
$dbDriver = Database::getDriverType();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ngân hàng đề thi trắc nghiệm - EduK Quiz</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="exams.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Thư viện đọc PDF & Word (.docx) trực tiếp trên Client -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
  <!-- Bộ phân tích đề thi PDF & văn bản không cấu trúc thông minh -->
  <script src="smart_parser.js"></script>
  <style>
    .page-wrapper {
      max-width: 1280px;
      margin: 0 auto;
      padding: 24px 20px 80px;
    }

    .top-menu-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
  </style>
</head>
<body>

  <!-- Top Navigation Bar -->
  <header class="topbar">
    <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
      <a href="index.php" class="brand" title="Hệ thống thi trắc nghiệm EduK">
        <i class="fa-solid fa-graduation-cap" style="color:#6366f1;"></i>
        <span>EduK Quiz</span>
        <span style="font-size:10.5px; background:linear-gradient(135deg, #6366f1, #a855f7); color:#fff; padding:2px 8px; border-radius:12px; font-weight:800; letter-spacing:0.5px;">CNTT PRO</span>
      </a>

      <!-- Menu Điều Hướng (1: DS các đề, 2: Tải đề lên) -->
      <nav class="main-nav-tabs">
        <button type="button" class="tab-nav-btn active" id="tabBtnList" onclick="switchMainTab('list')">
          <i class="fa-solid fa-layer-group"></i> 1. Danh sách đề thi
        </button>
        <button type="button" class="tab-nav-btn" id="tabBtnUpload" onclick="switchMainTab('upload')">
          <i class="fa-solid fa-cloud-arrow-up"></i> 2. Tải đề lên
        </button>
      </nav>
    </div>

    <!-- Actions Bên Phải -->
    <div class="topbar-actions">
      <!-- Nút Trở về màu đỏ (chỉ hiện khi cần quay lại) -->
      <button type="button" class="btn-back-red" id="btnBackHome" onclick="switchMainTab('list')" title="Trở về danh sách đề thi" style="display:none;">
        <i class="fa-solid fa-arrow-left"></i> Trở về
      </button>

      <!-- Nút Quản Trị Admin Hoàng Gia dành riêng cho Anh Khánh -->
      <button type="button" class="btn-admin-vip" onclick="openAdminModal()" title="Mở Bảng điều khiển Quản trị viên">
        <i class="fa-solid fa-crown"></i> <span>Admin VIP</span>
      </button>

      <button class="icon-btn" id="darkModeBtn" title="Chế độ tối/sáng">
        <i class="fa-solid fa-moon"></i>
      </button>

      <button class="icon-btn" id="fullscreenBtn" title="Toàn màn hình">
        <i class="fa-solid fa-expand"></i>
      </button>

      <div class="avatar-badge" onclick="changeUserName()" style="cursor:pointer;" title="Bấm để đổi tên">
        <div class="avatar" id="headerAvatar" style="background:linear-gradient(135deg, #f59e0b, #ef4444); color:#fff; box-shadow:0 2px 8px rgba(245,158,11,0.4);">
          <i class="fa-solid fa-user-tie"></i>
        </div>
        <span id="userNameDisplay" style="font-weight:700;">Anh Khánh (Admin)</span>
      </div>
    </div>
  </header>

  <main class="page-wrapper">

    <!-- ================= TAB 1: DANH SÁCH CÁC ĐỀ ================= -->
    <section id="sectionList">
      
      <!-- Thanh Tìm kiếm và Đếm kết quả chuẩn theo ảnh -->
      <div class="search-header-row">
        <div class="search-box-wrap">
          <i class="fa-solid fa-magnifying-glass search-icon"></i>
          <input type="text" id="searchInput" class="search-box-input" 
                 placeholder="Tìm kiếm đề thi (ví dụ: kỹ nghệ phần mềm)..." 
                 value="" autocomplete="off">
        </div>
        <div class="search-result-count">
          <span class="count-num" id="resultCountNumber">0</span> kết quả
        </div>
      </div>

      <!-- Lưới Thẻ Đề Thi (Grid 4 cột như ảnh) -->
      <div class="exams-grid" id="examsGrid">
        <!-- Render bằng Javascript từ Database -->
        <div style="grid-column: 1 / -1; text-align:center; padding: 40px 0; color:var(--text-muted);">
          <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
          <p style="margin-top:10px; font-weight:600;">Đang tải danh sách đề thi từ Database...</p>
        </div>
      </div>

    </section>

    <!-- ================= TAB 2: TẢI ĐỀ LÊN DATABASE ================= -->
    <section id="sectionUpload" style="display:none;">
      
      <div style="max-width:850px; margin:0 auto;">
        
        <div class="hero-banner" style="text-align:left; margin-bottom:24px;">
          <h2 style="font-size:24px; font-weight:800; color:var(--text-main);">
            <i class="fa-solid fa-cloud-arrow-up" style="color:var(--primary); margin-right:8px;"></i>
            Tải Đề Thi Lên Hệ Thống
          </h2>
          <p class="note" style="margin-top:4px; font-size:14px;">
            Nhập thông tin đề thi và tải file (Word, PDF, JSON, TXT). Hệ thống sẽ tự động trích xuất câu hỏi và lưu vào Database.
          </p>
        </div>

        <div class="upload-panel-card">
          <form id="uploadExamForm" onsubmit="event.preventDefault(); submitUploadExam();">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
              <div>
                <label style="display:block; font-weight:700; font-size:13px; margin-bottom:6px; color:var(--text-main);">
                  Tên đề thi / Môn học <span style="color:var(--danger);">*</span>
                </label>
                <input type="text" id="upTitle" required class="search-box-input" style="padding-left:14px;" 
                       placeholder="VD: Kỹ nghệ phần mềm">
              </div>

              <div>
                <label style="display:block; font-weight:700; font-size:13px; margin-bottom:6px; color:var(--text-main);">
                  Người đăng / Tác giả
                </label>
                <input type="text" id="upAuthor" class="search-box-input" style="padding-left:14px;" 
                       placeholder="VD: Moon San hoặc tên bạn">
              </div>
            </div>

            <div style="margin-bottom:18px;">
              <label style="display:block; font-weight:700; font-size:13px; margin-bottom:6px; color:var(--text-main);">
                Trường / Đơn vị đào tạo
              </label>
              <input type="text" id="upOrg" class="search-box-input" style="padding-left:14px;" 
                     value="Đại học Kinh doanh và Công nghệ Hà Nội" placeholder="VD: Đại học Kinh doanh và Công nghệ Hà Nội">
            </div>

            <!-- Chọn Mẫu Ảnh Bìa Thumbnail -->
            <div style="margin-bottom:22px;">
              <label style="display:block; font-weight:700; font-size:13px; margin-bottom:6px; color:var(--text-main);">
                Chọn hình ảnh bìa đề thi (như trong ảnh mẫu)
              </label>
              <div class="cover-preset-picker">
                <div class="preset-thumb-option selected" data-cover="chalkboard" onclick="selectCoverPreset(this)">
                  <img src="assets/chalkboard.svg" alt="Bảng đen ĐH KD&CN">
                </div>
                <div class="preset-thumb-option" data-cover="book_yellow" onclick="selectCoverPreset(this)">
                  <img src="assets/book_yellow.svg" alt="Sách mở hoa vàng">
                </div>
                <div class="preset-thumb-option" data-cover="book_orange" onclick="selectCoverPreset(this)">
                  <img src="assets/book_orange.svg" alt="Sách mở bút chì">
                </div>
                <div class="preset-thumb-option" data-cover="book_blue" onclick="selectCoverPreset(this)">
                  <img src="assets/book_blue.svg" alt="Sách mở máy bay">
                </div>
              </div>
            </div>

            <!-- Lựa chọn phương thức nhập đề -->
            <div style="margin-bottom:14px;">
              <label style="display:block; font-weight:700; font-size:13px; margin-bottom:8px; color:var(--text-main);">
                Phương thức nạp câu hỏi <span style="color:var(--danger);">*</span>
              </label>
              <div class="upload-subnav">
                <button type="button" class="upload-subnav-btn active" id="btnModeFile" onclick="switchUploadMode('file')">
                  <i class="fa-solid fa-file-arrow-up"></i> Tải File (PDF, Word, TXT, JSON)
                </button>
                <button type="button" class="upload-subnav-btn" id="btnModePaste" onclick="switchUploadMode('paste')">
                  <i class="fa-solid fa-paste"></i> Dán Văn Bản Đề Thi Trực Tiếp
                </button>
              </div>
            </div>

            <!-- Cách 1: Khu Vực Kéo Thả / Tải File -->
            <div id="fileUploadContainer" style="margin-bottom:20px;">
              <div class="dropzone-area" id="dropZone" onclick="document.getElementById('docFileInput').click()">
                <i class="fa-solid fa-file-pdf" style="font-size:38px; color:var(--primary); margin-bottom:10px;"></i>
                <div style="font-weight:700; font-size:15px; color:var(--text-main); margin-bottom:4px;">
                  Nhấn vào đây để tải file hoặc kéo thả file vào khung
                </div>
                <div style="font-size:13px; color:var(--text-muted); line-height:1.6;">
                  Hỗ trợ định dạng: <b>PDF, Word (.docx), TXT, JSON</b><br>
                  <span style="color:var(--primary); font-weight:600;"><i class="fa-solid fa-wand-magic-sparkles"></i> Hệ thống tự động phân tích PDF không theo cấu trúc (đáp án cùng dòng, bảng đáp án ở cuối, dấu sao...)</span>
                </div>
                <input type="file" id="docFileInput" style="display:none;" 
                       accept=".docx,.pdf,.json,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json,text/plain"
                       onchange="handleSelectedFile(this.files[0])">
              </div>

              <!-- Trạng thái trích xuất file -->
              <div id="fileParseStatus" style="margin-top:12px; font-weight:600; font-size:14px; min-height:22px;"></div>
            </div>

            <!-- Cách 2: Khu Vực Dán Văn Bản Tự Do -->
            <div id="pasteUploadContainer" style="display:none; margin-bottom:20px;">
              <textarea id="rawTextPasteInput" rows="9" class="search-box-input" style="padding:12px; line-height:1.5; font-family:monospace; font-size:13px;"
                        placeholder="Dán nội dung đề thi của bạn vào đây (bất kể cấu trúc)...&#10;&#10;Ví dụ:&#10;Câu 1: Thủ đô nước ta là gì? A. Huế B. Hà Nội C. Đà Nẵng D. TP.HCM Đáp án: B&#10;2. HTML là gì? A. Ngôn ngữ lập trình *B. Ngôn ngữ đánh dấu C. Hệ điều hành D. Cơ sở dữ liệu&#10;... hoặc bảng đáp án ở cuối: 1.B 2.B"></textarea>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                <span style="font-size:12.5px; color:var(--text-muted);">
                  <i class="fa-solid fa-lightbulb" style="color:var(--warning);"></i> Tự động nhận diện mọi định dạng câu hỏi &amp; bảng đáp án
                </span>
                <button type="button" class="btn" onclick="parsePastedRawText()" style="padding:9px 18px; font-size:13.5px;">
                  <i class="fa-solid fa-wand-magic-sparkles"></i> PHÂN TÍCH NỘI DUNG NGAY
                </button>
              </div>
            </div>

            <!-- Khu vực xem trước & chỉnh sửa câu hỏi trực quan -->
            <div id="previewQuestionsZone" style="display:none; margin-bottom:24px; padding:18px; background:var(--bg-main); border-radius:14px; border:1px solid var(--card-border);">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <div>
                  <span style="font-weight:800; font-size:15px; color:var(--text-main);">
                    <i class="fa-solid fa-list-check" style="color:var(--success);"></i> Danh Sách Câu Hỏi Đã Nhận Diện:
                  </span>
                  <span id="previewCountBadge" style="background:var(--success-light); color:var(--success); font-weight:800; padding:4px 10px; border-radius:var(--radius-full); font-size:12px; margin-left:8px;">
                    0 câu hỏi
                  </span>
                </div>
                <div style="display:flex; gap:8px;">
                  <button type="button" class="btn btn-outline" onclick="addNewBlankQuestion()" style="padding:6px 12px; font-size:12px;">
                    <i class="fa-solid fa-plus"></i> Thêm câu hỏi
                  </button>
                  <button type="button" class="btn btn-outline" onclick="shuffleAnswerContents()" style="padding:6px 12px; font-size:12px; color:var(--primary); border-color:rgba(99,102,241,0.35);" title="Chỉ đảo khi bạn bấm xác nhận; đáp án đúng sẽ được đổi theo nội dung">
                    <i class="fa-solid fa-shuffle"></i> Đảo nội dung đáp án
                  </button>
                  <button type="button" class="btn btn-outline" id="btnUndoAnswerShuffle" onclick="undoAnswerShuffle()" disabled style="padding:6px 12px; font-size:12px;">
                    <i class="fa-solid fa-rotate-left"></i> Hoàn tác
                  </button>
                  <button type="button" class="btn btn-outline" onclick="clearAllParsedQuestions()" style="padding:6px 12px; font-size:12px; color:var(--danger); border-color:rgba(239,68,68,0.3);">
                    <i class="fa-solid fa-trash-can"></i> Xóa hết
                  </button>
                </div>
              </div>

              <!-- Danh sách câu hỏi có thể chỉnh sửa trực tiếp -->
              <div id="previewItemsList" style="max-height:480px; overflow-y:auto; padding-right:4px;"></div>
            </div>

            <button type="submit" id="btnSubmitUpload" class="btn success" style="width:100%; padding:14px; font-size:16px;">
              <i class="fa-solid fa-cloud-arrow-up"></i> LƯU ĐỀ THI VÀO DATABASE
            </button>

          </form>
        </div>

      </div>

    </section>

  </main>

  <!-- ================= MODAL CẤU HÌNH ÔN LUYỆN / THI ================= -->
  <div class="modal-overlay" id="examModalOverlay" onclick="if(event.target===this) closeModal()">
    <div class="modal-box">
      
      <div class="modal-header">
        <div>
          <div class="modal-title" id="modalExamTitle">Kỹ nghệ phần mềm</div>
          <div style="font-size:13px; color:var(--text-muted); margin-top:4px;" id="modalExamAuthor">
            Tác giả: Moon San · Đại học Kinh doanh và Công nghệ
          </div>
        </div>
        <button class="btn-close-modal" onclick="closeModal()" title="Đóng">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- Thống kê số câu -->
      <div style="background:var(--primary-light); border:1px solid rgba(99,102,241,0.2); border-radius:12px; padding:14px 18px; margin-bottom:20px; display:flex; align-items:center; gap:14px;">
        <div class="icon-square-question" style="width:36px; height:36px; font-size:18px; border-radius:8px;">?</div>
        <div>
          <div style="font-size:20px; font-weight:800; color:var(--primary);" id="modalTotalQuestions">230</div>
          <div style="font-size:12px; font-weight:600; color:var(--text-muted);">Câu hỏi có sẵn trong đề thi này</div>
        </div>
      </div>

      <!-- Cài đặt bài làm -->
      <div class="settings-group">
        <h4><i class="fa-solid fa-sliders" style="color:var(--primary);"></i> Cài Đặt Chế Độ Bài Làm</h4>

        <div class="mode-toggle">
          <button type="button" class="mode-btn active" id="modalModePractice" onclick="setModalMode('practice')">
            <i class="fa-solid fa-book-open"></i> Ôn Tập
          </button>
          <button type="button" class="mode-btn" id="modalModeExam" onclick="setModalMode('exam')">
            <i class="fa-solid fa-file-pen"></i> Thi Thật
          </button>
        </div>
        <p class="note" style="margin-top:0; margin-bottom:16px; text-align:left; font-size:12.5px;">
          <i class="fa-solid fa-circle-info"></i> <b>Ôn tập</b>: Hiện ngay đáp án đúng/sai khi chọn. <b>Thi thật</b>: Đếm giờ &amp; nộp bài chấm điểm.
        </p>

        <div class="time-select" id="modalQuestionCountWrap" style="display:none;">
          <label for="modalQuestionCount"><i class="fa-solid fa-list-ol"></i> Số câu muốn làm:</label>
          <input type="number" id="modalQuestionCount" min="1" step="1" style="width:100px;">
          <span class="note" id="modalQuestionCountMax" style="margin:0;">/ 0 câu</span>
        </div>

        <div class="time-select">
          <label for="modalTimeLimit"><i class="fa-regular fa-clock"></i> Thời gian làm bài:</label>
          <select id="modalTimeLimit">
            <option value="0">Không giới hạn</option>
            <option value="15">15 phút</option>
            <option value="30">30 phút</option>
            <option value="45">45 phút</option>
            <option value="60">60 phút</option>
            <option value="90">90 phút</option>
          </select>
        </div>

        <div style="display:flex; gap:16px; margin-top:16px; flex-wrap:wrap;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13.5px; font-weight:600;">
            <input type="checkbox" id="modalShuffleQuestions" style="accent-color:var(--primary); width:18px; height:18px;">
            <i class="fa-solid fa-shuffle"></i> Đảo thứ tự câu hỏi
          </label>
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13.5px; font-weight:600;">
            <input type="checkbox" id="modalShuffleAnswers" style="accent-color:var(--primary); width:18px; height:18px;">
            <i class="fa-solid fa-shuffle"></i> Đảo nội dung đáp án (giữ khóa đúng)
          </label>
        </div>
        <p class="note" style="margin:12px 0 0; text-align:left; font-size:12px;">
          <i class="fa-solid fa-shield-halved"></i> Khi bật, nhãn A/B/C/D vẫn giữ nguyên nhưng nội dung được tráo; đáp án đúng sẽ đi theo đúng nội dung cũ.
        </p>
      </div>

      <button class="btn success" id="btnStartQuiz" onclick="launchQuiz()" style="width:100%; margin-top:22px; padding:15px; font-size:16px;">
        <i class="fa-solid fa-play"></i> BẮT ĐẦU LÀM BÀI NGAY
      </button>

      <div class="modal-action-row">
        <a class="btn btn-outline" id="btnExportExamPdf" href="#" target="_blank" rel="noopener" aria-label="Xuất đề thi thành PDF để xem trên điện thoại">
          <i class="fa-solid fa-file-pdf"></i> Xuất PDF / Xem trên điện thoại
        </a>
      </div>

    </div>
  </div>

  <!-- ================= MODAL QUẢN TRỊ ADMIN (ANH KHÁNH) ================= -->
  <div class="modal-overlay" id="adminModalOverlay" onclick="if(event.target === this) closeAdminModal()">
    <div class="admin-modal-box">
      
      <!-- Nút Đóng Modal tròn xịn -->
      <button type="button" class="btn-close-modal" onclick="closeAdminModal()" title="Đóng" style="position:absolute; top:20px; right:20px;">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <!-- Header Banner Hoàng Gia -->
      <div style="text-align:left; margin-bottom:18px; padding-right:40px;">
        <div class="admin-header-badge">
          <i class="fa-solid fa-crown" style="color:#f59e0b;"></i> BẢNG ĐIỀU KHIỂN QUẢN TRỊ VIÊN VIP
        </div>
        <h3 style="font-size:22px; font-weight:800; color:var(--text-main); margin:4px 0 6px 0;">
          Trung Tâm Quản Trị Hệ Thống
        </h3>
        <p style="font-size:13.5px; color:var(--text-muted); margin:0; line-height:1.5;">
          Xin chào <b>anh Khánh đẹp trai nhất vũ trụ</b>! Tại đây anh có thể quản lý toàn bộ <b>21 bộ đề thi CNTT</b> và toàn quyền xóa các lượt thi, bài làm cũ của hệ thống.
        </p>
      </div>

      <!-- Sub Tabs: Lịch sử bài làm & Quản lý đề thi -->
      <div class="admin-sub-tabs">
        <button type="button" class="admin-sub-tab-btn active" id="btnAdminTabHistory" onclick="switchAdminTab('history')">
          <i class="fa-solid fa-clock-rotate-left"></i> Lịch Sử Bài Làm (<span id="adminHistoryCount">0</span>)
        </button>
        <button type="button" class="admin-sub-tab-btn" id="btnAdminTabExams" onclick="switchAdminTab('exams')">
          <i class="fa-solid fa-layer-group"></i> Ngân Hàng Đề Thi (<span id="adminExamsCount">0</span>)
        </button>
      </div>

      <!-- TAB 1: LỊCH SỬ BÀI THI ĐÃ LÀM -->
      <div id="adminTabHistorySection">
        <div class="admin-toolbar-row">
          <div style="display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-circle-check" style="color:#10b981;"></i>
            <span style="font-size:13.5px; font-weight:700; color:var(--text-main);">
              Danh sách các lượt thi đã hoàn thành:
            </span>
          </div>
          <button type="button" class="btn-clear-all-danger" onclick="clearAllAttempts()" id="btnClearAllHistoryBtn" title="Xóa toàn bộ lịch sử bài thi cũ">
            <i class="fa-solid fa-trash-can"></i> Xóa toàn bộ bài cũ đã làm
          </button>
        </div>

        <div class="admin-table-container">
          <table class="admin-data-table">
            <thead>
              <tr>
                <th style="width:40px;">#</th>
                <th>Đề thi</th>
                <th>Người thi</th>
                <th>Chế độ</th>
                <th>Điểm</th>
                <th>Số câu đúng</th>
                <th>Thời gian làm</th>
                <th>Thời điểm nộp</th>
                <th style="text-align:center; width:60px;">Xóa</th>
              </tr>
            </thead>
            <tbody id="adminHistoryTableBody">
              <tr>
                <td colspan="9" style="text-align:center; padding:35px; color:var(--text-muted);">
                  <i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu lịch sử bài làm...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: QUẢN LÝ ĐỀ THI -->
      <div id="adminTabExamsSection" style="display:none;">
        <div class="admin-toolbar-row">
          <div style="display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-book-open" style="color:#6366f1;"></i>
            <span style="font-size:13.5px; font-weight:700; color:var(--text-main);">
              Toàn bộ đề thi trong CSDL (bao gồm các môn CNTT HUBT):
            </span>
          </div>
        </div>

        <div class="admin-table-container">
          <table class="admin-data-table">
            <thead>
              <tr>
                <th style="width:50px;">ID</th>
                <th>Tên môn học / đề thi</th>
                <th>Khoa / Tác giả</th>
                <th>Số câu</th>
                <th>Lượt thi</th>
                <th>Ngày tạo</th>
                <th style="text-align:center; width:80px;">Thao tác</th>
              </tr>
            </thead>
            <tbody id="adminExamsTableBody">
              <tr>
                <td colspan="7" style="text-align:center; padding:35px; color:var(--text-muted);">
                  <i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách đề thi...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:14px; border-top:1px solid var(--card-border);">
        <span style="font-size:12.5px; color:var(--text-muted);">
          <i class="fa-solid fa-shield-halved" style="color:#f59e0b;"></i> Hệ thống Quản trị EduK Quiz v2.5 · Bản quyền anh Khánh VIP
        </span>
        <button type="button" class="btn btn-outline" onclick="closeAdminModal()" style="padding:9px 24px; font-weight:700; border-radius:var(--radius-full);">
          Đóng
        </button>
      </div>

    </div>
  </div>

  <script src="script.js"></script>
  <script>
    // ===== Dark Mode =====
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

    // Fullscreen
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen().catch(() => {});
        } else {
          document.exitFullscreen().catch(() => {});
        }
      });
    }

    // User name
    let currentUserName = localStorage.getItem('quizUser') || 'Học viên';
    document.getElementById('userNameDisplay').textContent = currentUserName;

    function changeUserName() {
      const name = prompt('Nhập tên học viên của bạn:', currentUserName);
      if (name && name.trim()) {
        currentUserName = name.trim();
        localStorage.setItem('quizUser', currentUserName);
        document.getElementById('userNameDisplay').textContent = currentUserName;
      }
    }

    // ===== Quản lý Tabs Menu (1: Danh sách các đề, 2: Tải đề lên) =====
    function switchMainTab(tab) {
      const tabBtnList = document.getElementById('tabBtnList');
      const tabBtnUpload = document.getElementById('tabBtnUpload');
      const sectionList = document.getElementById('sectionList');
      const sectionUpload = document.getElementById('sectionUpload');

      if (tab === 'list') {
        tabBtnList.classList.add('active');
        tabBtnUpload.classList.remove('active');
        sectionList.style.display = 'block';
        sectionUpload.style.display = 'none';
        loadExamsList(); // Tải lại danh sách
      } else {
        tabBtnUpload.classList.add('active');
        tabBtnList.classList.remove('active');
        sectionList.style.display = 'none';
        sectionUpload.style.display = 'block';
      }
    }

    // ===== Lấy danh sách đề thi từ Backend Database =====
    let allExams = [];
    const examsGrid = document.getElementById('examsGrid');
    const resultCountNumber = document.getElementById('resultCountNumber');
    const searchInput = document.getElementById('searchInput');

    async function loadExamsList(keyword = '') {
      try {
        const url = 'api.php?action=get_exams' + (keyword ? '&search=' + encodeURIComponent(keyword) : '');
        const res = await fetch(url);
        const json = await res.json();

        if (json.success) {
          allExams = json.data;
          renderExamsGrid(allExams);
        } else {
          examsGrid.innerHTML = `<div style="grid-column:1/-1; color:var(--danger); text-align:center; padding:30px;">Lỗi: ${json.message}</div>`;
        }
      } catch (err) {
        examsGrid.innerHTML = `<div style="grid-column:1/-1; color:var(--danger); text-align:center; padding:30px;">Không thể kết nối đến server API: ${err.message}</div>`;
      }
    }

    // Render danh sách thẻ đề thi y hệt trong ảnh chụp
    function renderExamsGrid(exams) {
      resultCountNumber.textContent = exams.length;

      if (exams.length === 0) {
        examsGrid.innerHTML = `
          <div style="grid-column:1/-1; text-align:center; padding:50px 20px; color:var(--text-muted);">
            <i class="fa-solid fa-folder-open fa-3x" style="opacity:0.4; margin-bottom:12px;"></i>
            <p style="font-weight:700; font-size:16px;">Không tìm thấy đề thi phù hợp</p>
            <p style="font-size:13px;">Hãy thử tìm từ khóa khác hoặc bấm tab "2. Tải đề lên" để thêm đề mới.</p>
          </div>
        `;
        return;
      }

      examsGrid.innerHTML = '';

      exams.forEach(item => {
        // Thumbnail handling
        let thumbSrc = 'assets/chalkboard.svg';
        if (item.thumbnail === 'chalkboard') thumbSrc = 'assets/chalkboard.svg';
        else if (item.thumbnail === 'book_yellow') thumbSrc = 'assets/book_yellow.svg';
        else if (item.thumbnail === 'book_orange') thumbSrc = 'assets/book_orange.svg';
        else if (item.thumbnail === 'book_blue') thumbSrc = 'assets/book_blue.svg';
        else if (item.thumbnail && item.thumbnail.startsWith('uploads/')) thumbSrc = item.thumbnail;

        // Avatar styling theo tác giả
        let avatarClass = 'author-avatar';
        let avatarHtml = `<i class="fa-solid fa-user"></i>`;
        const aName = item.author || 'Học viên';
        const initial = aName.charAt(0).toUpperCase();

        if (aName.includes('Moon San')) {
          avatarClass += ' avatar-moon';
          avatarHtml = `<i class="fa-solid fa-palette"></i>`;
        } else if (aName.includes('Công nghệ')) {
          avatarClass += ' avatar-tech';
          avatarHtml = `<i class="fa-solid fa-user"></i>`;
        } else {
          avatarClass += ' avatar-letter';
          avatarHtml = `<span>${initial}</span>`;
        }

        const card = document.createElement('div');
        card.className = 'exam-card';
        card.innerHTML = `
          <!-- Nút xóa đề (nếu cần quản lý) -->
          <button type="button" class="btn-delete-exam" title="Xóa đề thi này" onclick="deleteExam(${item.id}, '${item.title.replace(/'/g, "\\'")}', event, this)">
            <i class="fa-solid fa-trash-can"></i>
          </button>

          <!-- Ảnh thumbnail bìa -->
          <div class="exam-thumb-container">
            <img src="${thumbSrc}" alt="${item.title}" class="exam-thumb-img" loading="lazy">
          </div>

          <!-- Nội dung chi tiết card -->
          <div class="exam-card-body">
            <div class="exam-card-title" title="${item.title}">${item.title}</div>
            
            <div class="exam-date-row">
              <i class="fa-regular fa-clock"></i>
              <span>${item.formatted_date || '09/06/2026'}</span>
            </div>

            <div class="exam-stats-row">
              <div class="badge-stat-item" title="Số câu hỏi">
                <span class="icon-square-question">?</span>
                <span>${item.question_count}</span>
              </div>
              <div class="badge-stat-item" title="Số lượt học / thi">
                <span class="icon-square-chart"><i class="fa-solid fa-chart-simple"></i></span>
                <span>${item.views_count}</span>
              </div>
            </div>

            <div class="exam-author-row">
              <div class="${avatarClass}">
                ${avatarHtml}
              </div>
              <span class="author-name" title="${item.author}">${item.author}</span>
            </div>

            <div class="exam-org-tag" title="${item.organization}">
              ${item.organization}
            </div>

            <button type="button" class="btn-enter-exam" onclick="openExamModal(${item.id})">
              <i class="fa-solid fa-circle-play"></i> Vào ôn thi
            </button>
          </div>
        `;

        examsGrid.appendChild(card);
      });
    }

    // Tìm kiếm realtime
    let searchDebounce = null;
    searchInput.addEventListener('input', function() {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        loadExamsList(this.value.trim());
      }, 250);
    });

    // ===== MODAL CẤU HÌNH VÀO ÔN THI =====
    let currentSelectedExam = null;
    let selectedModalMode = 'practice';
    const examModalOverlay = document.getElementById('examModalOverlay');
    const modalExamTitle = document.getElementById('modalExamTitle');
    const modalExamAuthor = document.getElementById('modalExamAuthor');
    const modalTotalQuestions = document.getElementById('modalTotalQuestions');
    const modalQuestionCountWrap = document.getElementById('modalQuestionCountWrap');
    const modalQuestionCount = document.getElementById('modalQuestionCount');
    const modalQuestionCountMax = document.getElementById('modalQuestionCountMax');
    const btnExportExamPdf = document.getElementById('btnExportExamPdf');

    async function openExamModal(examId) {
      try {
        const res = await fetch(`api.php?action=get_exam&id=${examId}`);
        const json = await res.json();
        if (!json.success) {
          alert('Không thể tải thông tin đề thi: ' + json.message);
          return;
        }

        currentSelectedExam = json;
        modalExamTitle.textContent = json.exam.title;
        modalExamAuthor.textContent = `Tác giả: ${json.exam.author} · ${json.exam.organization}`;
        modalTotalQuestions.textContent = json.questions.length;
        btnExportExamPdf.href = `export_exam.php?exam_id=${encodeURIComponent(json.exam.id)}`;
        btnExportExamPdf.setAttribute('aria-label', 'Xuất đề thi thành PDF để xem trên điện thoại');

        modalQuestionCount.max = json.questions.length;
        modalQuestionCount.value = json.questions.length;
        modalQuestionCountMax.textContent = `/ ${json.questions.length} câu`;

        setModalMode('practice');
        examModalOverlay.classList.add('active');
      } catch (err) {
        alert('Lỗi: ' + err.message);
      }
    }

    function closeModal() {
      examModalOverlay.classList.remove('active');
    }

    function setModalMode(mode) {
      selectedModalMode = mode;
      const btnPractice = document.getElementById('modalModePractice');
      const btnExam = document.getElementById('modalModeExam');

      if (mode === 'practice') {
        btnPractice.classList.add('active');
        btnExam.classList.remove('active');
        modalQuestionCountWrap.style.display = 'none';
      } else {
        btnExam.classList.add('active');
        btnPractice.classList.remove('active');
        modalQuestionCountWrap.style.display = 'flex';
      }
    }

    // Bắt đầu làm bài thi
    function launchQuiz() {
      if (!currentSelectedExam) return;

      localStorage.removeItem('quizAnswers');
      localStorage.removeItem('quizResult');
      localStorage.removeItem('quizStartTime');
      localStorage.removeItem('quizElapsed');
      localStorage.setItem('quizSessionId', `${Date.now()}-${Math.random().toString(36).slice(2)}`);

      let questions = currentSelectedExam.questions.slice();
      const examName = currentSelectedExam.exam.title;
      const timeLimit = document.getElementById('modalTimeLimit').value;
      const shuffleAnswersEnabled = document.getElementById('modalShuffleAnswers').checked;

      if (shuffleAnswersEnabled && !confirm(
        'Bạn đang bật đảo nội dung đáp án.\n\n' +
        'Hệ thống giữ nguyên đáp án đúng theo NỘI DUNG, chỉ đổi vị trí A-H.\n' +
        'Tiếp tục?'
      )) return;

      if (selectedModalMode === 'exam') {
        let count = parseInt(modalQuestionCount.value, 10);
        if (!count || count < 1) count = questions.length;
        if (count > questions.length) count = questions.length;
        if (count < questions.length) {
          questions = shuffleArray(questions).slice(0, count);
        }
      }

      if (document.getElementById('modalShuffleQuestions').checked) {
        questions = shuffleArray(questions);
      }
      if (shuffleAnswersEnabled) {
        questions = questions.map(q => shuffleQuestionOptions(q));
      }

      // Lưu vào localStorage để quiz.php chạy mượt mà ngay lập tức
      localStorage.setItem('quizData', JSON.stringify(questions));
      localStorage.setItem('quizExamName', examName);
      localStorage.setItem('quizExamId', currentSelectedExam.exam.id);
      localStorage.setItem('quizMode', selectedModalMode);
      localStorage.setItem('quizTimeLimit', timeLimit);
      localStorage.setItem('quizElapsed', '0');
      localStorage.setItem('quizStartTime', Date.now().toString());

      // Chuyển sang màn hình ôn luyện thi quiz.php
      window.location.href = `quiz.php?exam_id=${currentSelectedExam.exam.id}`;
    }

    // Xóa đề thi
    async function deleteExam(examId, title, e, button) {
      e.stopPropagation();
      if (!confirm(`Bạn có chắc chắn muốn xóa đề thi "${title}" khỏi Database không?`)) return;

      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
      }

      try {
        const res = await fetch('api.php?action=delete_exam', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: examId })
        });
        const json = await res.json();
        if (json.success) {
          allExams = allExams.filter(exam => Number(exam.id) !== Number(examId));
          renderExamsGrid(allExams);
        } else {
          alert('Lỗi: ' + json.message);
          if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
          }
        }
      } catch (err) {
        alert('Lỗi: ' + err.message);
        if (button) {
          button.disabled = false;
          button.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
        }
      }
    }

    // ===== TẢI ĐỀ THI LÊN (SMART PARSER: PDF TỰ DO, WORD, TXT, JSON, DÁN TEXT) =====
    let selectedCoverPreset = 'chalkboard';
    let parsedQuestionsList = [];
    let answerShuffleSnapshot = null;

    function selectCoverPreset(el) {
      document.querySelectorAll('.preset-thumb-option').forEach(opt => opt.classList.remove('selected'));
      el.classList.add('selected');
      selectedCoverPreset = el.getAttribute('data-cover');
    }

    // Chuyển đổi giữa 2 tab Tải File vs Dán Văn Bản
    function switchUploadMode(mode) {
      const btnModeFile = document.getElementById('btnModeFile');
      const btnModePaste = document.getElementById('btnModePaste');
      const fileUploadContainer = document.getElementById('fileUploadContainer');
      const pasteUploadContainer = document.getElementById('pasteUploadContainer');

      if (mode === 'file') {
        btnModeFile.classList.add('active');
        btnModePaste.classList.remove('active');
        fileUploadContainer.style.display = 'block';
        pasteUploadContainer.style.display = 'none';
      } else {
        btnModePaste.classList.add('active');
        btnModeFile.classList.remove('active');
        fileUploadContainer.style.display = 'none';
        pasteUploadContainer.style.display = 'block';
      }
    }

    // Xử lý đọc file khi người dùng chọn (PDF, DOCX, TXT, JSON)
    async function handleSelectedFile(file) {
      if (!file) return;

      const fileParseStatus = document.getElementById('fileParseStatus');
      const upTitle = document.getElementById('upTitle');

      fileParseStatus.style.color = 'var(--text-muted)';
      fileParseStatus.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang đọc và phân tích file <b>${file.name}</b> bằng AI Smart Parser...`;

      if (!upTitle.value.trim()) {
        upTitle.value = file.name.replace(/\.[^/.]+$/, '');
      }

      const ext = file.name.split('.').pop().toLowerCase();
      parsedQuestionsList = [];
      answerShuffleSnapshot = null;
      updateShuffleUndoButton();

      try {
        if (ext === 'pdf') {
          fileParseStatus.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang phân tích PDF chuyên sâu (Nhận diện màu đỏ & các màu đáp án đúng bằng Python AI Engine)...`;
          
          // Gửi file PDF lên server để backend Python (pdfplumber) đọc chính xác 100% màu sắc
          let backendSuccess = false;
          try {
            const formData = new FormData();
            formData.append('pdf_file', file);

            const res = await fetch('api.php?action=upload_pdf_color', {
              method: 'POST',
              body: formData
            });
            const json = await res.json();
            if (json.success && json.questions && json.questions.length > 0) {
              parsedQuestionsList = json.questions;
              backendSuccess = true;
            } else if (json.message) {
              console.warn('Backend PDF Parser thông báo:', json.message);
            }
          } catch (backendErr) {
            console.warn('Không thể gọi Backend PDF Color Parser, fallback sang client parser:', backendErr);
          }

          // Fallback nếu server không chạy được Python
          if (!backendSuccess) {
            if (typeof pdfjsLib === 'undefined') throw new Error('Thư viện đọc PDF chưa sẵn sàng');
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const fullText = await SmartQuizParser.extractTextFromPdf(pdf);
            parsedQuestionsList = SmartQuizParser.parse(fullText);
          }

        } else if (ext === 'docx' || ext === 'doc') {
          if (typeof mammoth === 'undefined') throw new Error('Thư viện đọc Word chưa sẵn sàng');
          const arrayBuffer = await file.arrayBuffer();
          const result = await mammoth.extractRawText({ arrayBuffer });
          parsedQuestionsList = SmartQuizParser.parse(result.value);

        } else if (ext === 'json') {
          const text = await file.text();
          let rawData;
          try { rawData = JSON.parse(text); } catch (e) { rawData = null; }

          parsedQuestionsList = extractJsonQuestionList(rawData);
          if (parsedQuestionsList.length === 0) {
            parsedQuestionsList = SmartQuizParser.parse(text);
          }

        } else {
          // File TXT hoặc định dạng văn bản khác
          const text = await file.text();
          parsedQuestionsList = SmartQuizParser.parse(text);
        }

        // Chuẩn hóa và làm sạch câu hỏi
        cleanAndPrepareQuestions();

        fileParseStatus.style.color = 'var(--success)';
        fileParseStatus.innerHTML = `<i class="fa-solid fa-circle-check"></i> Đã nhận diện thành công <b>${parsedQuestionsList.length}</b> câu hỏi từ file! Bạn có thể xem và chỉnh sửa nhanh bên dưới:`;

        renderQuestionsEditor();

      } catch (err) {
        fileParseStatus.style.color = 'var(--danger)';
        fileParseStatus.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Lỗi phân tích: ${err.message}`;
        document.getElementById('previewQuestionsZone').style.display = 'none';
      }
    }

    // Phân tích văn bản dán tự do
    function parsePastedRawText() {
      const rawText = document.getElementById('rawTextPasteInput').value.trim();
      const fileParseStatus = document.getElementById('fileParseStatus');

      if (!rawText) {
        alert('Vui lòng dán nội dung câu hỏi vào ô văn bản trước!');
        return;
      }

      parsedQuestionsList = SmartQuizParser.parse(rawText);
      answerShuffleSnapshot = null;
      updateShuffleUndoButton();
      cleanAndPrepareQuestions();

      if (parsedQuestionsList.length === 0) {
        alert('Không nhận diện được câu hỏi nào từ văn bản vừa dán. Vui lòng kiểm tra lại!');
        return;
      }

      fileParseStatus.style.color = 'var(--success)';
      fileParseStatus.innerHTML = `<i class="fa-solid fa-circle-check"></i> Đã nhận diện thành công <b>${parsedQuestionsList.length}</b> câu hỏi từ văn bản! Bạn có thể xem và chỉnh sửa bên dưới:`;

      renderQuestionsEditor();
    }

    // Chuẩn hóa mảng câu hỏi và làm sạch triệt để tiền tố lặp 'Câu X:', 'Câu số X:'
    function extractJsonQuestionList(rawData) {
      const isQuestionLike = (value) => {
        if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
        const hasQuestionText = ['question', 'q', 'question_text', 'text', 'content']
          .some(key => typeof value[key] === 'string');
        const hasOptions = value.options && typeof value.options === 'object';
        return hasQuestionText || (typeof value.title === 'string' && hasOptions);
      };

      const fromValue = (value) => {
        if (Array.isArray(value)) {
          if (value.some(isQuestionLike)) return value;
          return value.flatMap(item => {
            if (!item || typeof item !== 'object') return [];
            return fromValue(item.questions || item.data || item.quiz || []);
          });
        }

        if (!value || typeof value !== 'object') return [];
        if (Array.isArray(value.questions) || (value.questions && typeof value.questions === 'object')) {
          const questions = fromValue(value.questions);
          if (questions.length > 0) return questions;
        }

        for (const key of ['data', 'quiz', 'result', 'payload']) {
          if (value[key] !== undefined) {
            const questions = fromValue(value[key]);
            if (questions.length > 0) return questions;
          }
        }
        return isQuestionLike(value) ? [value] : [];
      };

      return fromValue(rawData);
    }

    function cleanAndPrepareQuestions() {
      function cleanPrefix(str) {
        if (!str) return '';
        return str.replace(/^(?:(?:Câu\s*(?:số)?\s*\d+|\d+)[\s.:\)\/\-]+)+/gi, '').trim();
      }

      parsedQuestionsList = parsedQuestionsList.map((q, idx) => {
        const source = q && typeof q === 'object' ? q : {};
        const options = (source.options ?? source.o) && typeof (source.options ?? source.o) === 'object'
          ? (source.options ?? source.o) : {};
        const optionList = Array.isArray(options) ? options : [];
        const optionFromList = (label) => {
          const index = label.charCodeAt(0) - 'A'.charCodeAt(0);
          const item = optionList[index];
          if (item && typeof item === 'object') {
            return item.text ?? item.value ?? item.content ?? item.label ?? '';
          }
          return item ?? '';
        };
        const optionValue = (label) => {
          const lower = label.toLowerCase();
          const hasIndexedAnswer = Array.isArray(source.o) && Number.isInteger(source.a);
          const direct = source[label]
            ?? ((label === 'A' && hasIndexedAnswer) ? undefined : source[lower])
            ?? source[`option_${lower}`]
            ?? options[label] ?? options[lower] ?? options[`option_${lower}`];
          return direct !== undefined && direct !== null ? direct : optionFromList(label);
        };

        let rawQ = source.question ?? source.q ?? source.title ?? source.question_text
          ?? source.text ?? source.content ?? `Câu hỏi số ${idx + 1}`;
        let cleanQ = cleanPrefix(rawQ);
        if (!cleanQ) cleanQ = rawQ;

        let correct = source.correct ?? source.answer ?? source.key ?? source.correct_answer
          ?? source.correctAnswer ?? source.answer_key;
        if (correct === undefined && Number.isInteger(source.a) && Array.isArray(source.o)) {
          correct = String.fromCharCode('A'.charCodeAt(0) + source.a);
        }
        if (correct === undefined) correct = 'A';
        if (Array.isArray(correct)) {
          correct = correct.map(key => String(key).toUpperCase().trim()).filter(Boolean);
        } else if (typeof correct === 'string') {
          correct = correct.toUpperCase().trim();
        }

        return {
          question: cleanQ,
          A: optionValue('A'),
          B: optionValue('B'),
          C: optionValue('C'),
          D: optionValue('D'),
          E: optionValue('E'),
          F: optionValue('F'),
          G: optionValue('G'),
          H: optionValue('H'),
          correct: correct,
          image: source.image ?? source.img ?? source.image_url ?? source.imageUrl ?? '',
          explanation: source.explanation ?? source.explain ?? ''
        };
      }).filter(q => q.question && (q.A || q.B));
    }

    // Render bộ chỉnh sửa & xem trước câu hỏi trực quan
    function renderQuestionsEditor() {
      const previewZone = document.getElementById('previewQuestionsZone');
      const previewList = document.getElementById('previewItemsList');
      const previewCountBadge = document.getElementById('previewCountBadge');

      if (parsedQuestionsList.length === 0) {
        previewZone.style.display = 'none';
        return;
      }

      previewZone.style.display = 'block';
      previewCountBadge.textContent = `${parsedQuestionsList.length} câu hỏi`;
      previewList.innerHTML = '';

      parsedQuestionsList.forEach((q, idx) => {
        const card = document.createElement('div');
        card.className = 'editor-question-card';
        card.setAttribute('data-index', idx);

        const currentKeys = Array.isArray(q.correct) ? q.correct : [q.correct || 'A'];

        card.innerHTML = `
          <div class="editor-card-header">
            <span class="editor-card-num"><i class="fa-regular fa-circle-question"></i> Câu ${idx + 1}</span>
            <button type="button" class="editor-btn-delete" title="Xóa câu hỏi này" onclick="deleteEditorQuestion(${idx})">
              <i class="fa-solid fa-trash-can"></i> Xóa
            </button>
          </div>

          <input type="text" class="editor-input-q" value="${escapeHtml(q.question)}" 
                 placeholder="Nội dung câu hỏi..." onchange="updateEditorField(${idx}, 'question', this.value)">

          ${q.image ? `
            <div class="editor-question-image-wrap">
              <img src="${escapeHtml(q.image)}" alt="Hình minh họa câu ${idx + 1}" class="editor-question-image">
              <span><i class="fa-solid fa-image"></i> Đã nhận diện hình ảnh từ PDF</span>
            </div>
          ` : ''}

          <div class="editor-options-grid">
            <div class="editor-opt-input-group">
              <span class="editor-opt-label">A</span>
              <input type="text" class="editor-opt-input" value="${escapeHtml(q.A)}" 
                     placeholder="Đáp án A..." onchange="updateEditorField(${idx}, 'A', this.value)">
            </div>
            <div class="editor-opt-input-group">
              <span class="editor-opt-label">B</span>
              <input type="text" class="editor-opt-input" value="${escapeHtml(q.B)}" 
                     placeholder="Đáp án B..." onchange="updateEditorField(${idx}, 'B', this.value)">
            </div>
            <div class="editor-opt-input-group">
              <span class="editor-opt-label">C</span>
              <input type="text" class="editor-opt-input" value="${escapeHtml(q.C)}" 
                     placeholder="Đáp án C..." onchange="updateEditorField(${idx}, 'C', this.value)">
            </div>
            <div class="editor-opt-input-group">
              <span class="editor-opt-label">D</span>
              <input type="text" class="editor-opt-input" value="${escapeHtml(q.D)}" 
                     placeholder="Đáp án D..." onchange="updateEditorField(${idx}, 'D', this.value)">
            </div>
          </div>

          <div class="editor-correct-picker">
            <span class="editor-correct-label"><i class="fa-solid fa-check"></i> Đáp án đúng:</span>
            <button type="button" class="btn-key-pick ${currentKeys.includes('A') ? 'active' : ''}" onclick="setQuestionKey(${idx}, 'A')">A</button>
            <button type="button" class="btn-key-pick ${currentKeys.includes('B') ? 'active' : ''}" onclick="setQuestionKey(${idx}, 'B')">B</button>
            <button type="button" class="btn-key-pick ${currentKeys.includes('C') ? 'active' : ''}" onclick="setQuestionKey(${idx}, 'C')">C</button>
            <button type="button" class="btn-key-pick ${currentKeys.includes('D') ? 'active' : ''}" onclick="setQuestionKey(${idx}, 'D')">D</button>
          </div>
        `;

        previewList.appendChild(card);
      });
    }

    function escapeHtml(text) {
      if (!text) return '';
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    function updateEditorField(index, field, value) {
      if (parsedQuestionsList[index]) {
        parsedQuestionsList[index][field] = value;
      }
    }

    function setQuestionKey(index, key) {
      if (parsedQuestionsList[index]) {
        parsedQuestionsList[index].correct = key;
        const card = document.querySelector(`.editor-question-card[data-index="${index}"]`);
        if (card) {
          card.querySelectorAll('.btn-key-pick').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.trim() === key);
          });
        }
      }
    }

    function getEditorCorrectLabels(rawCorrect) {
      const values = Array.isArray(rawCorrect) ? rawCorrect : [rawCorrect];
      const labels = [];
      values.forEach(value => {
        const matches = String(value || '').toUpperCase().match(/[A-H]/g) || [];
        matches.forEach(label => {
          if (!labels.includes(label)) labels.push(label);
        });
      });
      return labels;
    }

    function updateShuffleUndoButton() {
      const undoButton = document.getElementById('btnUndoAnswerShuffle');
      if (undoButton) undoButton.disabled = !answerShuffleSnapshot;
    }

    // Chỉ chạy khi người dùng bấm nút và xác nhận. Nhãn A-H giữ nguyên,
    // còn khóa đáp án được ánh xạ theo nội dung cũ sang vị trí mới.
    function shuffleAnswerContents() {
      if (!parsedQuestionsList.length) return;
      if (answerShuffleSnapshot) {
        alert('Đề này đã được đảo đáp án. Hãy bấm "Hoàn tác" trước nếu muốn đảo lại.');
        return;
      }

      const confirmed = confirm(
        'Đảo nội dung các đáp án A-H?\n\n' +
        'Hệ thống sẽ giữ nguyên đáp án đúng theo NỘI DUNG và tự đổi sang nhãn mới.\n' +
        'Không thực hiện nếu bạn chưa muốn thay đổi đề.'
      );
      if (!confirmed) return;

      answerShuffleSnapshot = JSON.parse(JSON.stringify(parsedQuestionsList));
      let shuffledQuestionCount = 0;
      const labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

      parsedQuestionsList.forEach(question => {
        const availableLabels = labels.filter(label => String(question[label] || '').trim() !== '');
        if (availableLabels.length < 2) return;

        const values = availableLabels.map(label => question[label]);
        const sourceOrder = values.map((_, index) => index);

        // Fisher-Yates; cố gắng tránh giữ nguyên toàn bộ thứ tự.
        for (let i = sourceOrder.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [sourceOrder[i], sourceOrder[j]] = [sourceOrder[j], sourceOrder[i]];
        }
        if (sourceOrder.every((sourceIndex, index) => sourceIndex === index)) {
          [sourceOrder[0], sourceOrder[1]] = [sourceOrder[1], sourceOrder[0]];
        }

        const oldToNew = {};
        availableLabels.forEach((destinationLabel, destinationIndex) => {
          const sourceIndex = sourceOrder[destinationIndex];
          const sourceLabel = availableLabels[sourceIndex];
          question[destinationLabel] = values[sourceIndex];
          oldToNew[sourceLabel] = destinationLabel;
        });

        const oldCorrectLabels = getEditorCorrectLabels(question.correct);
        const newCorrectLabels = oldCorrectLabels
          .map(oldLabel => oldToNew[oldLabel] || oldLabel)
          .filter((label, index, list) => list.indexOf(label) === index);
        if (newCorrectLabels.length > 0) {
          question.correct = newCorrectLabels.length === 1 ? newCorrectLabels[0] : newCorrectLabels;
        }
        shuffledQuestionCount++;
      });

      updateShuffleUndoButton();
      renderQuestionsEditor();
      const status = document.getElementById('fileParseStatus');
      if (status) {
        status.style.color = 'var(--primary)';
        status.innerHTML = `<i class="fa-solid fa-shuffle"></i> Đã đảo nội dung đáp án cho <b>${shuffledQuestionCount}</b> câu. Đáp án đúng đã được giữ theo nội dung; có thể bấm Hoàn tác.`;
      }
    }

    function undoAnswerShuffle() {
      if (!answerShuffleSnapshot) return;
      parsedQuestionsList = JSON.parse(JSON.stringify(answerShuffleSnapshot));
      answerShuffleSnapshot = null;
      updateShuffleUndoButton();
      renderQuestionsEditor();
      const status = document.getElementById('fileParseStatus');
      if (status) status.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Đã hoàn tác việc đảo nội dung đáp án.';
    }

    function deleteEditorQuestion(index) {
      parsedQuestionsList.splice(index, 1);
      renderQuestionsEditor();
    }

    function addNewBlankQuestion() {
      parsedQuestionsList.push({
        question: `Câu hỏi số ${parsedQuestionsList.length + 1}`,
        A: '',
        B: '',
        C: '',
        D: '',
        E: '',
        correct: 'A',
        explanation: ''
      });
      renderQuestionsEditor();
      const previewList = document.getElementById('previewItemsList');
      previewList.scrollTop = previewList.scrollHeight;
    }

    function clearAllParsedQuestions() {
      if (confirm('Bạn có chắc chắn muốn xóa toàn bộ câu hỏi đã nhận diện?')) {
        parsedQuestionsList = [];
        renderQuestionsEditor();
        document.getElementById('fileParseStatus').innerHTML = '';
      }
    }

    // Kéo thả Dropzone
    const dropZone = document.getElementById('dropZone');
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); }, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); }, false);
    });
    dropZone.addEventListener('drop', (e) => {
      if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        handleSelectedFile(e.dataTransfer.files[0]);
      }
    });

    // Lưu Đề thi mới vào Database
    async function submitUploadExam() {
      const title = document.getElementById('upTitle').value.trim();
      const author = document.getElementById('upAuthor').value.trim() || currentUserName;
      const org = document.getElementById('upOrg').value.trim() || 'Đại học Kinh doanh và Công nghệ Hà Nội';

      if (!title) {
        alert('Vui lòng nhập tên đề thi');
        return;
      }

      if (parsedQuestionsList.length === 0) {
        alert('Vui lòng tải file hoặc dán văn bản câu hỏi trước khi lưu vào Database!');
        return;
      }

      const btnSubmit = document.getElementById('btnSubmitUpload');
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu đề thi vào Database...`;

      try {
        const payload = {
          title: title,
          author: author,
          organization: org,
          thumbnail: selectedCoverPreset,
          questions: parsedQuestionsList
        };

        const res = await fetch('api.php?action=upload_exam', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (json.success) {
          alert('🎉 ' + json.message);
          // Reset form
          document.getElementById('uploadExamForm').reset();
          document.getElementById('fileParseStatus').innerHTML = '';
          document.getElementById('previewQuestionsZone').style.display = 'none';
          parsedQuestionsList = [];
          answerShuffleSnapshot = null;
          updateShuffleUndoButton();
          
          // Chuyển sang Tab 1 và làm mới danh sách
          switchMainTab('list');
        } else {
          alert('Lỗi: ' + json.message);
        }
      } catch (err) {
        alert('Lỗi gửi dữ liệu: ' + err.message);
      } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> LƯU ĐỀ THI VÀO DATABASE`;
      }
    }

    // ===== ADMIN MODAL & QUẢN LÝ LỊCH SỬ THI (ANH KHÁNH ĐẸP TRAI NHẤT VŨ TRỤ) =====
    const adminModalOverlay = document.getElementById('adminModalOverlay');

    function openAdminModal() {
      adminModalOverlay.classList.add('active');
      switchAdminTab('history');
    }

    function closeAdminModal() {
      adminModalOverlay.classList.remove('active');
    }

    function switchAdminTab(tab) {
      const btnHist = document.getElementById('btnAdminTabHistory');
      const btnExams = document.getElementById('btnAdminTabExams');
      const secHist = document.getElementById('adminTabHistorySection');
      const secExams = document.getElementById('adminTabExamsSection');

      if (tab === 'history') {
        btnHist.classList.add('active');
        btnExams.classList.remove('active');
        secHist.style.display = 'block';
        secExams.style.display = 'none';
        loadAdminHistory();
      } else {
        btnExams.classList.add('active');
        btnHist.classList.remove('active');
        secHist.style.display = 'none';
        secExams.style.display = 'block';
        loadAdminExams();
      }
    }

    // Tải danh sách lịch sử các bài đã làm
    async function loadAdminHistory() {
      const tbody = document.getElementById('adminHistoryTableBody');
      const countEl = document.getElementById('adminHistoryCount');

      try {
        const res = await fetch('api.php?action=get_history');
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
          countEl.textContent = '0';
          tbody.innerHTML = `
            <tr>
              <td colspan="9">
                <div class="admin-empty-state">
                  <i class="fa-solid fa-clipboard-check"></i>
                  <h4>Chưa có bài thi nào được lưu lại</h4>
                  <p>Khi các học viên hoàn thành bài ôn tập hoặc thi thật, kết quả sẽ tự động được lưu vào đây.</p>
                </div>
              </td>
            </tr>
          `;
          return;
        }

        const list = json.data;
        countEl.textContent = list.length;
        tbody.innerHTML = '';

        list.forEach((att, idx) => {
          const tr = document.createElement('tr');
          const isPractice = att.mode === 'practice';
          const modeBadge = isPractice 
            ? `<span class="badge-mode-pill badge-mode-practice"><i class="fa-solid fa-book-open"></i> Ôn tập</span>`
            : `<span class="badge-mode-pill badge-mode-exam"><i class="fa-solid fa-file-pen"></i> Thi thật</span>`;
          
          const scoreNum = parseFloat(att.score || 0);
          let scoreClass = 'score-tag-high';
          if (scoreNum < 5) scoreClass = 'score-tag-low';
          else if (scoreNum < 8) scoreClass = 'score-tag-mid';

          // Format duration
          const sec = parseInt(att.duration_seconds || 0, 10);
          const m = Math.floor(sec / 60);
          const s = sec % 60;
          const durText = `${m}p ${s < 10 ? '0' : ''}${s}s`;

          tr.innerHTML = `
            <td style="font-weight:700; color:var(--text-muted);">${idx + 1}</td>
            <td style="font-weight:700; max-width:240px; word-break:break-word;">
              ${escapeHtml(att.exam_title || `Đề thi #${att.exam_id}`)}
            </td>
            <td><b>${escapeHtml(att.user_name || 'Học viên')}</b></td>
            <td>${modeBadge}</td>
            <td><span class="${scoreClass}" style="font-size:15px;">${scoreNum.toFixed(1)}/10</span></td>
            <td><b>${att.correct_count}</b> / ${att.total_questions}</td>
            <td><i class="fa-regular fa-clock" style="color:var(--text-muted);"></i> ${durText}</td>
            <td style="color:var(--text-muted); font-size:12.5px;">${att.created_at || ''}</td>
            <td style="text-align:center;">
              <button type="button" class="btn-del-action" title="Xóa lượt thi này" onclick="deleteSingleAttempt(${att.id}, this)">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </td>
          `;
          tbody.appendChild(tr);
        });

      } catch (err) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" style="text-align:center; padding:24px; color:var(--danger);">
              Lỗi tải lịch sử bài làm: ${err.message}
            </td>
          </tr>
        `;
      }
    }

    // Xóa toàn bộ lịch sử các bài thi cũ (Admin)
    async function clearAllAttempts() {
      if (!confirm('Anh Khánh có chắc chắn muốn XÓA TOÀN BỘ tất cả các bài thi cũ đã làm không?\n\nThao tác này sẽ làm sạch toàn bộ lịch sử thi và điểm số cũ!')) {
        return;
      }

      const btn = document.getElementById('btnClearAllHistoryBtn');
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Đang xóa...`;

      try {
        const res = await fetch('api.php?action=clear_history', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
        const json = await res.json();
        if (json.success) {
          alert('Đã xóa sạch toàn bộ lịch sử bài thi thành công!');
          loadAdminHistory();
        } else {
          alert('Lỗi: ' + json.message);
        }
      } catch (err) {
        alert('Lỗi kết nối: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-trash-can"></i> Xóa toàn bộ bài cũ đã làm`;
      }
    }

    // Xóa 1 bài thi cũ cụ thể
    async function deleteSingleAttempt(attemptId, button) {
      if (!confirm(`Anh Khánh có muốn xóa lượt thi #${attemptId} này không?`)) return;

      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
      }

      try {
        const res = await fetch('api.php?action=delete_attempt', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: attemptId })
        });
        const json = await res.json();
        if (json.success) {
          const row = button?.closest('tr');
          row?.remove();
          const countEl = document.getElementById('adminHistoryCount');
          const remainingRows = document.querySelectorAll('#adminHistoryTableBody > tr');
          countEl.textContent = String(Math.max(0, remainingRows.length));
          if (remainingRows.length === 0) {
            loadAdminHistory();
          }
        } else {
          alert('Lỗi: ' + json.message);
          if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
          }
        }
      } catch (err) {
        alert('Lỗi: ' + err.message);
        if (button) {
          button.disabled = false;
          button.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
        }
      }
    }

    // Tải danh sách đề thi trong tab Quản lý Admin
    async function loadAdminExams() {
      const tbody = document.getElementById('adminExamsTableBody');
      const countEl = document.getElementById('adminExamsCount');

      try {
        const res = await fetch('api.php?action=get_exams');
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
          countEl.textContent = '0';
          tbody.innerHTML = `
            <tr>
              <td colspan="7">
                <div class="admin-empty-state">
                  <i class="fa-solid fa-folder-open"></i>
                  <h4>Chưa có đề thi nào trong hệ thống</h4>
                  <p>Hãy tải đề thi mới lên từ file PDF, Word hoặc JSON.</p>
                </div>
              </td>
            </tr>
          `;
          return;
        }

        const list = json.data;
        countEl.textContent = list.length;
        tbody.innerHTML = '';

        list.forEach(ex => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td style="font-weight:700; color:var(--text-muted);">#${ex.id}</td>
            <td style="font-weight:700;">${escapeHtml(ex.title)}</td>
            <td>${escapeHtml(ex.author || 'Học viên')}</td>
            <td><b>${ex.question_count}</b> câu</td>
            <td>${ex.views_count} lượt</td>
            <td style="color:var(--text-muted); font-size:12.5px;">${ex.formatted_date || ''}</td>
            <td style="text-align:center;">
              <a class="btn-pdf-action" href="export_exam.php?exam_id=${encodeURIComponent(ex.id)}" target="_blank" rel="noopener" title="Mở bản PDF để xem trên điện thoại" aria-label="Mở PDF của ${escapeHtml(ex.title)}">
                <i class="fa-solid fa-file-pdf"></i>
              </a>
              <button type="button" class="btn-del-action" title="Xóa đề thi này khỏi hệ thống" onclick="deleteAdminExam(${ex.id}, '${ex.title.replace(/'/g, "\\'")}', this)">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </td>
          `;
          tbody.appendChild(tr);
        });

      } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--danger); padding:20px;">Lỗi: ${err.message}</td></tr>`;
      }
    }

    async function deleteAdminExam(examId, title, button) {
      if (!confirm(`Anh Khánh có chắc chắn muốn xóa vĩnh viễn đề thi "${title}" cùng toàn bộ câu hỏi không?`)) return;

      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
      }

      try {
        const res = await fetch('api.php?action=delete_exam', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: examId })
        });
        const json = await res.json();
        if (json.success) {
          // Cập nhật ngay danh sách đang hiển thị, không tải lại toàn bộ dữ liệu.
          const row = button?.closest('tr');
          row?.remove();
          allExams = allExams.filter(exam => Number(exam.id) !== Number(examId));
          renderExamsGrid(allExams);

          const countEl = document.getElementById('adminExamsCount');
          const remainingRows = document.querySelectorAll('#adminExamsTableBody > tr');
          countEl.textContent = String(Math.max(0, remainingRows.length));
          if (remainingRows.length === 0) {
            loadAdminExams();
          }
        } else {
          alert('Lỗi: ' + json.message);
          if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
          }
        }
      } catch (err) {
        alert('Lỗi: ' + err.message);
        if (button) {
          button.disabled = false;
          button.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
        }
      }
    }

    // Tự động tải danh sách đề khi mở trang
    loadExamsList();
  </script>
</body>
</html>
