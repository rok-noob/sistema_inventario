<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\AlmacenDAO;
use App\Helpers\ResponseHelper;

class AlmacenController
{
    private AlmacenDAO $almacenDao;

    public function __construct()
    {
        $this->almacenDao = new AlmacenDAO();
    }

    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];

        require __DIR__ . '/../Views/almacenes/index.php';
    }

    public function listar(): void
    {
        $almacenes = $this->almacenDao->listarTodos();
        ResponseHelper::success('Almacenes obtenidos correctamente.', [
            'almacenes' => $almacenes,
        ]);
    }

    public function obtener(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            ResponseHelper::error('ID de almacén inválido.', 400);
        }

        $almacen = $this->almacenDao->obtenerPorId($id);
        if ($almacen === null) {
            ResponseHelper::error('El almacén no existe.', 404);
        }

        ResponseHelper::success('Almacén encontrado.', [
            'almacen' => $almacen,
        ]);
    }

    public function guardar(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            ResponseHelper::error('Formato de datos inválido.', 400);
        }

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $ubicacion = trim((string) ($input['ubicacion'] ?? ''));
        $activo = isset($input['activo']) && (int) $input['activo'] === 0 ? 0 : 1;

        if ($nombre === '') {
            ResponseHelper::error('El nombre del almacén es obligatorio.', 422);
        }

        if (mb_strlen($nombre) > 100) {
            ResponseHelper::error('El nombre no puede exceder los 100 caracteres.', 422);
        }

        if ($this->almacenDao->existeNombre($nombre, $id > 0 ? $id : null)) {
            ResponseHelper::error('Ya existe otro almacén con ese nombre.', 409);
        }

        if ($id > 0) {
            $actual = $this->almacenDao->obtenerPorId($id);
            if ($actual === null) {
                ResponseHelper::error('El almacén que intentas editar no existe.', 404);
            }

            $this->almacenDao->actualizar($id, $nombre, $ubicacion, $activo);
            ResponseHelper::success('Almacén actualizado correctamente.');
        }

        $nuevoId = $this->almacenDao->crear($nombre, $ubicacion, $activo);
        ResponseHelper::success('Almacén creado correctamente.', ['id' => $nuevoId], 201);
    }

    public function eliminar(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        $id = is_array($input) && isset($input['id']) ? (int) $input['id'] : 0;

        if ($id <= 0) {
            ResponseHelper::error('ID de almacén inválido.', 400);
        }

        $almacen = $this->almacenDao->obtenerPorId($id);
        if ($almacen === null) {
            ResponseHelper::error('El almacén no existe.', 404);
        }

        if ($this->almacenDao->tieneRegistrosAsociados($id)) {
            ResponseHelper::error(
                'No se puede eliminar este almacén porque tiene historial de inventario o movimientos. Desactívalo en su lugar.',
                409
            );
        }

        $this->almacenDao->eliminar($id);
        ResponseHelper::success('Almacén eliminado correctamente.');
    }
}