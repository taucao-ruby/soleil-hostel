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
- Axios
- UI/UX tối giản, tập trung vào **chủ homestay**

---

### 🖥️ Backend

- **Laravel 12 – PHP 8.2+**
- Clean Architecture:
  - Controllers
  - Service Layer
  - Repository Pattern
- Authentication: **Laravel Sanctum**
- RESTful API
- **1037 automated tests** (Feature + Unit) + **236 frontend tests**
- Security & performance-focused design

---

### 🛢️ Data Layer

- **PostgreSQL 16**
- **Redis 7** _(optional – cache & queue)_

---

### ⚙️ DevOps

- Docker & Docker Compose
- GitHub Actions (CI/CD)
- Automated test execution before merge
- Production-ready structure

---

## 📂 Project Structure

```
soleil-hostel/
├── backend/                          # Laravel 12 Backend
│   ├── app/                          # Application Code
│   │   ├── Console/                  # Artisan Commands
│   │   ├── Enums/                    # PHP Enums
│   │   ├── Events/                   # Event Classes
│   │   ├── Exceptions/               # Custom Exceptions
│   │   ├── Helpers/                  # Helper Functions
│   │   ├── Http/                     # Controllers & Middleware
│   │   ├── Jobs/                     # Queue Jobs
│   │   ├── Models/                   # Eloquent Models
│   │   ├── Policies/                 # Authorization Policies
│   │   ├── Services/                 # Business Logic Layer
│   │   ├── Traits/                   # Reusable Traits
│   │   └── ...                       # Other Laravel directories
│   ├── config/                       # Configuration Files
│   ├── database/                     # Migrations & Seeders
│   ├── public/                       # Public Assets
│   ├── resources/                    # Views & Assets
│   ├── routes/                       # API Routes
│   ├── storage/                      # File Storage
│   ├── tests/                        # 1037 Automated Tests
│   │   ├── Feature/                  # Feature Tests
│   │   └── Unit/                     # Unit Tests

│   ├── composer.json                 # PHP Dependencies
│   ├── phpunit.xml                   # Test Configuration
│   └── artisan                       # Laravel CLI
│
├── frontend/                         # React + TypeScript Frontend
│   ├── src/                          # Source Code
│   │   ├── app/                      # App Configuration
│   │   ├── features/                 # Feature Modules
│   │   ├── pages/                    # Page Components
│   │   ├── shared/                   # Shared Components & API Client
│   │   ├── types/                    # TypeScript Types
│   │   └── utils/                    # Utility Functions
│   ├── public/                       # Static Assets
│   ├── tests/                        # Playwright E2E Tests
│   ├── package.json                  # Node Dependencies
│   ├── vite.config.ts                # Vite Configuration
│   └── tailwind.config.js            # Tailwind CSS Config
│
├── docs/                             # Technical Documentation
│   ├── backend/                      # Backend Documentation
│   │   ├── architecture/             # System Architecture
│   │   ├── features/                 # Feature Documentation
│   │   ├── guides/                   # How-to Guides
│   │   └── security/                 # Security Documentation
│   ├── frontend/                     # Frontend Documentation (12 modular files)
│   │   ├── ARCHITECTURE.md           # Main Architecture
│   │   ├── APP_LAYER.md              # App Layer
│   │   ├── FEATURES_LAYER.md         # Features
│   │   ├── SERVICES_LAYER.md         # API Services
│   │   ├── SHARED_LAYER.md           # Shared Components
│   │   ├── TYPES_LAYER.md            # TypeScript Types
│   │   ├── UTILS_LAYER.md            # Utilities
│   │   ├── CONFIGURATION.md          # Configuration
│   │   ├── TESTING.md                # Testing Guide
│   │   ├── PERFORMANCE_SECURITY.md   # Performance & Security
│   │   └── DEPLOYMENT.md             # Deployment
│   ├── DATABASE.md                   # Database Schema & Migrations
│   └── README.md                     # Documentation Index
│
├── database/                         # Database Files
│   ├── docker/                       # Docker Database Setup
│   └── docs/                         # Database Documentation
│
├── scripts/                          # Utility Scripts
├── resources/                        # Shared Resources
├── docker-compose.yml                # Docker Services
├── redis.conf                        # Redis Configuration
├── package.json                      # Root Dependencies
└── README.md                         # Project README
```

