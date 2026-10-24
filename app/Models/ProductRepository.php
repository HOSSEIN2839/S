<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Db;
use App\Support\Text;
use PDO;

/**
 * خواندن محصولات از دیتابیس. این کلاس فقط SELECT می‌زند؛ دستیار هرگز چیزی در دیتابیس نمی‌نویسد.
 * همه ورودی‌ها با Prepared Statement به SQL می‌روند (بدون جمع‌کردن رشته).
 */
class ProductRepository
{
    private const SELECT = 'SELECT p.id, p.woo_id, p.name, p.brand, p.category_id, p.base_price, p.discount_percent,
                                   p.in_stock, p.description, p.features, p.image, p.product_url, c.name AS category_name
                            FROM products p LEFT JOIN categories c ON c.id = p.category_id';

    /**
     * @return array<int,array<string,mixed>> حداکثر $limit محصول موجود با قیمت نهایی
     */
    public function search(?string $query, ?string $category, ?int $maxPrice, ?string $brand, int $limit = 5): array
    {
        $where = ['p.in_stock = 1'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'c.name = :category';
            $params[':category'] = $category;
        }
        if ($brand !== null && $brand !== '') {
            $where[] = 'p.brand LIKE :brand';
            $params[':brand'] = '%' . Text::escapeLike($brand) . '%';
        }

        $tokens = [];
        if ($query !== null && trim($query) !== '') {
            $tokens = array_slice(array_values(array_filter(
                preg_split('/\s+/u', trim($query)) ?: [],
                static fn(string $t): bool => mb_strlen($t) >= 2
            )), 0, 4);
            if ($tokens !== []) {
                // با PDO در حالت native هر نام پارامتر فقط یک بار می‌تواند در SQL بیاید؛ پس برای هر ستون یک نام جدا
                $parts = [];
                foreach ($tokens as $i => $token) {
                    $like = '%' . Text::escapeLike($token) . '%';
                    $keys = [];
                    foreach (['n', 'b', 'd', 'f'] as $col) {
                        $keys[$col] = ':q' . $i . $col;
                        $params[$keys[$col]] = $like;
                    }
                    $parts[] = '(p.name LIKE ' . $keys['n'] . ' OR p.brand LIKE ' . $keys['b']
                        . ' OR p.description LIKE ' . $keys['d'] . ' OR p.features LIKE ' . $keys['f'] . ')';
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        $sql = self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id ASC LIMIT 200';
        $stmt = Db::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $p = $this->map($row);
            if ($maxPrice !== null && $maxPrice > 0 && $p['final_price'] > $maxPrice) {
                continue;
            }
            $p['_score'] = $this->score($p, $tokens);
            $items[] = $p;
        }

        // اول بیشترین تطابق با کلمات؛ بعد اگر بودجه گفته شده، نزدیک‌ترین به بودجه؛ وگرنه ارزان‌تر اول
        $budget = ($maxPrice !== null && $maxPrice > 0);
        usort($items, static function (array $a, array $b) use ($budget): int {
            if ($a['_score'] !== $b['_score']) {
                return $b['_score'] <=> $a['_score'];
            }
            return $budget ? ($b['final_price'] <=> $a['final_price']) : ($a['final_price'] <=> $b['final_price']);
        });

        $items = array_slice($items, 0, max(1, $limit));
        foreach ($items as &$it) {
            unset($it['_score']);
        }
        unset($it);
        return $items;
    }

    /**
     * @param int[] $ids
     * @return array<int,array<string,mixed>> به ترتیب ids (فقط محصولات موجود در دیتابیس)
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Db::pdo()->prepare(self::SELECT . " WHERE p.id IN ($placeholders)");
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $p = $this->map($row);
            $byId[$p['id']] = $p;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): array
    {
        $base = (int) $row['base_price'];
        $disc = (int) $row['discount_percent'];
        return [
            'id'               => (int) $row['id'],
            'name'             => (string) $row['name'],
            'brand'            => (string) ($row['brand'] ?? ''),
            'category'         => (string) ($row['category_name'] ?? ''),
            'base_price'       => $base,
            'discount_percent' => $disc,
            'final_price'      => PriceCalculator::finalPrice($base, $disc),
            'in_stock'         => (int) $row['in_stock'] === 1,
            'description'      => (string) ($row['description'] ?? ''),
            'features'         => (string) ($row['features'] ?? ''),
            'image_url'        => (string) ($row['image'] ?? ''),
            'product_url'      => (string) ($row['product_url'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $p @param string[] $tokens */
    private function score(array $p, array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }
        $hay = mb_strtolower($p['name'] . ' ' . $p['brand'] . ' ' . $p['description'] . ' ' . $p['features']);
        $score = 0;
        foreach ($tokens as $t) {
            if (mb_strpos($hay, mb_strtolower($t)) !== false) {
                $score++;
            }
        }
        return $score;
    }
}
