<?php

declare(strict_types=1);

require_once __DIR__ . '/utils.php';

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
