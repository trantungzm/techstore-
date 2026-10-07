# Zewvron Rust Service

Backend Rust chay song song voi backend C# cua Zewvron. Service nay doc chung SQL Server database `techstore1`, bind mac dinh port `7001`, va duoc ApiGateway forward qua prefix `/api/rust/*`.

## Vai tro

- Rust la BE thu 2, khong thay the C#.
- C# phu trach luong nghiep vu chinh: Product Catalog, Product Detail, auth, cart, order, inventory, admin CRUD, coupon, warranty, ticket.
- Rust chi phu trach cac API read-only bo tro:
  - Product Compare Service
  - Recommendation Service
  - Search Suggestion Service
- Rust khong chay migration, seed, tao/sua/xoa database.

## Chay dong thoi cac service

Khi test tren frontend, can chay:

1. `Zewvron.ApiGateway`
2. `Zewvron.APIService`
3. `RustService/zewvron_rustService`
4. `Zewvron.WebClient` neu dang dev frontend rieng

Luong request:

```text
Frontend -> ApiGateway -> /api/products/...              -> C# APIService
Frontend -> ApiGateway -> /api/rust/product-compare      -> RustService
Frontend -> ApiGateway -> /api/rust/recommendations/*    -> RustService
Frontend -> ApiGateway -> /api/rust/search-suggestions   -> RustService
```

ApiGateway da co route `/api/rust/{everything}` tro den Rust service `http://localhost:7001`.

## Chay Rust service

```powershell
cd RustService\zewvron_rustService
cargo run
```

Mac dinh service bind (khong can set gi):

```text
http://127.0.0.1:7001
```

Các biến môi trường dùng tiền tố `ZEWVRON_RUST_`. **Service này không đọc file `.env`** — không có crate `dotenv`/`dotenvy` nào trong `Cargo.toml`, chỉ đọc biến môi trường thật của process (`std::env::var`). Một file `.env` nằm trong thư mục này (nếu có) hoàn toàn không có tác dụng với `cargo run`/binary đã build — phải export các biến vào môi trường của tiến trình trước khi chạy, bằng một trong các cách:
- Shell: `$env:ZEWVRON_RUST_DATABASE_URL = "..."` (PowerShell) rồi `cargo run` trong **cùng session** đó.
- Script khởi động đọc `.env` và export thủ công (ví dụ `Get-Content .env | ... | Set-Item -Path Env:...`), vì bản thân binary không tự làm việc này.
- Biến môi trường container khi deploy bằng Docker (`environment:`/`env_file:` trong compose) — Docker tiêm trực tiếp vào môi trường process bên trong container, đây **không phải** Rust đọc `.env`, mà là runtime container làm việc đó trước khi binary khởi động.

`ZEWVRON_RUST_DATABASE_URL` và `ZEWVRON_RUST_CORS_ORIGINS` **bắt buộc phải set** — service không còn connection string mặc định hardcode, sẽ báo lỗi rõ ràng và không start nếu thiếu biến nào (riêng `ZEWVRON_RUST_CORS_ORIGINS` debug build có fallback origin dev, xem phần CORS bên dưới).

```powershell
$env:ZEWVRON_RUST_BIND = "127.0.0.1:7001"
$env:ZEWVRON_RUST_DATABASE_URL = "Server=LUONG-CONG;Database=techstore1;Integrated Security=true;Encrypt=true;TrustServerCertificate=true"
$env:ZEWVRON_RUST_CORS_ORIGINS = "http://localhost:3000,http://localhost:5000"
cargo run
```

**Chuỗi kết nối dev cần `TrustServerCertificate=true`**, vì SQL Server dev dùng chứng chỉ tự ký (self-signed) — `tiberius` sẽ từ chối kết nối với lỗi "certificate chain... terminated in a root certificate which is not trusted" nếu thiếu cờ này. Lưu ý: `Encrypt=false` **không** loại bỏ TLS hoàn toàn — gói tin login (TDS login packet) của SQL Server luôn bắt buộc mã hoá TLS bất kể `Encrypt`, nên vẫn cần `TrustServerCertificate=true` để handshake lúc đăng nhập đi qua được, dù các gói tin sau đó có mã hoá hay không. Khuyến nghị `Encrypt=true;TrustServerCertificate=true` cho dev.

