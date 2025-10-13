<?php
declare(strict_types=1);

class CategoryRepository
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table = 'categories')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $sql = sprintf('SELECT * FROM %s ORDER BY name ASC', $this->table);
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE id = :id', $this->table));
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['slug'] ?? '')) ?: $this->slugify($name);
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        $sql = sprintf('INSERT INTO %s (name, slug, is_active) VALUES (:name, :slug, :is_active)', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        if (array_key_exists('name', $data)) { $fields[] = 'name = :name'; $params[':name'] = trim((string)$data['name']); }
        if (array_key_exists('slug', $data)) { $fields[] = 'slug = :slug'; $params[':slug'] = trim((string)$data['slug']); }
        if (array_key_exists('is_active', $data)) { $fields[] = 'is_active = :is_active'; $params[':is_active'] = (int)(bool)$data['is_active']; }

        if (empty($fields)) return false;

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $this->table, implode(', ', $fields));
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if ($v === null) $stmt->bindValue($k, null, PDO::PARAM_NULL);
            elseif (is_int($v)) $stmt->bindValue($k, $v, PDO::PARAM_INT);
            else $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        return $stmt->execute();
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare(sprintf('UPDATE %s SET is_active = 0 WHERE id = :id', $this->table));
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    private function slugify(string $s): string
    {
        $s = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s));
        $s = trim($s, '-');
        return $s ?: bin2hex(random_bytes(4));
    }
}
