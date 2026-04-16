<?php
/**
 * JWT Token Handler
 * 
 * Create and validate JWT tokens
 */

require_once __DIR__ . '/../config.php';

class JWT
{
    /**
     * Generate JWT token
     */
    public static function encode($payload)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => JWT_ALGORITHM
        ];

        // Add expiration time
        $payload['exp'] = time() + getJwtExpireSeconds();
        $payload['iat'] = time();

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Decode and validate JWT token
     */
    public static function decode($token)
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        // Verify signature
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $signatureCheck = self::base64UrlEncode($signature);

        if ($signatureCheck !== $signatureEncoded) {
            return null;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
