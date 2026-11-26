<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\CategoriaDAO;
use App\Helpers\ResponseHelper;

class CategoriaController
{
    private CategoriaDAO $categoriaDao;

    public function __construct()
    {
        $this->categoriaDao = new CategoriaDAO();
    }

    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];

        require __DIR__ . '/../Views/categorias/index.php';
    }

    public function listar(): void
    {
        $categorias = $this->categoriaDao->listarTodos();
        ResponseHelper::success('Categorías obtenidas correctamente.', [
            'categorias' => $categorias,
        ]);
    }

    public function obtener(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            ResponseHelper::error('ID de categoría inválido.', 400);
        }

        $categoria = $this->categoriaDao->obtenerPorId($id);
        if ($categoria === null) {
            ResponseHelper::error('La categoría no existe.', 404);
        }

        ResponseHelper::success('Categoría encontrada.', [
            'categoria' => $categoria,
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
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $activo = isset($input['activo']) && (int) $input['activo'] === 0 ? 0 : 1;

        if ($nombre === '') {
            ResponseHelper::error('El nombre de la categoría es obligatorio.', 422);
        }

        if (mb_strlen($nombre) > 100) {
            ResponseHelper::error('El nombre no puede exceder los 100 caracteres.', 422);
        }

        if ($this->categoriaDao->existeNombre($nombre, $id > 0 ? $id : null)) {
            ResponseHelper::error('Ya existe otra categoría con ese nombre.', 409);
        }

        if ($id > 0) {
            $actual = $this->categoriaDao->obtenerPorId($id);
            if ($actual === null) {
                ResponseHelper::error('La categoría que intentas editar no existe.', 404);
            }

            $this->categoriaDao->actualizar($id, $nombre, $descripcion, $activo);
            ResponseHelper::success('Categoría actualizada correctamente.');
        }

        $nuevoId = $this->categoriaDao->crear($nombre, $descripcion, $activo);
        ResponseHelper::success('Categoría creada correctamente.', ['id' => $nuevoId], 201);
    }

    public function eliminar(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        $id = is_array($input) && isset($input['id']) ? (int) $input['id'] : 0;

        if ($id <= 0) {
            ResponseHelper::error('ID de categoría inválido.', 400);
        }

        $categoria = $this->categoriaDao->obtenerPorId($id);
        if ($categoria === null) {
            ResponseHelper::error('La categoría no existe.', 404);
        }

        $totalProductos = $this->categoriaDao->contarProductosAsociados($id);
        if ($totalProductos > 0) {
            ResponseHelper::error(
                "No se puede eliminar porque tiene {$totalProductos} producto(s) asociado(s). Edítala y márcala como inactiva.",
                409
            );
        }

        $this->categoriaDao->eliminar($id);
        ResponseHelper::success('Categoría eliminada correctamente.');
    }
}