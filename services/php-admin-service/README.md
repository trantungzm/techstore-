# tech-php-admin-service

Service nay la dich vu `PHP` du kien phu trach:

- `Banner`
- `Settings`
- `Notifications admin`

## Muc tieu

- Tach cac module CRUD/admin khoi `BaseCore.APIService`.
- Van su dung chung `SQL Server` trong giai doan dau.
- Xac thuc bang cung JWT voi he thong `.NET`.

## Module

- `Banner`: CRUD + toggle active
- `Settings`: store settings + pickup branches (read-only)
- `Notifications admin`: templates, campaigns, jobs

## Endpoint muc tieu

- `GET /api/banners/active`
- `GET /api/banners`
- `GET /api/banners/{id}`
- `POST /api/banners`
- `PUT /api/banners/{id}`
- `DELETE /api/banners/{id}`
- `PUT /api/banners/{id}/toggle`
- `GET /api/settings`
- `GET /api/settings/pickup-branches`
- `PUT /api/settings`
- `GET /api/admin/notification-templates`
- `POST /api/admin/notification-templates`
- `PUT /api/admin/notification-templates/{id}`
- `DELETE /api/admin/notification-templates/{id}`
- `GET /api/admin/notifications/campaigns`
- `POST /api/admin/notifications/campaigns`

## Cau truc de xuat

```text
services/php-admin-service
  app/
    Http/Controllers/
    Models/
    Services/
  routes/
  database/
  public/
```

## Bien moi truong

Xem file `.env.example`.

## Ghi chu

- Hien tai repo moi scaffold khung de bat dau trien khai.
- Co the nang cap len Laravel day du sau khi doi nganh quyet dinh xong cong cu/bo khung.
