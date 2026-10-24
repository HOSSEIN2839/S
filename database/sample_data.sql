-- Optional demonstration records only; these are not a full product import.
USE smart_shop;
START TRANSACTION;

INSERT INTO brands (source_brand_id, name, slug, source_url)
VALUES (234, 'هابلوت', 'hublot', 'https://zarifwatch.ir/brand/')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO categories (source_category_id, name, slug, source_url)
VALUES
  (119, '10 تا25 میلیون', '10-25million', 'https://zarifwatch.ir/product-category/10-25million/'),
  (211, 'بند چرم', 'leather-strap', 'https://zarifwatch.ir/product-category/style/leather-strap/'),
  (16, 'ساعت مچی', 'watch', 'https://zarifwatch.ir/product-category/watch/'),
  (18, 'ساعت مردانه', 'mens-watch', 'https://zarifwatch.ir/product-category/watch/mens-watch/')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products
(source_product_id, name, slug, product_type, short_description, regular_price, sale_price, current_price, currency_code, currency_symbol, on_sale, stock_status, is_purchasable, product_url, source_api_url)
VALUES
(6037, 'خرید ساعت هابلوت مردانه Classic Fusion سه موتوره با گارانتی', 'hublot-classic-fusion-mens-watch', 'simple',
 'مشخصات نمونه از داده ارسالی؛ متن کامل توضیحات در واردسازی واقعی حفظ شود.',
 14180000.00, 13650000.00, 13650000.00, 'IRT', 'تومان', 1, 'in_stock', 1,
 'https://zarifwatch.ir/product/hublot-classic-fusion-mens-watch/',
 'https://zarifwatch.ir/wp-json/wc/store/v1/products/6037')
ON DUPLICATE KEY UPDATE
 name = VALUES(name), current_price = VALUES(current_price), regular_price = VALUES(regular_price),
 sale_price = VALUES(sale_price), stock_status = VALUES(stock_status), product_url = VALUES(product_url);

INSERT INTO product_images (product_id, source_image_id, image_url, thumbnail_url, image_name, sort_order, is_primary)
SELECT p.id, 6002,
 'https://zarifwatch.ir/wp-content/uploads/2026/10/InShot_20260920_155912053-1.webp',
 'https://zarifwatch.ir/wp-content/uploads/2026/10/InShot_20260920_155912053-1-300x300.webp',
 'hublot-classic-fusion-mens-watch', 0, 1
FROM products p WHERE p.source_product_id = 6037
AND NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id AND i.source_image_id = 6002);

INSERT INTO product_categories (product_id, category_id)
SELECT p.id, c.id FROM products p JOIN categories c ON c.source_category_id IN (119,211,16,18)
WHERE p.source_product_id = 6037
ON DUPLICATE KEY UPDATE product_id = VALUES(product_id);

INSERT INTO product_attributes (product_id, attribute_name, attribute_value, sort_order)
SELECT p.id, a.attribute_name, a.attribute_value, a.sort_order
FROM products p
JOIN (
 SELECT 'مدل' AS attribute_name, 'Classic Fusion' AS attribute_value, 1 AS sort_order
 UNION ALL SELECT 'نوع موتور', 'کوارتز ژاپنی', 2
 UNION ALL SELECT 'قابلیت‌ها', 'سه موتوره، کرنوگراف، نمایش ۲۴ ساعته و تاریخ‌شمار', 3
 UNION ALL SELECT 'جنس بدنه', 'استیل ضد زنگ', 4
 UNION ALL SELECT 'جنس بند', 'چرم اصلی', 5
 UNION ALL SELECT 'جنس شیشه', 'معدنی', 6
 UNION ALL SELECT 'رنگ صفحه', 'مشکی', 7
 UNION ALL SELECT 'عرض قاب', '۴۲ میلی‌متر', 8
 UNION ALL SELECT 'مقاومت در برابر آب', '3ATM', 9
 UNION ALL SELECT 'گارانتی', 'یک سال', 10
) a
WHERE p.source_product_id = 6037
AND NOT EXISTS (
 SELECT 1 FROM product_attributes pa
 WHERE pa.product_id = p.id AND pa.attribute_name = a.attribute_name
);

COMMIT;
