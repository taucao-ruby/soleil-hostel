🌞 Soleil Hostel – Hệ thống Quản lý Nhà Nghỉ / Homestay Hiện Đại

Soleil Hostel là nền tảng quản lý nhà nghỉ/homestay được thiết kế theo phong cách tối giản – hiện đại – mobile-first, giúp chủ homestay truyền thống vận hành hiệu quả hơn: quản lý phòng, đặt phòng, khách hàng, thống kê, và tự động hóa quy trình.

Dự án hướng đến việc nâng cấp mô hình homestay gia đình bằng cách đưa công nghệ vào đúng nơi họ cần: tối ưu vận hành, giảm sai sót, tăng trải nghiệm khách.

🚀 Tính năng chính
🏨 1. Quản lý Phòng

Thêm / sửa / xoá phòng

Quản lý trạng thái phòng (trống, đã đặt, đang dọn dẹp, bảo trì)

Phân loại phòng theo:

Loại phòng

Giá

Sức chứa

Dashboard trực quan xem trạng thái toàn bộ phòng theo thời gian thực

📅 2. Hệ thống Đặt Phòng

Đặt phòng trực tuyến

Lịch đặt phòng dạng calendar

Chỉnh sửa / hủy đặt phòng

Tự động kiểm tra phòng trống

Tối ưu quy trình check-in / check-out

👥 3. Quản lý Khách Hàng

Lưu trữ thông tin khách

Nhật ký lưu trú

Lịch sử đặt phòng

Hỗ trợ chương trình ưu đãi / giảm giá

📊 4. Báo cáo – Thống kê

Doanh thu theo ngày / tuần / tháng

Biểu đồ tỷ lệ lấp đầy phòng

Báo cáo khách hàng quay lại

Export dữ liệu phục vụ quản trị



🧰 Công nghệ sử dụng
🎨 Frontend

ReactJS + TypeScript

Kiến trúc: Mobile-first, tối ưu Core Web Vitals

UI framework: TailwindCSS

React Query, Zustand, Axios

Bố cục tối giản, tập trung vào UX cho chủ homestay

🖥️ Backend

Laravel 11 – PHP 8+

Clean Architecture – Service Layer – Repository Pattern

Authentication (Sanctum)

RESTful API

Test suite: 206 tests (Feature + Unit)

Tối ưu hiệu năng & caching

🛢️ Cơ sở dữ liệu

PostgreSQL

Redis (tuỳ chọn – caching & queue)

⚙️ DevOps

Docker / Docker Compose

GitHub Actions CI/CD

Kiểm thử tự động trước khi deploy

Production-ready structure


📂 Cấu trúc thư mục
soleil-hostel/
├── backend/                 # Laravel backend
│   ├── src/
│   ├── tests/               # 206 tests – full coverage structure
│   └── composer.json
│
├── frontend/                # React + TS frontend
│   ├── src/
│   ├── public/
│   └── package.json
│
├── docs/                    # Tài liệu hệ thống (được tổ chức chuẩn hóa)
│   ├── PROJECT_STATUS.md
│   ├── SESSION_*.md
│   ├── TEST_SUITE_SUMMARY.md
│   └── ...
│
├── scripts/                 # Các script hỗ trợ
└── README.md                # Tài liệu chính (file này)


🛠️ Cài đặt & Chạy dự án
🧱 Yêu cầu hệ thống

Node.js 18+

PHP 8.2+

Composer

PostgreSQL 12+

Redis (tuỳ chọn)

1️⃣ Clone dự án
git clone https://github.com/taucao-ruby/soleil-hostel.git
cd soleil-hostel

2️⃣ Cài đặt Frontend
cd frontend
npm install
npm run dev

3️⃣ Cài đặt Backend
cd ../backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve


🤝 Đóng góp vào dự án

Chúng tôi hoan nghênh mọi đóng góp:

Fork repo

Tạo branch mới
git checkout -b feature/my-feature

Commit thay đổi
git commit -m "Add new feature"

Push lên GitHub
git push origin feature/my-feature


Tạo Pull Request

🧭 Feature Roadmap (Lộ trình phát triển)
✅ Hoàn thành

Kiến trúc Frontend (React + TS, mobile-first)

Kiến trúc Backend (Laravel + API Layer + Repository Pattern)

Dashboard quản lý phòng

CRUD phòng + trạng thái phòng

Booking flow cơ bản

Customer management cơ bản

CI/CD với GitHub Actions

Test suite hoàn chỉnh: 206 tests

Documentation chuẩn hóa

🚧 Đang phát triển

Lịch đặt phòng dạng Calendar nâng cao

Module báo cáo doanh thu

Module thống kê khách hàng

Hệ thống phân quyền (Admin / Staff)

UI/UX tối ưu cho chủ homestay lớn tuổi

📝 Dự kiến triển khai

Công cụ check-in / check-out nhanh

Tự động gửi email / hóa đơn

Kết nối OTA (Booking.com, Agoda…)

Ứng dụng Mobile

Thanh toán online


🏗️ System Architecture (Kiến trúc hệ thống)
Sơ đồ kiến trúc ở dạng mô tả (ASCII). Nếu bạn muốn mình có thể tạo bản PNG đẹp để đưa vào repo.
                            ┌─────────────────────────┐
                            │        Frontend         │
                            │  React + TypeScript     │
                            │  Tailwind + Axios       │
                            └────────────┬────────────┘
                                         │ REST API
                                         ▼
                            ┌─────────────────────────┐
                            │         Backend         │
                            │        Laravel          │
                            │  Controllers / Services │
                            │  Repositories / Models  │
                            └────────────┬────────────┘
                                         │
                   ┌─────────────────────┴─────────────────────┐
                   ▼                                           ▼
        ┌───────────────────────┐                    ┌───────────────────────┐
        │      PostgreSQL       │                    │         Redis         │
        │   (phòng, khách,      │                    │  Cache + Queue jobs   │
        │    booking, logs…)    │                    └───────────────────────┘
        └───────────────────────┘
                   
                   ┌───────────────────────────────────────────┐
                   │                 DevOps                     │
                   │ Docker | GitHub Actions | Deploy scripts   │
                   └───────────────────────────────────────────┘

🧪 Test Suite Summary (Tổng quan test suite – 206 tests)

Dự án hiện có 206 tests bao gồm unit test + feature test, đảm bảo các module hoạt động ổn định trước khi release.

📁 Phân bổ theo nhóm
Nhóm Test	Số lượng	Mô tả
Feature Tests	120	Kiểm thử toàn bộ flow API (phòng, booking, khách hàng…).
Unit Tests	86	Kiểm thử logic lớp Repository, Service, Helper…
Auth Tests	12	Kiểm tra login, token, quyền truy cập.
Booking Tests	40	Kiểm tra quy trình đặt phòng + kiểm tra phòng trống.
Room Tests	30	CRUD phòng, trạng thái phòng.
Customer Tests	18	Tạo – cập nhật – lịch sử khách hàng.
Report Tests	10	Kiểm tra logic tính doanh thu và tỷ lệ lấp đầy.
🎯 Mục tiêu chất lượng

Toàn bộ API quan trọng có test coverage

CI chạy test tự động trong mỗi pull request

Đảm bảo không merge code lỗi vào branch main


📬 Liên hệ
Tác giả: Ngọc Tàu
Email: tau.cao21@student.passerellesnumeriques.org
GitHub: https://github.com/taucao-ruby

📄 Giấy phép 
Dự án được phát hành theo giấy phép MIT.
Xem file LICENSE để biết chi tiết.


