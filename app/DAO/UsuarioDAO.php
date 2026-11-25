<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;

class UsuarioDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca un usuario por su correo electrónico junto con el nombre de su rol.
     */
    public function buscarPorEmail(string $email): ?array
    {
        $sql = "SELECT 
                    u.id,
                    u.nombre,
                    u.email,
                    u.password_hash,
                    u.rol_id,
                    u.activo,
                    r.nombre AS rol_nombre
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.email = :email
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    /**
     * Obtiene el arreglo plano de claves de permisos asignados a un rol (ej. ['catalogos.ver', ...]).
     */
    public function obtenerPermisosPorRol(int $rolId): array
    {
        $sql = "SELECT p.clave
                FROM permisos p
                INNER JOIN rol_permiso rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = :rol_id
                ORDER BY p.clave ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':rol_id' => $rolId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}