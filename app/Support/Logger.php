<?php
declare(strict_types=1);

namespace App\Support;

final class Logger
{
    private const SECRET_KEYS = ['api_key', 'password', 'authorization', 'canary', 'ip_salt', 'token', 'csrf'];

    /**
     * یک خط JSON در logs/app.log می‌نویسد. هیچ‌وقت کلید، رمز یا متن کامل پرامپت را ننویسید.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        try {
            $cfg = Config::get('app');
            $file = (string) ($cfg['log_file'] ?? (BASE_PATH . '/logs/app.log'));
            foreach ($context as $k => $v) {
                if (in_array(strtolower((string) $k), self::SECRET_KEYS, true)) {
                    $context[$k] = '***';
                }
            }
            $line = json_encode([
                'time'    => date('Y-m-d H:i:s'),
                'level'   => $level,
                'message' => $message,
                'context' => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line !== false) {
                @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $e) {
            // لاگ نباید برنامه را از کار بیندازد
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::log('security', $message, $context);
    }
}
