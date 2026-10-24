<?php
declare(strict_types=1);

namespace App\Models;

/**
 * تنها جایی که «قیمت نهایی» حساب می‌شود (تسک تابع قیمت، B2).
 * قیمت نهایی در دیتابیس ذخیره نمی‌شود و هوش مصنوعی هرگز آن را حساب نمی‌کند.
 * فرمول RFP: پایه − پایه×تخفیف÷۱۰۰ ، گرد به نزدیک‌ترین هزار ، تخفیف بالای ۹۰ برابر ۹۰ و کمتر از ۰ برابر ۰.
 * فرض: base_price عدد صحیح به تومان است (واحد را D3 با سایت چک می‌کند).
 */
final class PriceCalculator
{
    public static function finalPrice(int $basePrice, int $discountPercent): int
    {
        $discount = max(0, min(90, $discountPercent));
        $base = max(0, $basePrice);
        $raw = $base - ($base * $discount / 100);
        return (int) (round($raw / 1000) * 1000);
    }
}
