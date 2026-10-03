# TechStore

Du an TechStore gom frontend React/Vite va cac backend service xay dung tren .NET.

## Cau truc chinh

- `BaseCore.WebClient`: giao dien nguoi dung va trang quan tri
- `BaseCore.ApiGateway`: gateway phuc vu frontend build va route API
- `BaseCore.APIService`: service chinh cho san pham, don hang, banner, upload anh
- `BaseCore.AuthService`: xac thuc va quan ly nguoi dung
- `BaseCore.Repository`, `BaseCore.Services`, `BaseCore.Entities`, `BaseCore.DTO`: cac tang du lieu va nghiep vu dung chung

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
- `RustService/techstore_rustService`

## Cach chay toan bo du an (dev)

Yeu cau truoc khi chay:

- SQL Server (vi du SQLEXPRESS) dang chay, database `techStore1` da co san.
- Connection string trong `appsettings.Development.json` cua `BaseCore.APIService` va `BaseCore.AuthService` phai tro dung ten may/instance SQL Server cua ban.
- Bien moi truong `JWT_SECRET`: mot chuoi bi mat tu dat ra (toi thieu 32 ky tu, khong can dang cu the, chi can du dai va kho doan), dung **giong het nhau** cho ca AuthService va APIService vi mot ben ky token, mot ben xac thuc token. Khong duoc commit gia tri nay vao git.

Moi service chay trong mot cua so terminal rieng. Vi du voi PowerShell:

**1. AuthService** (cong `5002`)

```powershell
cd BaseCore.AuthService
$env:JWT_SECRET = "chuoi-bi-mat-cua-ban-it-nhat-32-ky-tu"
dotnet run
```

**2. APIService** (cong `5001`) — set lai JWT_SECRET giong het buoc 1

```powershell
cd BaseCore.APIService
$env:JWT_SECRET = "chuoi-bi-mat-cua-ban-it-nhat-32-ky-tu"
dotnet run
```

**3. ApiGateway** (cong `5000`) — khong can JWT_SECRET

```powershell
cd BaseCore.ApiGateway
dotnet run
```

**4. WebClient** (frontend)

```powershell
cd BaseCore.WebClient
npm install
npm run dev
```

Mac dinh Vite chay o cong `3000`. Neu cong `3000` da bi chiem boi ung dung khac tren may ban, chay voi cong khac:

```powershell
npm run dev -- --port 3011 --strictPort
```

**5. PHP admin service** (cong `5003`, tuy chon — phuc vu Banner/Settings/Notifications admin)

```powershell
cd services\php-admin-service\laravel
composer install
php artisan serve --port=5003
```

**6. Rust service** (cong `7001`, tuy chon — phuc vu Product Compare, Recommendations, Search Suggestions)

```powershell
cd RustService\techstore_rustService
$env:TECHSTORE_RUST_BIND = "127.0.0.1:7001"
$env:TECHSTORE_RUST_DATABASE_URL = "Server=127.0.0.1,<PORT>;Database=techStore1;Integrated Security=true;Encrypt=false;TrustServerCertificate=true"
$env:TECHSTORE_RUST_CORS_ORIGINS = "http://localhost:3000,http://localhost:5000"
cargo run
```

`tiberius` (driver SQL Server cua Rust) khong tu resolve named instance (`Server=TEN-MAY\SQLEXPRESS`) nhu `Microsoft.Data.SqlClient` — phai dung IP + port TCP that cua instance. Lay port dong bang lenh PowerShell:

```powershell
Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server\MSSQL*.SQLEXPRESS\MSSQLServer\SuperSocketNetLib\Tcp\IPAll' | Select-Object TcpDynamicPorts
```

roi thay `<PORT>` bang gia tri tra ve.

## Cach build frontend

Tai thu muc `BaseCore.WebClient`:

```bash
npm run build
```

Ban build se duoc dua vao `BaseCore.ApiGateway/wwwroot`.

## Cac cong mac dinh

- Gateway: `http://localhost:5000`
- APIService: `http://localhost:5001`
- AuthService: `http://localhost:5002`
- PHP admin service: `http://localhost:5003`
- Rust service: `http://localhost:7001`
- WebClient dev: `http://localhost:3000`

## Ghi chu

- Anh upload duoc phuc vu qua duong dan `/uploads/...`
- Du an hien co ho tro doc anh tu cac thu muc `Image_Shop` va `Picture SP`
- Neu thay doi frontend ma chay qua gateway, hay build lai de cap nhat `wwwroot`
- Khong commit gia tri `JWT_SECRET` hay connection string that vao git
