# tech-rust-backend-service

Service nay la dich vu `Rust` du kien phu trach:

- `Recommendations`
- `Notifications worker`

## Muc tieu

- Tach module recommendation khoi `.NET`.
- Chuyen notification sang worker doc lap theo mo hinh outbox.

## Module

- `GET /api/recommendations/cross-sell`
- `PUT /api/recommendations/cross-sell/{productId}`
- `PUT /api/recommendations/cross-sell?productId=`
- `GET /api/recommendations/auto-cross-sell`
- Worker noi bo xu ly `NotificationOutbox`

## Toolchain

May hien tai chua co `rustc` va `cargo`, vi vay repo moi scaffold san `Cargo.toml` va `src/main.rs`.

Khi can chay:

```bash
rustup default stable
cargo run
```

## Bien moi truong de xuat

- `APP_PORT=5004`
- `DATABASE_URL=sqlserver://sa:password@localhost:1433/techstore`
- `JWT_SECRET=...`
- `JWT_ISSUER=...`
- `JWT_AUDIENCE=...`
- `NOTIFICATION_MODE=worker`
