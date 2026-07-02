using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace BaseCore.Repository.Migrations
{
    /// <inheritdoc />
    public partial class AddNotificationOutbox : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.Sql(@"
IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = N'IX_ProductVariants_Sku'
      AND object_id = OBJECT_ID(N'[dbo].[ProductVariants]')
)
BEGIN
    DROP INDEX [IX_ProductVariants_Sku] ON [dbo].[ProductVariants];
END
");

            migrationBuilder.Sql(@"
IF COL_LENGTH(N'[dbo].[Banners]', N'ClickCount') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [ClickCount];
IF COL_LENGTH(N'[dbo].[Banners]', N'CreatedBy') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [CreatedBy];
IF COL_LENGTH(N'[dbo].[Banners]', N'DeletedAt') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [DeletedAt];
IF COL_LENGTH(N'[dbo].[Banners]', N'EndDate') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [EndDate];
IF COL_LENGTH(N'[dbo].[Banners]', N'IsDeleted') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [IsDeleted];
IF COL_LENGTH(N'[dbo].[Banners]', N'Position') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [Position];
IF COL_LENGTH(N'[dbo].[Banners]', N'StartDate') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [StartDate];
IF COL_LENGTH(N'[dbo].[Banners]', N'UpdatedBy') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [UpdatedBy];
IF COL_LENGTH(N'[dbo].[Banners]', N'ViewCount') IS NOT NULL
    ALTER TABLE [dbo].[Banners] DROP COLUMN [ViewCount];
");

            migrationBuilder.Sql(@"
IF COL_LENGTH(N'[dbo].[StoreSettings]', N'BankAccountsJson') IS NULL
    ALTER TABLE [dbo].[StoreSettings] ADD [BankAccountsJson] nvarchar(max) NULL;
");

            migrationBuilder.AlterColumn<bool>(
                name: "IsDefault",
                table: "ProductVariants",
                type: "bit",
                nullable: false,
                oldClrType: typeof(bool),
                oldType: "bit",
                oldDefaultValue: false);

            migrationBuilder.AlterColumn<string>(
                name: "Slug",
                table: "Products",
                type: "nvarchar(220)",
                maxLength: 220,
                nullable: true,
                oldClrType: typeof(string),
                oldType: "nvarchar(250)",
                oldMaxLength: 250,
                oldNullable: true);

            migrationBuilder.Sql(@"
IF OBJECT_ID(N'[dbo].[NotificationOutboxMessages]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[NotificationOutboxMessages] (
        [Id] bigint NOT NULL IDENTITY(1,1),
        [EventId] uniqueidentifier NOT NULL,
        [EventType] nvarchar(80) NOT NULL,
        [AggregateType] nvarchar(80) NOT NULL,
        [AggregateId] nvarchar(80) NOT NULL,
        [UserId] uniqueidentifier NULL,
        [Title] nvarchar(200) NOT NULL,
        [Message] nvarchar(1000) NOT NULL,
        [PayloadJson] nvarchar(max) NULL,
        [Status] nvarchar(30) NOT NULL,
        [AvailableAt] datetime2 NOT NULL,
        [ProcessedAt] datetime2 NULL,
        [LastError] nvarchar(max) NULL,
        [RetryCount] int NOT NULL,
        [CreatedAt] datetime2 NOT NULL CONSTRAINT [DF_NotificationOutboxMessages_CreatedAt] DEFAULT (GETUTCDATE()),
        CONSTRAINT [PK_NotificationOutboxMessages] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_NotificationOutboxMessages_Users_UserId] FOREIGN KEY ([UserId]) REFERENCES [dbo].[Users] ([Id])
    );
END
");

            migrationBuilder.Sql(@"
IF OBJECT_ID(N'[dbo].[PaymentSessions]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[PaymentSessions] (
        [Id] int NOT NULL IDENTITY(1,1),
        [SessionId] nvarchar(40) NOT NULL,
        [Token] nvarchar(64) NOT NULL,
        [OrderId] int NULL,
        [UserId] uniqueidentifier NULL,
        [OrderPayloadJson] nvarchar(max) NULL,
        [Amount] decimal(18,2) NOT NULL,
        [Status] nvarchar(20) NOT NULL,
        [TransactionId] nvarchar(80) NULL,
        [ExpiresAt] datetime2 NOT NULL,
        [PaidAt] datetime2 NULL,
        [CreatedAt] datetime2 NOT NULL,
        CONSTRAINT [PK_PaymentSessions] PRIMARY KEY ([Id])
    );
END
");

            migrationBuilder.Sql(@"
IF OBJECT_ID(N'[dbo].[ProductCategories]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[ProductCategories] (
        [ProductId] int NOT NULL,
        [CategoryId] int NOT NULL,
        CONSTRAINT [PK_ProductCategories] PRIMARY KEY ([ProductId], [CategoryId]),
        CONSTRAINT [FK_ProductCategories_Categories_CategoryId] FOREIGN KEY ([CategoryId]) REFERENCES [dbo].[Categories] ([Id]) ON DELETE NO ACTION,
        CONSTRAINT [FK_ProductCategories_Products_ProductId] FOREIGN KEY ([ProductId]) REFERENCES [dbo].[Products] ([Id]) ON DELETE CASCADE
    );
END
");

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 1,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 28990000m, 32990000m });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 2,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 21990000m, 24990000m });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 3,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 31990000m, 35990000m });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 4,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 35990000m, 39990000m });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 5,
                column: "OriginalPrice",
                value: 6990000m);

            migrationBuilder.UpdateData(
                table: "StoreSettings",
                keyColumn: "Id",
                keyValue: 1,
                column: "BankAccountsJson",
                value: null);

            migrationBuilder.Sql(@"
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_NotificationOutboxMessages_EventId' AND object_id = OBJECT_ID(N'[dbo].[NotificationOutboxMessages]'))
    CREATE UNIQUE INDEX [IX_NotificationOutboxMessages_EventId] ON [dbo].[NotificationOutboxMessages] ([EventId]);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_NotificationOutboxMessages_Status_AvailableAt' AND object_id = OBJECT_ID(N'[dbo].[NotificationOutboxMessages]'))
    CREATE INDEX [IX_NotificationOutboxMessages_Status_AvailableAt] ON [dbo].[NotificationOutboxMessages] ([Status], [AvailableAt]);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_NotificationOutboxMessages_UserId' AND object_id = OBJECT_ID(N'[dbo].[NotificationOutboxMessages]'))
    CREATE INDEX [IX_NotificationOutboxMessages_UserId] ON [dbo].[NotificationOutboxMessages] ([UserId]);
IF OBJECT_ID(N'[dbo].[PaymentSessions]', N'U') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PaymentSessions_OrderId' AND object_id = OBJECT_ID(N'[dbo].[PaymentSessions]'))
    CREATE INDEX [IX_PaymentSessions_OrderId] ON [dbo].[PaymentSessions] ([OrderId]);
IF OBJECT_ID(N'[dbo].[PaymentSessions]', N'U') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PaymentSessions_SessionId' AND object_id = OBJECT_ID(N'[dbo].[PaymentSessions]'))
    CREATE UNIQUE INDEX [IX_PaymentSessions_SessionId] ON [dbo].[PaymentSessions] ([SessionId]);
IF OBJECT_ID(N'[dbo].[ProductCategories]', N'U') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ProductCategories_CategoryId' AND object_id = OBJECT_ID(N'[dbo].[ProductCategories]'))
    CREATE INDEX [IX_ProductCategories_CategoryId] ON [dbo].[ProductCategories] ([CategoryId]);
");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "NotificationOutboxMessages");

            migrationBuilder.DropTable(
                name: "PaymentSessions");

            migrationBuilder.DropTable(
                name: "ProductCategories");

            migrationBuilder.DropColumn(
                name: "BankAccountsJson",
                table: "StoreSettings");

            migrationBuilder.AlterColumn<bool>(
                name: "IsDefault",
                table: "ProductVariants",
                type: "bit",
                nullable: false,
                defaultValue: false,
                oldClrType: typeof(bool),
                oldType: "bit");

            migrationBuilder.AlterColumn<string>(
                name: "Slug",
                table: "Products",
                type: "nvarchar(250)",
                maxLength: 250,
                nullable: true,
                oldClrType: typeof(string),
                oldType: "nvarchar(220)",
                oldMaxLength: 220,
                oldNullable: true);

            migrationBuilder.AddColumn<int>(
                name: "ClickCount",
                table: "Banners",
                type: "int",
                nullable: false,
                defaultValue: 0);

            migrationBuilder.AddColumn<string>(
                name: "CreatedBy",
                table: "Banners",
                type: "nvarchar(max)",
                nullable: false,
                defaultValue: "");

            migrationBuilder.AddColumn<DateTime>(
                name: "DeletedAt",
                table: "Banners",
                type: "datetime2",
                nullable: true);

            migrationBuilder.AddColumn<DateTime>(
                name: "EndDate",
                table: "Banners",
                type: "datetime2",
                nullable: true);

            migrationBuilder.AddColumn<bool>(
                name: "IsDeleted",
                table: "Banners",
                type: "bit",
                nullable: false,
                defaultValue: false);

            migrationBuilder.AddColumn<int>(
                name: "Position",
                table: "Banners",
                type: "int",
                nullable: false,
                defaultValue: 0);

            migrationBuilder.AddColumn<DateTime>(
                name: "StartDate",
                table: "Banners",
                type: "datetime2",
                nullable: true);

            migrationBuilder.AddColumn<string>(
                name: "UpdatedBy",
                table: "Banners",
                type: "nvarchar(max)",
                nullable: false,
                defaultValue: "");

            migrationBuilder.AddColumn<int>(
                name: "ViewCount",
                table: "Banners",
                type: "int",
                nullable: false,
                defaultValue: 0);

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 1,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 35990000m, null });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 2,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 25490000m, null });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 3,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 38990000m, null });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 4,
                columns: new[] { "MaxPrice", "OriginalPrice" },
                values: new object[] { 44990000m, null });

            migrationBuilder.UpdateData(
                table: "Products",
                keyColumn: "Id",
                keyValue: 5,
                column: "OriginalPrice",
                value: null);

            migrationBuilder.CreateIndex(
                name: "IX_ProductVariants_Sku",
                table: "ProductVariants",
                column: "Sku",
                unique: true,
                filter: "[Sku] IS NOT NULL");
        }
    }
}
