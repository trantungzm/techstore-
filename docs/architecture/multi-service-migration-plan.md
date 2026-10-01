# Ke hoach chuyen doi da service

Tai lieu nay dong vai tro "phase 1" cho lo trinh tach mot phan backend hien tai sang `PHP` va `Rust` ma van giu nguyen frontend va gateway.

## Muc tieu

- Giữ `Auth`, `Orders`, `Inventory`, `Products`, `Warranty`, `Repairs`, `Tickets` o `.NET`.
- Chuyen `Banner`, `Settings`, `Notifications admin` sang `PHP`.
- Chuyen `Recommendations`, `Notifications worker` sang `Rust`.
- Giữ frontend goi qua `ApiGateway` de khong phai sua nhieu code giao dien.

## Kien truc muc tieu

```text
WebClient
   |
   v
BaseCore.ApiGateway
   |----> BaseCore.AuthService (.NET)
   |----> BaseCore.APIService (.NET)
   |----> tech-php-admin-service (PHP)
   \----> tech-rust-backend-service (Rust)
```

## Data Ownership

| Module | Service so huu | Bang chinh |
| --- | --- | --- |
| Banner | PHP | `Banners` |
| Settings | PHP | `StoreSettings` |
| Notifications admin | PHP (ghi) | `NotificationTemplates`, `NotificationCampaigns`, `NotificationJobs` |
| Recommendations | Rust (chi doc) | `ProductRecommendations` |
| Notifications worker | Rust | `NotificationOutbox`, `Notifications` |
| Notifications user-facing API | .NET giai doan dau | `Notifications` doc/mark-read/delete |

**Ghi chu ve `ProductRecommendations`:**

- Rust chi **doc** bang nay (2 endpoint GET cross-sell/auto-cross-sell), khong co endpoint ghi nao.
- Truoc day .NET `RecommendationsController` co 2 action PUT de admin cau hinh thu cong
  san pham cross-sell cho tung san pham, nhung khong co trang/component nao o frontend
  goi toi (0 call site, xac nhan luc cutover). Khi xoa controller nay (commit `b89ae58`),
  2 action PUT bi retired luon thay vi chuyen sang Rust — hien **khong co API nao ghi duoc**
  `ProductRecommendations`. Neu sau nay can tinh nang nay lai, phai thiet ke moi (vd. them
  write endpoint co auth vao Rust, hoac giu lai o mot service khac) — khong phai chuyen
  nguyen trang vi ban cu da chet tu truoc.

**Ghi chu ve `NotificationTemplates` / `NotificationCampaigns` / `NotificationJobs`:**

- PHP admin service la noi duy nhat duoc ghi (insert/update/delete) 3 bang nay —
  quan ly qua `NotificationController` (`templates`, `createTemplate`,
  `updateTemplate`, `deleteTemplate`, `campaigns`, `createCampaign`).
- `Rust worker` (giai doan sau, xem "Giai doan 5" ben duoi) chi **doc**
  `NotificationTemplates` (de lay noi dung) va `NotificationJobs` (de lay job
  `Pending` can xu ly), sau do cap nhat `Status`/`ProcessedAt`/`LastError` cua
  tung job da xu ly. Worker khong duoc tao/sua/xoa `NotificationTemplates`
  hay `NotificationCampaigns` — do la nghiep vu cua PHP admin service.

## Mapping endpoint

### Chuyen sang PHP

| Endpoint hien tai | Trang thai muc tieu | Ghi chu |
| --- | --- | --- |
| `GET /api/banners/active` | PHP | Public storefront |
| `GET /api/banners` | PHP | Admin only |
| `GET /api/banners/{id}` | PHP | Admin only |
| `POST /api/banners` | PHP | Admin only |
| `PUT /api/banners/{id}` | PHP | Admin only |
| `DELETE /api/banners/{id}` | PHP | Admin only |
| `PUT /api/banners/{id}/toggle` | PHP | Admin only |
| `GET /api/settings` | PHP | Public read |
| `GET /api/settings/pickup-branches` | PHP | Public read, SQL read-only |
| `PUT /api/settings` | PHP | Admin only |
| `GET /api/admin/notification-templates` | PHP moi | Module moi |
| `POST /api/admin/notification-templates` | PHP moi | Module moi |
| `PUT /api/admin/notification-templates/{id}` | PHP moi | Module moi |
| `DELETE /api/admin/notification-templates/{id}` | PHP moi | Module moi |
| `POST /api/admin/notifications/campaigns` | PHP moi | Tao campaign/job |
| `GET /api/admin/notifications/campaigns` | PHP moi | Xem lich su |

