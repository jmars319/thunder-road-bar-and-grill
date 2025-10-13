<?php
declare(strict_types=1);

/**
 * Simple repository for menu items using PDO.
 *
 * Assumptions:
 * - Menu table default name: `menu`
 * - Categories table default name: `categories` with columns `id`, `name`, `slug`
 * - Menu table has columns: id, name, category_id, description, price, image_path, is_active
 */
class MenuRepository
{
    private PDO $pdo;
    private string $menuTable;
    private string $categoryTable;

    public function __construct(PDO $pdo, string $menuTable = 'menu', string $categoryTable = 'categories')
    {
        $this->pdo = $pdo;
        $this->menuTable = $menuTable;
        $this->categoryTable = $categoryTable;
    }

    /**
     * Return all active menu items joined with category name, ordered by category then name.
     * @return array<int, array<string,mixed>>
     */
    public function allActive(): array
    {
        $sql = sprintf(
            'SELECT m.*, c.name AS category_name, c.slug AS category_slug FROM %s m JOIN %s c ON m.category_id = c.id WHERE m.is_active = 1 ORDER BY c.name ASC, m.name ASC',
            $this->menuTable,
            $this->categoryTable
        );

        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return active items for a given category slug.
     * @param string $slug
     * @return array<int, array<string,mixed>>
     */
    public function byCategorySlug(string $slug): array
    {
        $sql = sprintf(
            'SELECT m.*, c.name AS category_name, c.slug AS category_slug FROM %s m JOIN %s c ON m.category_id = c.id WHERE c.slug = :slug AND m.is_active = 1 ORDER BY m.name ASC',
            $this->menuTable,
            $this->categoryTable
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', trim($slug), PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insert a menu item. $data accepts keys: name, category_id, description, price, image_path, is_active
     * Returns the inserted row as an associative array with `id` included.
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        // Validate and cast
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : 0;
        $description = array_key_exists('description', $data) && $data['description'] !== null ? trim((string)$data['description']) : null;
        $price = isset($data['price']) ? (float)$data['price'] : 0.0;
        $imagePath = array_key_exists('image_path', $data) && $data['image_path'] !== null ? trim((string)$data['image_path']) : null;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        $sql = sprintf(
            'INSERT INTO %s (name, category_id, description, price, image_path, is_active) VALUES (:name, :category_id, :description, :price, :image_path, :is_active)',
            $this->menuTable
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':price', $price);
        $stmt->bindValue(':image_path', $imagePath, $imagePath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->execute();

        $id = (int)$this->pdo->lastInsertId();

        return $this->fetchById($id) ?: ['id' => $id];
    }

    /**
     * Update menu item fields. Returns updated row as associative array or empty array if not found.
     * @param int $id
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function update(int $id, array $data): array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return [];
        }

        // Validate and cast (allow partial updates)
        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $data)) {
            $fields[] = 'name = :name';
            $params[':name'] = trim((string)$data['name']);
        }
        if (array_key_exists('category_id', $data)) {
            $fields[] = 'category_id = :category_id';
            $params[':category_id'] = (int)$data['category_id'];
        }
        if (array_key_exists('description', $data)) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'] === null ? null : trim((string)$data['description']);
        }
        if (array_key_exists('price', $data)) {
            $fields[] = 'price = :price';
            $params[':price'] = (float)$data['price'];
        }
        if (array_key_exists('image_path', $data)) {
            $fields[] = 'image_path = :image_path';
            $params[':image_path'] = $data['image_path'] === null ? null : trim((string)$data['image_path']);
        }
        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = :is_active';
            $params[':is_active'] = (int)(bool)$data['is_active'];
        }

        if (empty($fields)) {
            return $this->fetchById($id) ?: [];
        }

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $this->menuTable, implode(', ', $fields));
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            if ($v === null) {
                $stmt->bindValue($k, null, PDO::PARAM_NULL);
            } elseif (is_int($v)) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }

        $stmt->execute();

        return $this->fetchById($id) ?: [];
    }

    /**
     * Deactivate a menu item (set is_active = 0). Returns updated row or empty array.
     * @param int $id
     * @return array<string,mixed>
     */
    public function deactivate(int $id): array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return [];
        }

        $sql = sprintf('UPDATE %s SET is_active = 0 WHERE id = :id', $this->menuTable);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $this->fetchById($id) ?: [];
    }

    /**
     * Fetch a single menu row by id joined with category name.
     * @param int $id
     * @return array<string,mixed>|null
     */
    private function fetchById(int $id): ?array
    {
        $sql = sprintf(
            'SELECT m.*, c.name AS category_name, c.slug AS category_slug FROM %s m JOIN %s c ON m.category_id = c.id WHERE m.id = :id',
            $this->menuTable,
            $this->categoryTable
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Public accessor to fetch a menu item by id.
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        return $this->fetchById($id);
    }
}
