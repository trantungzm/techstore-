using System;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.EntityFrameworkCore;
using NUnit.Framework;
using Zewvron.DTO.Store;
using Zewvron.Entities;
using Zewvron.Repository;
using Zewvron.Repository.EFCore;
using Zewvron.Services;

namespace Zewvron.UnitTest
{
    // Hoi quy cho bug: dat hang khong giam kho (Product.Stock la [NotMapped], EF khong dich duoc
    // trong Where/ExecuteUpdate; va mot "resave" du thua tren entity cu lam ghi de atomic update).
    // Can mot SQL Server thuc de chay (EF InMemory khong ho tro ExecuteUpdateAsync).
    // Doc "Cach chay" trong README_INSTALL.md / README.md truoc khi chay nhom test nay.
    [TestFixture]
    public class OrderStockRegressionTests
    {
        private const string EnvVarName = "ZEWVRON_TEST_DB_CONNECTION";
        private static readonly string ConnectionString =
            Environment.GetEnvironmentVariable(EnvVarName)
            ?? "Server=localhost\\SQLEXPRESS;Database=zewvron_unittest_order_stock;Trusted_Connection=True;MultipleActiveResultSets=True;Encrypt=False;TrustServerCertificate=True";

        private int _categoryId;
        private int _productWithStock20Id;
        private int _productWithStock30Id;

        private static AppDbContext MakeContext()
        {
            var options = new DbContextOptionsBuilder<AppDbContext>().UseSqlServer(ConnectionString).Options;
            return new AppDbContext(options);
        }

        private static OrderService MakeOrderService(AppDbContext ctx) => new OrderService(
            new OrderRepositoryEF(ctx),
            new OrderDetailRepositoryEF(ctx),
            new OrderTimelineRepositoryEF(ctx),
            new OrderCancellationRepositoryEF(ctx),
            new ProductRepositoryEF(ctx),
            new ProductVariantRepositoryEF(ctx),
            new WarehouseRepositoryEF(ctx),
            new StockItemRepositoryEF(ctx),
            new OrderDetailStockItemRepositoryEF(ctx));

        [OneTimeSetUp]
        public void OneTimeSetUp()
        {
            var builder = new Microsoft.Data.SqlClient.SqlConnectionStringBuilder(ConnectionString);
            if (builder.InitialCatalog.IndexOf("test", StringComparison.OrdinalIgnoreCase) < 0)
            {
                Assert.Ignore($"Bo qua: database '{builder.InitialCatalog}' khong chua chu 'test' trong ten - " +
                    $"khong chay test tren DB co the la DB thuc. Dat {EnvVarName} toi mot DB test rieng.");
            }

            try
            {
                using var ctx = MakeContext();
                ctx.Database.EnsureDeleted();
                ctx.Database.EnsureCreated();
            }
            catch (Exception ex)
            {
                Assert.Ignore($"Bo qua nhom test hoi quy ton kho: khong ket noi duoc SQL Server ({ex.GetType().Name}: {ex.Message}). " +
                    $"Dat bien moi truong {EnvVarName} toi mot connection string SQL Server hop le (ten DB phai chua 'test') de chay nhom test nay.");
            }
        }

        [OneTimeTearDown]
        public void OneTimeTearDown()
        {
            try
            {
                using var ctx = MakeContext();
                ctx.Database.EnsureDeleted();
            }
            catch
            {
                // Khong co gi de don (OneTimeSetUp da Assert.Ignore truoc khi tao DB).
            }
        }

        [SetUp]
        public async Task SetUp()
        {
            // Moi test chay tren mot cap san pham moi (stock 20 / 30), tranh test truoc anh huong test sau.
            using var ctx = MakeContext();
            var category = new Category { Name = $"Test-{Guid.NewGuid():N}" };
            ctx.Categories.Add(category);
            await ctx.SaveChangesAsync();
            _categoryId = category.Id;

            var p1 = new Product { Name = "Regression Product A", CategoryId = _categoryId, TotalStock = 20, IsActive = true, BasePrice = 1000000 };
            var p2 = new Product { Name = "Regression Product B", CategoryId = _categoryId, TotalStock = 30, IsActive = true, BasePrice = 500000 };
            ctx.Products.AddRange(p1, p2);
            await ctx.SaveChangesAsync();
            _productWithStock20Id = p1.Id;
            _productWithStock30Id = p2.Id;
        }

        private async Task<int?> GetStock(int productId)
        {
            using var ctx = MakeContext();
            return await ctx.Products.Where(p => p.Id == productId).Select(p => p.TotalStock).FirstOrDefaultAsync();
        }

