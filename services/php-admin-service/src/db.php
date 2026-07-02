<?php

declare(strict_types=1);

require_once __DIR__ . '/utils.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = strtolower((string) envValue('DB_CONNECTION', 'sqlsrv'));
    if (!class_exists(PDO::class)) {
        throw new RuntimeException('PDO is not available.', 500);
    }

    $availableDrivers = PDO::getAvailableDrivers();
    if (!in_array($driver, $availableDrivers, true)) {
        throw new RuntimeException(
            sprintf('PDO driver "%s" is not installed. Available drivers: %s', $driver, implode(', ', $availableDrivers)),
            500
        );
    }

    if ($driver === 'sqlsrv') {
        $protocol = trim((string) envValue('DB_PROTOCOL', ''));
        $host = (string) envValue('DB_HOST', 'localhost');
        $port = (string) envValue('DB_PORT', '1433');
        $database = (string) envValue('DB_DATABASE', 'techStore1');
        $server = str_contains($host, '\\') ? $host : ($host . ',' . $port);
        if ($protocol !== '') {
            $server = rtrim($protocol, ':') . ':' . $server;
        }

        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=1',
            $server,
            $database
        );
    } elseif ($driver === 'sqlite') {
        $dsn = 'sqlite:' . envValue('DB_DATABASE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database.sqlite');
    } else {
        throw new RuntimeException('Unsupported DB_CONNECTION. Use sqlsrv or sqlite.', 500);
    }

    $pdo = new PDO(
        $dsn,
        envValue('DB_USERNAME', ''),
        envValue('DB_PASSWORD', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}

function fetchAll(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll() ?: [];
}

function fetchOne(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function fetchBannerById(int $id): ?array
{
    return fetchOne(
        'SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
         FROM Banners
         WHERE Id = :Id',
        [':Id' => $id]
    );
}

function fetchOrCreateDefaultSetting(): array
{
    $setting = fetchOne('SELECT TOP 1 * FROM StoreSettings ORDER BY Id');
    if ($setting !== null) {
        $setting['BankAccounts'] = deserializeBankAccounts($setting['BankAccountsJson'] ?? null);
        unset($setting['BankAccountsJson']);
        return $setting;
    }

    $statement = db()->prepare(
        'INSERT INTO StoreSettings
            (Id, StoreName, Hotline, SupportEmail, Address, WarrantyAddress, DefaultShippingFee, FreeShippingThreshold, SupportTime, LogoUrl, FacebookUrl, ZaloUrl, BankName, BankAccountNumber, BankAccountHolder, BankAccountsJson, CreatedAt, UpdatedAt)
         VALUES
            (:Id, :StoreName, :Hotline, :SupportEmail, :Address, :WarrantyAddress, :DefaultShippingFee, :FreeShippingThreshold, :SupportTime, :LogoUrl, :FacebookUrl, :ZaloUrl, :BankName, :BankAccountNumber, :BankAccountHolder, :BankAccountsJson, :CreatedAt, :UpdatedAt)'
    );

    $now = gmdate('Y-m-d H:i:s');
    $statement->execute([
        ':Id' => 1,
        ':StoreName' => 'CNTHHT Store',
        ':Hotline' => '0327 188 459',
        ':SupportEmail' => 'support@cnthht.vn',
        ':Address' => '',
        ':WarrantyAddress' => '',
        ':DefaultShippingFee' => 0,
        ':FreeShippingThreshold' => null,
        ':SupportTime' => '',
        ':LogoUrl' => '',
        ':FacebookUrl' => '',
        ':ZaloUrl' => '',
        ':BankName' => '',
        ':BankAccountNumber' => '',
        ':BankAccountHolder' => '',
        ':BankAccountsJson' => null,
        ':CreatedAt' => $now,
        ':UpdatedAt' => $now,
    ]);

    $setting = fetchOne('SELECT TOP 1 * FROM StoreSettings ORDER BY Id');
    $setting['BankAccounts'] = deserializeBankAccounts($setting['BankAccountsJson'] ?? null);
    unset($setting['BankAccountsJson']);
    return $setting;
}

function ensureNotificationsAdminTables(): void
{
    $pdo = db();
    $pdo->exec(
        'IF OBJECT_ID(N\'NotificationTemplates\', N\'U\') IS NULL
         CREATE TABLE NotificationTemplates (
            Id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            Code NVARCHAR(80) NOT NULL UNIQUE,
            Name NVARCHAR(200) NOT NULL,
            Channel NVARCHAR(20) NOT NULL,
            TitleTemplate NVARCHAR(200) NOT NULL,
            BodyTemplate NVARCHAR(1000) NOT NULL,
            IsActive BIT NOT NULL DEFAULT(1),
            CreatedAt DATETIME2 NOT NULL DEFAULT(SYSUTCDATETIME()),
            UpdatedAt DATETIME2 NULL
         )'
    );

    $pdo->exec(
        'IF OBJECT_ID(N\'NotificationCampaigns\', N\'U\') IS NULL
         CREATE TABLE NotificationCampaigns (
            Id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            Name NVARCHAR(200) NOT NULL,
            TemplateId INT NOT NULL,
            PayloadJson NVARCHAR(MAX) NULL,
            Audience NVARCHAR(40) NOT NULL,
            Status NVARCHAR(20) NOT NULL,
            CreatedAt DATETIME2 NOT NULL DEFAULT(SYSUTCDATETIME())
         )'
    );

    $pdo->exec(
        'IF OBJECT_ID(N\'NotificationJobs\', N\'U\') IS NULL
         CREATE TABLE NotificationJobs (
            Id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            CampaignId BIGINT NULL,
            TemplateId INT NOT NULL,
            UserId UNIQUEIDENTIFIER NULL,
            Title NVARCHAR(200) NOT NULL,
            Message NVARCHAR(1000) NOT NULL,
            PayloadJson NVARCHAR(MAX) NULL,
            Status NVARCHAR(20) NOT NULL,
            RetryCount INT NOT NULL DEFAULT(0),
            AvailableAt DATETIME2 NOT NULL DEFAULT(SYSUTCDATETIME()),
            CreatedAt DATETIME2 NOT NULL DEFAULT(SYSUTCDATETIME())
         )'
    );
}

function fetchTemplateById(int $id): ?array
{
    return fetchOne(
        'SELECT Id, Code, Name, Channel, TitleTemplate, BodyTemplate, IsActive, CreatedAt, UpdatedAt
         FROM NotificationTemplates
         WHERE Id = :Id',
        [':Id' => $id]
    );
}
