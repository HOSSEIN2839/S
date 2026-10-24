<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/_bootstrap.php';
use App\Models\ProductRepository;
use App\Support\Db;
use App\Support\Config;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function cart_json(int $status,array $body): never { http_response_code($status); echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
try {
 app_start_session();
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); cart_json(405,['ok'=>false,'message'=>'فقط POST پذیرفته می‌شود.']); }
 $csrf=(string)($_SESSION['csrf']??'');
 $given=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if($csrf===''||$given===''||!hash_equals($csrf,$given)) cart_json(403,['ok'=>false,'message'=>'نشست معتبر نیست؛ صفحه را دوباره باز کنید.']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)) cart_json(400,['ok'=>false,'message'=>'درخواست نامعتبر است.']);
 $id=filter_var($input['product_id']??null,FILTER_VALIDATE_INT);
 $qty=filter_var($input['quantity']??1,FILTER_VALIDATE_INT);
 if(!$id||$qty===false||$qty<1||$qty>10) cart_json(422,['ok'=>false,'message'=>'شناسه یا تعداد محصول معتبر نیست.']);
 $items=(new ProductRepository())->findByIds([(int)$id]);
 if(!$items||empty($items[0]['in_stock'])) cart_json(404,['ok'=>false,'message'=>'این محصول موجود یا قابل خرید نیست.']);
 $p=$items[0]; $available=10;
 try { $q=Db::pdo()->prepare('SELECT stock_quantity FROM products WHERE id=:id'); $q->execute([':id'=>$id]); $stock=$q->fetchColumn(); if($stock!==false&&$stock!==null) $available=min(10,max(0,(int)$stock)); } catch(Throwable $ignored) {}
 if($available===0) cart_json(409,['ok'=>false,'message'=>'موجودی این محصول تمام شده است.']);
 $qty=min((int)$qty,$available);
 if(!isset($_SESSION['cart'])||!is_array($_SESSION['cart'])) $_SESSION['cart']=[];
 $current=(int)($_SESSION['cart'][(string)$id]??0);
 $_SESSION['cart'][(string)$id]=min($available,$current+$qty);
 cart_json(200,['ok'=>true,'message'=>'محصول به سبد خرید اضافه شد.','cart_count'=>array_sum($_SESSION['cart']),'product'=>['id'=>$p['id'],'name'=>$p['name'],'price'=>$p['final_price']]]);
} catch(Throwable $e) { error_log('[cart-add] '.$e->getMessage()); cart_json(500,['ok'=>false,'message'=>'ثبت سبد خرید انجام نشد.']); }
