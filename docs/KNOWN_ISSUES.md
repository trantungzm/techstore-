# Known issues / backlog chưa xử lý

Việc đã xác nhận là vấn đề thật nhưng **chưa làm** — ghi lại để không quên, không phải danh sách việc cần làm ngay.

## 1. `BaseCore.AuditLog` / `BaseCore.LogService` — chưa từng build được từ commit đầu tiên

- **Hiện trạng:** `BaseCore.AuditLog` vẫn ở `netcoreapp2.2`, không build được (NU1201: `BaseCore.LogService` không tương thích netcoreapp2.2). `BaseCore.LogService` — dù đã là `net8.0` — **tự nó cũng không build được**, độc lập hoàn toàn với việc migrate TFM: `LogActionService.cs` và `LogErrorService.cs` tham chiếu `MongoRepository<T>`, `IMongoRepository<T>`, `IDbContext` từ namespace `BaseCore.Libs.Repository` — namespace này **chưa từng tồn tại trong repo**, kể cả ở commit tạo LogService đầu tiên (`e0de952`, `a8ef05c`, 2026-06-23; đã xác nhận qua `git log --all -S "namespace BaseCore.Libs.Repository"` và tìm file `MongoRepository.cs`/`IDbContext.cs` từng bị xoá — không có kết quả nào).
- **Ảnh hưởng thực tế hiện tại:** không có gì — không project nào reference `BaseCore.AuditLog`, không ai chạy nó. Đây là code chết từ khi tạo ra, chưa từng chạy được trong production.
- **Cần quyết định trước khi đầu tư sửa:** có tiếp tục dùng MongoDB cho audit log (cần viết mới `MongoRepository<T>`/`IDbContext`/`IMongoRepository<T>` — kết nối, cấu hình, error handling thật) hay đổi sang SQL Server cho đồng bộ với phần còn lại của hệ thống. Đây là quyết định kiến trúc, không phải chỗ nên tự đoán khi sửa lỗi.
- **Không gấp** — không ai đang phụ thuộc vào service này.

## 2. Batch 3 — dependency High/Critical (từ audit bảo mật)

**Đã xử lý** trên nhánh `fix/dependency-bump-batch3` (2026-09-30), trừ `vite` (xem mục 2b). Build/test/chạy thật đã xác nhận không có lỗi runtime mới do version mismatch — chi tiết trong message của 3 commit tương ứng.

### Frontend (npm) — commit `chore(webclient): bump dependency vá lỗ hổng High (trừ vite)`
- ✅ `axios` 1.13.4 → 1.20.0 (yêu cầu ≥1.18.0): prototype-pollution, ReDoS, proxy-auth header leak
- ✅ `react-router-dom` / `react-router` 7.13.0 → 7.18.4 (yêu cầu ≥7.18.2): RCE qua turbo-stream deserialization, CVSS 8.1
- ✅ `postcss` 8.4.32 → 8.5.28 (yêu cầu ≥8.5.23): arbitrary file read qua sourceMappingURL
- ✅ `rollup` (transitive qua vite) 4.57.0 → 4.63.5 (yêu cầu ≥4.59.0): arbitrary file write qua path traversal
- ✅ `nanoid` (transitive qua postcss) 3.3.11 → 3.3.19 (yêu cầu ≥3.3.18): infinite loop / integer overflow
- ✅ `form-data` (transitive qua axios) 4.0.5 → 4.0.6: CRLF injection trong multipart field
- ✅ `browserslist` (transitive) 4.28.2 → 4.29.2 (yêu cầu ≥4.28.7): unbounded memory growth
- ⬜ `vite` — **chưa bump**, xem mục 2b bên dưới

### PHP (composer) — commit `chore(php-admin): bump guzzle và commonmark vá lỗ hổng High`
- ✅ `guzzlehttp/guzzle` 7.13.1 → 7.15.5 (yêu cầu ≥7.15.2): noncanonical host bypass host-based check, CVE-2026-69246
- ✅ `league/commonmark` 2.8.2 → 2.10.3 (yêu cầu ≥2.10.0): 8 advisory DoS, XSS filter bypass

### .NET (NuGet) — commit `chore(dotnet): bump NuGet package vá lỗ hổng High`
- ✅ `System.Text.Json` 4.7.2 (transitive qua Azure.Identity/Microsoft.Data.SqlClient) → 8.0.6, ở APIService/AuthService/Repository/Services
- ✅ `Microsoft.Extensions.Caching.Memory` 8.0.0 → 8.0.1, cùng 4 project trên
- ✅ `System.Formats.Asn1` 5.0.0 → 8.0.2, cùng 4 project trên
- ✅ `Snappier` (BaseCore.LogService) 1.0.0 → **1.3.1** (không phải 1.0.1 như audit ban đầu ghi — advisory High mới hơn CVE-2026-44302, infinite loop khi decompress stream lỗi định dạng, chỉ được vá từ bản 1.3.1; đã xác nhận qua `dotnet restore` cảnh báo NU1903 trước khi nâng)
- Cả 4 gói đều là transitive dependency (không project nào khai báo trực tiếp) — elevate bằng cách thêm `PackageReference` tường minh vào từng project bị ảnh hưởng, không sửa gì khác.

