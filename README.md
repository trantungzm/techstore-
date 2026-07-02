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
- `services/rust-backend-service`

## Cach chay frontend

Tai thu muc `BaseCore.WebClient`:

```bash
npm install
npm run dev
```

Mac dinh frontend chay o cong `3000`.

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
- WebClient dev: `http://localhost:3000`

## Ghi chu

- Anh upload duoc phuc vu qua duong dan `/uploads/...`
- Du an hien co ho tro doc anh tu cac thu muc `Image_Shop` va `Picture SP`
- Neu thay doi frontend ma chay qua gateway, hay build lai de cap nhat `wwwroot`
