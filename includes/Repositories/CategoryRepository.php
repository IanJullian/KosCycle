<?php
declare(strict_types=1);

class CategoryRepository
{
    public function active(): array
    {
        return db()->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
    }

    public function all(): array
    {
        return db()->query('SELECT id, name, status, created_at FROM categories ORDER BY id DESC')->fetchAll();
    }

    public function create(string $name): void
    {
        $stmt = db()->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);
    }

    public function update(int $id, string $name, string $status): void
    {
        $stmt = db()->prepare('UPDATE categories SET name = ?, status = ? WHERE id = ?');
        $stmt->execute([$name, $status, $id]);
    }
}
