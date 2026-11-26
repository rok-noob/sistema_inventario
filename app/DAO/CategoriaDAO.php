<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;

class CategoriaDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT c.id, c.nombre, c.descripcion, c.activo, c.created_at,
                       COUNT(p.id) AS total_productos
                FROM categorias c
                LEFT JOIN productos p ON p.categoria_id = c.id
                GROUP BY c.id, c.nombre, c.descripcion, c.activo, c.created_at
                ORDER BY c.nombre ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarActivas(): array
    {
        $sql = 'SELECT id, nombre
                FROM categorias
                WHERE activo = 1
                ORDER BY nombre ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT id, nombre, descripcion, activo, created_at
                FROM categorias
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categorias WHERE nombre = :nombre';
        $params = [':nombre' => $nombre];

        if ($excluirId !== null) {
            $sql .= ' AND id != :excluir_id';
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(string $nombre, ?string $descripcion, int $activo = 1): int
    {
        $sql = 'INSERT INTO categorias (nombre, descripcion, activo)
                VALUES (:nombre, :descripcion, :activo)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':activo'      => $activo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, string $nombre, ?string $descripcion, int $activo): bool
    {
        $sql = 'UPDATE categorias
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    activo = :activo
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':activo'      => $activo,
        ]);
    }

    public function contarProductosAsociados(int $id): int
    {
        $sql = 'SELECT COUNT(*) FROM productos WHERE categoria_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function eliminar(int $id): bool
    {
        $sql = 'DELETE FROM categorias WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }
}