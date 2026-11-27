<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;

class AlmacenDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT a.id, a.nombre, a.ubicacion, a.activo, a.created_at,
                       COUNT(DISTINCT i.producto_id) AS total_productos,
                       COALESCE(SUM(i.stock_actual), 0) AS total_unidades
                FROM almacenes a
                LEFT JOIN inventario i ON i.almacen_id = a.id
                GROUP BY a.id, a.nombre, a.ubicacion, a.activo, a.created_at
                ORDER BY a.nombre ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarActivos(): array
    {
        $sql = 'SELECT id, nombre, ubicacion
                FROM almacenes
                WHERE activo = 1
                ORDER BY nombre ASC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT id, nombre, ubicacion, activo, created_at
                FROM almacenes
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM almacenes WHERE nombre = :nombre';
        $params = [':nombre' => $nombre];

        if ($excluirId !== null) {
            $sql .= ' AND id != :excluir_id';
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(string $nombre, ?string $ubicacion, int $activo = 1): int
    {
        $sql = 'INSERT INTO almacenes (nombre, ubicacion, activo)
                VALUES (:nombre, :ubicacion, :activo)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'    => $nombre,
            ':ubicacion' => $ubicacion !== '' ? $ubicacion : null,
            ':activo'    => $activo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, string $nombre, ?string $ubicacion, int $activo): bool
    {
        $sql = 'UPDATE almacenes
                SET nombre = :nombre,
                    ubicacion = :ubicacion,
                    activo = :activo
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'        => $id,
            ':nombre'    => $nombre,
            ':ubicacion' => $ubicacion !== '' ? $ubicacion : null,
            ':activo'    => $activo,
        ]);
    }

    public function tieneRegistrosAsociados(int $id): bool
    {
        $stmtInv = $this->db->prepare('SELECT COUNT(*) FROM inventario WHERE almacen_id = :id');
        $stmtInv->execute([':id' => $id]);
        if ((int) $stmtInv->fetchColumn() > 0) {
            return true;
        }

        $stmtMov = $this->db->prepare(
            'SELECT COUNT(*) FROM movimientos WHERE almacen_origen_id = :id_origen OR almacen_destino_id = :id_destino'
        );
        $stmtMov->execute([
            ':id_origen'  => $id,
            ':id_destino' => $id,
        ]);

        return (int) $stmtMov->fetchColumn() > 0;
    }

    public function eliminar(int $id): bool
    {
        $sql = 'DELETE FROM almacenes WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }
}