📚 **Technical Documentation:**  
→ **[Go to docs/README.md](./docs/README.md)**

---

## 🛠️ Local Development

### 🧱 System Requirements

- Node.js **20+**
- PHP **8.2+**
- Composer
- PostgreSQL **16+**
- Redis _(optional)_

---

### 🚀 Quick Start

#### 1️⃣ Clone repository

```bash
git clone https://github.com/taucao-ruby/soleil-hostel.git
cd soleil-hostel
```

#### 2️⃣ Frontend

```bash
cd frontend
pnpm install
pnpm dev
# Frontend will be available at: http://localhost:5173
```

#### 3️⃣ Backend

```bash
cd ../backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed

# Start PHP development server
php -S 127.0.0.1:8000 -t public public/index.php
# Backend API available at: http://127.0.0.1:8000
```

#### 🤝 Contributing

Mọi đóng góp đều được hoan nghênh:

```bash
# Create new branch
git checkout -b feature/my-feature

# Commit changes
git commit -m "Add new feature"

# Push branch
git push origin feature/my-feature
```

Sau đó mở Pull Request trên GitHub.

## 🧭 Roadmap

### ✅ Completed

- Frontend architecture (React + TypeScript, mobile-first)
- Backend architecture (Laravel + Clean Architecture)
- Room dashboard & CRUD with optimistic locking
- Core booking flow with pessimistic locking
- Customer management
- CI/CD with GitHub Actions
- Full test suite (1037 backend tests, 2803 assertions + 236 frontend tests)
- Documentation consolidation & cleanup
- Frontend documentation restructured into 12 modular files (January 2, 2026)
- Backend documentation fully organized
- Both servers verified running successfully
- Optimistic locking for room concurrency control (January 2026)
- Role-based access control: 3 roles (User, Moderator, Admin), 7 authorization gates (March 2026)
- DB hardening: FK delete policies, CHECK constraints, booking status enforcement (March 2026)
- Operational domain: stays, room assignments, service recovery cases — four-layer model (March 2026)
- Booking notifications: event-driven queued emails (confirm, update, cancel)
- Security hardening: A+ headers, HTML Purifier XSS, multi-tier rate limiting, CSRF
- Admin audit log with forensic recovery
- Customer management endpoints (admin)

---

### 🚧 In Progress

- Advanced booking calendar
- Revenue & analytics modules
- Customer statistics module
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

