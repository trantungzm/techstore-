# Checklist bắt buộc trước khi deploy production lần đầu

Chưa mục nào trong danh sách này được thực hiện — đây chỉ là ghi nhận để không quên trước khi go-live. Tất cả các mục đều là điều kiện chặn (blocker), không phải optional.

## 1. Secrets

- [ ] Set `JWT_SECRET` **thật** (khác hẳn secret dùng ở môi trường dev) qua biến môi trường hoặc secret manager, áp dụng đồng thời cho cả 4 nơi: `BaseCore.AuthService`, `BaseCore.APIService`, `BaseCore.ApiGateway` (nếu cần), và `services/php-admin-service/laravel` (`JWT_SECRET` trong `.env`). Cả 3-4 service phải dùng **cùng một giá trị** để verify JWT chéo nhau được.

## 2. CORS

- [ ] Đổi tên `appsettings.Production.json.example` → `appsettings.Production.json` cho cả 3 service .NET (`BaseCore.ApiGateway`, `BaseCore.APIService`, `BaseCore.AuthService`), điền domain CORS thật vào `Cors:WithOrigin` (thay placeholder `https://REPLACE_WITH_PRODUCTION_ORIGIN`). Thiếu file này, service sẽ crash ngay khi khởi động ở môi trường Production (đây là hành vi cố ý — fail-fast thay vì âm thầm mở CORS).
- [ ] Set `CORS_ALLOWED_ORIGINS` thật (không phải giá trị dev `http://localhost:3000,http://localhost:5000`) cho PHP admin service, dùng bởi `config/cors.php`.

## 3. Debug mode

- [ ] `APP_DEBUG=false` đã là mặc định trong `.env.example`, nhưng **verify lại thủ công** giá trị thật trong `.env` của môi trường production trước khi go-live — không suy diễn từ `.env.example`.

## 4. Database migrations

- [ ] Chạy `dotnet ef database update --project BaseCore.Repository --startup-project BaseCore.AuthService` (hoặc APIService) trên DB staging/production **trước khi deploy code mới** — đặc biệt migration `AddRefreshTokens` (bảng mới cho tính năng refresh-token) và bất kỳ migration nào khác đang pending. `Database:AutoMigrateOnStartup` mặc định `false`, migration không tự áp dụng khi service khởi động.

## 5. Nợ kỹ thuật từ audit bảo mật — CHƯA làm, coi là điều kiện chặn

- [ ] **Batch 2 — Migrate `BaseCore.AuditLog` và `BaseCore.UnitTest` khỏi `netcoreapp2.2`.** Framework này đã EOL, mang theo CVE Critical chưa có bản vá (Kestrel.Core GHSA-5rrx-jjjq-q2r5, System.Text.Encodings.Web GHSA-ghhp-997w-qr28) do không thể vá trong dòng netcoreapp2.2 — chỉ hết khi nâng lên net8.0 như phần còn lại của stack.
- [ ] **Batch 3 — Bump dependency High/Critical** đã liệt kê trong audit: `axios`, `react-router-dom`, `postcss`, `vite`/`rollup`, `nanoid`, `form-data`, `browserslist` (frontend); `guzzlehttp/guzzle`, `league/commonmark` (PHP); `System.Text.Json`, `Microsoft.Extensions.Caching.Memory`, `System.Formats.Asn1`, `Snappier` (.NET).

## Ghi chú

Checklist này được tạo sau đợt fix Critical/High (9 commit, xem lịch sử `main`) — các mục Critical/High đã có trong đợt đó (JWT secret hardcode, CORS mở toang, IDOR, role check, upload không validate, exception leak) đã được xử lý và merge vào `main`. Danh sách trên là phần **còn lại** trước khi có thể go-live an toàn.
