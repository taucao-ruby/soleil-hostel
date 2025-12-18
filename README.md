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

---

## 🎯 Project Philosophy

- Product-first, không phải demo kỹ thuật
- Maintainability > Feature bloat
- Test & Documentation là một phần của sản phẩm
- Thiết kế cho người dùng không rành công nghệ

Dự án được xây dựng như một hệ thống có thể sống lâu dài, không phải proof-of-concept.

---

## 🚀 Core Features

### 🏨 1. Room Management

- CRUD phòng (thêm / sửa / xoá)
- Quản lý trạng thái:
  - Trống
  - Đã đặt
  - Đang dọn dẹp
  - Bảo trì
- Phân loại theo:
  - Loại phòng
  - Giá
  - Sức chứa
- Dashboard trạng thái phòng theo thời gian thực

---

### 📅 2. Booking System

- Đặt phòng trực tuyến
- Lịch đặt phòng dạng **Calendar**
- Chỉnh sửa / huỷ booking
- Tự động kiểm tra phòng trống
- Ngăn **double-booking** ở tầng business logic
- Tối ưu quy trình **check-in / check-out**

---

### 👥 3. Customer Management

- Lưu trữ thông tin khách
- Nhật ký lưu trú
- Lịch sử đặt phòng
- Nền tảng cho ưu đãi & khách hàng thân thiết

---

### 📊 4. Reports & Analytics

- Doanh thu theo ngày / tuần / tháng
- Biểu đồ tỷ lệ lấp đầy phòng
- Báo cáo khách hàng quay lại
- Xuất dữ liệu phục vụ quản trị

---

## 🧰 Tech Stack

### 🎨 Frontend

- **ReactJS + TypeScript**
- Mobile-first, tối ưu **Core Web Vitals**
- **TailwindCSS**
- React Query, Zustand, Axios
- UI/UX tối giản, tập trung vào **chủ homestay**

---

### 🖥️ Backend

- **Laravel 11 – PHP 8.2+**
- Clean Architecture:
  - Controllers
  - Service Layer
  - Repository Pattern
- Authentication: **Laravel Sanctum**
- RESTful API
- **206 automated tests** (Feature + Unit)
- Security & performance-focused design

---

### 🛢️ Data Layer

- **PostgreSQL**
- **Redis** _(optional – cache & queue)_

---

### ⚙️ DevOps

- Docker & Docker Compose
- GitHub Actions (CI/CD)
- Automated test execution before merge
- Production-ready structure

---

## 📂 Project Structure (High-level)

soleil-hostel/
├── backend/ # Laravel backend
│ ├── src/
│ ├── tests/ # 206 automated tests
│ └── composer.json
│
├── frontend/ # React + TypeScript
│ ├── src/
│ ├── public/
│ └── package.json
│
├── docs/ # Consolidated system documentation
│ ├── getting-started/
│ ├── features/
│ ├── architecture/
│ ├── testing/
│ ├── deployment/
│ └── archive/
│
└── README.md

📚 **Technical Documentation:**  
→ **[Go to docs/README.md](./docs/README.md)**

---

## 🛠️ Local Development

### 🧱 System Requirements

- Node.js **18+**
- PHP **8.2+**
- Composer
- PostgreSQL **12+**
- Redis _(optional)_

---

### 🚀 Quick Start

#### 1️⃣ Clone repository

```bash
git clone https://github.com/taucao-ruby/soleil-hostel.git
cd soleil-hostel

####2️⃣ Frontend
cd frontend
npm install
npm run dev

####3️⃣ Backend
cd ../backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

####🤝 Contributing
Mọi đóng góp đều được hoan nghênh:
# Create new branch
git checkout -b feature/my-feature

# Commit changes
git commit -m "Add new feature"

# Push branch
git push origin feature/my-feature

Sau đó mở Pull Request trên GitHub.
```

## 🧭 Roadmap

### ✅ Completed

- Frontend architecture (React + TypeScript, mobile-first)
- Backend architecture (Laravel + Clean Architecture)
- Room dashboard & CRUD
- Core booking flow
- Customer management
- CI/CD with GitHub Actions
- Full test suite (206 tests)
- Documentation consolidation & cleanup

---

### 🚧 In Progress

- Advanced booking calendar
- Revenue & analytics modules
- Customer statistics module
- Role-based access (Admin / Staff)
- UX optimization for non-technical / elderly homestay owners

---

### 📝 Planned

- Fast check-in / check-out flow
- Automated email & invoice sending
- OTA integration (Booking.com, Agoda…)
- Mobile application
- Online payment integration

---

## 🏗️ System Architecture

