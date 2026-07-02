# Notification Outbox Design

Tai lieu nay mo ta cach chuyen module notification tu `.NET` monolith service sang mo hinh:

- `.NET`: phat sinh event nghiep vu
- `PHP`: quan tri template/campaign
- `Rust`: worker xu ly outbox va ghi notification

## Van de hien tai

Hien tai nhieu service nghiep vu trong `.NET` goi truc tiep `INotificationService.CreateAsync(...)`.

Dieu nay khien:

- Logic tao notification bi gan chat vao service nghiep vu.
- Kho tach notification worker sang service doc lap.
- Kho retry/an toan neu mot buoc ghi notification bi loi.

## Muc tieu

- `.NET` chi ghi ra mot event outbox.
- `Rust worker` doc event va render notification.
- `PHP admin service` quan ly template va campaign.
- API doc/read/delete notification cua user van co the giu o `.NET` trong giai doan dau.

## Bang moi de xuat

### `NotificationOutbox`

| Cot | Kieu | Ghi chu |
| --- | --- | --- |
| `Id` | `bigint identity` | Khoa chinh |
| `EventId` | `uniqueidentifier` | Idempotency key |
| `EventType` | `nvarchar(80)` | Vi du `OrderCreated`, `TicketUpdated` |
| `AggregateType` | `nvarchar(80)` | `Order`, `Ticket`, `WarrantyClaim` |
| `AggregateId` | `nvarchar(80)` | Co the dung string de linh hoat |
| `UserId` | `uniqueidentifier null` | Nguoi nhan |
| `Title` | `nvarchar(200)` | Tieu de fallback |
| `Message` | `nvarchar(1000)` | Noi dung fallback |
| `PayloadJson` | `nvarchar(max)` | Du lieu bo sung |
| `Status` | `nvarchar(30)` | `Pending`, `Processing`, `Processed`, `Failed` |
| `AvailableAt` | `datetime2` | Ho tro retry delay |
| `ProcessedAt` | `datetime2 null` | Thoi diem xu ly xong |
| `LastError` | `nvarchar(1000) null` | Loi gan nhat |
| `RetryCount` | `int` | So lan retry |
| `CreatedAt` | `datetime2` | Mac dinh `GETUTCDATE()` |

### `NotificationTemplates`

- Duoc service PHP quan tri.
- Gom `Code`, `Name`, `Channel`, `TitleTemplate`, `BodyTemplate`, `IsActive`.

### `NotificationCampaigns`

- Dung cho admin gui thong bao hang loat hoac lap lich.

### `NotificationJobs`

- Luu tung job tao tu campaign/template de worker Rust xu ly.

## Luong xu ly muc tieu

```text
.NET business service
   -> ghi NotificationOutbox
Rust worker
   -> doc Pending events
   -> tai template/cau hinh
   -> render noi dung
   -> ghi Notifications
   -> cap nhat outbox = Processed
Frontend/.NET API
   -> doc Notifications cua user
```

## Cach retrofit vao code hien tai

### Buoc 1

- Them entity `NotificationOutbox`.
- Them `DbSet<NotificationOutbox>` vao `AppDbContext`.

### Buoc 2

- Tao `INotificationOutboxRepository`.
- Tao `NotificationOutboxRepositoryEF`.

### Buoc 3

- Doi `NotificationService.CreateAsync(...)` thanh ghi outbox thay vi ghi truc tiep bang `Notifications`.

### Buoc 4

- Trong giai doan chuyen tiep, co the giu co che ghi truc tiep bang feature flag:
  - `Notifications:Mode = DirectWrite | Outbox`

## Event types de xuat

- `OrderCreated`
- `OrderReadyForPickup`
- `OrderCancelled`
- `TicketCreated`
- `TicketUpdated`
- `TicketAssigned`
- `WarrantyClaimCreated`
- `WarrantyClaimStatusChanged`
- `WarrantyActivated`
- `RepairIntakeCreated`
- `RepairStatusChanged`

## Quy tac worker Rust

- Chi xu ly record `Pending`.
- Dung `EventId` de tranh tao trung notification.
- Tang `RetryCount` va dat lai `AvailableAt` khi loi.
- Danh dau `Failed` khi vuot nguong retry.
- Co endpoint `GET /health`.

## Giai doan chuyen tiep khuyen nghi

1. Them outbox va van de che do `DirectWrite`.
2. Chuyen 1-2 luong notification dau tien sang `Outbox`.
3. Chay worker Rust.
4. Khi worker on dinh, chuyen toan bo event notification sang `Outbox`.