### 2b. `vite` — major bump 5.x → 8.x, để riêng chờ quyết định

- **Hiện tại:** `^5.0.8` trong `package.json`, resolve thực tế `5.4.21` (đã là bản mới nhất của nhánh 5.x).
- **Đề xuất:** `8.3.1` (bản mới nhất tại thời điểm audit, 2026-09-30).
- **Vì sao chưa bump:** major bump 3 version liên tiếp (5→6→7→8), kéo theo bump bắt buộc `@vitejs/plugin-react` — bản mới nhất của plugin này (dùng cho vite 8) yêu cầu peer dependency mới hoàn toàn: `oxc-transform-react`, `@rolldown/plugin-babel`, `babel-plugin-react-compiler` (đã xác nhận qua `npm view @vitejs/plugin-react@latest peerDependencies`). `@tailwindcss/vite` thì tương thích sẵn cả 5/6/7/8 (`peerDependencies.vite: "^5.2.0 || ^6 || ^7 || ^8"`), không phải lo.
- **Breaking change đáng chú ý** (tổng hợp từ migration guide chính thức của Vite, đọc trực tiếp `v6.vite.dev` / `v7.vite.dev` / `vite.dev` main vì package không có sẵn CHANGELOG trong `node_modules`):
  - **Node.js:** vite 7+ và 8 yêu cầu Node `^20.19.0 || >=22.12.0` (bỏ hỗ trợ Node 18). Máy dev hiện tại (Node v24.13.0) đủ điều kiện, nhưng `package.json` **chưa khai báo `engines.node`** — cần kiểm tra môi trường build/CI khác (nếu có) trước khi bump.
  - **Vite 8** đổi hẳn build engine từ esbuild/Rollup sang **Rolldown + Oxc**: `build.rollupOptions` → `build.rolldownOptions`, `esbuild` options → `oxc` options (có auto-convert), Lightning CSS thay esbuild làm CSS minifier mặc định (có thể tăng nhẹ kích thước CSS bundle), một số plugin hook bị xoá (`shouldTransformCachedModule`, `resolveImportMeta`, `renderDynamicImport`).
  - **Vite 7** đổi browser target mặc định (Chrome 87→107, Firefox 78→104, Safari 14→16, dùng `'baseline-widely-available'` thay `'modules'`), xoá `splitVendorChunkPlugin` (dùng `build.rollupOptions.output.manualChunks` thay), đổi `transformIndexHtml` hook (`enforce`/`transform` → `order`/`handler`), bỏ Sass legacy API hoàn toàn.
  - **Vite 6** đổi default cho `resolve.conditions`, Sass chuyển sang modern API mặc định (dự án này dùng Tailwind CSS 4, không dùng Sass/SCSS nên không ảnh hưởng), đổi tên file CSS mặc định ở library mode (không áp dụng — dự án không build library mode).
  - Tổng kết: **rủi ro chính là phải bump `@vitejs/plugin-react` kèm chuỗi peer dependency Rolldown/Oxc mới**, không phải các thay đổi CSS/Sass (dự án không dùng Sass). Nên làm thành batch riêng, test kỹ `npm run build`/`npm run dev` và toàn bộ trang trước khi merge.

## 2c. Advisory mới phát hiện trong lúc audit lại batch 3 (2026-09-30) — chưa xử lý, mức độ thấp hơn High

Phát sinh từ `npm audit` / `composer audit` / `dotnet list package --vulnerable` sau khi bump — không có trong audit gốc, mức độ Low/Moderate nên **để ngoài phạm vi batch 3** (chỉ xử lý High/Critical). Ghi lại để theo dõi ở batch sau:

- npm: `@babel/core` ≤7.29.0 (Low, arbitrary file read qua sourceMappingURL, transitive qua `@vitejs/plugin-react`) — `npm audit fix` (không force) có thể vá được, chưa thử.
- composer: `laravel/framework` (Low, XSS in Debug Page Information, CVE-2026-102279) và `league/flysystem` (Low, path normalizer bypass với UTF-8 lỗi định dạng, CVE-2026-102601) — cả hai vừa được công bố advisory ngày 2026-09-29/30, cần `composer update` riêng framework/flysystem ở batch sau.
- NuGet: `Azure.Identity` 1.10.3 (2× Moderate) và `Microsoft.Identity.Client` 4.56.0 (Low + Moderate) — transitive qua `Microsoft.Data.SqlClient`, ảnh hưởng APIService/AuthService/Repository/Services. `SharpCompress` 0.30.1 (Moderate) — transitive qua `MongoDB.Driver` trong LogService.

