SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET ARITHABORT ON;

-- Create a filtered covering index to speed up active banner queries.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'IX_Banners_Active_DisplayOrder_Id' AND object_id = OBJECT_ID('dbo.Banners')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_Banners_Active_DisplayOrder_Id
    ON dbo.Banners (DisplayOrder, Id)
    INCLUDE (Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct)
    WHERE IsActive = 1;
END

-- Create a covering index for the admin banner list, which returns all rows.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'IX_Banners_DisplayOrder_Id' AND object_id = OBJECT_ID('dbo.Banners')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_Banners_DisplayOrder_Id
    ON dbo.Banners (DisplayOrder, Id)
    INCLUDE (Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, IsActive, CreatedAt, UpdatedAt);
END

-- Note: run this script in the SQL Server connected to the `zewvron1` database.
-- Example (sqlcmd):
-- sqlcmd -S .\SQLEXPRESS -d zewvron1 -i create_indexes.sql
