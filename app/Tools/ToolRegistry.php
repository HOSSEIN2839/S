<?php
declare(strict_types=1);

namespace App\Tools;

use App\Models\ProductRepository;
use App\Support\Text;

/**
 * فهرست ابزارهایی که هوش مصنوعی اجازه صدا زدنشان را دارد (Allowlist) و اجرای امن آن‌ها.
 * همه ابزارها فقط می‌خوانند. قیمت، عکس و لینک را همیشه سرور از دیتابیس می‌سازد.
 */
class ToolRegistry
{
    private ProductRepository $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    /** تعریف ابزارها برای مدل (قالب OpenAI tools). */
    public function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'جستجوی ساعت‌های موجود فروشگاه. فقط محصولات موجود را برمی‌گرداند (حداکثر ۵).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query'     => ['type' => 'string', 'description' => 'کلمه‌های جستجو (اختیاری)'],
                            'category'  => ['type' => 'string', 'description' => 'دسته: مردانه، زنانه یا رومیزی (اختیاری)'],
                            'max_price' => ['type' => 'integer', 'description' => 'حداکثر قیمت نهایی به تومان (اختیاری)'],
                            'brand'     => ['type' => 'string', 'description' => 'برند (اختیاری)'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'compare_products',
                    'description' => 'مقایسه ۲ تا ۴ محصول با شناسه‌هایی که قبلاً از search_products گرفته شده.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                                'description' => 'فهرست ۲ تا ۴ شناسه محصول',
                            ],
                        ],
                        'required' => ['product_ids'],
                    ],
                ],
            ],
        ];
    }

    /**
     * ابزار خواسته‌شده را اجرا می‌کند. نام ناشناخته یا آرگومان خراب فقط یک خطای مؤدبانه می‌دهد.
     * @return array{payload:array<string,mixed>,product_ids:int[]}
     */
    public function run(string $name, array $args): array
    {
        try {
            return match ($name) {
                'search_products'  => $this->search($args),
                'compare_products' => $this->compare($args),
                default            => ['payload' => ['error' => 'ابزار ناشناخته است.'], 'product_ids' => []],
            };
        } catch (\Throwable $e) {
            \App\Support\Logger::error('tool_failed', ['tool' => $name, 'error' => $e->getMessage()]);
            return ['payload' => ['error' => 'خطا در خواندن محصولات.'], 'product_ids' => []];
        }
    }

    /** @return array{payload:array<string,mixed>,product_ids:int[]} */
    private function search(array $args): array
    {
        $query = isset($args['query']) && is_string($args['query']) ? mb_substr(trim($args['query']), 0, 80) : null;
        $category = isset($args['category']) && is_string($args['category']) ? mb_substr(trim($args['category']), 0, 40) : null;
        $brand = isset($args['brand']) && is_string($args['brand']) ? mb_substr(trim($args['brand']), 0, 40) : null;
        $maxPrice = null;
        if (isset($args['max_price']) && is_numeric($args['max_price'])) {
            $maxPrice = max(0, min(1000000000, (int) $args['max_price']));
        }

        $found = $this->products->search($query, $category, $maxPrice, $brand, 5);
        if ($found === []) {
            return ['payload' => ['products' => [], 'message' => 'محصول مناسبی پیدا نشد.'], 'product_ids' => []];
        }
        return [
            'payload'     => ['products' => array_map([$this, 'forModel'], $found)],
            'product_ids' => array_map(static fn(array $p): int => $p['id'], $found),
        ];
    }

    /** @return array{payload:array<string,mixed>,product_ids:int[]} */
    private function compare(array $args): array
    {
        $ids = $args['product_ids'] ?? null;
        if (!is_array($ids)) {
            return ['payload' => ['error' => 'فهرست شناسه محصولات لازم است.'], 'product_ids' => []];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) < 2 || count($ids) > 4) {
            return ['payload' => ['error' => 'برای مقایسه ۲ تا ۴ محصول لازم است.'], 'product_ids' => []];
        }
        $found = $this->products->findByIds($ids);
        if (count($found) < 2) {
            return ['payload' => ['error' => 'حداقل دو محصول معتبر پیدا نشد.'], 'product_ids' => []];
        }
        return [
            'payload'     => ['products' => array_map([$this, 'forModel'], $found)],
            'product_ids' => array_map(static fn(array $p): int => $p['id'], $found),
        ];
    }

    /** فقط داده‌ای که مدل برای جواب لازم دارد؛ بدون عکس و لینک. */
    private function forModel(array $p): array
    {
        return [
            'id'         => $p['id'],
            'name'       => $p['name'],
            'brand'      => $p['brand'],
            'category'   => $p['category'],
            'price_text' => Text::priceText($p['final_price']),
            'in_stock'   => $p['in_stock'],
            'features'   => Text::limit($p['features'], 300),
        ];
    }

    /**
     * کارت محصول برای مرورگر (قرارداد با F): قیمت، عکس و لینک از دیتابیس می‌آید، نه از مدل.
     * اگر نام محصولی در جواب آمده باشد فقط همان‌ها؛ وگرنه حداکثر ۳ محصول اول.
     * @param int[] $candidateIds
     * @return array<int,array{id:int,name:string,price_text:string,image_url:string,product_url:string}>
     */
    public function cards(array $candidateIds, string $reply): array
    {
        $ids = array_slice(array_values(array_unique($candidateIds)), 0, 8);
        if ($ids === []) {
            return [];
        }
        $items = $this->products->findByIds($ids);
        $mentioned = [];
        foreach ($items as $p) {
            if ($p['name'] !== '' && mb_stripos($reply, $p['name']) !== false) {
                $mentioned[] = $p;
            }
        }
        $chosen = $mentioned !== [] ? $mentioned : array_slice($items, 0, 3);

        $cards = [];
        foreach (array_slice($chosen, 0, 4) as $p) {
            $cards[] = [
                'id'          => $p['id'],
                'name'        => $p['name'],
                'price_text'  => Text::priceText($p['final_price']),
                'image_url'   => $this->safeUrl($p['image_url']),
                'product_url' => $this->safeUrl($p['product_url']),
            ];
        }
        return $cards;
    }

    private function safeUrl(string $url): string
    {
        return preg_match('~^https://~i', $url) === 1 ? $url : '';
    }
}
