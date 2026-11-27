<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;
use Throwable;

class RolDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista todos los roles con el conteo de permisos y usuarios asignados.
     */
    public function listarTodos(): array
    {
        $sql = "SELECT 
                    r.id,
                    r.nombre,
                    r.descripcion,
                    r.created_at,
                    COUNT(DISTINCT rp.permiso_id) AS total_permisos,
                    COUNT(DISTINCT u.id) AS total_usuarios
                FROM roles r
                LEFT JOIN rol_permiso rp ON r.id = rp.rol_id
                LEFT JOIN usuarios u ON r.id = u.rol_id
                GROUP BY r.id, r.nombre, r.descripcion, r.created_at
                ORDER BY r.id ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el catálogo completo de permisos disponibles en el sistema.
     */
    public function listarPermisos(): array
    {
        $sql = "SELECT id, clave, descripcion
                FROM permisos
                ORDER BY clave ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un rol por ID junto con el arreglo de IDs de sus permisos asignados.
     */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT id, nombre, descripcion, created_at
                FROM roles
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rol) {
            return null;
        }

        $sqlPermisos = "SELECT permiso_id
                        FROM rol_permiso
                        WHERE rol_id = :rol_id";

        $stmtPerm = $this->db->prepare($sqlPermisos);
        $stmtPerm->execute([':rol_id' => $id]);
        $permisoIds = $stmtPerm->fetchAll(PDO::FETCH_COLUMN);

        $rol['permisos'] = array_map('intval', $permisoIds);

        return $rol;
    }

    /**
     * Verifica si ya existe un rol con el mismo nombre.
     */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        if ($excluirId !== null) {
            $sql = "SELECT COUNT(*) FROM roles WHERE nombre = :nombre AND id != :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':nombre' => $nombre, ':id' => $excluirId]);
        } else {
            $sql = "SELECT COUNT(*) FROM roles WHERE nombre = :nombre";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':nombre' => $nombre]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea un nuevo rol y asigna sus permisos dentro de una transacción.
     *
     * @param int[] $permisoIds
     */
    public function crear(string $nombre, ?string $descripcion, array $permisoIds): int
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO roles (nombre, descripcion)
                    VALUES (:nombre, :descripcion)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre'      => $nombre,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ]);

            $rolId = (int) $this->db->lastInsertId();

            $this->sincronizarPermisos($rolId, $permisoIds);

            $this->db->commit();
            return $rolId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza los datos de un rol y reemplaza sus permisos dentro de una transacción.
     *
     * @param int[] $permisoIds
     */
    public function actualizar(int $id, string $nombre, ?string $descripcion, array $permisoIds): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE roles
                    SET nombre = :nombre,
                        descripcion = :descripcion
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre'      => $nombre,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':id'          => $id,
            ]);

            $sqlDelete = "DELETE FROM rol_permiso WHERE rol_id = :rol_id";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute([':rol_id' => $id]);

            $this->sincronizarPermisos($id, $permisoIds);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cuenta cuántos usuarios tienen asignado este rol.
     */
    public function contarUsuariosAsignados(int $rolId): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE rol_id = :rol_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':rol_id' => $rolId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Elimina un rol y sus relaciones en rol_permiso.
     */
    public function eliminar(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            $stmtPerm = $this->db->prepare("DELETE FROM rol_permiso WHERE rol_id = :rol_id");
            $stmtPerm->execute([':rol_id' => $id]);

            $stmtRol = $this->db->prepare("DELETE FROM roles WHERE id = :id");
            $stmtRol->execute([':id' => $id]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inserta las relaciones en rol_permiso eliminando duplicados.
     *
     * @param int[] $permisoIds
     */
    private function sincronizarPermisos(int $rolId, array $permisoIds): void
    {
        $idsLimpios = array_values(array_unique(array_filter(
            array_map('intval', $permisoIds),
            static fn (int $pid): bool => $pid > 0
        )));

        if ($idsLimpios === []) {
            return;
        }

        $sql = "INSERT INTO rol_permiso (rol_id, permiso_id)
                VALUES (:rol_id, :permiso_id)";
        $stmt = $this->db->prepare($sql);

        foreach ($idsLimpios as $permisoId) {
            $stmt->execute([
                ':rol_id'     => $rolId,
                ':permiso_id' => $permisoId,
            ]);
        }
    }
}