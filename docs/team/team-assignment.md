# Phân công nhóm - TechStore (BaseCore)

## Thông tin nhóm
- Repository: https://github.com/trantungzm/techstore-
- Mục tiêu: Xây dựng hệ thống TechStore theo kiến trúc BaseCore (APIService/AuthService/ApiGateway/WebClient) và vận hành bằng SQL Server.

## Thành viên & vai trò

### 1) Trần Thanh Tùng — Trưởng nhóm / Tester / Tích hợp
- Quản lý repo: branch, PR, review, merge rule
- Điều phối tiến độ, phân rã công việc theo giai đoạn
- Kiểm thử chức năng (test plan, test cases, bug log), nghiệm thu theo Definition of Done
- Tích hợp tổng thể: backend ↔ gateway ↔ frontend, kiểm tra luồng end-to-end
- Viết/tổng hợp tài liệu: README, hướng dẫn chạy, ghi chú triển khai

### 2) Bình — Backend Developer
- Thiết kế và triển khai API: Auth, Catalog, Order, Inventory, Support
- EF Core: entity, migration, seed, repository/service
- Chuẩn hóa response, validation và phân quyền theo role
- Fix bug backend theo bug log, hỗ trợ tích hợp

### 3) Lương — Frontend Developer / Integration
- WebClient (React): UI store + admin, routing, state, gọi API qua gateway
- Tích hợp login/role routing, xử lý lỗi và loading
- Fix bug frontend, phối hợp xác nhận API contract

## Nguyên tắc bàn giao
- Mọi hạng mục hoàn thành phải có:
  - Code trên branch feature
  - PR vào develop
  - Mô tả thay đổi + hướng dẫn test nhanh
  - Đã self-test và cập nhật checklist/bằng chứng (ảnh/chú thích) nếu cần