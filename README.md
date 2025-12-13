# 🌞 Soleil Hostel  
## Modern Homestay / Hostel Management System

**Soleil Hostel** là nền tảng quản lý nhà nghỉ/homestay được thiết kế theo phong cách **tối giản – hiện đại – mobile-first**, giúp chủ homestay truyền thống vận hành hiệu quả hơn thông qua việc:

- Quản lý phòng  
- Đặt phòng  
- Quản lý khách hàng  
- Thống kê & báo cáo  
- Tự động hóa quy trình vận hành  

Dự án hướng đến mục tiêu **nâng cấp mô hình homestay gia đình**, đưa công nghệ vào đúng nơi họ cần để:
- Giảm sai sót vận hành thủ công
- Tiết kiệm thời gian  
- Nâng cao trải nghiệm khách lưu trú
- Chuẩn hóa quy trình khi quy mô dần mở rộng

🎯 Project Philosophy
- Product-first, không phải demo kỹ thuật
- Maintainability > Feature bloat
- Test & Documentation là một phần của sản phẩm
- Thiết kế cho người dùng không rành công nghệ
Dự án được xây dựng như một hệ thống có thể sống lâu dài, không phải proof-of-concept.

---

## 🚀 Tính năng chính - Core features

### 🏨 1. Quản lý Phòng - Room Management
- Thêm / sửa / xoá phòng  CRUD 
- Quản lý trạng thái phòng:
  - Trống  
  - Đã đặt  
  - Đang dọn dẹp  
  - Bảo trì  
- Phân loại phòng theo:
  - Loại phòng  
  - Giá  
  - Sức chứa  
- Dashboard trực quan hiển thị trạng thái phòng theo thời gian thực  

---

### 📅 2. Hệ thống Đặt Phòng - Booking System
- Đặt phòng trực tuyến  
- Lịch đặt phòng dạng **Calendar**  
- Chỉnh sửa / huỷ đặt phòng  
- Tự động kiểm tra phòng trống  
- Tối ưu quy trình **check-in / check-out**, ngăn double-booking ở tầng business logic

---

### 👥 3. Quản lý Khách Hàng - Customer Management
- Lưu trữ thông tin khách  
- Nhật ký lưu trú  
- Lịch sử đặt phòng  
- Hỗ trợ chương trình ưu đãi / giảm giá khách hàng quen.

---

### 📊 4. Báo cáo & Thống kê - Reports & Analytics
- Doanh thu theo:
  - Ngày  
  - Tuần  
  - Tháng  
- Biểu đồ tỷ lệ lấp đầy phòng  
- Báo cáo khách hàng quay lại  
- Xuất dữ liệu phục vụ quản trị  

---

## 🧰 Công nghệ sử dụng - Tech Stack

### 🎨 Frontend
- **ReactJS + TypeScript**  
- Kiến trúc: **Mobile-first**, tối ưu **Core Web Vitals**  
- UI Framework: **TailwindCSS**  
- State & Data:
  - React Query  
  - Zustand  
  - Axios  
- Thiết kế UI/UX tối giản, tập trung vào người dùng là **chủ homestay**

---

### 🖥️ Backend
- **Laravel 11 – PHP 8+**  
- Clean Architecture:
  - Service Layer
  - Controllers
  - Repository Pattern  
- Authentication: **Laravel Sanctum**  
- RESTful API  
- **Test Suite: 206 tests** (Feature + Unit) 
- Tối ưu hiệu năng & caching  - Security & performance-focused design

---

### 🛢️ Cơ sở dữ liệu - Data Layer
- **PostgreSQL**  
- **Redis** *(tuỳ chọn – caching & queue)*  

---

### ⚙️ DevOps
- Docker / Docker Compose  
- GitHub Actions (CI/CD)  
- Kiểm thử tự động trước khi deploy  - Automated test execution before merge
- Production-ready structure  

---

## 📂 Cấu trúc thư mục - Project Structure (High-level)
soleil-hostel/
├── backend/          # Laravel backend
│   ├── src/
│   ├── tests/        # 206 automated tests
│   └── composer.json
│
├── frontend/         # React + TypeScript
│   ├── src/
│   ├── public/
│   └── package.json
│
├── docs/             # Consolidated system documentation
│   ├── getting-started/
│   ├── features/
│   ├── architecture/
│   ├── testing/
│   ├── deployment/
│   └── archive/
│
└── README.md

