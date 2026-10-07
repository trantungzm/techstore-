<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = trim($matches[1]);
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return response()->json(['message' => 'Invalid JWT format'], 401);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        try {
            $headerData = json_decode(self::base64UrlDecode($encodedHeader), true);
            $payloadData = json_decode(self::base64UrlDecode($encodedPayload), true);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid JWT payload'], 401);
        }

        if (!is_array($headerData) || !is_array($payloadData)) {
            return response()->json(['message' => 'Invalid JWT payload'], 401);
        }

        $algorithm = $headerData['alg'] ?? 'HS256';
        if ($algorithm !== 'HS256') {
            return response()->json(['message' => 'Unsupported JWT algorithm'], 401);
        }

        $secret = env('JWT_SECRET', '');
        if ($secret === '') {
            return response()->json(['message' => 'JWT secret is not configured'], 500);
        }

        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true));
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return response()->json(['message' => 'Invalid token signature'], 401);
        }

        $now = time();
        if (isset($payloadData['exp']) && is_numeric($payloadData['exp']) && (int) $payloadData['exp'] < $now) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        $expectedIssuer = env('JWT_ISSUER', '');
        $expectedAudience = env('JWT_AUDIENCE', '');
        $actualAudience = $payloadData['aud'] ?? null;
        $audienceMatches = is_string($actualAudience)
            ? hash_equals($expectedAudience, $actualAudience)
            : (is_array($actualAudience) && in_array($expectedAudience, $actualAudience, true));

        if ($expectedIssuer === '' || $expectedAudience === ''
            || !isset($payloadData['iss']) || !hash_equals($expectedIssuer, (string) $payloadData['iss'])
            || !$audienceMatches) {
            return response()->json(['message' => 'Invalid token issuer or audience'], 401);
        }

        $request->attributes->set('claims', $payloadData);
        return $next($request);
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url data.');
        }

        return $decoded;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
