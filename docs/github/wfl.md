# GitHub Workflow

## Luồng làm việc chuẩn
1) Lấy code mới nhất từ develop
2) Tạo branch theo đúng quy ước
3) Commit theo nhóm thay đổi nhỏ, message rõ ràng
4) Push branch lên GitHub
5) Tạo Pull Request vào `develop`
6) Code review + sửa theo comment
7) Merge vào `develop`
8) Khi ổn định, tạo PR `develop` → `main` để chốt bản nộp/milestone

## Quy tắc Pull Request
- Title rõ ràng theo mục tiêu
- Mô tả gồm:
  - Mục tiêu thay đổi
  - Phạm vi ảnh hưởng
  - Cách test (endpoint/URL/steps)
  - Ghi chú cấu hình (nếu có)

## Quy tắc merge
- `main`: chỉ nhận từ PR `develop` → `main`
- `develop`: nhận từ PR feature/fix/docs/test
- Không merge khi PR thiếu mô tả hoặc chưa test tối thiểu