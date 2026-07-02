<?php

declare(strict_types=1);

require_once __DIR__ . '/utils.php';

function validateBannerPayload(array $payload, bool $isUpdate): array
{
    $normalized = [
        'kicker' => trim((string) ($payload['kicker'] ?? '')),
        'title' => trim((string) ($payload['title'] ?? '')),
        'subTitle' => trim((string) ($payload['subTitle'] ?? '')),
        'ctaLabel' => trim((string) ($payload['ctaLabel'] ?? '')),
        'ctaTo' => trim((string) ($payload['ctaTo'] ?? '')),
        'imageUrl' => trim((string) ($payload['imageUrl'] ?? '')),
        'offerTitle' => trim((string) ($payload['offerTitle'] ?? '')),
        'offerDiscount' => trim((string) ($payload['offerDiscount'] ?? '')),
        'offerProduct' => trim((string) ($payload['offerProduct'] ?? '')),
        'displayOrder' => (int) ($payload['displayOrder'] ?? 0),
        'isActive' => array_key_exists('isActive', $payload) ? toBool($payload['isActive']) : true,
    ];

    $requiredFields = ['kicker', 'title', 'subTitle', 'ctaLabel', 'ctaTo', 'imageUrl'];
    foreach ($requiredFields as $field) {
        if ($normalized[$field] === '') {
            throw new InvalidArgumentException(sprintf('Field "%s" is required.', $field), 400);
        }
    }

    if ($normalized['displayOrder'] < 0) {
        throw new InvalidArgumentException('displayOrder must be greater than or equal to 0.', 400);
    }

    return $normalized;
}

function validateSettingsPayload(array $payload): array
{
    $normalized = [
        'storeName' => trim((string) ($payload['storeName'] ?? '')),
        'hotline' => trim((string) ($payload['hotline'] ?? '')),
        'supportEmail' => normalizeOptional($payload['supportEmail'] ?? null),
        'address' => normalizeOptional($payload['address'] ?? null),
        'warrantyAddress' => normalizeOptional($payload['warrantyAddress'] ?? null),
        'defaultShippingFee' => (float) ($payload['defaultShippingFee'] ?? 0),
        'freeShippingThreshold' => array_key_exists('freeShippingThreshold', $payload) && $payload['freeShippingThreshold'] !== null
            ? (float) $payload['freeShippingThreshold']
            : null,
        'supportTime' => normalizeOptional($payload['supportTime'] ?? null),
        'logoUrl' => normalizeOptional($payload['logoUrl'] ?? null),
        'facebookUrl' => normalizeOptional($payload['facebookUrl'] ?? null),
        'zaloUrl' => normalizeOptional($payload['zaloUrl'] ?? null),
        'bankName' => normalizeOptional($payload['bankName'] ?? null),
        'bankAccountNumber' => normalizeOptional($payload['bankAccountNumber'] ?? null),
        'bankAccountHolder' => normalizeOptional($payload['bankAccountHolder'] ?? null),
        'bankAccounts' => is_array($payload['bankAccounts'] ?? null) ? $payload['bankAccounts'] : [],
    ];

    if ($normalized['storeName'] === '') {
        throw new InvalidArgumentException('Store name is required.', 400);
    }

    if ($normalized['hotline'] === '') {
        throw new InvalidArgumentException('Hotline is required.', 400);
    }

    if ($normalized['defaultShippingFee'] < 0) {
        throw new InvalidArgumentException('Default shipping fee must be greater than or equal to 0.', 400);
    }

    if ($normalized['freeShippingThreshold'] !== null && $normalized['freeShippingThreshold'] < 0) {
        throw new InvalidArgumentException('Free shipping threshold must be greater than or equal to 0.', 400);
    }

    if ($normalized['supportEmail'] !== '' && !filter_var($normalized['supportEmail'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Support email is invalid.', 400);
    }

    return $normalized;
}

function validateTemplatePayload(array $payload, bool $isUpdate): array
{
    $normalized = [
        'code' => trim((string) ($payload['code'] ?? '')),
        'name' => trim((string) ($payload['name'] ?? '')),
        'channel' => trim((string) ($payload['channel'] ?? 'System')),
        'titleTemplate' => trim((string) ($payload['titleTemplate'] ?? '')),
        'bodyTemplate' => trim((string) ($payload['bodyTemplate'] ?? '')),
        'isActive' => array_key_exists('isActive', $payload) ? toBool($payload['isActive']) : true,
    ];

    if ($normalized['code'] === '') throw new InvalidArgumentException('code is required.', 400);
    if ($normalized['name'] === '') throw new InvalidArgumentException('name is required.', 400);
    if ($normalized['channel'] === '') throw new InvalidArgumentException('channel is required.', 400);
    if ($normalized['titleTemplate'] === '') throw new InvalidArgumentException('titleTemplate is required.', 400);
    if ($normalized['bodyTemplate'] === '') throw new InvalidArgumentException('bodyTemplate is required.', 400);

    return $normalized;
}

function validateCampaignPayload(array $payload): array
{
    $normalized = [
        'name' => trim((string) ($payload['name'] ?? '')),
        'templateId' => (int) ($payload['templateId'] ?? 0),
        'audience' => trim((string) ($payload['audience'] ?? 'SingleUser')),
        'userId' => $payload['userId'] ?? null,
        'payloadJson' => array_key_exists('payload', $payload) ? json_encode($payload['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'title' => array_key_exists('title', $payload) ? trim((string) $payload['title']) : null,
        'message' => array_key_exists('message', $payload) ? trim((string) $payload['message']) : null,
    ];

    if ($normalized['name'] === '') throw new InvalidArgumentException('name is required.', 400);
    if ($normalized['templateId'] <= 0) throw new InvalidArgumentException('templateId is required.', 400);
    if ($normalized['audience'] === '') throw new InvalidArgumentException('audience is required.', 400);

    if ($normalized['audience'] === 'SingleUser') {
        if ($normalized['userId'] === null || trim((string) $normalized['userId']) === '') {
            throw new InvalidArgumentException('userId is required for SingleUser audience.', 400);
        }
    }

    return $normalized;
}
