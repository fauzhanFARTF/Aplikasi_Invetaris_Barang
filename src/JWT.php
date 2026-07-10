<?php
declare(strict_types=1);
// Minimal HS256 JWT implementation (self-contained, no external deps)
class JWT {
    public static function encode(array $payload, string $secret): string {
        $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h64 = self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p64 = self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', "$h64.$p64", $secret, true);
        $s64 = self::b64url($sig);
        return "$h64.$p64.$s64";
    }

    public static function decode(string $token, string $secret): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h64, $p64, $s64] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "$h64.$p64", $secret, true));
        if (!hash_equals($expected, $s64)) return null;
        $payload = json_decode(self::b64urlDecode($p64), true);
        if (!is_array($payload)) return null;
        if (isset($payload['exp']) && time() > (int) $payload['exp']) return null;
        return $payload;
    }

    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function b64urlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
