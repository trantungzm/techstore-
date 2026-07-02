<?php

declare(strict_types=1);

loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    route($method, $path);
} catch (Throwable $exception) {
    jsonResponse(
        [
            'message' => $exception->getMessage(),
        ],
        $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 500
    );
}

function route(string $method, string $path): void
{
    if ($path === '/health') {
        jsonResponse([
            'service' => 'tech-php-admin-service',
            'status' => 'ok',
            'modules' => ['banners', 'settings', 'notifications-admin'],
            'databaseDriver' => envValue('DB_CONNECTION', 'sqlsrv'),
            'availablePdoDrivers' => class_exists(PDO::class) ? PDO::getAvailableDrivers() : [],
        ]);
    }

    if ((str_starts_with($path, '/api/banners') || str_starts_with($path, '/api/settings')) && shouldProxyLegacy()) {
        proxyToLegacy($path);
    }

    if ($path === '/api/banners/active' && $method === 'GET') {
        jsonResponse(fetchAll(
            'SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
             FROM Banners
             WHERE IsActive = 1
             ORDER BY DisplayOrder, Id'
        ));
    }

    if ($path === '/api/banners' && $method === 'GET') {
        requireAdmin();
        jsonResponse(fetchAll(
            'SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
             FROM Banners
             ORDER BY DisplayOrder, Id'
        ));
    }

    if ($path === '/api/banners' && $method === 'POST') {
        requireAdmin();
        $payload = validateBannerPayload(readJsonBody(), false);
        $pdo = db();
        $statement = $pdo->prepare(
            'INSERT INTO Banners
                (Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt)
             VALUES
                (:Kicker, :Title, :SubTitle, :CtaLabel, :CtaTo, :ImageUrl, :OfferTitle, :OfferDiscount, :OfferProduct, :DisplayOrder, :IsActive, :CreatedAt, :UpdatedAt)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            ':Kicker' => $payload['kicker'],
            ':Title' => $payload['title'],
            ':SubTitle' => $payload['subTitle'],
            ':CtaLabel' => $payload['ctaLabel'],
            ':CtaTo' => $payload['ctaTo'],
            ':ImageUrl' => $payload['imageUrl'],
            ':OfferTitle' => $payload['offerTitle'],
            ':OfferDiscount' => $payload['offerDiscount'],
            ':OfferProduct' => $payload['offerProduct'],
            ':DisplayOrder' => $payload['displayOrder'],
            ':IsActive' => $payload['isActive'] ? 1 : 0,
            ':CreatedAt' => $now,
            ':UpdatedAt' => null,
        ]);

        $id = (int) $pdo->lastInsertId();
        jsonResponse(fetchBannerById($id), 201);
    }

    if (preg_match('#^/api/banners/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        requireAdmin();
        $banner = fetchBannerById((int) $matches[1]);
        if ($banner === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }
        jsonResponse($banner);
    }

    if (preg_match('#^/api/banners/(\d+)$#', $path, $matches) === 1 && $method === 'PUT') {
        requireAdmin();
        $id = (int) $matches[1];
        if (fetchBannerById($id) === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }

        $payload = validateBannerPayload(readJsonBody(), true);
        $statement = db()->prepare(
            'UPDATE Banners SET
                Kicker = :Kicker,
                Title = :Title,
                SubTitle = :SubTitle,
                CtaLabel = :CtaLabel,
                CtaTo = :CtaTo,
                ImageUrl = :ImageUrl,
                OfferTitle = :OfferTitle,
                OfferDiscount = :OfferDiscount,
                OfferProduct = :OfferProduct,
                DisplayOrder = :DisplayOrder,
                IsActive = :IsActive,
                UpdatedAt = :UpdatedAt
             WHERE Id = :Id'
        );
        $statement->execute([
            ':Id' => $id,
            ':Kicker' => $payload['kicker'],
            ':Title' => $payload['title'],
            ':SubTitle' => $payload['subTitle'],
            ':CtaLabel' => $payload['ctaLabel'],
            ':CtaTo' => $payload['ctaTo'],
            ':ImageUrl' => $payload['imageUrl'],
            ':OfferTitle' => $payload['offerTitle'],
            ':OfferDiscount' => $payload['offerDiscount'],
            ':OfferProduct' => $payload['offerProduct'],
            ':DisplayOrder' => $payload['displayOrder'],
            ':IsActive' => $payload['isActive'] ? 1 : 0,
            ':UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        jsonResponse(fetchBannerById($id));
    }

    if (preg_match('#^/api/banners/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        requireAdmin();
        $id = (int) $matches[1];
        if (fetchBannerById($id) === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }
        $statement = db()->prepare('DELETE FROM Banners WHERE Id = :Id');
        $statement->execute([':Id' => $id]);
        jsonResponse(['message' => 'Banner deleted successfully']);
    }

    if (preg_match('#^/api/banners/(\d+)/toggle$#', $path, $matches) === 1 && $method === 'PUT') {
        requireAdmin();
        $id = (int) $matches[1];
        $banner = fetchBannerById($id);
        if ($banner === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }

        $statement = db()->prepare(
            'UPDATE Banners SET IsActive = :IsActive, UpdatedAt = :UpdatedAt WHERE Id = :Id'
        );
        $statement->execute([
            ':Id' => $id,
            ':IsActive' => !toBool($banner['IsActive']) ? 1 : 0,
            ':UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        jsonResponse(fetchBannerById($id));
    }

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

    jsonResponse([
        'message' => 'Route not found',
        'method' => $method,
        'path' => $path,
    ], 404);
}

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
        $dsn = sprintf(
            'sqlsrv:Server=%s,%s;Database=%s;TrustServerCertificate=1',
            envValue('DB_HOST', 'localhost'),
            envValue('DB_PORT', '1433'),
            envValue('DB_DATABASE', 'techStore1')
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

function shouldProxyLegacy(): bool
{
    if (!toBool(envValue('LEGACY_PROXY_ENABLED', 'true'))) {
        return false;
    }

    if (!class_exists(PDO::class)) {
        return true;
    }

    $driver = strtolower((string) envValue('DB_CONNECTION', 'sqlsrv'));
    return !in_array($driver, PDO::getAvailableDrivers(), true);
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

function proxyToLegacy(string $path): void
{
    $baseUrl = rtrim((string) envValue('LEGACY_API_BASE_URL', 'http://localhost:5001'), '/');
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $url = $baseUrl . $path . ($query !== '' ? '?' . $query : '');

    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL is required for legacy proxy fallback.', 500);
    }

    $headers = [];
    foreach (getallheadersSafe() as $name => $value) {
        if (strcasecmp($name, 'Host') === 0 || strcasecmp($name, 'Content-Length') === 0) {
            continue;
        }
        $headers[] = $name . ': ' . $value;
    }

    $body = file_get_contents('php://input');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body === false ? '' : $body,
        CURLOPT_HEADER => true,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $message = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Legacy proxy request failed: ' . $message, 502);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    curl_close($ch);

    foreach (preg_split("/\r\n|\n|\r/", $rawHeaders) ?: [] as $headerLine) {
        if (!str_contains($headerLine, ':')) {
            continue;
        }

        [$name, $value] = explode(':', $headerLine, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '' || in_array(strtolower($name), ['content-length', 'transfer-encoding', 'connection'], true)) {
            continue;
        }

        header($name . ': ' . $value, true);
    }

    http_response_code($statusCode > 0 ? $statusCode : 502);
    echo $responseBody;
    exit;
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

function serializeBankAccounts(array $items): ?string
{
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $bankName = trim((string) ($item['bankName'] ?? ''));
        $accountNumber = trim((string) ($item['accountNumber'] ?? ''));
        $accountHolder = normalizeOptional($item['accountHolder'] ?? null);

        if ($bankName === '' || $accountNumber === '') {
            continue;
        }

        $clean[] = [
            'bankName' => $bankName,
            'accountNumber' => $accountNumber,
            'accountHolder' => $accountHolder,
        ];
    }

    return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function deserializeBankAccounts(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function requireAdmin(): void
{
    $claims = authenticateJwt();
    $roles = extractRoles($claims);
    if (!in_array('Admin', $roles, true)) {
        jsonResponse(['message' => 'Forbidden'], 403);
    }
}

function authenticateJwt(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        jsonResponse(['message' => 'Unauthorized'], 401);
    }

    $token = trim($matches[1]);
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        jsonResponse(['message' => 'Invalid JWT format'], 401);
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $headerData = json_decode(base64UrlDecode($encodedHeader), true);
    $payloadData = json_decode(base64UrlDecode($encodedPayload), true);

    if (!is_array($headerData) || !is_array($payloadData)) {
        jsonResponse(['message' => 'Invalid JWT payload'], 401);
    }

    $algorithm = $headerData['alg'] ?? 'HS256';
    if ($algorithm !== 'HS256') {
        jsonResponse(['message' => 'Unsupported JWT algorithm'], 401);
    }

    $secret = (string) envValue('JWT_SECRET', '');
    if ($secret === '') {
        jsonResponse(['message' => 'JWT secret is not configured'], 500);
    }

    $expectedSignature = base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true));
    if (!hash_equals($expectedSignature, $encodedSignature)) {
        jsonResponse(['message' => 'Invalid token signature'], 401);
    }

    $now = time();
    if (isset($payloadData['exp']) && is_numeric($payloadData['exp']) && (int) $payloadData['exp'] < $now) {
        jsonResponse(['message' => 'Token expired'], 401);
    }

    $issuer = (string) envValue('JWT_ISSUER', '');
    if ($issuer !== '' && ($payloadData['iss'] ?? null) !== $issuer) {
        jsonResponse(['message' => 'Invalid token issuer'], 401);
    }

    $audience = (string) envValue('JWT_AUDIENCE', '');
    if ($audience !== '') {
        $aud = $payloadData['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        if (!in_array($audience, $audiences, true)) {
            jsonResponse(['message' => 'Invalid token audience'], 401);
        }
    }

    return $payloadData;
}

function extractRoles(array $claims): array
{
    $candidates = [
        $claims['role'] ?? null,
        $claims['roles'] ?? null,
        $claims['http://schemas.microsoft.com/ws/2008/06/identity/claims/role'] ?? null,
    ];

    $roles = [];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            $roles[] = $candidate;
        } elseif (is_array($candidate)) {
            foreach ($candidate as $item) {
                if (is_string($item) && $item !== '') {
                    $roles[] = $item;
                }
            }
        }
    }

    return array_values(array_unique($roles));
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Invalid JSON body.', 400);
    }

    return $decoded;
}

function normalizeOptional(mixed $value): string
{
    $normalized = trim((string) ($value ?? ''));
    return $normalized;
}

function getallheadersSafe(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        return is_array($headers) ? $headers : [];
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (!str_starts_with($key, 'HTTP_')) {
            continue;
        }

        $name = str_replace('_', '-', strtolower(substr($key, 5)));
        $headers[implode('-', array_map('ucfirst', explode('-', $name)))] = (string) $value;
    }

    return $headers;
}

function toBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (int) $value === 1;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function envValue(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : $value;
}

function loadEnv(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function base64UrlDecode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid base64url data.', 401);
    }

    return $decoded;
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