Service nay chua co auth layer rieng (xem "API hien co" ben duoi) — **khong bind `0.0.0.0`** tru khi thuc su can (vd. chay trong container co network isolation rieng, va host/security group da chan truy cap tu ngoai vao port nay). Neu bat buoc phai bind `0.0.0.0`, dam bao firewall/security group chan port 7001 khoi internet truoc.

Neu process khong dung duoc Windows integrated auth, dung SQL auth:

```powershell
$env:ZEWVRON_RUST_DATABASE_URL = "Server=LUONG-CONG;Database=techstore1;User Id=YOUR_USER;Password=YOUR_PASSWORD;Encrypt=true;TrustServerCertificate=true"
cargo run
```

## API hien co

Tat ca endpoint Rust nam duoi prefix:

```text
/api/rust
```

### Product Compare

So sanh san pham theo danh sach id:

```http
POST /api/rust/product-compare
```

Payload:

```json
{
  "productIds": [1, 2]
}
```

### Recommendation

Lay san pham cross-sell da cau hinh, logic giong C# `GET /api/recommendations/cross-sell`:

```http
GET /api/rust/recommendations/cross-sell?productId=1&maxItems=4
```

Lay san pham goi y tu dong, logic giong C# `GET /api/recommendations/auto-cross-sell`:

```http
GET /api/rust/recommendations/auto-cross-sell?productId=1&maxItems=4
```

Ket qua tra ve dang `RecommendationDto[]`, moi item co field `product` dung cho card san pham.

### Search Suggestions

Goi y san pham khi user go tu khoa search:

```http
GET /api/rust/search-suggestions?q=iphone&maxItems=6
```

Neu `q` rong, API tra ve cac san pham noi bat/ban chay de hien thi trong dropdown search.

## Frontend

Frontend goi Rust qua `Zewvron.WebClient/src/services/api.js`:

```js
rustApi.productCompare.compare(productIds)
rustApi.recommendations.getCrossSell(productId, maxItems)
rustApi.recommendations.getAutoCrossSell(productId, maxItems)
rustApi.searchSuggestions.get(q, maxItems)
```

Shop va Product Detail quay lai dung API C#:

```js
productApi.getAll(params)
productApi.getById(id)
```

## Kiem tra nhanh

Goi truc tiep Rust service:

```powershell
Invoke-RestMethod -Method Post "http://localhost:7001/api/rust/product-compare" -ContentType "application/json" -Body '{"productIds":[1,2]}'
Invoke-RestMethod "http://localhost:7001/api/rust/recommendations/cross-sell?productId=1&maxItems=4"
Invoke-RestMethod "http://localhost:7001/api/rust/recommendations/auto-cross-sell?productId=1&maxItems=4"
Invoke-RestMethod "http://localhost:7001/api/rust/search-suggestions?q=iphone&maxItems=6"
```

Goi qua ApiGateway:

```powershell
Invoke-RestMethod -Method Post "http://localhost:5000/api/rust/product-compare" -ContentType "application/json" -Body '{"productIds":[1,2]}'
Invoke-RestMethod "http://localhost:5000/api/rust/recommendations/cross-sell?productId=1&maxItems=4"
Invoke-RestMethod "http://localhost:5000/api/rust/recommendations/auto-cross-sell?productId=1&maxItems=4"
Invoke-RestMethod "http://localhost:5000/api/rust/search-suggestions?q=iphone&maxItems=6"
```

## Kiem tra build

```powershell
cargo check
```

## Module hien tai

```text
src/dto/product_compare.rs
src/dto/recommendation.rs
src/repositories/product_compare_repository.rs
src/repositories/recommendation_repository.rs
src/services/product_compare_service.rs
src/services/recommendation_service.rs
src/routes/product_compare_routes.rs
src/routes/recommendation_routes.rs
```

## Ghi chu

Product Catalog Rust khong con duoc public route. Neu can danh sach/chi tiet san pham, frontend dung C# APIService.
