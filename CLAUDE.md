# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Zewvron — an e-commerce site for tech products. React/Vite frontend + a .NET microservice backend, with an active migration effort splitting some backend modules into PHP (Laravel) and Rust services behind a shared Ocelot API gateway.

## Architecture

```
Zewvron.WebClient (React/Vite, :3000 dev)
        |
        v
Zewvron.ApiGateway (Ocelot, :5000) -- serves the built SPA from wwwroot/ and routes /api/*
        |-- Zewvron.AuthService (.NET, :5002)      Auth, users, roles
        |-- Zewvron.APIService  (.NET, :5001)      Products, Orders, Inventory, Warranty,
        |                                            Repairs, Tickets, Coupons,
        |                                            SignalR chat hub
        |-- services/php-admin-service (Laravel, :5003)   Banners, Settings, Notifications admin
        \-- RustService/zewvron_rustService (Rust, :7001, routed through the gateway)
                                                           Product Compare, Recommendations,
                                                           Search Suggestions (read-only, no auth layer)
```

Route ownership is being migrated module-by-module per `docs/architecture/multi-service-migration-plan.md`:
- Stays on .NET: Auth, Orders, Inventory, Products, Warranty, Repairs, Tickets.
- Moving to PHP: Banner, Settings, Notifications admin.
- Moved to Rust (done): Product Compare, Recommendations, Search Suggestions — all read-only, mirroring the equivalent (previously .NET) endpoints. No Notification worker exists yet despite the `NotificationOutbox` table already being written by the .NET side — nothing currently polls it, from Rust or otherwise.
  - **Product Compare** (`POST /api/rust/product-compare`) and **Search Suggestions** (`GET /api/rust/search-suggestions`) are wired into `ocelot.json` and reachable through the gateway — new functionality, no .NET equivalent ever existed, frontend doesn't call either yet.
  - **Recommendations** (`GET /api/recommendations/cross-sell`, `/auto-cross-sell`) fully cut over to Rust — `ocelot.json` routes both paths to `127.0.0.1:7001` (same upstream path, no frontend change needed), and `Zewvron.APIService/Controllers/RecommendationsController.cs` (the old .NET owner) has been deleted. This cutover doubled as a bugfix: the .NET `auto-cross-sell` fallback crashed on every request (EF Core couldn't translate the computed `Product.Stock` property in that LINQ query — see commit `a3b62f1`), so it had been silently broken before this migration touched it. The admin write endpoints that used to live on this controller (`PUT /api/recommendations/cross-sell[/{id}]`, for manually configuring which products cross-sell) were retired, not migrated — they had zero frontend call sites and Rust's recommendations routes are read-only by design (no auth layer). Nobody can currently write to `ProductRecommendations` through an API; see the Data Ownership note in the migration plan doc.
- When migrating a route: change the mapping in `Zewvron.ApiGateway/ocelot.json` first, keep the JSON response shape identical, and don't change the frontend call site unless the contract changes. Every route needs both an `{everything}` wildcard entry and an exact-path entry in `ocelot.json` (see existing banner/settings/notification entries for the pattern).
- Multiple services must never own writes to the same table without explicit ownership (see the Data Ownership table in the migration plan doc).

### .NET solution layout (no top-level .sln; each project has its own .csproj)

- `Zewvron.ApiGateway` — Ocelot gateway + serves the SPA build output from `wwwroot/`.
- `Zewvron.APIService` — main business API (port 5001), owns the SignalR hub (`/zewvronChatHub`).
- `Zewvron.AuthService` — auth/users/roles (port 5002).
- `Zewvron.AuditLog`, `Zewvron.LogService` — supporting services.
- `Zewvron.Entities` — EF Core entities.
- `Zewvron.Repository` — repository pattern over EF Core, under `EFCore/` (e.g. `ProductRepository.cs`, `OrderRepository.cs`, `NotificationOutboxRepository.cs`), keyed by `IRepository<T>` / `Repository<T>` base classes.
- `Zewvron.Services` — business/domain services consumed by controllers (`ProductService`, `OrderService`, `InventoryService`, etc.), each with an `I*Service` interface.
- `Zewvron.DTO` — request/response DTOs.
- `Zewvron.Common`, `Zewvron.Libs` — shared utilities.
- `Zewvron.UnitTest` — NUnit tests referencing `Zewvron.Common`, `Zewvron.Libs`, `Zewvron.Repository` (targets `netcoreapp2.2`; older than the rest of the stack).

Standard flow for a new API feature in the .NET side: Entity (`Zewvron.Entities`) → Repository (`Zewvron.Repository/EFCore`) → Service + interface (`Zewvron.Services`) → DTO (`Zewvron.DTO`) → Controller (`Zewvron.APIService/Controllers`) → wire into DI via `ServiceCollectionExtensions.cs` (`AddPersistence`, `AddDomainServices`) → add a route in `ocelot.json` if it needs to be reachable through the gateway.

Database migrations are **not** applied automatically — `Zewvron.APIService/Program.cs` only calls `db.Database.Migrate()` when `Database:AutoMigrateOnStartup` is explicitly set to `true` in config. Apply schema changes deliberately.

### Frontend (`Zewvron.WebClient`)

- React 18 + React Router 7 + Vite 5 + Tailwind CSS 4, Axios for HTTP, `@microsoft/signalr` for the chat/notifications hub.
- Admin pages live in `src/pages/admin/`; routes are registered in `src/App.jsx` and gated with `<ProtectedRoute allowedRoles={[...]}>`.
- `vite.config.js` proxies `/api` to the gateway and `/zewvronChatHub` directly to the APIService (port 5001) in dev, since SignalR needs a direct WS connection.
- Building (`npm run build`) outputs into `Zewvron.ApiGateway/wwwroot` — rebuild after frontend changes if testing through the gateway rather than the Vite dev server.

### PHP service (`services/php-admin-service/laravel`)

- Laravel app; API routes belong in `routes/api.php` (auto-prefixed with `/api`), not `routes/web.php`.
- Auth uses a custom `JwtMiddleware` (validates the same JWT issued by `Zewvron.AuthService`) applied via `Route::middleware([JwtMiddleware::class])->group(...)`.
- Connects to the same SQL Server database as the .NET services (`config/database.php`, `sqlsrv` driver). Dev env disables encryption/cert trust (`DB_ENCRYPT=no`, `DB_TRUST_SERVER_CERTIFICATE=yes`).
- `DB_HOST` must be an IP/hostname (e.g. `127.0.0.1`) — never a named-pipe string (`np:\\.\pipe\...`) combined with `DB_PORT`. The ODBC driver can't parse the two together and fails with "Connection string is not valid [87]" only after ~15s (SNI tries shared-memory/TCP/named-pipe in turn before giving up), not immediately. SQL Server Express listens on a dynamic TCP port by default — look it up via the registry (`HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server\MSSQL*.<INSTANCE>\MSSQLServer\SuperSocketNetLib\Tcp\IPAll`, `TcpDynamicPorts`) and set `DB_PORT` accordingly, or configure a static TCP port in SQL Server Configuration Manager to avoid re-checking it after every SQL Server restart.
- See `services/php-admin-service/laravel/PRODUCTION.md` for production setup notes.
- On Windows, `php artisan serve` binds IPv4 only (`127.0.0.1`), not `[::1]`. Ocelot routes to this service must use `"Host": "127.0.0.1"` in `ocelot.json`, not `"localhost"` — `localhost` resolves to both `::1` and `127.0.0.1`, and the gateway's HTTP client tries the IPv6 address first, adding ~2s of fallback latency to every request before it gives up and retries on IPv4.

### Rust service (`RustService/zewvron_rustService`)

The one and only Rust service (merged via PR #27; the earlier `services/rust-backend-service` scaffold was deleted once this became the real implementation — don't recreate it). Axum + `tiberius` (SQL Server driver), reads the same `zewvron` database directly (no writes, no migrations). Currently exposes 5 read-only routes under `/api/rust` (Product Compare, Recommendations, Search Suggestions), so no auth layer exists in this service at all. **All three are wired into `ocelot.json`** and reachable through the gateway — Product Compare and Search Suggestions are new functionality with no .NET equivalent; Recommendations fully replaced the old .NET `RecommendationsController` (see the route ownership note above), which has been deleted.

`tiberius` doesn't resolve named SQL Server instances (`Server=HOST\INSTANCE`) the way `Microsoft.Data.SqlClient` does — it needs a literal host/port (e.g. `Server=127.0.0.1,PORT`) instead of relying on SQL Browser (UDP 1434) to resolve the named instance. If `ZEWVRON_RUST_DATABASE_URL` uses a named instance and the service logs "target machine actively refused it" on startup, resolve the instance's dynamic TCP port (`Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server\MSSQL*.<INSTANCE>\MSSQLServer\SuperSocketNetLib\Tcp\IPAll'`) and use that instead.

- Binds `127.0.0.1:7001` by default (loopback-only — deliberate, since there's no auth layer; don't default to `0.0.0.0`).
- Env vars (no hardcoded fallback except bind address — all of these must be set explicitly or the service refuses to start): `ZEWVRON_RUST_BIND` (default `127.0.0.1:7001`), `ZEWVRON_RUST_DATABASE_URL` (ADO-style SQL Server connection string, **required**, no default), `ZEWVRON_RUST_DB_POOL_SIZE` (default `4`), `ZEWVRON_RUST_CORS_ORIGINS` (comma-separated whitelist; debug builds fall back to `http://localhost:3000,http://localhost:5000`, release builds **require** it set — refuses to start with CORS wide open otherwise).
- Does **not** use JWT/`JWT_SECRET` at all currently — if a future route needs auth (anything beyond read-only public data), add JWT middleware (same HS256 secret as .NET/PHP) before exposing it; don't ship a protected route with no auth layer to fall back on.
- See `RustService/zewvron_rustService/README.md` for the full env var / endpoint reference.

## Commands

### Frontend (`Zewvron.WebClient/`)

```bash
npm install
npm run dev       # dev server on :3000, proxies /api -> gateway
npm run build     # outputs to ../Zewvron.ApiGateway/wwwroot
npm run preview
```

### .NET services (run from the repo root or the individual project directory)

```bash
dotnet run --project Zewvron.ApiGateway     # :5000
dotnet run --project Zewvron.APIService     # :5001
dotnet run --project Zewvron.AuthService    # :5002
dotnet build                                  # build a single project from within its directory
```

Unit tests (`Zewvron.UnitTest`, NUnit):

```bash
dotnet test Zewvron.UnitTest
dotnet test Zewvron.UnitTest --filter "FullyQualifiedName~ClassName.MethodName"   # single test
```

### PHP service (`services/php-admin-service/laravel/`)

```bash
composer install
php artisan serve --port=5003
php artisan test                              # or: vendor/bin/phpunit
php artisan test --filter=TestName            # single test
```

### Rust service (`RustService/zewvron_rustService/`)

```bash
cd RustService/zewvron_rustService
$env:ZEWVRON_RUST_DATABASE_URL = "Server=...;Database=zewvron;..."   # required, no default
$env:ZEWVRON_RUST_CORS_ORIGINS = "http://localhost:3000,http://localhost:5000"  # required in release builds
cargo run          # :7001, loopback only by default
cargo build --release
```

## Git workflow

### Branch model

- `main` is the single source of truth. The `develop` branch was retired (2026-09) — it had gone stale for ~87 days while work continued directly on `main`; rather than resurrect a fork nobody was syncing, it was deleted.
- Feature/fix work happens on `feature/<name>` or `fix/<name>` branches created off `main`, merged back into `main` via Pull Request.
- Before committing, check the current branch (`git branch --show-current`). If on `main` directly and the work is a discrete feature/fix, create the proper branch first instead of committing in place.
- This is a solo-maintained repo today; the branch discipline is kept anyway so history stays reviewable and the project is ready to onboard more contributors without changing process later.

### When to commit

- Commit only after a task/feature is **fully complete** — code builds, runs, and has been self-reviewed (re-read the diff, run tests/build where possible). Do not commit mid-refactor or unverified code.
- If one request spans multiple services (e.g. gateway route + PHP API + frontend page), it's fine to split into several commits **as long as each commit alone doesn't break the repo's runnable state**. Prefer several small, reviewable/revertable commits over one giant one, unless the pieces genuinely can't work independently.
- Never commit: `.env`/secrets/connection strings, build output (`bin/`, `obj/`, `node_modules/`, `vendor/`, `target/`), or leftover debug code (`console.log`, `dd()`, commented-out blocks).

### Commit message format — Conventional Commits, Vietnamese description

```
<type>: <mô tả ngắn gọn bằng tiếng Việt>
```

Types: `feat`, `fix`, `refactor`, `chore`, `docs`, `style`, `test`, `perf`, `build`, `revert`.

Optional scope by service area — `gateway`, `auth`, `api`, `php-admin`, `rust-backend`, `webclient`, `docs`:

```
feat(php-admin): thêm API lọc sản phẩm theo danh mục
fix(api): sửa lỗi tính tồn kho khi xuất hóa đơn
feat(gateway): thêm route Ocelot cho notification campaigns admin
```

- First line ≤ 72 chars, present tense ("thêm", "sửa" — not "đã thêm").
- No vague messages (`update`, `fix bug`, `wip`).
- Use a bullet-point body for commits that bundle several related changes.

### Push policy

- **Never push automatically.** Commits stay local until the user explicitly says to push (or has given a standing instruction otherwise for the session).
- Never `push --force`, and never push directly to `main`/`dev` bypassing the PR flow.
- After committing, report what was committed and wait for confirmation before pushing.

### Pre-commit checklist

1. `git status` / `git diff` — review every changed file, no stray build artifacts or unrelated files.
2. No secrets/credentials leaked in the diff.
3. `.gitignore` correctly covers each stack's build/cache dirs (.NET `bin/`/`obj/`, Node `node_modules/`, PHP `vendor/`, Rust `target/`).
4. Message follows the Conventional Commits format above.

## Notes

- Uploaded images are served from `/uploads/...`; the project also reads images from the `Image_Shop` and `Picture SP` directories at the repo root.
- Default ports: Gateway `5000`, APIService `5001`, AuthService `5002`, PHP admin service `5003`, Rust service (`RustService/zewvron_rustService`) `7001` (gateway-routed — see Rust service section), WebClient dev `3000`.