📚 **Technical Documentation:**  
→ **[Go to docs/README.md](./docs/README.md)**


---

## 🛠️ Cài đặt & Chạy dự án - Local Development

### 🧱 Yêu cầu hệ thống- System Requirements
- Node.js **18+**  
- PHP **8.2+**  
- Composer  
- PostgreSQL **12+**  
- Redis *(tuỳ chọn)*  

---

### Quick Start
1️⃣ Clone dự án 
```bash
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
```

###🤝 Đóng góp vào dự án
Mọi đóng góp đều được hoan nghênh:

Fork repository
Tạo branch mới
git checkout -b feature/my-feature

Commit thay đổi
git commit -m "Add new feature"

Push lên GitHub
git push origin feature/my-feature

Mở Pull Request
git pull

###🧭 Feature Roadmap
##✅ Hoàn thành
- Kiến trúc Frontend (React + TypeScript, mobile-first)
- Kiến trúc Backend (Laravel + Clean Architecture)
- Dashboard quản lý phòng
- CRUD phòng & trạng thái phòng
- Booking flow cơ bản
- Quản lý khách hàng
- CI/CD với GitHub Actions
- Test suite hoàn chỉnh (206 tests)
- Documentation chuẩn hóa


##🚧 Đang phát triển
- Lịch đặt phòng nâng cao
- Module báo cáo doanh thu
- Module thống kê khách hàng 
- Phân quyền (Admin / Staff)
- UI/UX tối ưu cho chủ homestay lớn tuổi

##📝 Dự kiến triển khai
- Check-in / Check-out nhanh
- Gửi email & hoá đơn tự động
- Kết nối OTA (Booking.com, Agoda…)
- Ứng dụng Mobile
- Thanh toán online

##🏗️ System Architecture
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
   ┌─────────┴─────────┐
   ▼                   ▼
┌───────────────┐  ┌───────────────┐
│  PostgreSQL   │  │     Redis     │
│ Room, Booking │  │ Cache & Queue │
│ Customer Data │  └───────────────┘
└───────────────┘

┌───────────────────────────────────┐
│              DevOps               │
│ Docker | GitHub Actions | CI/CD   │
└───────────────────────────────────┘

##🧪 Test Suite Summary

Dự án hiện có 206 tests, bao gồm Unit Tests và Feature Tests, đảm bảo hệ thống ổn định trước khi release.
| Nhóm Test      | Số lượng | Mô tả                     |
| -------------- | -------- | ------------------------- |
| Feature Tests  | 120      | Kiểm thử toàn bộ flow API |
| Unit Tests     | 86       | Logic Service, Repository |
| Auth Tests     | 12       | Xác thực & phân quyền     |
| Booking Tests  | 40       | Đặt phòng & phòng trống   |
| Room Tests     | 30       | CRUD & trạng thái phòng   |
| Customer Tests | 18       | Quản lý khách hàng        |
| Report Tests   | 10       | Doanh thu & thống kê      |

✔️ CI chạy test tự động cho mỗi Pull Request
✔️ Không merge code lỗi vào main

##🎯 Mục tiêu chất lượng
- API quan trọng đều có test coverage
- CI chạy test tự động cho mỗi Pull Request
- Không merge code lỗi vào main


## 🤖 AI-assisted Development

Dự án này được phát triển với sự hỗ trợ của AI agents nhằm tăng tốc độ phát triển.

Vai trò của AI:
- Sinh code boilerplate
- Gợi ý cấu trúc
- Hỗ trợ viết test

Vai trò của tác giả:
- Thiết kế kiến trúc hệ thống
- Xây dựng business logic
- Review, refactor và tối ưu code
- Thiết kế test strategy và CI/CD
- Chịu trách nhiệm hoàn toàn cho chất lượng cuối cùng


##📬 Liên hệ - Contact
Tác giả: Ngọc Tàu
Email: tau.cao21@student.passerellesnumeriques.org
GitHub: https://github.com/taucao-ruby

##📄 Giấy phép - License
This project is licensed under the **[MIT License](./LICENSE)**.
