<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;

class ProductoDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT p.id, p.sku, p.nombre, p.descripcion, p.categoria_id,
                       c.nombre AS categoria_nombre,
                       p.precio_unitario, p.stock_minimo, p.activo, p.created_at,
                       COALESCE(SUM(i.stock_actual), 0) AS stock_total
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN inventario i ON i.producto_id = p.id
                GROUP BY p.id, p.sku, p.nombre, p.descripcion, p.categoria_id,
                         c.nombre, p.precio_unitario, p.stock_minimo, p.activo, p.created_at
                ORDER BY p.nombre ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT id, sku, nombre, descripcion, categoria_id,
                       precio_unitario, stock_minimo, activo, created_at
                FROM productos
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function existeSku(string $sku, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM productos WHERE sku = :sku';
        $params = [':sku' => $sku];

        if ($excluirId !== null) {
            $sql .= ' AND id != :excluir_id';
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(
        string $sku,
        string $nombre,
        ?string $descripcion,
        int $categoriaId,
        float $precioUnitario,
        int $stockMinimo,
        int $activo = 1
    ): int {
        $sql = 'INSERT INTO productos (sku, nombre, descripcion, categoria_id, precio_unitario, stock_minimo, activo)
                VALUES (:sku, :nombre, :descripcion, :categoria_id, :precio_unitario, :stock_minimo, :activo)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sku'             => $sku,
            ':nombre'          => $nombre,
            ':descripcion'     => $descripcion !== '' ? $descripcion : null,
            ':categoria_id'    => $categoriaId,
            ':precio_unitario' => $precioUnitario,
            ':stock_minimo'    => $stockMinimo,
            ':activo'          => $activo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(
        int $id,
        string $sku,
        string $nombre,
        ?string $descripcion,
        int $categoriaId,
        float $precioUnitario,
        int $stockMinimo,
        int $activo
    ): bool {
        $sql = 'UPDATE productos
                SET sku = :sku,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    categoria_id = :categoria_id,
                    precio_unitario = :precio_unitario,
                    stock_minimo = :stock_minimo,
                    activo = :activo
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'              => $id,
            ':sku'             => $sku,
            ':nombre'          => $nombre,
            ':descripcion'     => $descripcion !== '' ? $descripcion : null,
            ':categoria_id'    => $categoriaId,
            ':precio_unitario' => $precioUnitario,
            ':stock_minimo'    => $stockMinimo,
            ':activo'          => $activo,
        ]);
    }

    public function tieneRegistrosAsociados(int $id): bool
    {
        $stmtInv = $this->db->prepare('SELECT COUNT(*) FROM inventario WHERE producto_id = :id');
        $stmtInv->execute([':id' => $id]);
        if ((int) $stmtInv->fetchColumn() > 0) {
            return true;
        }

        $stmtDet = $this->db->prepare('SELECT COUNT(*) FROM detalle_movimientos WHERE producto_id = :id');
        $stmtDet->execute([':id' => $id]);

        return (int) $stmtDet->fetchColumn() > 0;
    }

    public function eliminar(int $id): bool
    {
        $sql = 'DELETE FROM productos WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }
}