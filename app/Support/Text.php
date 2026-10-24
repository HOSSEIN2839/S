<?php
declare(strict_types=1);

namespace App\Support;

final class Text
{
    /** اعداد انگلیسی را فارسی می‌کند و جداکننده هزارگان فارسی می‌گذارد: ۴٬۵۰۰٬۰۰۰ */
    public static function priceText(int $amount): string
    {
        $formatted = number_format($amount, 0, '.', '٬');
        $map = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];
        return strtr($formatted, $map) . ' تومان';
    }

    /** ی و ک عربی را فارسی می‌کند و ارقام فارسی/عربی را انگلیسی (برای جستجو و الگوها). */
    public static function normalize(string $s): string
    {
        $s = strtr($s, ['ي' => 'ی', 'ك' => 'ک', 'ە' => 'ه']);
        $digits = ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                   '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                   '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                   '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];
        return strtr($s, $digits);
    }

    /** کاراکترهای خاص LIKE را بی‌اثر می‌کند. */
    public static function escapeLike(string $s): string
    {
        return strtr($s, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
    }

    public static function limit(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }
}