## 3. Cấu hình bắt buộc trước khi deploy production

Đã ghi chi tiết ở [`docs/PRE_DEPLOY_CHECKLIST.md`](./PRE_DEPLOY_CHECKLIST.md) — chỉ tham chiếu lại ở đây, không lặp nội dung:

- `JWT_SECRET` thật cho cả 3 service .NET + PHP.
- Domain CORS thật (`Cors:WithOrigin` cho Gateway/APIService/AuthService, `CORS_ALLOWED_ORIGINS` cho PHP admin service).
- Chạy migration DB (bao gồm `AddRefreshTokens`) trên staging/production trước khi deploy code.

## 4. `RecommendationsController.GetAutoCrossSell` (.NET) crash 100% request — đã xử lý qua cutover sang Rust

**Đã xử lý** trên nhánh `feature/wire-rust-gateway` (2026-10-01), commit `a3b62f1` + `b89ae58`.

- **Bug:** `BaseCore.APIService/Controllers/RecommendationsController.cs:71` (action `GetAutoCrossSell`) dùng `x.Stock > 0` trong LINQ, với `Stock` là computed property (`get => TotalStock ?? 0`) trên entity `Product`. EF Core không dịch được biểu thức này sang SQL cho câu query cụ thể này, ném `InvalidOperationException: Translation of member 'Stock' on entity type 'Product' failed`.
- **Mức độ:** nghiêm trọng trên thực tế — `ProductRecommendations` rỗng hoàn toàn (xác nhận qua `sqlcmd`), nên **100% request** gọi `GET /api/recommendations/auto-cross-sell` đều rơi vào nhánh fallback chứa LINQ lỗi này, trả về HTTP 400 cho mọi sản phẩm. Tính năng "gợi ý tự động" đã chết từ trước khi có bất kỳ thay đổi nào trong đợt cutover — không phải do thay đổi lần này gây ra, chỉ là được phát hiện trong lúc so sánh output .NET vs Rust trước khi cutover (test 14 product ID, 14/14 đều lỗi 400).
- **Cách phát hiện:** so sánh output thật giữa .NET (`:5001`) và Rust (`:7001`) cho cùng 14 product ID đa dạng category/tồn kho trước khi cutover — phát hiện .NET luôn 400 còn Rust luôn 200 với dữ liệu hợp lý.
- **Cách xử lý:** không vá bug .NET riêng lẻ — cutover thẳng route `/api/recommendations/cross-sell` và `/auto-cross-sell` sang RustService (đã có logic lọc/sắp xếp tương đương, viết bằng SQL trực tiếp nên không gặp lỗi dịch LINQ), sau đó xoá hẳn `RecommendationsController.cs`. Ghi lại ở đây để không bị hiểu lầm là "xoá code đang chạy tốt" — controller này đã crash từ trước khi bị xoá.
- **Tác dụng phụ cần biết:** 2 action `PUT` của controller cũ (cấu hình thủ công cross-sell cho từng sản phẩm) bị retired theo, không migrate sang Rust (Rust read-only, không có auth layer). Xác nhận trước khi xoá: 0 call site ở frontend. Hiện **không có API nào ghi được** bảng `ProductRecommendations` — xem `docs/architecture/multi-service-migration-plan.md` mục Data Ownership nếu cần làm lại tính năng này.

## 5. Tính năng ghi `ProductRecommendations` thủ công (admin cấu hình cross-sell tay) — đã gỡ, chưa có nơi thay thế

Tính năng ghi `ProductRecommendations` thủ công (admin cấu hình cross-sell tay cho sản phẩm cụ thể) đã bị gỡ cùng đợt cutover Recommendations sang Rust (`PUT /api/recommendations` cũ không có consumer, Rust chỉ đọc theo nguyên tắc data ownership — xem mục 4 phía trên).

Nếu sau này cần lại tính năng này (phổ biến trong e-commerce thật — ghim sản phẩm gợi ý theo chiến dịch/marketing), nên làm ở Rust (thêm khả năng ghi, phá nguyên tắc read-only hiện tại có chủ đích) hoặc PHP admin service (đồng bộ pattern Banner/Settings/Notifications đã quản trị qua PHP) — không nên làm lại ở .NET vì Recommendations đã không còn là service sở hữu bảng này.