┌───────────────────────────────────────┐
│ Frontend │
│ React + TypeScript │
│ TailwindCSS + Axios │
│ Mobile-first UI/UX │
└───────────────────┬───────────────────┘
│ REST API (JSON)
▼
┌───────────────────────────────────────┐
│ Backend │
│ Laravel │
│ Controllers │ Services │ Repositories │
│ Clean Architecture │
└───────────────────┬───────────────────┘
│
┌───────────┴───────────┐
▼ ▼
┌───────────────────────┐ ┌───────────────────────┐
│ PostgreSQL │ │ Redis │
│ Rooms / Bookings │ │ Cache / Queue │
│ Customers / Reports │ │ Performance Boost │
└───────────────────────┘ └───────────────────────┘

┌───────────────────────────────────────┐
│ DevOps │
│ Docker │ GitHub Actions │ CI / CD │
│ Automated Tests & Deployments │
└───────────────────────────────────────┘

## 🧪 Test Suite Summary

Dự án hiện có **296 automated tests**, bao gồm **Unit Tests** và **Feature Tests**, đảm bảo hệ thống ổn định trước khi release.

| Nhóm Test      | Số lượng | Mô tả                           |
| -------------- | -------- | ------------------------------- |
| Feature Tests  | 246      | Kiểm thử toàn bộ flow API       |
| Unit Tests     | 50       | Logic Service, Model, Enum      |
| Auth Tests     | 26       | Xác thực & token management     |
| Booking Tests  | 60       | Đặt phòng, overlap, soft delete |
| Room Tests     | 24       | Optimistic locking              |
| RBAC Tests     | 47       | Role-based access control       |
| Security Tests | 77       | Headers, XSS, Rate limiting     |
| Cache Tests    | 6        | Redis cache layer               |

✔️ CI chạy test tự động cho mỗi Pull Request  
✔️ Không merge code lỗi vào `main`

---

## 📚 Documentation

Tài liệu dự án được tổ chức trong thư mục `docs/`:

```
docs/
├── README.md                    # Documentation index
├── guides/                      # How-to guides
│   ├── ENVIRONMENT_SETUP.md     # Dev environment
│   ├── TESTING.md               # Testing guide
│   └── DEPLOYMENT.md            # Deployment
├── architecture/                # System design
│   ├── README.md                # Architecture overview
│   └── DATABASE.md              # Schema & indexes
├── features/                    # Feature docs
│   ├── AUTHENTICATION.md        # Auth system
│   ├── BOOKING.md               # Booking system
│   ├── ROOMS.md                 # Room management
│   ├── RBAC.md                  # Access control
│   └── CACHING.md               # Redis cache
└── security/                    # Security docs
    ├── README.md                # Security overview
    ├── HEADERS.md               # Security headers
    ├── XSS_PROTECTION.md        # HTML Purifier
    └── RATE_LIMITING.md         # Rate limiting
```

📚 **Full Documentation:** → **[docs/README.md](./docs/README.md)**

## 🎯 Quality Goals

Soleil Hostel được xây dựng với mục tiêu **ổn định – dễ bảo trì – sẵn sàng production**, không đánh đổi chất lượng để lấy tốc độ ngắn hạn.

### Quality Principles

- **Correctness first**: Tính đúng nghiệp vụ quan trọng hơn tối ưu sớm
- **Test-driven confidence**: Test là hàng rào bảo vệ hệ thống
- **Fail fast, fix early**: Phát hiện lỗi sớm trong CI thay vì production
- **Maintainability over cleverness**: Code dễ đọc > code thông minh nhưng khó hiểu

### Quality Standards

- Tất cả **critical APIs** đều có test coverage
- Business logic nằm ở **Service Layer**, không nằm trong Controller
- Không cho phép **double-booking** ở mọi kịch bản
- Validation & authorization được kiểm soát chặt chẽ
- Không merge code khi:
  - Test fail
  - CI không pass
  - Vi phạm coding standards

---

## 🤖 AI-assisted Development

Dự án được phát triển với sự hỗ trợ của **AI agents** nhằm tăng tốc độ phát triển,  
nhưng **AI không thay thế tư duy kỹ thuật và trách nhiệm của con người**.

### AI được sử dụng để:

- Sinh code boilerplate
- Gợi ý cấu trúc thư mục & module
- Hỗ trợ viết test và refactor
- Hỗ trợ phân tích edge cases

### Trách nhiệm của tác giả:

- Thiết kế kiến trúc tổng thể
- Xây dựng & kiểm soát business logic
- Review, refactor và tối ưu code
- Thiết kế test strategy & CI/CD pipeline
- Chịu trách nhiệm **100%** cho chất lượng cuối cùng của sản phẩm

---

## 📬 Contact

**Author:** Ngọc Tàu  
**Email:** tau.cao21@student.passerellesnumeriques.org  
**GitHub:** https://github.com/taucao-ruby

---

## 📄 License

This project is licensed under the **[MIT License](./LICENSE)**.
