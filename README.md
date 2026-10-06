# Zewvron

Du an Zewvron gom frontend React/Vite va cac backend service xay dung tren .NET.

## Cau truc chinh

- `Zewvron.WebClient`: giao dien nguoi dung va trang quan tri
- `Zewvron.ApiGateway`: gateway phuc vu frontend build va route API
- `Zewvron.APIService`: service chinh cho san pham, don hang, banner, upload anh
- `Zewvron.AuthService`: xac thuc va quan ly nguoi dung
- `Zewvron.Repository`, `Zewvron.Services`, `Zewvron.Entities`, `Zewvron.DTO`: cac tang du lieu va nghiep vu dung chung

## Cong nghe su dung

- Frontend: React, Vite, Tailwind CSS, Axios
- Backend: ASP.NET Core, Entity Framework Core
- Khac: SignalR, Ocelot

## Lo trinh da service

Du an dang duoc chuan bi cho lo trinh tach mot so module backend sang nhieu ngon ngu:

- `PHP`: `Banner`, `Settings`, `Notifications admin`
- `Rust`: `Recommendations`, `Notifications worker`
- `.NET`: giu `Auth`, `Orders`, `Inventory`, `Products` va cac module loi

Tai lieu lien quan:

- `docs/architecture/multi-service-migration-plan.md`
- `docs/architecture/notification-outbox-design.md`

Khung service moi:

- `services/php-admin-service`
- `RustService/zewvron_rustService`

## Cach chay frontend

Tai thu muc `Zewvron.WebClient`:

```bash
npm install
npm run dev
```

Mac dinh frontend chay o cong `3000`.

## Cach build frontend

Tai thu muc `Zewvron.WebClient`:

```bash
npm run build
```

Ban build se duoc dua vao `Zewvron.ApiGateway/wwwroot`.

## Cac cong mac dinh

- Gateway: `http://localhost:5000`
- APIService: `http://localhost:5001`
- AuthService: `http://localhost:5002`
- WebClient dev: `http://localhost:3010`
1. WebClient (frontend) — cửa sổ mới, nhớ port khác 3000
cd BaseCore.WebClient
npm run dev -- --port 3011 --strictPort
→ mở http://localhost:3011

2. PHP admin service (port 5003) — cửa sổ mới, không cần JWT_SECRET (có JwtMiddleware riêng đọc từ .env)
cd services\php-admin-service\laravel
php artisan serve --port=5003

3. Rust service (port 7001) — cửa sổ mới
cd RustService\techstore_rustService
$env:TECHSTORE_RUST_BIND = "127.0.0.1:7001"
$env:TECHSTORE_RUST"Server=127.0.0.1,59704;Database=techStore1;Integrated Security=true;Encryte=true"
$env:TECHSTORE_RUST_CORS_ORIGINS = "http://localhost:3
cargo run 
## Ghi chu

- Anh upload duoc phuc vu qua duong dan `/uploads/...`
- Du an hien co ho tro doc anh tu cac thu muc `Image_Shop` va `Picture SP`
- Neu thay doi frontend ma chay qua gateway, hay build lai de cap nhat `wwwroot`
