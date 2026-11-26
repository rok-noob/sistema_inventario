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

    /**
     * Guarda el hash del token de recuperación y su fecha/hora de expiración.
     */
    public function guardarTokenRecuperacion(int $usuarioId, string $tokenHash, string $expira): bool
    {
        $sql = "UPDATE usuarios
                SET token_recuperacion = :token,
                    token_expira = :expira
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':token'  => $tokenHash,
            ':expira' => $expira,
            ':id'     => $usuarioId,
        ]);
    }

    /**
     * Busca un usuario activo cuyo token de recuperación coincida y no haya expirado.
     */
    public function buscarPorTokenValido(string $tokenHash): ?array
    {
        $sql = "SELECT id, nombre, email, activo
                FROM usuarios
                WHERE token_recuperacion = :token
                  AND token_expira IS NOT NULL
                  AND token_expira > :ahora
                  AND activo = 1
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':token' => $tokenHash,
            ':ahora' => date('Y-m-d H:i:s'),
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }

    /**
     * Actualiza la contraseña del usuario e invalida el token de un solo uso.
     */
    public function actualizarPasswordYLimpiarToken(int $usuarioId, string $passwordHash): bool
    {
        $sql = "UPDATE usuarios
                SET password_hash = :password_hash,
                    token_recuperacion = NULL,
                    token_expira = NULL
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id'            => $usuarioId,
        ]);
    }

    /**
     * Lista todos los usuarios junto con su rol asignado.
     */
    public function listarTodos(): array
    {
        $sql = "SELECT 
                    u.id,
                    u.nombre,
                    u.email,
                    u.rol_id,
                    u.activo,
                    u.created_at,
                    r.nombre AS rol_nombre
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                ORDER BY u.id ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un usuario por su ID (sin exponer el hash de contraseña).
     */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT id, nombre, email, rol_id, activo
                FROM usuarios
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    /**
     * Verifica si ya existe un usuario registrado con ese correo electrónico.
     */
    public function existeEmail(string $email, ?int $excluirId = null): bool
    {
        if ($excluirId !== null) {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => $email, ':id' => $excluirId]);
        } else {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => $email]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Inserta un nuevo usuario en la base de datos.
     */
    public function crear(string $nombre, string $email, string $passwordHash, int $rolId, int $activo = 1): int
    {
        $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo)
                VALUES (:nombre, :email, :password_hash, :rol_id, :activo)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'        => $nombre,
            ':email'         => $email,
            ':password_hash' => $passwordHash,
            ':rol_id'        => $rolId,
            ':activo'        => $activo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza los datos de un usuario y, opcionalmente, su contraseña.
     */
    public function actualizar(
        int $id,
        string $nombre,
        string $email,
        int $rolId,
        int $activo,
        ?string $nuevoPasswordHash = null
    ): bool {
        if ($nuevoPasswordHash !== null) {
            $sql = "UPDATE usuarios
                    SET nombre = :nombre,
                        email = :email,
                        rol_id = :rol_id,
                        activo = :activo,
                        password_hash = :password_hash
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':nombre'        => $nombre,
                ':email'         => $email,
                ':rol_id'        => $rolId,
                ':activo'        => $activo,
                ':password_hash' => $nuevoPasswordHash,
                ':id'            => $id,
            ]);
        }

        $sql = "UPDATE usuarios
                SET nombre = :nombre,
                    email = :email,
                    rol_id = :rol_id,
                    activo = :activo
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $nombre,
            ':email'  => $email,
            ':rol_id' => $rolId,
            ':activo' => $activo,
            ':id'     => $id,
        ]);
    }

    /**
     * Elimina un usuario por su ID.
     */
    public function eliminar(int $id): bool
    {
        $sql = "DELETE FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}