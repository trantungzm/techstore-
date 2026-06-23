# Quy định làm việc nhóm

## 1. Quy tắc Git
- Không commit trực tiếp lên `main`
- Tất cả thay đổi đi qua `develop` bằng Pull Request
- Mỗi tính năng/sửa lỗi làm trên branch riêng (feature/*, fix/*)
- PR phải có mô tả rõ: mục tiêu, phạm vi, cách test, ảnh/chứng cứ (nếu UI)

## 2. Quy tắc commit message
- Format: `<type>(<scope>): <message>`
- Type dùng: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`
- Ví dụ:
  - `feat(auth): add login and JWT issuing`
  - `fix(order): prevent invalid status transition`
  - `docs: update SQL Server setup guide`

## 3. Code review & merge
- Ít nhất 1 reviewer (trưởng nhóm) trước khi merge vào develop
- Không merge khi còn lỗi build/run cơ bản hoặc chưa test luồng chính
- Ưu tiên squash merge cho PR nhỏ; PR lớn tách nhỏ theo module

## 4. Definition of Done (DoD)
- Build/run được trên máy nhóm (theo hướng dẫn README)
- Không để file rác: `bin/`, `obj/`, `node_modules/`, `dist/`, `.vs/`
- API/FE thay đổi thì phải cập nhật hướng dẫn test nhanh hoặc tài liệu liên quan
- Bug fix phải có mô tả cách tái hiện + cách xác nhận đã fix

## 5. Giao tiếp & cập nhật
- Mỗi thành viên cập nhật tiến độ theo ngày/phiên làm việc
- Các quyết định thay đổi lớn phải ghi lại trong docs (hoặc PR description)