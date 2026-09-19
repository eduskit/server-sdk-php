<?php

namespace Eduskit;

final class TokenSigner
{
    public static function edu(string $appId, string $appSecret, array $input): array
    {
        $userId = self::required($input, 'eduUserId', 'classroom');
        $expiresIn = self::expiry($input, 86400, 'classroom');
        $claims = ['appId' => $appId];
        foreach (['originId', 'role'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') $claims[$key] = $input[$key];
        }
        [$token, $expiresAt] = self::sign($appId, $appSecret, $userId, 'eduskit-edu-auth', 'eduskit-edu', $expiresIn, $claims, 'classroom');
        return ['accessToken' => $token, 'expiresIn' => $expiresIn, 'expiresAt' => self::iso($expiresAt),
            'tokenType' => 'Bearer', 'appId' => $appId, 'eduUserId' => $userId,
            'originId' => $input['originId'] ?? '', 'role' => $input['role'] ?? ''];
    }

    public static function room(string $appId, string $appSecret, array $input): array
    {
        $roomId = self::required($input, 'roomId', 'whiteboard');
        $userId = self::required($input, 'userId', 'whiteboard');
        $role = self::required($input, 'role', 'whiteboard');
        if (!in_array($role, ['host', 'participant', 'observer'], true)) self::error('invalid role', 'whiteboard');
        $expiresIn = self::expiry($input, 3600, 'whiteboard');
        [$token, $expiresAt] = self::sign($appId, $appSecret, $userId, 'eduskit', 'eduskit-room', $expiresIn,
            ['app_id' => $appId, 'room_id' => $roomId, 'role' => $role, 'source' => 'server_sdk'], 'whiteboard');
        return ['token' => $token, 'appId' => $appId, 'roomId' => $roomId, 'userId' => $userId,
            'role' => $role, 'expiresIn' => $expiresIn, 'expiresAt' => self::iso($expiresAt)];
    }

    private static function sign(string $appId, string $secret, string $subject, string $issuer,
        string $audience, int $expiresIn, array $claims, string $source): array
    {
        if ($appId === '' || $secret === '') self::error('appId and appSecret are required', $source);
        if ($expiresIn < 60 || $expiresIn > 604800) self::error('expiresIn must be between 60 and 604800', $source);
        $now = time(); $expiresAt = $now + $expiresIn;
        $payload = array_merge($claims, ['iss' => $issuer, 'aud' => $audience, 'sub' => $subject, 'iat' => $now, 'exp' => $expiresAt]);
        $content = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)) . '.' . self::b64(json_encode($payload, JSON_THROW_ON_ERROR));
        return [$content . '.' . self::b64(hash_hmac('sha256', $content, $secret, true)), $expiresAt];
    }

    private static function required(array $input, string $key, string $source): string
    { $value = $input[$key] ?? null; if (!is_string($value) || $value === '') self::error("{$key} is required", $source); return $value; }
    private static function expiry(array $input, int $fallback, string $source): int
    { $value = $input['expiresIn'] ?? $fallback; if (!is_int($value)) self::error('expiresIn must be an integer', $source); return $value; }
    private static function b64(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function iso(int $seconds): string { return gmdate('Y-m-d\TH:i:s\Z', $seconds); }
    private static function error(string $message, string $source): never { throw new EduskitError($message, null, 'SDK_TOKEN_INPUT_INVALID', null, null, $source); }
}
