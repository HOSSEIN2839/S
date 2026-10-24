<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Db;
use App\Support\Text;
use PDO;

/**
 * Read-only repository for the canonical smart_shop WooCommerce schema.
 * Internal products.id is used for conversation/tool references; source_product_id
 * is retained only as the external WooCommerce identifier.
 */
final class ProductRepository
{
    private const SELECT = "
        SELECT
            p.id,
            p.source_product_id,
            p.name,
            COALESCE(b.name, '') AS brand,
            COALESCE(cats.category_name, '') AS category_name,
            p.current_price,
            p.regular_price,
            p.sale_price,
            p.stock_status,
            p.is_purchasable,
            COALESCE(p.short_description, p.description, '') AS description,
            COALESCE(attrs.features, '') AS features,
            COALESCE((SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1), '') AS image_url,
            COALESCE(p.product_url, '') AS product_url
        FROM products p
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN (
            SELECT pc.product_id, GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS category_name
            FROM product_categories pc
            INNER JOIN categories c ON c.id = pc.category_id
            GROUP BY pc.product_id
        ) cats ON cats.product_id = p.id
        LEFT JOIN (
            SELECT product_id, GROUP_CONCAT(CONCAT(attribute_name, ': ', attribute_value) ORDER BY sort_order SEPARATOR '; ') AS features
            FROM product_attributes
            GROUP BY product_id
        ) attrs ON attrs.product_id = p.id
    ";

    public function search(?string $query, ?string $category, ?int $maxPrice, ?string $brand, int $limit = 5): array
    {
        $where = ["p.stock_status = 'in_stock'", 'p.is_purchasable = 1', 'p.current_price IS NOT NULL', 'p.current_price >= 0'];
        $params = [];

        if ($category !== null && trim($category) !== '') {
            $where[] = 'cats.category_name LIKE :category';
            $params[':category'] = '%' . Text::escapeLike(trim($category)) . '%';
        }
        if ($brand !== null && trim($brand) !== '') {
            $where[] = 'b.name LIKE :brand';
            $params[':brand'] = '%' . Text::escapeLike(trim($brand)) . '%';
        }
        if ($maxPrice !== null && $maxPrice > 0) {
            $where[] = 'p.current_price <= :max_price';
            $params[':max_price'] = $maxPrice;
        }

        $tokens = [];
        if ($query !== null && trim($query) !== '') {
            $tokens = array_slice(array_values(array_filter(
                preg_split('/\s+/u', trim(Text::normalize($query))) ?: [],
                static fn(string $token): bool => mb_strlen($token) >= 2
            )), 0, 5);
            foreach ($tokens as $i => $token) {
                $like = '%' . Text::escapeLike($token) . '%';
                $keys = [];
                foreach (['name', 'short', 'description', 'brand', 'category', 'attribute'] as $column) {
                    $keys[$column] = ':q' . $i . '_' . $column;
                    $params[$keys[$column]] = $like;
                }
                $where[] = "(p.name LIKE {$keys['name']} OR p.short_description LIKE {$keys['short']} OR p.description LIKE {$keys['description']} OR b.name LIKE {$keys['brand']} OR cats.category_name LIKE {$keys['category']} OR EXISTS (SELECT 1 FROM product_attributes pa WHERE pa.product_id = p.id AND pa.attribute_value LIKE {$keys['attribute']}))";
            }
        }

        $sql = self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.current_price ASC, p.id ASC LIMIT ' . max(1, min(5, $limit));
        $stmt = Db::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return array_map(fn(array $row): array => $this->map($row), $stmt->fetchAll());
    }

    /** @param int[] $ids */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
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
            $product = $this->map($row);
            $byId[$product['id']] = $product;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    private function map(array $row): array
    {
        $price = (int) round((float) $row['current_price']);
        return [
            'id' => (int) $row['id'],
            'source_product_id' => (int) $row['source_product_id'],
            'name' => (string) $row['name'],
            'brand' => (string) $row['brand'],
            'category' => (string) $row['category_name'],
            'base_price' => $price,
            'discount_percent' => 0,
            'final_price' => $price,
            'in_stock' => $row['stock_status'] === 'in_stock' && (int) $row['is_purchasable'] === 1,
            'description' => (string) $row['description'],
            'features' => (string) $row['features'],
            'image_url' => (string) $row['image_url'],
            'product_url' => (string) $row['product_url'],
        ];
    }
}
