<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\CategoriaDAO;
use App\DAO\ProductoDAO;
use App\Helpers\ResponseHelper;

class ProductoController
{
    private ProductoDAO $productoDao;
    private CategoriaDAO $categoriaDao;

    public function __construct()
    {
        $this->productoDao = new ProductoDAO();
        $this->categoriaDao = new CategoriaDAO();
    }

    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];
        $categorias = $this->categoriaDao->listarActivas();

        require __DIR__ . '/../Views/productos/index.php';
    }

    public function listar(): void
    {
        $productos = $this->productoDao->listarTodos();
        ResponseHelper::success('Productos obtenidos correctamente.', [
            'productos' => $productos,
        ]);
    }

    public function obtener(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            ResponseHelper::error('ID de producto inválido.', 400);
        }

        $producto = $this->productoDao->obtenerPorId($id);
        if ($producto === null) {
            ResponseHelper::error('El producto no existe.', 404);
        }

        ResponseHelper::success('Producto encontrado.', [
            'producto' => $producto,
        ]);
    }

    public function guardar(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            ResponseHelper::error('Formato de datos inválido.', 400);
        }

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $categoriaId = isset($input['categoria_id']) ? (int) $input['categoria_id'] : 0;
        $precioUnitario = isset($input['precio_unitario']) ? (float) $input['precio_unitario'] : -1.0;
        $stockMinimo = isset($input['stock_minimo']) ? (int) $input['stock_minimo'] : 5;
        $activo = isset($input['activo']) && (int) $input['activo'] === 0 ? 0 : 1;

        if ($sku === '' || $nombre === '') {
            ResponseHelper::error('El SKU y el nombre del producto son obligatorios.', 422);
        }

        if (mb_strlen($sku) > 50 || mb_strlen($nombre) > 150) {
            ResponseHelper::error('El SKU (máx. 50) o el nombre (máx. 150) exceden la longitud permitida.', 422);
        }

        if ($categoriaId <= 0 || $this->categoriaDao->obtenerPorId($categoriaId) === null) {
            ResponseHelper::error('Selecciona una categoría válida.', 422);
        }

        if ($precioUnitario < 0 || $stockMinimo < 0) {
            ResponseHelper::error('El precio unitario y el stock mínimo no pueden ser negativos.', 422);
        }

        if ($this->productoDao->existeSku($sku, $id > 0 ? $id : null)) {
            ResponseHelper::error("El SKU '{$sku}' ya está registrado en otro producto.", 409);
        }

        if ($id > 0) {
            $actual = $this->productoDao->obtenerPorId($id);
            if ($actual === null) {
                ResponseHelper::error('El producto que intentas editar no existe.', 404);
            }

            $this->productoDao->actualizar(
                $id,
                $sku,
                $nombre,
                $descripcion,
                $categoriaId,
                $precioUnitario,
                $stockMinimo,
                $activo
            );
            ResponseHelper::success('Producto actualizado correctamente.');
        }

        $nuevoId = $this->productoDao->crear(
            $sku,
            $nombre,
            $descripcion,
            $categoriaId,
            $precioUnitario,
            $stockMinimo,
            $activo
        );
        ResponseHelper::success('Producto creado correctamente.', ['id' => $nuevoId], 201);
    }

    public function eliminar(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        $id = is_array($input) && isset($input['id']) ? (int) $input['id'] : 0;

        if ($id <= 0) {
            ResponseHelper::error('ID de producto inválido.', 400);
        }

        $producto = $this->productoDao->obtenerPorId($id);
        if ($producto === null) {
            ResponseHelper::error('El producto no existe.', 404);
        }

        if ($this->productoDao->tieneRegistrosAsociados($id)) {
            ResponseHelper::error(
                'No se puede eliminar este producto porque ya tiene existencias o movimientos registrados. Desactívalo en su lugar.',
                409
            );
        }

        $this->productoDao->eliminar($id);
        ResponseHelper::success('Producto eliminado correctamente.');
    }
}