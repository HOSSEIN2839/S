<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Config
{
    /** @var array<string,array> */
    private static array $cache = [];

    /**
     * فایل config/{name}.php را می‌خواند. name: app | ai | secrets | database
     */
    public static function get(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $file = BASE_PATH . '/config/' . $name . '.php';
        if (!is_file($file)) {
            throw new RuntimeException(
                "فایل تنظیمات config/{$name}.php پیدا نشد. نسخه example آن را کپی کنید."
            );
        }
        $data = require $file;
        if (!is_array($data)) {
            throw new RuntimeException("فایل config/{$name}.php باید یک آرایه برگرداند.");
        }
        return self::$cache[$name] = $data;
    }

    public static function secret(string $key, string $default = ''): string
    {
        $s = self::get('secrets');
        return isset($s[$key]) ? (string) $s[$key] : $default;
    }
}
