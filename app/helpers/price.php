<?php
// محاسبه قیمت نهایی - قیمت نهایی فقط با این تابع حساب می‌شود.

function final_price($base, $discount)
{
    if ($discount > 90) { $discount = 90; }
    if ($discount < 0)  { $discount = 0; }

    $price = $base - ($base * $discount / 100);
    return (int) (round($price / 1000) * 1000);   // گرد به نزدیک‌ترین هزار
}

function format_price($n)
{
    return number_format($n) . ' تومان';
}