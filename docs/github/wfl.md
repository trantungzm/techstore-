GitHub Workflow
Luồng làm việc chuẩn
Lấy code mới nhất từ develop
Tạo branch theo đúng quy ước
Commit theo nhóm thay đổi nhỏ, message rõ ràng
Push branch lên GitHub
Tạo Pull Request vào develop
Code review + sửa theo comment
Merge vào develop
Khi ổn định, tạo PR develop → main để chốt bản nộp/milestone
Quy tắc Pull Request
Title rõ ràng theo mục tiêu
Mô tả gồm:
Mục tiêu thay đổi
Phạm vi ảnh hưởng
Cách test (endpoint/URL/steps)
Ghi chú cấu hình (nếu có)
Quy tắc merge
main: chỉ nhận từ PR develop → main
develop: nhận từ PR feature/fix/docs/test
Không merge khi PR thiếu mô tả hoặc chưa test tối thiểu