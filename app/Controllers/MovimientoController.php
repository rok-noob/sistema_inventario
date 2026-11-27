<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\AlmacenDAO;
use App\DAO\MovimientoDAO;
use App\DAO\ProductoDAO;
use App\Helpers\ResponseHelper;
use RuntimeException;
use Throwable;

class MovimientoController
{
    private MovimientoDAO $movimientoDAO;
    private AlmacenDAO $almacenDAO;
    private ProductoDAO $productoDAO;

    public function __construct()
    {
        $this->movimientoDAO = new MovimientoDAO();
        $this->almacenDAO    = new AlmacenDAO();
        $this->productoDAO   = new ProductoDAO();
    }

    public function index(): void
    {
        $usuario   = $_SESSION['usuario'];
        $permisos  = $_SESSION['permisos'] ?? [];
        $almacenes = $this->almacenDAO->listarActivos();
        $productos = $this->productoDAO->listarActivos();

        require_once dirname(__DIR__) . '/Views/movimientos/index.php';
    }

    public function listar(): void
    {
        $tipo      = isset($_GET['tipo']) ? strtoupper(trim((string) $_GET['tipo'])) : null;
        $almacenId = isset($_GET['almacen_id']) ? (int) $_GET['almacen_id'] : null;

        if ($tipo === '') {
            $tipo = null;
        }

        $movimientos = $this->movimientoDAO->listarMovimientos($tipo, $almacenId);
        ResponseHelper::success('Historial de movimientos obtenido.', $movimientos);
    }

    public function obtener(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            ResponseHelper::error('ID de movimiento inválido.', 400);
        }

        $movimiento = $this->movimientoDAO->obtenerPorId($id);
        if ($movimiento === null) {
            ResponseHelper::error('El movimiento solicitado no existe.', 404);
        }

        ResponseHelper::success('Detalle del movimiento obtenido.', $movimiento);
    }

    public function stockAlmacen(): void
    {
        $almacenId = isset($_GET['almacen_id']) ? (int) $_GET['almacen_id'] : 0;

        if ($almacenId <= 0) {
            ResponseHelper::error('Selecciona un almacén válido.', 400);
        }

        $existencias = $this->movimientoDAO->obtenerStockPorAlmacen($almacenId);
        ResponseHelper::success('Existencias del almacén obtenidas.', $existencias);
    }

    public function registrar(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            ResponseHelper::error('Formato de datos JSON inválido.', 400);
        }

        $tipo             = strtoupper(trim((string) ($input['tipo'] ?? '')));
        $almacenOrigenId  = isset($input['almacen_origen_id']) && (int) $input['almacen_origen_id'] > 0
            ? (int) $input['almacen_origen_id']
            : null;
        $almacenDestinoId = isset($input['almacen_destino_id']) && (int) $input['almacen_destino_id'] > 0
            ? (int) $input['almacen_destino_id']
            : null;
        $observaciones    = isset($input['observaciones']) ? trim((string) $input['observaciones']) : null;
        $items            = $input['items'] ?? [];

        // 1. Verificar permiso RBAC granular según el tipo de movimiento
        $mapaPermisos = [
            'ENTRADA'       => 'movimientos.entrada',
            'SALIDA'        => 'movimientos.salida',
            'TRANSFERENCIA' => 'movimientos.transferencia',
        ];

        if (!isset($mapaPermisos[$tipo])) {
            ResponseHelper::error('El tipo de movimiento debe ser ENTRADA, SALIDA o TRANSFERENCIA.', 400);
        }

        $permisoRequerido = $mapaPermisos[$tipo];
        $permisosUsuario  = $_SESSION['permisos'] ?? [];

        if (!in_array($permisoRequerido, $permisosUsuario, true)) {
            ResponseHelper::error("Acceso denegado. No cuentas con el permiso: {$permisoRequerido}", 403);
        }

        // 2. Limpiar almacenes según la dirección del movimiento
        if ($tipo === 'ENTRADA') {
            $almacenOrigenId = null;
            if ($almacenDestinoId === null) {
                ResponseHelper::error('Selecciona el almacén de destino para la entrada.', 400);
            }
        } elseif ($tipo === 'SALIDA') {
            $almacenDestinoId = null;
            if ($almacenOrigenId === null) {
                ResponseHelper::error('Selecciona el almacén de origen para la salida.', 400);
            }
        } else {
            if ($almacenOrigenId === null || $almacenDestinoId === null) {
                ResponseHelper::error('Selecciona tanto el almacén de origen como el de destino.', 400);
            }
            if ($almacenOrigenId === $almacenDestinoId) {
                ResponseHelper::error('El almacén de origen y destino no pueden ser iguales.', 400);
            }
        }

        // 3. Validar almacenes activos
        if ($almacenOrigenId !== null) {
            $origen = $this->almacenDAO->obtenerPorId($almacenOrigenId);
            if ($origen === null || (int) $origen['activo'] !== 1) {
                ResponseHelper::error('El almacén de origen no existe o está inactivo.', 400);
            }
        }

        if ($almacenDestinoId !== null) {
            $destino = $this->almacenDAO->obtenerPorId($almacenDestinoId);
            if ($destino === null || (int) $destino['activo'] !== 1) {
                ResponseHelper::error('El almacén de destino no existe o está inactivo.', 400);
            }
        }

        if (!is_array($items) || count($items) === 0) {
            ResponseHelper::error('Agrega al menos un producto al detalle del movimiento.', 400);
        }

        $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);
        if ($usuarioId <= 0) {
            ResponseHelper::error('No se pudo identificar al usuario en sesión.', 401);
        }

        try {
            $movimientoId = $this->movimientoDAO->registrarMovimiento(
                $tipo,
                $almacenOrigenId,
                $almacenDestinoId,
                $usuarioId,
                $observaciones,
                $items
            );

            ResponseHelper::success(
                "Movimiento #{$movimientoId} ({$tipo}) registrado y stock actualizado correctamente.",
                ['id' => $movimientoId],
                201
            );
        } catch (RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::error('Error interno al procesar la transacción de inventario.', 500);
        }
    }
}