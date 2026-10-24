<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
app_start_session();
$cart=is_array($_SESSION['cart']??null)?$_SESSION['cart']:[];
$products=$cart?(new \App\Models\ProductRepository())->findByIds(array_map('intval',array_keys($cart))):[];
function h(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function toman(mixed $v): string { return number_format((int)$v).' تومان'; }
$total=0;
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>سبد خرید | بازار</title><link rel="stylesheet" href="css/shop.css"></head><body class="shop-home"><header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">ب</span><span>بازار</span></a><a href="index.php">ادامه خرید</a></header><main class="section"><h1>سبد خرید</h1><?php if(!$products): ?><div class="state-panel"><h2>سبد خرید خالی است</h2><a class="button button-primary" href="index.php">دیدن محصولات</a></div><?php else: ?><div class="product-grid"><?php foreach($products as $p): $qty=(int)($cart[(string)$p['id']]??0); $line=$qty*(int)$p['final_price']; $total+=$line; ?><article class="product-card"><?php if($p['image_url']): ?><img src="<?=h($p['image_url'])?>" alt="<?=h($p['name'])?>"><?php endif; ?><div class="product-info"><h3><?=h($p['name'])?></h3><p>تعداد: <?=$qty?></p><strong><?=h(toman($line))?></strong><a class="product-link" href="product.php?id=<?=(int)$p['id']?>">مشاهده محصول</a></div></article><?php endforeach; ?></div><h2>مجموع: <?=h(toman($total))?></h2><p>این نسخه سبد خرید را در نشست کاربر نگه می‌دارد؛ پرداخت آنلاین هنوز پیاده‌سازی نشده است.</p><?php endif; ?></main></body></html>