        private static CreateOrderDto BuildOrderDto(params OrderItemDto[] items) => new CreateOrderDto
        {
            CustomerName = "Regression Test",
            CustomerPhone = "0900000000",
            CustomerEmail = "regression@test.local",
            ShippingMethod = "Delivery",
            Province = "HCM",
            District = "Q1",
            Ward = "P1",
            AddressDetail = "1 Test St",
            PaymentMethod = "COD",
            Items = items.ToList()
        };

        [Test]
        public async Task CreateOrder_DecrementsProductStock()
        {
            using var ctx = MakeContext();
            var svc = MakeOrderService(ctx);

            var order = await svc.CreateOrderAsync(null, BuildOrderDto(
                new OrderItemDto { ProductId = _productWithStock20Id, Quantity = 2 }));

            Assert.That(order.Status, Is.EqualTo("Pending"));
            Assert.That(await GetStock(_productWithStock20Id), Is.EqualTo(18));
        }

        [Test]
        public async Task CancelOrder_ApprovedByAdmin_RestoresStock()
        {
            int orderId;
            using (var ctx = MakeContext())
            {
                var svc = MakeOrderService(ctx);
                var order = await svc.CreateOrderAsync(null, BuildOrderDto(
                    new OrderItemDto { ProductId = _productWithStock20Id, Quantity = 2 }));
                orderId = order.Id;
            }

            Assert.That(await GetStock(_productWithStock20Id), Is.EqualTo(18), "Stock phai giam sau khi dat hang");

            using (var ctx = MakeContext())
            {
                var svc = MakeOrderService(ctx);
                var cancelled = await svc.CancelOrderAsync(orderId, "Regression test cancel", null);
                Assert.That(cancelled!.Status, Is.EqualTo("CancelRequested"));
            }

            Assert.That(await GetStock(_productWithStock20Id), Is.EqualTo(18), "Chi hoan kho khi admin duyet, chua duyet thi kho giu nguyen");

            using (var ctx = MakeContext())
            {
                var svc = MakeOrderService(ctx);
                var reviewed = await svc.ReviewCancellationAsync(orderId, new ReviewCancelOrderDto { Approved = true }, null);
                Assert.That(reviewed!.Status, Is.EqualTo("Cancelled"));
            }

            Assert.That(await GetStock(_productWithStock20Id), Is.EqualTo(20), "Kho phai duoc hoan lai dung so luong sau khi duyet huy");
        }

        [Test]
        public async Task CreateOrder_SecondLineExceedsStock_RollsBackEntireOrderAndFirstLineStockUnchanged()
        {
            using var ctx = MakeContext();
            var svc = MakeOrderService(ctx);

            var ordersBefore = await ctx.Orders.CountAsync();

            Assert.ThrowsAsync<InvalidOperationException>(async () =>
            {
                await svc.CreateOrderAsync(null, BuildOrderDto(
                    new OrderItemDto { ProductId = _productWithStock20Id, Quantity = 2 },   // valid
                    new OrderItemDto { ProductId = _productWithStock30Id, Quantity = 9999 } // exceeds stock
                ));
            });

            Assert.That(await GetStock(_productWithStock20Id), Is.EqualTo(20), "Dong hop le khong duoc giu lai thay doi khi dong sau that bai (transaction phai rollback)");
            Assert.That(await GetStock(_productWithStock30Id), Is.EqualTo(30));

            using var verifyCtx = MakeContext();
            var ordersAfter = await verifyCtx.Orders.CountAsync();
            Assert.That(ordersAfter, Is.EqualTo(ordersBefore), "Khong duoc tao order moi khi giao dich bi huy bo");
        }

        private static InventoryService MakeInventoryService(AppDbContext ctx) => new InventoryService(
            new WarehouseRepositoryEF(ctx),
            new StockItemRepositoryEF(ctx),
            new GoodsReceiptRepositoryEF(ctx),
            new GoodsReceiptLineRepositoryEF(ctx),
            new GoodsReceiptSerialRepositoryEF(ctx),
            new StockMovementRepositoryEF(ctx),
            new InventoryReturnRepositoryEF(ctx),
            new OrderDetailStockItemRepositoryEF(ctx),
            new ProductRepositoryEF(ctx),
            new OrderRepositoryEF(ctx),
            new OrderDetailRepositoryEF(ctx),
            new SupplierRepositoryEF(ctx),
            new InventoryTransactionRepositoryEF(ctx));

