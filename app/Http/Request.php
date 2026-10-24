<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    private const MAX_BODY_BYTES = 8192;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($_SERVER[$key] ?? '');
    }

    /**
     * بدنه JSON را می‌خواند. اگر معتبر نبود null برمی‌گرداند.
     */
    public static function jsonBody(): ?array
    {
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false || $raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /** IP کاربر. فقط REMOTE_ADDR مورد اعتماد است (هدرهای X-Forwarded-For قابل جعل‌اند). */
    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
    }
}
