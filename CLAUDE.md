# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

TechStore — an e-commerce site for tech products. React/Vite frontend + a .NET microservice backend, with an active migration effort splitting some backend modules into PHP (Laravel) and Rust services behind a shared Ocelot API gateway.

## Architecture

```
BaseCore.WebClient (React/Vite, :3000 dev)
        |
        v
BaseCore.ApiGateway (Ocelot, :5000) -- serves the built SPA from wwwroot/ and routes /api/*
        |-- BaseCore.AuthService (.NET, :5002)      Auth, users, roles
        |-- BaseCore.APIService  (.NET, :5001)      Products, Orders, Inventory, Warranty,
        |                                            Repairs, Tickets, Coupons, Banners,
        |                                            Settings, Recommendations, SignalR chat hub
        |-- services/php-admin-service (Laravel, :5003)   Banners, Settings, Notifications admin
        \-- services/rust-backend-service (Rust, :5004, scaffold only — no rustc/cargo installed yet)
                                                           Recommendations, Notifications worker
```

Route ownership is being migrated module-by-module per `docs/architecture/multi-service-migration-plan.md`:
- Stays on .NET: Auth, Orders, Inventory, Products, Warranty, Repairs, Tickets.
- Moving to PHP: Banner, Settings, Notifications admin.
- Moving to Rust: Recommendations, Notifications worker (via a `NotificationOutbox` table the .NET side writes to and Rust polls — not yet wired up).
- When migrating a route: change the mapping in `BaseCore.ApiGateway/ocelot.json` first, keep the JSON response shape identical, and don't change the frontend call site unless the contract changes. Every route needs both an `{everything}` wildcard entry and an exact-path entry in `ocelot.json` (see existing banner/settings/notification entries for the pattern).
- Multiple services must never own writes to the same table without explicit ownership (see the Data Ownership table in the migration plan doc).

### .NET solution layout (no top-level .sln; each project has its own .csproj)

- `BaseCore.ApiGateway` — Ocelot gateway + serves the SPA build output from `wwwroot/`.
- `BaseCore.APIService` — main business API (port 5001), owns the SignalR hub (`/techstoreChatHub`).
- `BaseCore.AuthService` — auth/users/roles (port 5002).
- `BaseCore.AuditLog`, `BaseCore.LogService` — supporting services.
- `BaseCore.Entities` — EF Core entities.
- `BaseCore.Repository` — repository pattern over EF Core, under `EFCore/` (e.g. `ProductRepository.cs`, `OrderRepository.cs`, `NotificationOutboxRepository.cs`), keyed by `IRepository<T>` / `Repository<T>` base classes.
- `BaseCore.Services` — business/domain services consumed by controllers (`ProductService`, `OrderService`, `InventoryService`, etc.), each with an `I*Service` interface.
- `BaseCore.DTO` — request/response DTOs.
- `BaseCore.Common`, `BaseCore.Libs` — shared utilities.
- `BaseCore.UnitTest` — NUnit tests referencing `BaseCore.Common`, `BaseCore.Libs`, `BaseCore.Repository` (targets `netcoreapp2.2`; older than the rest of the stack).

Standard flow for a new API feature in the .NET side: Entity (`BaseCore.Entities`) → Repository (`BaseCore.Repository/EFCore`) → Service + interface (`BaseCore.Services`) → DTO (`BaseCore.DTO`) → Controller (`BaseCore.APIService/Controllers`) → wire into DI via `ServiceCollectionExtensions.cs` (`AddPersistence`, `AddDomainServices`) → add a route in `ocelot.json` if it needs to be reachable through the gateway.

Database migrations are **not** applied automatically — `BaseCore.APIService/Program.cs` only calls `db.Database.Migrate()` when `Database:AutoMigrateOnStartup` is explicitly set to `true` in config. Apply schema changes deliberately.

### Frontend (`BaseCore.WebClient`)

- React 18 + React Router 7 + Vite 5 + Tailwind CSS 4, Axios for HTTP, `@microsoft/signalr` for the chat/notifications hub.
- Admin pages live in `src/pages/admin/`; routes are registered in `src/App.jsx` and gated with `<ProtectedRoute allowedRoles={[...]}>`.
- `vite.config.js` proxies `/api` to the gateway and `/techstoreChatHub` directly to the APIService (port 5001) in dev, since SignalR needs a direct WS connection.
- Building (`npm run build`) outputs into `BaseCore.ApiGateway/wwwroot` — rebuild after frontend changes if testing through the gateway rather than the Vite dev server.

### PHP service (`services/php-admin-service/laravel`)

- Laravel app; API routes belong in `routes/api.php` (auto-prefixed with `/api`), not `routes/web.php`.
- Auth uses a custom `JwtMiddleware` (validates the same JWT issued by `BaseCore.AuthService`) applied via `Route::middleware([JwtMiddleware::class])->group(...)`.
- Connects to the same SQL Server database as the .NET services (`config/database.php`, `sqlsrv` driver). Dev env disables encryption/cert trust (`DB_ENCRYPT=no`, `DB_TRUST_SERVER_CERTIFICATE=yes`).
- See `services/php-admin-service/laravel/PRODUCTION.md` for production setup notes.

### Rust service (`services/rust-backend-service`)

Scaffold only (`Cargo.toml` + `src/main.rs`); no toolchain installed in this environment. Intended env vars: `APP_PORT=5004`, `DATABASE_URL`, `JWT_SECRET`/`JWT_ISSUER`/`JWT_AUDIENCE`, `NOTIFICATION_MODE=worker`.

## Commands

### Frontend (`BaseCore.WebClient/`)

```bash
npm install
npm run dev       # dev server on :3000, proxies /api -> gateway
npm run build     # outputs to ../BaseCore.ApiGateway/wwwroot
npm run preview
```

### .NET services (run from the repo root or the individual project directory)

```bash
dotnet run --project BaseCore.ApiGateway     # :5000
dotnet run --project BaseCore.APIService     # :5001
dotnet run --project BaseCore.AuthService    # :5002
dotnet build                                  # build a single project from within its directory
```

Unit tests (`BaseCore.UnitTest`, NUnit):

```bash
dotnet test BaseCore.UnitTest
dotnet test BaseCore.UnitTest --filter "FullyQualifiedName~ClassName.MethodName"   # single test
```

### PHP service (`services/php-admin-service/laravel/`)

```bash
composer install
php artisan serve --port=5003
php artisan test                              # or: vendor/bin/phpunit
php artisan test --filter=TestName            # single test
```

### Rust service (`services/rust-backend-service/`)

```bash
rustup default stable
cargo run
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
- Default ports: Gateway `5000`, APIService `5001`, AuthService `5002`, PHP admin service `5003`, Rust service `5004` (planned), WebClient dev `3000`.