        private static OrderService MakeOrderServiceWithInventory(AppDbContext ctx) => new OrderService(
            new OrderRepositoryEF(ctx),
            new OrderDetailRepositoryEF(ctx),
            new OrderTimelineRepositoryEF(ctx),
            new OrderCancellationRepositoryEF(ctx),
            new ProductRepositoryEF(ctx),
            new ProductVariantRepositoryEF(ctx),
            new WarehouseRepositoryEF(ctx),
            new StockItemRepositoryEF(ctx),
            new OrderDetailStockItemRepositoryEF(ctx),
            inventoryService: MakeInventoryService(ctx));

        // Hoi quy cho vong doi san pham co theo doi serial/IMEI: dat hang (reserve serial) ->
        // xac nhan -> xu ly -> giao -> hoan tat (xuat ban, StockItem chuyen "Sold"). Doi soat kho
        // (ReconcileStockAsync) phai khop giua Product.TotalStock va so StockItem "InStock" thuc te
        // o ca hai thoi diem truoc/sau.
        [Test]
        public async Task SerialTrackedProduct_FullLifecycle_ReservesThenSellsStockItem()
        {
            int productId;
            int stockItemAId, stockItemBId;
            using (var ctx = MakeContext())
            {
                var category = new Category { Name = $"Test-Serial-{Guid.NewGuid():N}" };
                ctx.Categories.Add(category);
                await ctx.SaveChangesAsync();

                var product = new Product { Name = "Regression Serial Product", CategoryId = category.Id, TotalStock = 2, IsActive = true, BasePrice = 2000000, RequiresSerialTracking = true };
                ctx.Products.Add(product);
                await ctx.SaveChangesAsync();
                productId = product.Id;

                var itemA = new StockItem { ProductId = product.Id, SerialOrImei = $"SN-{Guid.NewGuid():N}"[..20], Status = "InStock" };
                var itemB = new StockItem { ProductId = product.Id, SerialOrImei = $"SN-{Guid.NewGuid():N}"[..20], Status = "InStock" };
                ctx.StockItems.AddRange(itemA, itemB);
                await ctx.SaveChangesAsync();
                stockItemAId = itemA.Id;
                stockItemBId = itemB.Id;
            }

            // Doi soat truoc khi dat hang: TotalStock (2) phai khop so StockItem InStock thuc te (2).
            // Chi kiem tra rieng san pham cua test nay (loc theo productId) vi HasData seed 5 san pham
            // mau (Id 1-5, xem AppDbContext.cs) co RequiresSerialTracking=true nhung KHONG co StockItem
            // nao duoc seed kem theo - ReconcileStockAsync se luon bao lech cho ca 5 san pham do, khong
            // lien quan gi toi logic dang test o day. Day chinh la bug can bao cao rieng (xem muc B5).
            using (var ctx = MakeContext())
            {
                var inv = MakeInventoryService(ctx);
                var before = await inv.ReconcileStockAsync(backfillTags: false, userId: null);
                Assert.That(before.Changes.Any(c => c.ProductId == productId), Is.False, "Truoc khi dat hang, kho ao va kho thuc cua san pham test phai dang khop nhau");
            }

            int orderId;
            using (var ctx = MakeContext())
            {
                var svc = MakeOrderServiceWithInventory(ctx);
                var order = await svc.CreateOrderAsync(null, BuildOrderDto(
                    new OrderItemDto { ProductId = productId, Quantity = 1 }));
                orderId = order.Id;
            }

            using (var ctx = MakeContext())
            {
                var reservedCount = await ctx.StockItems.CountAsync(x => x.ProductId == productId && x.Status == "Reserved" && x.OrderId == orderId);
                Assert.That(reservedCount, Is.EqualTo(1), "Dat hang phai reserve dung 1 StockItem cho san pham theo doi serial");
            }
            Assert.That(await GetStock(productId), Is.EqualTo(1), "TotalStock phai giam 1 sau khi dat hang");

            using (var ctx = MakeContext())
            {
                var inv = MakeInventoryService(ctx);
                var afterOrder = await inv.ReconcileStockAsync(backfillTags: false, userId: null);
                Assert.That(afterOrder.Changes.Any(c => c.ProductId == productId), Is.False, "Sau khi dat hang, TotalStock (1) phai khop so StockItem InStock con lai (1)");
            }

            using (var ctx = MakeContext())
            {
                var svc = MakeOrderServiceWithInventory(ctx);
                await svc.UpdateStatusAsync(orderId, new UpdateOrderStatusDto { Status = "Confirmed" }, null);
            }
            using (var ctx = MakeContext())
            {
                var svc = MakeOrderServiceWithInventory(ctx);
                await svc.UpdateStatusAsync(orderId, new UpdateOrderStatusDto { Status = "Processing" }, null);
            }
            using (var ctx = MakeContext())
            {
                var svc = MakeOrderServiceWithInventory(ctx);
                await svc.UpdateStatusAsync(orderId, new UpdateOrderStatusDto { Status = "Shipping" }, null);
            }
            using (var ctx = MakeContext())
            {
                var svc = MakeOrderServiceWithInventory(ctx);
                var completed = await svc.UpdateStatusAsync(orderId, new UpdateOrderStatusDto { Status = "Completed" }, null);
                Assert.That(completed!.Status, Is.EqualTo("Completed"));
            }

            using (var ctx = MakeContext())
            {
                var sold = await ctx.StockItems.Where(x => x.ProductId == productId && x.OrderId == orderId).ToListAsync();
                Assert.That(sold.Count, Is.EqualTo(1));
                Assert.That(sold[0].Status, Is.EqualTo("Sold"), "Sau khi don hoan tat, StockItem da reserve phai chuyen sang Sold");
                Assert.That(sold[0].SoldAt, Is.Not.Null);
            }

            using (var ctx = MakeContext())
            {
                var inv = MakeInventoryService(ctx);
                var afterCompleted = await inv.ReconcileStockAsync(backfillTags: false, userId: null);
                Assert.That(afterCompleted.Changes.Any(c => c.ProductId == productId), Is.False, "Sau khi hoan tat don, TotalStock (1) phai khop so StockItem InStock con lai (1, da tru item Sold)");
            }
        }

