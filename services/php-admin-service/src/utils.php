<?php

declare(strict_types=1);

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

function normalizeOptional(mixed $value): string
{
    $normalized = trim((string) ($value ?? ''));
    return $normalized;
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
