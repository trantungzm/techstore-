<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../validators.php';

function handleSettings(string $method, string $path): void
{
    if ($path === '/api/settings' && $method === 'GET') {
        jsonResponse(fetchOrCreateDefaultSetting());
    }

    if ($path === '/api/settings/pickup-branches' && $method === 'GET') {
        jsonResponse(fetchAll(
            'SELECT Id, Code, Name, Address
             FROM Warehouses
             WHERE IsActive = 1
             ORDER BY Id'
        ));
    }

    if ($path === '/api/settings' && $method === 'PUT') {
        requireAdmin();
        $payload = validateSettingsPayload(readJsonBody());
        $setting = fetchOrCreateDefaultSetting();

        $statement = db()->prepare(
            'UPDATE StoreSettings SET
                StoreName = :StoreName,
                Hotline = :Hotline,
                SupportEmail = :SupportEmail,
                Address = :Address,
                WarrantyAddress = :WarrantyAddress,
                DefaultShippingFee = :DefaultShippingFee,
                FreeShippingThreshold = :FreeShippingThreshold,
                SupportTime = :SupportTime,
                LogoUrl = :LogoUrl,
                FacebookUrl = :FacebookUrl,
                ZaloUrl = :ZaloUrl,
                BankName = :BankName,
                BankAccountNumber = :BankAccountNumber,
                BankAccountHolder = :BankAccountHolder,
                BankAccountsJson = :BankAccountsJson,
                UpdatedAt = :UpdatedAt
             WHERE Id = :Id'
        );

        $statement->execute([
            ':Id' => $setting['Id'],
            ':StoreName' => $payload['storeName'],
            ':Hotline' => $payload['hotline'],
            ':SupportEmail' => $payload['supportEmail'],
            ':Address' => $payload['address'],
            ':WarrantyAddress' => $payload['warrantyAddress'],
            ':DefaultShippingFee' => $payload['defaultShippingFee'],
            ':FreeShippingThreshold' => $payload['freeShippingThreshold'],
            ':SupportTime' => $payload['supportTime'],
            ':LogoUrl' => $payload['logoUrl'],
            ':FacebookUrl' => $payload['facebookUrl'],
            ':ZaloUrl' => $payload['zaloUrl'],
            ':BankName' => $payload['bankName'],
            ':BankAccountNumber' => $payload['bankAccountNumber'],
            ':BankAccountHolder' => $payload['bankAccountHolder'],
            ':BankAccountsJson' => serializeBankAccounts($payload['bankAccounts']),
            ':UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        jsonResponse(fetchOrCreateDefaultSetting());
    }
}
