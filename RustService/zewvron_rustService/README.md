# Zewvron Rust Service

Backend Rust chay song song voi backend C# cua Zewvron. Service nay doc chung SQL Server database `zewvron`, bind mac dinh port `7001`, va duoc ApiGateway forward qua prefix `/api/rust/*`.

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

`ZEWVRON_RUST_DATABASE_URL` va `ZEWVRON_RUST_CORS_ORIGINS` **bat buoc phai set** — service khong con connection string mac dinh hardcode, se bao loi ro rang va khong start neu thieu bien nao (rieng `ZEWVRON_RUST_CORS_ORIGINS` debug build co fallback origin dev, xem phan CORS ben duoi).

```powershell
$env:ZEWVRON_RUST_BIND = "127.0.0.1:7001"
$env:ZEWVRON_RUST_DATABASE_URL = "Server=LUONG-CONG;Database=zewvron;Integrated Security=true;Encrypt=true;TrustServerCertificate=true"
$env:ZEWVRON_RUST_CORS_ORIGINS = "http://localhost:3000,http://localhost:5000"
cargo run
```

Khuyen nghi `Encrypt=true`. Chi dung `Encrypt=false` khi may dev khong co cert SQL Server hop le va biet ro dang chay trong mang noi bo tin cay (khong encrypt = du lieu + password (neu dung SQL auth) di qua ket noi TCP dang plain text).

Service nay chua co auth layer rieng (xem "API hien co" ben duoi) — **khong bind `0.0.0.0`** tru khi thuc su can (vd. chay trong container co network isolation rieng, va host/security group da chan truy cap tu ngoai vao port nay). Neu bat buoc phai bind `0.0.0.0`, dam bao firewall/security group chan port 7001 khoi internet truoc.

Neu process khong dung duoc Windows integrated auth, dung SQL auth:

```powershell
$env:ZEWVRON_RUST_DATABASE_URL = "Server=LUONG-CONG;Database=zewvron;User Id=YOUR_USER;Password=YOUR_PASSWORD;Encrypt=true;TrustServerCertificate=true"
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
