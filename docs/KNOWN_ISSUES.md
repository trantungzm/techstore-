# Known issues / backlog chưa xử lý

Việc đã xác nhận là vấn đề thật nhưng **chưa làm** — ghi lại để không quên, không phải danh sách việc cần làm ngay.

## 1. `BaseCore.AuditLog` / `BaseCore.LogService` — chưa từng build được từ commit đầu tiên

- **Hiện trạng:** `BaseCore.AuditLog` vẫn ở `netcoreapp2.2`, không build được (NU1201: `BaseCore.LogService` không tương thích netcoreapp2.2). `BaseCore.LogService` — dù đã là `net8.0` — **tự nó cũng không build được**, độc lập hoàn toàn với việc migrate TFM: `LogActionService.cs` và `LogErrorService.cs` tham chiếu `MongoRepository<T>`, `IMongoRepository<T>`, `IDbContext` từ namespace `BaseCore.Libs.Repository` — namespace này **chưa từng tồn tại trong repo**, kể cả ở commit tạo LogService đầu tiên (`e0de952`, `a8ef05c`, 2026-06-23; đã xác nhận qua `git log --all -S "namespace BaseCore.Libs.Repository"` và tìm file `MongoRepository.cs`/`IDbContext.cs` từng bị xoá — không có kết quả nào).
- **Ảnh hưởng thực tế hiện tại:** không có gì — không project nào reference `BaseCore.AuditLog`, không ai chạy nó. Đây là code chết từ khi tạo ra, chưa từng chạy được trong production.
- **Cần quyết định trước khi đầu tư sửa:** có tiếp tục dùng MongoDB cho audit log (cần viết mới `MongoRepository<T>`/`IDbContext`/`IMongoRepository<T>` — kết nối, cấu hình, error handling thật) hay đổi sang SQL Server cho đồng bộ với phần còn lại của hệ thống. Đây là quyết định kiến trúc, không phải chỗ nên tự đoán khi sửa lỗi.
- **Không gấp** — không ai đang phụ thuộc vào service này.

## 2. Batch 3 — dependency High/Critical còn lại chưa bump (từ audit bảo mật)

### Frontend (npm)
- `axios` → ≥1.18.0 (prototype-pollution, ReDoS, proxy-auth header leak)
- `react-router-dom` / `react-router` → ≥7.18.2 (RCE qua turbo-stream deserialization, CVSS 8.1)
- `postcss` → ≥8.5.23 (arbitrary file read qua sourceMappingURL)
- `vite` → 8.3.0 (major bump — cần đánh giá breaking change trước khi lên)
- `rollup` (transitive) → ≥4.59.0 (arbitrary file write qua path traversal)
- `nanoid` (transitive) → ≥3.3.18 (infinite loop / integer overflow)
- `form-data` (transitive) → ≥4.0.6 (CRLF injection trong multipart field)
- `browserslist` (transitive) → ≥4.28.7 (unbounded memory growth)

### PHP (composer)
- `guzzlehttp/guzzle` → ≥7.15.2 (noncanonical host bypass host-based check, CVE-2026-69246)
- `league/commonmark` → ≥2.10.0 (8 advisory: DoS, XSS filter bypass)

### .NET (NuGet)
- `System.Text.Json` → ≥8.0.5 (APIService, AuthService, Repository)
- `Microsoft.Extensions.Caching.Memory` → ≥8.0.1
- `System.Formats.Asn1` 5.0.0 → bản vá mới nhất tương ứng
- `Snappier` (BaseCore.LogService) → ≥1.0.1

## 3. Cấu hình bắt buộc trước khi deploy production

Đã ghi chi tiết ở [`docs/PRE_DEPLOY_CHECKLIST.md`](./PRE_DEPLOY_CHECKLIST.md) — chỉ tham chiếu lại ở đây, không lặp nội dung:

- `JWT_SECRET` thật cho cả 3 service .NET + PHP.
- Domain CORS thật (`Cors:WithOrigin` cho Gateway/APIService/AuthService, `CORS_ALLOWED_ORIGINS` cho PHP admin service).
- Chạy migration DB (bao gồm `AddRefreshTokens`) trên staging/production trước khi deploy code.
