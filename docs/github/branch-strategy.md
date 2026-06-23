
# Branch Strategy

## Branch chính
- `main`: ổn định, dùng để nộp/báo cáo, hạn chế thay đổi trực tiếp
- `develop`: nhánh tích hợp, nơi nhận PR từ các nhánh tính năng

## Nhánh công việc
- `feature/backend-*`: tính năng backend (ví dụ: feature/backend-auth)
- `feature/frontend-*`: tính năng frontend (ví dụ: feature/frontend-admin-ui)
- `fix/*`: sửa lỗi (ví dụ: fix/order-status)
- `docs/*`: tài liệu (ví dụ: docs/sql-setup)
- `test/*`: test, checklist, test plan (ví dụ: test/e2e-checklist)

## Quy tắc tạo nhánh
- Tạo từ `develop`
- Tên nhánh ngắn gọn, mô tả đúng nội dung

## Merge rule
- PR vào `develop` phải qua review
- `main` chỉ merge từ `develop` (PR riêng để chốt milestone)