### Chuyen sang Rust

| Endpoint hien tai | Trang thai | Ghi chu |
| --- | --- | --- |
| `GET /api/recommendations/cross-sell` | **Da xong** | Rust, route qua gateway tu commit `a3b62f1` |
| `GET /api/recommendations/auto-cross-sell` | **Da xong** | Rust, route qua gateway tu commit `a3b62f1` — cutover nay dong thoi la bugfix: ban .NET cu crash 100% request (EF Core khong dich duoc LINQ `x.Stock > 0`), xem commit `a3b62f1` |
| `PUT /api/recommendations/cross-sell/{productId}` | **Retired, khong migrate** | Khong co consumer nao o frontend (0 call site); Rust khong co write endpoint (read-only, khong co auth layer) nen khong the nhan PUT. Da xoa cung luc voi `RecommendationsController.cs` (commit `b89ae58`) thay vi chuyen sang Rust nhu ke hoach ban dau ghi o day |
| `PUT /api/recommendations/cross-sell?productId=` | **Retired, khong migrate** | Nhu tren |
| `GET /api/rust/product-compare` | **Da xong** | Rust, khong co .NET tuong duong tu truoc |
| `GET /api/rust/search-suggestions` | **Da xong** | Rust, khong co .NET tuong duong tu truoc |
| `POST /internal/notifications/process` | Chua lam | Rust noi bo, tuy chon cho worker trigger tay |

### Giu tam thoi o .NET

| Endpoint | Ly do |
| --- | --- |
| `GET /api/notifications/my` | Frontend dang dung, giu on dinh |
| `GET /api/notifications/my/unread-count` | Frontend dang dung |
| `PUT /api/notifications/{id}/read` | Frontend dang dung |
| `PUT /api/notifications/my/read-all` | Frontend dang dung |
| `DELETE /api/notifications/{id}` | Frontend dang dung |

## Lo trinh trien khai

### Giai doan 1

- Them tai lieu mapping, ownership va scaffold service.
- Them `NotificationOutbox` vao `.NET` de chuan bi cho worker.

### Giai doan 2

- Tao `tech-php-admin-service`.
- Chuyen `Banner` va `Settings`.
- Route qua gateway, frontend khong doi endpoint.

### Giai doan 3 — Da hoan tat

- Tao `RustService/techstore_rustService` (PR #27).
- Chuyen `Recommendations` (cross-sell, auto-cross-sell) — route qua gateway, `RecommendationsController.cs`
  cu ben .NET da xoa (commit `b89ae58`). Cutover nay dong thoi fix mot bug production co san:
  ban .NET crash 100% request auto-cross-sell do EF Core khong dich duoc LINQ (commit `a3b62f1`).
- Them `Product Compare` va `Search Suggestions` — 2 tinh nang moi hoan toan, khong co ban .NET
  tien nhiem, route qua gateway tu dau.
- Con lai ngoai pham vi giai doan nay: 2 action PUT cau hinh cross-sell thu cong bi retired
  (xem ghi chu Data Ownership o tren), chua co ke hoach lam lai.

### Giai doan 4

- Bo sung `Notifications admin` trong PHP.
- Tao template, campaign, job, history.

### Giai doan 5

- Chuyen `.NET` tu ghi `Notifications` truc tiep sang ghi `NotificationOutbox`.
- Cho `Rust worker` doc outbox va ghi `Notifications`.

## Nguyen tac chuyen doi

- Khong de nhieu service cung sua mot bang ma khong co ownership ro rang.
- Giu response JSON on dinh de frontend khong phai doi hang loat.
- Route thay doi tai gateway truoc, khong thay doi frontend truoc.
- Notification worker phai idempotent va co retry.
- Moi service moi phai co `README`, `health check`, env config, va convention logging.
