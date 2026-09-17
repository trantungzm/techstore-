SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET ARITHABORT ON;

-- Schema owned by services/php-admin-service (see docs/architecture/notification-outbox-design.md).
-- Idempotent: safe to re-run against the shared `techStore1` database.

IF OBJECT_ID(N'[dbo].[NotificationTemplates]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[NotificationTemplates] (
        [Id] int NOT NULL IDENTITY(1,1),
        [Code] nvarchar(80) NOT NULL,
        [Name] nvarchar(200) NOT NULL,
        [Channel] nvarchar(30) NOT NULL CONSTRAINT [DF_NotificationTemplates_Channel] DEFAULT ('System'),
        [TitleTemplate] nvarchar(200) NOT NULL,
        [BodyTemplate] nvarchar(1000) NOT NULL,
        [IsActive] bit NOT NULL CONSTRAINT [DF_NotificationTemplates_IsActive] DEFAULT (1),
        [CreatedAt] datetime2 NOT NULL CONSTRAINT [DF_NotificationTemplates_CreatedAt] DEFAULT (GETUTCDATE()),
        [UpdatedAt] datetime2 NOT NULL CONSTRAINT [DF_NotificationTemplates_UpdatedAt] DEFAULT (GETUTCDATE()),
        CONSTRAINT [PK_NotificationTemplates] PRIMARY KEY ([Id])
    );

    CREATE UNIQUE INDEX [IX_NotificationTemplates_Code] ON [dbo].[NotificationTemplates] ([Code]);
END

IF OBJECT_ID(N'[dbo].[NotificationCampaigns]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[NotificationCampaigns] (
        [Id] int NOT NULL IDENTITY(1,1),
        [Name] nvarchar(200) NOT NULL,
        [TemplateId] int NOT NULL,
        [PayloadJson] nvarchar(max) NULL,
        [Audience] nvarchar(30) NOT NULL CONSTRAINT [DF_NotificationCampaigns_Audience] DEFAULT ('SingleUser'),
        [Status] nvarchar(30) NOT NULL CONSTRAINT [DF_NotificationCampaigns_Status] DEFAULT ('Created'),
        [CreatedAt] datetime2 NOT NULL CONSTRAINT [DF_NotificationCampaigns_CreatedAt] DEFAULT (GETUTCDATE()),
        CONSTRAINT [PK_NotificationCampaigns] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_NotificationCampaigns_NotificationTemplates_TemplateId] FOREIGN KEY ([TemplateId]) REFERENCES [dbo].[NotificationTemplates] ([Id])
    );

    CREATE INDEX [IX_NotificationCampaigns_TemplateId] ON [dbo].[NotificationCampaigns] ([TemplateId]);
END

IF OBJECT_ID(N'[dbo].[NotificationJobs]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[NotificationJobs] (
        [Id] bigint NOT NULL IDENTITY(1,1),
        [CampaignId] int NOT NULL,
        [TemplateId] int NOT NULL,
        [UserId] uniqueidentifier NULL,
        [Title] nvarchar(200) NOT NULL,
        [Message] nvarchar(1000) NOT NULL,
        [PayloadJson] nvarchar(max) NULL,
        [Status] nvarchar(30) NOT NULL CONSTRAINT [DF_NotificationJobs_Status] DEFAULT ('Pending'),
        [RetryCount] int NOT NULL CONSTRAINT [DF_NotificationJobs_RetryCount] DEFAULT (0),
        [AvailableAt] datetime2 NOT NULL CONSTRAINT [DF_NotificationJobs_AvailableAt] DEFAULT (GETUTCDATE()),
        [ProcessedAt] datetime2 NULL,
        [LastError] nvarchar(max) NULL,
        [CreatedAt] datetime2 NOT NULL CONSTRAINT [DF_NotificationJobs_CreatedAt] DEFAULT (GETUTCDATE()),
        CONSTRAINT [PK_NotificationJobs] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_NotificationJobs_NotificationCampaigns_CampaignId] FOREIGN KEY ([CampaignId]) REFERENCES [dbo].[NotificationCampaigns] ([Id]),
        CONSTRAINT [FK_NotificationJobs_NotificationTemplates_TemplateId] FOREIGN KEY ([TemplateId]) REFERENCES [dbo].[NotificationTemplates] ([Id]),
        CONSTRAINT [FK_NotificationJobs_Users_UserId] FOREIGN KEY ([UserId]) REFERENCES [dbo].[Users] ([Id])
    );

    CREATE INDEX [IX_NotificationJobs_Status_AvailableAt] ON [dbo].[NotificationJobs] ([Status], [AvailableAt]);
    CREATE INDEX [IX_NotificationJobs_CampaignId] ON [dbo].[NotificationJobs] ([CampaignId]);
END

-- Example (sqlcmd):
-- sqlcmd -S .\SQLEXPRESS -d techStore1 -i create_notification_tables.sql
