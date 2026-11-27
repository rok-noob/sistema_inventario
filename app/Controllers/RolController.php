<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\RolDAO;
use App\DAO\UsuarioDAO;
use App\Helpers\ResponseHelper;
use Throwable;

class RolController
{
    private RolDAO $rolDao;
    private UsuarioDAO $usuarioDao;

    public function __construct()
    {
        $this->rolDao = new RolDAO();
        $this->usuarioDao = new UsuarioDAO();
    }

    /**
     * Renderiza la vista de administración de roles y permisos.
     */
    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];
        $catalogoPermisos = $this->rolDao->listarPermisos();

        require __DIR__ . '/../Views/roles/index.php';
    }

    /**
     * Devuelve la lista de roles en JSON para Fetch.
     */
    public function listar(): void
    {
        $roles = $this->rolDao->listarTodos();
        ResponseHelper::success('Roles cargados correctamente.', ['roles' => $roles]);
    }

    /**
     * Devuelve los datos de un rol específico y los IDs de sus permisos.
     */
    public function obtener(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            ResponseHelper::error('ID de rol no válido.', 400);
        }

        $rol = $this->rolDao->obtenerPorId($id);
        if ($rol === null) {
            ResponseHelper::error('El rol solicitado no existe.', 404);
        }

        ResponseHelper::success('Rol encontrado.', ['rol' => $rol]);
    }

    /**
     * Crea o actualiza un rol y sincroniza sus permisos.
     */
    public function guardar(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $id = (int) ($input['id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $permisosRaw = is_array($input['permisos'] ?? null) ? $input['permisos'] : [];
        $permisoIds = array_map('intval', $permisosRaw);

        if ($nombre === '') {
            ResponseHelper::error('El nombre del rol es obligatorio.', 400);
        }

        if (mb_strlen($nombre) > 50) {
            ResponseHelper::error('El nombre del rol no puede exceder 50 caracteres.', 400);
        }

        if ($this->rolDao->existeNombre($nombre, $id > 0 ? $id : null)) {
            ResponseHelper::error('Ya existe otro rol con ese nombre.', 409);
        }

        try {
            if ($id > 0) {
                $existente = $this->rolDao->obtenerPorId($id);
                if ($existente === null) {
                    ResponseHelper::error('El rol que intentas editar no existe.', 404);
                }

                $this->rolDao->actualizar($id, $nombre, $descripcion, $permisoIds);

                // Si el usuario editó su propio rol, refrescar permisos en sesión al instante
                if ((int) $_SESSION['usuario']['rol_id'] === $id) {
                    $_SESSION['usuario']['rol_nombre'] = $nombre;
                    $_SESSION['permisos'] = $this->usuarioDao->obtenerPermisosPorRol($id);
                }

                ResponseHelper::success('Rol actualizado correctamente.');
            }

            $nuevoId = $this->rolDao->crear($nombre, $descripcion, $permisoIds);
            ResponseHelper::success('Rol creado correctamente.', ['id' => $nuevoId], 201);
        } catch (Throwable $e) {
            error_log('Error en RolController::guardar: ' . $e->getMessage());
            ResponseHelper::error('Ocurrió un error al guardar el rol.', 500);
        }
    }

    /**
     * Elimina un rol si no es el Administrador base ni tiene usuarios asignados.
     */
    public function eliminar(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            ResponseHelper::error('ID de rol no válido.', 400);
        }

        if ($id === 1) {
            ResponseHelper::error('El rol Administrador principal del sistema no puede eliminarse.', 403);
        }

        $rol = $this->rolDao->obtenerPorId($id);
        if ($rol === null) {
            ResponseHelper::error('El rol no existe.', 404);
        }

        $usuariosAsignados = $this->rolDao->contarUsuariosAsignados($id);
        if ($usuariosAsignados > 0) {
            ResponseHelper::error(
                "No se puede eliminar este rol porque tiene {$usuariosAsignados} usuario(s) asignado(s).",
                409
            );
        }

        try {
            $this->rolDao->eliminar($id);
            ResponseHelper::success('Rol eliminado correctamente.');
        } catch (Throwable $e) {
            error_log('Error en RolController::eliminar: ' . $e->getMessage());
            ResponseHelper::error('No se pudo eliminar el rol.', 500);
        }
    }
}