```
┌─────────────────────────────────────────────────────────────────┐
│                    🌞 SOLEIL HOSTEL SYSTEM                        │
│                    Modern Homestay Management                    │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                        🖥️ FRONTEND LAYER                         │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   React 19      │  │  TypeScript     │  │  TailwindCSS    │ │
│  │   Components    │  │  Type Safety    │  │  Mobile-first   │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  Vitest         │  │   Axios         │  │  Feature-Sliced │ │
│  │  Unit Tests     │  │   HTTP Client   │  │   Architecture  │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   Vite          │  │  Playwright     │  │   ESLint        │ │
│  │   Build Tool    │  │   E2E Tests     │  │   Code Quality  │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                       🔄 API COMMUNICATION                       │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │                    RESTful API (JSON)                       │ │
│  │              Laravel Sanctum Authentication                 │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                        🖥️ BACKEND LAYER                         │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │                 Laravel 12 - Clean Architecture              │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  Controllers    │  │   Services      │  │  Repositories   │ │
│  │  HTTP Layer     │  │  Business Logic │  │  Data Access    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   Middleware    │  │   Policies      │  │    Events       │ │
│  │   Security      │  │   Authorization │  │   Async Tasks   │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   PHPUnit       │  │   PHPStan       │  │    Psalm        │ │
│  │  1037 Tests     │  │   Static Analysis│  │  Code Quality  │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                        🛢️ DATA LAYER                            │
│                                                                 │
│  ┌─────────────────┐                    ┌─────────────────┐     │
│  │   PostgreSQL    │◄──────────────────►│     Redis       │     │
│  │   Primary DB    │                    │   Cache/Queue   │     │
│  │                 │                    │                 │     │
│  │  ┌─────────────┐│  ┌─────────────┐   │  ┌─────────────┐│     │
│  │  │   Rooms     ││  │  Bookings   │   │  │   Cache     ││     │
│  │  │   Customers ││  │  Reports    │   │  │   Sessions  ││     │
│  │  │   Users     ││  │  Analytics  │   │  │   Queue     ││     │
│  │  └─────────────┘│  └─────────────┘   │  └─────────────┘│     │
│  └─────────────────┘                    └─────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                        ⚙️ DEVOPS LAYER                          │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │    Docker       │  │ GitHub Actions  │  │   CI/CD         │ │
│  │  Containers     │  │   Automation    │  │   Pipelines     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   Testing       │  │   Security      │  │   Deployment    │ │
│  │   Automation    │  │   Headers       │  │   Production    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### 🏛️ Architecture Principles

- **Clean Architecture**: Separation of concerns with clear layer boundaries
- **Service Layer Pattern**: Business logic isolated from HTTP concerns
- **Repository Pattern**: Data access abstraction
- **Event-Driven**: Asynchronous processing for performance
- **Security-First**: Multiple layers of protection (headers, validation, auth)
- **Test-Driven**: Comprehensive test coverage ensuring reliability

## 🧪 Test Suite Summary

Dự án hiện có **1037 backend tests** + **236 frontend tests**, bao gồm Feature Tests, Unit Tests, và UI tests.

| Nhóm Test      | Số lượng | Mô tả                                    |
| -------------- | -------- | ---------------------------------------- |
| Backend Tests  | 1037     | Feature + Unit (2803 assertions)         |
| Frontend Tests | 236      | Vitest (24 test files)                   |
| Auth Tests     | 44       | Xác thực & token management              |
| Booking Tests  | 60       | Đặt phòng, overlap, soft delete          |
| Room Tests     | 151      | CRUD + Optimistic locking                |
| RBAC Tests     | 47       | Role-based access control                |
| Security Tests | 91       | Headers, XSS, Rate limiting             |
| Cache Tests    | 6        | Redis cache layer                        |
| Operational    | 48+      | Stays, room assignments, service recovery|
| DB Hardening   | 8        | FK policies, CHECK constraints           |

✔️ CI chạy test tự động cho mỗi Pull Request  
✔️ Không merge code lỗi vào `main`

---

## 📚 Documentation

Tài liệu dự án được tổ chức trong thư mục `docs/`:

```
docs/
├── README.md                    # Documentation index
├── DATABASE.md                  # Database schema & indexes
├── DB_FACTS.md                  # DB invariants & constraints
├── DOMAIN_LAYERS.md             # Four-layer operational domain model
├── backend/                     # Backend documentation
│   ├── README.md                # Backend index
│   ├── architecture/            # System design (API, services, middleware, events...)
│   ├── features/                # Feature docs (auth, booking, rooms, RBAC, caching...)
│   ├── guides/                  # How-to guides (setup, testing, deployment, migration...)
│   └── security/                # Security docs (headers, XSS, rate limiting)
├── frontend/                    # Frontend documentation (12 modular files)
│   ├── ARCHITECTURE.md          # Main architecture
│   ├── FEATURES_LAYER.md        # Feature modules
│   ├── SERVICES_LAYER.md        # API services
│   ├── TESTING.md               # Testing guide
│   └── ...                      # App, shared, types, utils, config, deployment layers
└── agents/                      # AI agent framework
    ├── CONTRACT.md              # Definition of Done
    ├── ARCHITECTURE_FACTS.md    # Domain invariants
    └── COMMANDS.md              # Verified commands
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
