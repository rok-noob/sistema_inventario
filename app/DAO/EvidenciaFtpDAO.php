<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;

class EvidenciaFtpDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodas(?int $movimientoId = null, int $limite = 200): array
    {
        $sql = 'SELECT e.id, e.movimiento_id, e.nombre_archivo, e.ruta_remota,
                       e.tamano_bytes, e.usuario_id, e.fecha_subida,
                       m.tipo AS movimiento_tipo, m.fecha AS movimiento_fecha,
                       u.nombre AS usuario_nombre
                FROM evidencias_ftp e
                INNER JOIN movimientos m ON m.id = e.movimiento_id
                INNER JOIN usuarios u ON u.id = e.usuario_id
                WHERE 1 = 1';

        $params = [];

        if ($movimientoId !== null && $movimientoId > 0) {
            $sql .= ' AND e.movimiento_id = :movimiento_id';
            $params[':movimiento_id'] = $movimientoId;
        }

        $sql .= ' ORDER BY e.fecha_subida DESC, e.id DESC
                  LIMIT :limite';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', max(1, min($limite, 500)), PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT e.id, e.movimiento_id, e.nombre_archivo, e.ruta_remota,
                       e.tamano_bytes, e.usuario_id, e.fecha_subida,
                       m.tipo AS movimiento_tipo,
                       u.nombre AS usuario_nombre
                FROM evidencias_ftp e
                INNER JOIN movimientos m ON m.id = e.movimiento_id
                INNER JOIN usuarios u ON u.id = e.usuario_id
                WHERE e.id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function obtenerPorRutaRemota(string $rutaRemota): ?array
    {
        $sql = 'SELECT id, movimiento_id, nombre_archivo, ruta_remota,
                       tamano_bytes, usuario_id, fecha_subida
                FROM evidencias_ftp
                WHERE ruta_remota = :ruta_remota
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':ruta_remota' => $rutaRemota]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function registrar(
        int $movimientoId,
        string $nombreArchivo,
        string $rutaRemota,
        int $tamanoBytes,
        int $usuarioId
    ): int {
        $sql = 'INSERT INTO evidencias_ftp (movimiento_id, nombre_archivo, ruta_remota, tamano_bytes, usuario_id)
                VALUES (:movimiento_id, :nombre_archivo, :ruta_remota, :tamano_bytes, :usuario_id)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':movimiento_id'  => $movimientoId,
            ':nombre_archivo' => $nombreArchivo,
            ':ruta_remota'    => $rutaRemota,
            ':tamano_bytes'   => max(0, $tamanoBytes),
            ':usuario_id'     => $usuarioId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        $sql  = 'DELETE FROM evidencias_ftp WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function eliminarPorRutaRemota(string $rutaRemota): bool
    {
        $sql  = 'DELETE FROM evidencias_ftp WHERE ruta_remota = :ruta_remota';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':ruta_remota' => $rutaRemota]);

        return $stmt->rowCount() > 0;
    }
}