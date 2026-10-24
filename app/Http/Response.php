<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function error(string $code, string $message, int $status): void
    {
        self::json(['ok' => false, 'error' => $code, 'message' => $message], $status);
    }
}