        // Hoi quy cho don hang co bien the: dat hang tru kho bien the, huy don khong hoan kho ngay,
        // chi hoan kho khi admin duyet huy.
        [Test]
        public async Task VariantOrder_CancelThenApprove_RestoresVariantStock()
        {
            int productId, variantId;
            using (var ctx = MakeContext())
            {
                var category = new Category { Name = $"Test-Variant-{Guid.NewGuid():N}" };
                ctx.Categories.Add(category);
                await ctx.SaveChangesAsync();

                var product = new Product { Name = "Regression Variant Product", CategoryId = category.Id, TotalStock = 0, IsActive = true, BasePrice = 3000000 };
                ctx.Products.Add(product);
                await ctx.SaveChangesAsync();
                productId = product.Id;

                var variant = new ProductVariant { ProductId = product.Id, VariantName = "Variant A", Price = 3000000, Stock = 10, IsActive = true };
                ctx.ProductVariants.Add(variant);
                await ctx.SaveChangesAsync();
                variantId = variant.Id;
            }

            int orderId;
            using (var ctx = MakeContext())
            {
                var svc = MakeOrderService(ctx);
                var order = await svc.CreateOrderAsync(null, BuildOrderDto(
                    new OrderItemDto { ProductId = productId, VariantId = variantId, Quantity = 3 }));
                orderId = order.Id;
            }

            using (var ctx = MakeContext())
            {
                var stock = await ctx.ProductVariants.Where(v => v.Id == variantId).Select(v => v.Stock).FirstAsync();
                Assert.That(stock, Is.EqualTo(7), "Dat hang bien the phai tru dung so luong tren Variant.Stock");
            }

            using (var ctx = MakeContext())
            {
                var svc = MakeOrderService(ctx);
                var cancelled = await svc.CancelOrderAsync(orderId, "Regression test cancel variant", null);
                Assert.That(cancelled!.Status, Is.EqualTo("CancelRequested"));
            }

            using (var ctx = MakeContext())
            {
                var stock = await ctx.ProductVariants.Where(v => v.Id == variantId).Select(v => v.Stock).FirstAsync();
                Assert.That(stock, Is.EqualTo(7), "Chi yeu cau huy, chua duyet thi kho bien the giu nguyen");
            }

            using (var ctx = MakeContext())
            {
                var svc = MakeOrderService(ctx);
                var reviewed = await svc.ReviewCancellationAsync(orderId, new ReviewCancelOrderDto { Approved = true }, null);
                Assert.That(reviewed!.Status, Is.EqualTo("Cancelled"));
            }

            using (var ctx = MakeContext())
            {
                var stock = await ctx.ProductVariants.Where(v => v.Id == variantId).Select(v => v.Stock).FirstAsync();
                Assert.That(stock, Is.EqualTo(10), "Sau khi duyet huy, kho bien the phai duoc hoan lai day du");
            }
        }
    }
}
