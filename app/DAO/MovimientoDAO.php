<?php

declare(strict_types=1);

namespace App\DAO;

use App\Config\Database;
use PDO;
use RuntimeException;
use Throwable;

class MovimientoDAO
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarMovimientos(?string $tipo = null, ?int $almacenId = null, int $limite = 150): array
    {
        $sql = 'SELECT m.id, m.tipo, m.almacen_origen_id, m.almacen_destino_id,
                       ao.nombre AS almacen_origen_nombre,
                       ad.nombre AS almacen_destino_nombre,
                       m.usuario_id, u.nombre AS usuario_nombre,
                       m.observaciones, m.fecha,
                       COUNT(dm.id) AS total_partidas,
                       COALESCE(SUM(dm.cantidad), 0) AS total_unidades,
                       COALESCE(SUM(dm.cantidad * dm.costo_unitario), 0.00) AS monto_total
                FROM movimientos m
                INNER JOIN usuarios u ON u.id = m.usuario_id
                LEFT JOIN almacenes ao ON ao.id = m.almacen_origen_id
                LEFT JOIN almacenes ad ON ad.id = m.almacen_destino_id
                LEFT JOIN detalle_movimientos dm ON dm.movimiento_id = m.id
                WHERE 1 = 1';

        $params = [];

        if ($tipo !== null && in_array($tipo, ['ENTRADA', 'SALIDA', 'TRANSFERENCIA'], true)) {
            $sql .= ' AND m.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        if ($almacenId !== null && $almacenId > 0) {
            $sql .= ' AND (m.almacen_origen_id = :alm_origen OR m.almacen_destino_id = :alm_destino)';
            $params[':alm_origen']  = $almacenId;
            $params[':alm_destino'] = $almacenId;
        }

        $sql .= ' GROUP BY m.id, m.tipo, m.almacen_origen_id, m.almacen_destino_id,
                           ao.nombre, ad.nombre, m.usuario_id, u.nombre,
                           m.observaciones, m.fecha
                  ORDER BY m.fecha DESC, m.id DESC
                  LIMIT :limite';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, min($limite, 500)), PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $sqlHeader = 'SELECT m.id, m.tipo, m.almacen_origen_id, m.almacen_destino_id,
                             ao.nombre AS almacen_origen_nombre,
                             ad.nombre AS almacen_destino_nombre,
                             m.usuario_id, u.nombre AS usuario_nombre,
                             m.observaciones, m.fecha
                      FROM movimientos m
                      INNER JOIN usuarios u ON u.id = m.usuario_id
                      LEFT JOIN almacenes ao ON ao.id = m.almacen_origen_id
                      LEFT JOIN almacenes ad ON ad.id = m.almacen_destino_id
                      WHERE m.id = :id
                      LIMIT 1';

        $stmtHeader = $this->db->prepare($sqlHeader);
        $stmtHeader->execute([':id' => $id]);
        $movimiento = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if ($movimiento === false) {
            return null;
        }

        $sqlDetalle = 'SELECT dm.id, dm.producto_id, p.sku, p.nombre AS producto_nombre,
                              dm.cantidad, dm.costo_unitario,
                              (dm.cantidad * dm.costo_unitario) AS subtotal
                       FROM detalle_movimientos dm
                       INNER JOIN productos p ON p.id = dm.producto_id
                       WHERE dm.movimiento_id = :movimiento_id
                       ORDER BY dm.id ASC';

        $stmtDetalle = $this->db->prepare($sqlDetalle);
        $stmtDetalle->execute([':movimiento_id' => $id]);
        $movimiento['detalles'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

        return $movimiento;
    }

    public function obtenerStockPorAlmacen(int $almacenId): array
    {
        $sql = 'SELECT i.producto_id, i.stock_actual, p.sku, p.nombre, p.stock_minimo
                FROM inventario i
                INNER JOIN productos p ON p.id = i.producto_id
                WHERE i.almacen_id = :almacen_id
                  AND i.stock_actual > 0
                ORDER BY p.nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':almacen_id' => $almacenId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra un movimiento atómico (ENTRADA, SALIDA o TRANSFERENCIA) y actualiza inventario.
     *
     * @param array<int, array{producto_id: int, cantidad: int, costo_unitario?: float}> $items
     */
    public function registrarMovimiento(
        string $tipo,
        ?int $almacenOrigenId,
        ?int $almacenDestinoId,
        int $usuarioId,
        ?string $observaciones,
        array $items
    ): int {
        if (!in_array($tipo, ['ENTRADA', 'SALIDA', 'TRANSFERENCIA'], true)) {
            throw new RuntimeException('Tipo de movimiento no válido.');
        }

        if (empty($items)) {
            throw new RuntimeException('Debes agregar al menos un producto al movimiento.');
        }

        // Consolidar partidas si el mismo producto viene repetido en el arreglo
        $partidasConsolidadas = [];
        foreach ($items as $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            $cantidad   = (int) ($item['cantidad'] ?? 0);
            $costo      = isset($item['costo_unitario']) ? (float) $item['costo_unitario'] : null;

            if ($productoId <= 0 || $cantidad <= 0) {
                throw new RuntimeException('Todos los productos deben tener una cantidad mayor a cero.');
            }

            if ($costo !== null && $costo < 0) {
                throw new RuntimeException('El costo unitario no puede ser negativo.');
            }

            if (!isset($partidasConsolidadas[$productoId])) {
                $partidasConsolidadas[$productoId] = [
                    'producto_id'    => $productoId,
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo,
                ];
            } else {
                $partidasConsolidadas[$productoId]['cantidad'] += $cantidad;
                if ($costo !== null) {
                    $partidasConsolidadas[$productoId]['costo_unitario'] = $costo;
                }
            }
        }

        try {
            $this->db->beginTransaction();

            // 1. Insertar cabecera del movimiento
            $sqlMov = 'INSERT INTO movimientos (tipo, almacen_origen_id, almacen_destino_id, usuario_id, observaciones)
                       VALUES (:tipo, :origen, :destino, :usuario_id, :observaciones)';

            $stmtMov = $this->db->prepare($sqlMov);
            $stmtMov->execute([
                ':tipo'          => $tipo,
                ':origen'        => $almacenOrigenId,
                ':destino'       => $almacenDestinoId,
                ':usuario_id'    => $usuarioId,
                ':observaciones' => ($observaciones !== null && trim($observaciones) !== '') ? trim($observaciones) : null,
            ]);

            $movimientoId = (int) $this->db->lastInsertId();

            // Preparar sentencias reutilizables dentro de la transacción
            $stmtProducto = $this->db->prepare(
                'SELECT id, sku, nombre, precio_unitario, activo FROM productos WHERE id = :id LIMIT 1'
            );

            $stmtLockStock = $this->db->prepare(
                'SELECT stock_actual FROM inventario
                 WHERE producto_id = :producto_id AND almacen_id = :almacen_id
                 FOR UPDATE'
            );

            $stmtRestarStock = $this->db->prepare(
                'UPDATE inventario
                 SET stock_actual = stock_actual - :cantidad
                 WHERE producto_id = :producto_id AND almacen_id = :almacen_id'
            );

            $stmtSumarStock = $this->db->prepare(
                'INSERT INTO inventario (producto_id, almacen_id, stock_actual)
                 VALUES (:producto_id, :almacen_id, :cantidad_ins)
                 ON DUPLICATE KEY UPDATE stock_actual = stock_actual + :cantidad_upd'
            );

            $stmtDetalle = $this->db->prepare(
                'INSERT INTO detalle_movimientos (movimiento_id, producto_id, cantidad, costo_unitario)
                 VALUES (:movimiento_id, :producto_id, :cantidad, :costo_unitario)'
            );

            // 2. Procesar cada partida y aplicar reglas de stock
            foreach ($partidasConsolidadas as $partida) {
                $productoId = $partida['producto_id'];
                $cantidad   = $partida['cantidad'];

                $stmtProducto->execute([':id' => $productoId]);
                $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

                if ($producto === false) {
                    throw new RuntimeException("El producto con ID {$productoId} no existe.");
                }

                if ((int) $producto['activo'] !== 1) {
                    throw new RuntimeException("El producto {$producto['sku']} ({$producto['nombre']}) está inactivo.");
                }

                // En ENTRADA se respeta el costo capturado (si viene); en SALIDA/TRANSFERENCIA se usa precio_unitario
                $costoUnitario = ($tipo === 'ENTRADA' && $partida['costo_unitario'] !== null)
                    ? $partida['costo_unitario']
                    : (float) $producto['precio_unitario'];

                if ($tipo === 'ENTRADA') {
                    if ($almacenDestinoId === null || $almacenDestinoId <= 0) {
                        throw new RuntimeException('Debes seleccionar un almacén de destino para la entrada.');
                    }

                    $stmtSumarStock->execute([
                        ':producto_id'  => $productoId,
                        ':almacen_id'   => $almacenDestinoId,
                        ':cantidad_ins' => $cantidad,
                        ':cantidad_upd' => $cantidad,
                    ]);
                } elseif ($tipo === 'SALIDA') {
                    if ($almacenOrigenId === null || $almacenOrigenId <= 0) {
                        throw new RuntimeException('Debes seleccionar un almacén de origen para la salida.');
                    }

                    $stmtLockStock->execute([
                        ':producto_id' => $productoId,
                        ':almacen_id'  => $almacenOrigenId,
                    ]);
                    $stockRow = $stmtLockStock->fetch(PDO::FETCH_ASSOC);
                    $stockDisponible = $stockRow !== false ? (int) $stockRow['stock_actual'] : 0;

                    if ($stockDisponible < $cantidad) {
                        throw new RuntimeException(
                            "Stock insuficiente para [{$producto['sku']}] {$producto['nombre']}. Disponible: {$stockDisponible}, solicitado: {$cantidad}."
                        );
                    }

                    $stmtRestarStock->execute([
                        ':cantidad'    => $cantidad,
                        ':producto_id' => $productoId,
                        ':almacen_id'  => $almacenOrigenId,
                    ]);
                } elseif ($tipo === 'TRANSFERENCIA') {
                    if ($almacenOrigenId === null || $almacenOrigenId <= 0 || $almacenDestinoId === null || $almacenDestinoId <= 0) {
                        throw new RuntimeException('Debes seleccionar almacén de origen y almacén de destino.');
                    }

                    if ($almacenOrigenId === $almacenDestinoId) {
                        throw new RuntimeException('El almacén de origen y el de destino no pueden ser el mismo.');
                    }

                    $stmtLockStock->execute([
                        ':producto_id' => $productoId,
                        ':almacen_id'  => $almacenOrigenId,
                    ]);
                    $stockRow = $stmtLockStock->fetch(PDO::FETCH_ASSOC);
                    $stockDisponible = $stockRow !== false ? (int) $stockRow['stock_actual'] : 0;

                    if ($stockDisponible < $cantidad) {
                        throw new RuntimeException(
                            "Stock insuficiente en origen para [{$producto['sku']}] {$producto['nombre']}. Disponible: {$stockDisponible}, solicitado: {$cantidad}."
                        );
                    }

                    $stmtRestarStock->execute([
                        ':cantidad'    => $cantidad,
                        ':producto_id' => $productoId,
                        ':almacen_id'  => $almacenOrigenId,
                    ]);

                    $stmtSumarStock->execute([
                        ':producto_id'  => $productoId,
                        ':almacen_id'   => $almacenDestinoId,
                        ':cantidad_ins' => $cantidad,
                        ':cantidad_upd' => $cantidad,
                    ]);
                }

                // 3. Insertar detalle del movimiento
                $stmtDetalle->execute([
                    ':movimiento_id'  => $movimientoId,
                    ':producto_id'    => $productoId,
                    ':cantidad'       => $cantidad,
                    ':costo_unitario' => $costoUnitario,
                ]);
            }

            $this->db->commit();
            return $movimientoId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}