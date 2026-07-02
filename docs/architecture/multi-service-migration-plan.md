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
| Notifications admin | PHP | `NotificationTemplates`, `NotificationCampaigns`, `NotificationJobs` |
| Recommendations | Rust | `ProductRecommendations` |
| Notifications worker | Rust | `NotificationOutbox`, `Notifications` |
| Notifications user-facing API | .NET giai doan dau | `Notifications` doc/mark-read/delete |

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

| Endpoint hien tai | Trang thai muc tieu | Ghi chu |
| --- | --- | --- |
| `GET /api/recommendations/cross-sell` | Rust | Doc recommendations |
| `PUT /api/recommendations/cross-sell/{productId}` | Rust | Admin/auth |
| `PUT /api/recommendations/cross-sell?productId=` | Rust | Admin/auth |
| `GET /api/recommendations/auto-cross-sell` | Rust | Tu dong tinh de xuat |
| `POST /internal/notifications/process` | Rust noi bo | Tuy chon cho worker trigger tay |

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

### Giai doan 3

- Tao `tech-rust-backend-service`.
- Chuyen `Recommendations`.
- Route qua gateway.

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
