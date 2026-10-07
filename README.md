# Zewvron

Zewvron is an e-commerce application with a React/Vite frontend and .NET, Laravel, and Rust backend services.

## Main projects

- `Zewvron.WebClient` — React/Vite storefront and admin UI.
- `Zewvron.ApiGateway` — Ocelot gateway and host for the built frontend.
- `Zewvron.APIService` — product, order, inventory, and SignalR APIs.
- `Zewvron.AuthService` — authentication and user APIs.
- `services/php-admin-service/laravel` — admin APIs for banners, settings, and notification templates.
- `RustService/zewvron_rustService` — read-only product comparison, recommendation, and search APIs.

All services use the existing SQL Server database `techstore1`. The project rename did not rename the database.

## Default local ports

| Service | Port |
| --- | ---: |
| Vite frontend | 3000 |
| API Gateway | 5000 |
| APIService | 5001 |
| AuthService | 5002 |
| PHP admin service | 5003 |
| Rust service | 7001 |

## Build the frontend

```powershell
cd Zewvron.WebClient
npm ci
npm run build
```

The build output goes to `Zewvron.ApiGateway/wwwroot`. To run Vite separately, start the gateway and APIService first, then run `npm run dev`; Vite proxies `/api` to port 5000 and `/zewvronChatHub` to APIService on port 5001.

`Zewvron.ApiGateway/wwwroot` is gitignored (build output only) — the `npm run build` step above is required at least once before the gateway can serve the SPA; without it there's nothing at `/`.

## Run backend services

From the repository root, start the .NET services in separate terminals:

```powershell
dotnet run --project Zewvron.ApiGateway
dotnet run --project Zewvron.APIService
dotnet run --project Zewvron.AuthService
```

Start PHP admin service:

```powershell
cd services/php-admin-service/laravel
php artisan serve --host=127.0.0.1 --port=5003
```

Start Rust (configure its local `.env` with `ZEWVRON_RUST_DATABASE_URL` and, for release builds, `ZEWVRON_RUST_CORS_ORIGINS`):

```powershell
cd RustService/zewvron_rustService
cargo run
```

Rust reads `techstore1` and does not run migrations or write to the database. Its endpoints are exposed through the gateway under `/api/rust/*`.

## Rename and token note

The ASP.NET DataProtection application names changed with the service rename. Previously issued protected tokens/cookies are no longer valid and users must sign in again. The SQL Server database remains `techstore1`.
