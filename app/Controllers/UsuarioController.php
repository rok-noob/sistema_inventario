<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\RolDAO;
use App\DAO\UsuarioDAO;
use App\Helpers\ResponseHelper;
use Throwable;

class UsuarioController
{
    private UsuarioDAO $usuarioDao;
    private RolDAO $rolDao;

    public function __construct()
    {
        $this->usuarioDao = new UsuarioDAO();
        $this->rolDao = new RolDAO();
    }

    /**
     * Renderiza la vista de gestión de usuarios.
     */
    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];
        $roles = $this->rolDao->listarTodos();

        require __DIR__ . '/../Views/usuarios/index.php';
    }

    /**
     * Devuelve el listado completo de usuarios en JSON.
     */
    public function listar(): void
    {
        $usuarios = $this->usuarioDao->listarTodos();
        ResponseHelper::success('Usuarios cargados correctamente.', ['usuarios' => $usuarios]);
    }

    /**
     * Devuelve los datos de un usuario por ID para precargar el modal de edición.
     */
    public function obtener(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            ResponseHelper::error('ID de usuario no válido.', 400);
        }

        $registro = $this->usuarioDao->obtenerPorId($id);
        if ($registro === null) {
            ResponseHelper::error('El usuario solicitado no existe.', 404);
        }

        ResponseHelper::success('Usuario encontrado.', ['usuario' => $registro]);
    }

    /**
     * Crea o actualiza un usuario validando correo único, rol existente y contraseña.
     */
    public function guardar(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $id = (int) ($input['id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $rolId = (int) ($input['rol_id'] ?? 0);
        $activo = !empty($input['activo']) ? 1 : 0;

        if ($nombre === '' || $email === '' || $rolId <= 0) {
            ResponseHelper::error('Nombre, correo electrónico y rol son obligatorios.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('El formato del correo electrónico no es válido.', 400);
        }

        if ($this->rolDao->obtenerPorId($rolId) === null) {
            ResponseHelper::error('El rol seleccionado no es válido.', 400);
        }

        if ($this->usuarioDao->existeEmail($email, $id > 0 ? $id : null)) {
            ResponseHelper::error('Ya existe otro usuario registrado con ese correo.', 409);
        }

        // Evitar que el usuario en sesión se desactive a sí mismo
        if ($id > 0 && $id === (int) $_SESSION['usuario']['id'] && $activo === 0) {
            ResponseHelper::error('No puedes desactivar tu propia cuenta mientras tienes sesión iniciada.', 400);
        }

        try {
            if ($id > 0) {
                $existente = $this->usuarioDao->obtenerPorId($id);
                if ($existente === null) {
                    ResponseHelper::error('El usuario que intentas editar no existe.', 404);
                }

                $nuevoHash = null;
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        ResponseHelper::error('La nueva contraseña debe tener al menos 8 caracteres.', 400);
                    }
                    $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                }

                $this->usuarioDao->actualizar($id, $nombre, $email, $rolId, $activo, $nuevoHash);

                // Si editó su propio perfil, sincronizar sesión
                if ($id === (int) $_SESSION['usuario']['id']) {
                    $rolActualizado = $this->rolDao->obtenerPorId($rolId);
                    $_SESSION['usuario']['nombre'] = $nombre;
                    $_SESSION['usuario']['email'] = $email;
                    $_SESSION['usuario']['rol_id'] = $rolId;
                    $_SESSION['usuario']['rol_nombre'] = $rolActualizado['nombre'] ?? $_SESSION['usuario']['rol_nombre'];
                    $_SESSION['permisos'] = $this->usuarioDao->obtenerPermisosPorRol($rolId);
                }

                ResponseHelper::success('Usuario actualizado correctamente.');
            }

            if (strlen($password) < 8) {
                ResponseHelper::error('La contraseña inicial debe tener al menos 8 caracteres.', 400);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $nuevoId = $this->usuarioDao->crear($nombre, $email, $passwordHash, $rolId, $activo);

            ResponseHelper::success('Usuario registrado correctamente.', ['id' => $nuevoId], 201);
        } catch (Throwable $e) {
            error_log('Error en UsuarioController::guardar: ' . $e->getMessage());
            ResponseHelper::error('Ocurrió un error al guardar el usuario.', 500);
        }
    }

    /**
     * Elimina un usuario (impide borrarse a sí mismo).
     */
    public function eliminar(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            ResponseHelper::error('ID de usuario no válido.', 400);
        }

        if ($id === (int) $_SESSION['usuario']['id']) {
            ResponseHelper::error('No puedes eliminar tu propio usuario en sesión.', 403);
        }

        $usuario = $this->usuarioDao->obtenerPorId($id);
        if ($usuario === null) {
            ResponseHelper::error('El usuario no existe.', 404);
        }

        try {
            $this->usuarioDao->eliminar($id);
            ResponseHelper::success('Usuario eliminado correctamente.');
        } catch (Throwable $e) {
            error_log('Error en UsuarioController::eliminar: ' . $e->getMessage());
            ResponseHelper::error(
                'No se puede eliminar este usuario porque tiene movimientos o registros históricos asociados. Desactívalo en su lugar.',
                409
            );
        }
    }
}