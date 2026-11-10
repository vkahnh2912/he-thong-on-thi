@echo off
title EduK Quiz - Localhost Server
chcp 65001 >nul
cls

echo ========================================================
echo       HỆ THỐNG ÔN TẬP & THI TRẮC NGHIỆM EDUK
echo   Khởi động bởi: Anh Khánh đẹp trai nhất vũ trụ
echo ========================================================
echo.
echo [*] Đang khởi động máy chủ PHP Localhost tại cổng 8000...
echo [*] Thư mục dự án: %~dp0
echo.

:: Mở trình duyệt web sau 1 giây
start "" "http://127.0.0.1:8000/index.php"

:: Khởi chạy PHP server
php -S 127.0.0.1:8000 -t "%~dp0"

pause
