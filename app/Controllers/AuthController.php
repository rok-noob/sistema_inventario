<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\UsuarioDAO;
use App\Helpers\CookieHelper;
use App\Helpers\ResponseHelper;

class AuthController
{
    private UsuarioDAO $usuarioDao;

    public function __construct()
    {
        $this->usuarioDao = new UsuarioDAO();
    }

    /**
     * Renderiza la pantalla de inicio de sesión.
     */
    public function showLogin(): void
    {
        if (!empty($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $emailRecordado = CookieHelper::get('recordar_email', '');
        $cookiesAceptadas = CookieHelper::get('consentimiento_cookies') === 'aceptado';

        require __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Procesa el inicio de sesión vía Fetch / AJAX (JSON).
     */
    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $recordar = !empty($input['recordar']);

        if ($email === '' || $password === '') {
            ResponseHelper::error('Ingresa tu correo electrónico y contraseña.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('El formato del correo electrónico no es válido.', 400);
        }

        $usuario = $this->usuarioDao->buscarPorEmail($email);

        if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
            ResponseHelper::error('Credenciales incorrectas. Verifica tu correo o contraseña.', 401);
        }

        if ((int) $usuario['activo'] !== 1) {
            ResponseHelper::error('Tu cuenta está desactivada. Contacta al administrador.', 403);
        }

        // Evitar fijación de sesión
        session_regenerate_id(true);

        $permisos = $this->usuarioDao->obtenerPermisosPorRol((int) $usuario['rol_id']);

        $_SESSION['usuario'] = [
            'id'         => (int) $usuario['id'],
            'nombre'     => $usuario['nombre'],
            'email'      => $usuario['email'],
            'rol_id'     => (int) $usuario['rol_id'],
            'rol_nombre' => $usuario['rol_nombre'],
        ];
        $_SESSION['permisos'] = $permisos;

        // Gestión de cookies al iniciar sesión
        if ($recordar) {
            CookieHelper::set('recordar_email', $email, 30);
        } else {
            CookieHelper::delete('recordar_email');
        }

        CookieHelper::set('ultimo_acceso', date('Y-m-d H:i:s'), 7);

        ResponseHelper::success('Bienvenido, ' . $usuario['nombre'], [
            'redirect' => BASE_URL . '/dashboard',
        ]);
    }

    /**
     * Cierra la sesión actual y redirige al login.
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }

    /**
     * Muestra el panel principal después de iniciar sesión.
     */
    public function dashboard(): void
    {
        $usuario = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];
        $cookiesActivas = CookieHelper::all();

        require __DIR__ . '/../Views/dashboard/index.php';
    }

    /**
     * Endpoint Fetch para demostrar crear, leer, caducar y eliminar cookies.
     */
    public function gestionarCookies(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $accion = (string) ($input['accion'] ?? '');

        switch ($accion) {
            case 'aceptar':
                CookieHelper::set('consentimiento_cookies', 'aceptado', 365);
                ResponseHelper::success('Preferencia de cookies guardada por 1 año.', [
                    'cookies' => CookieHelper::all(),
                ]);
                break;

            case 'crear_prueba':
                CookieHelper::set('preferencia_vista', 'tabla_compacta', 7);
                ResponseHelper::success('Cookie "preferencia_vista" creada por 7 días.', [
                    'cookies' => CookieHelper::all(),
                ]);
                break;

            case 'caducar':
                $nombre = (string) ($input['nombre'] ?? 'preferencia_vista');
                if (!CookieHelper::expire($nombre, 15)) {
                    ResponseHelper::error("La cookie '{$nombre}' no existe actualmente.", 404);
                }
                ResponseHelper::success(
                    "La cookie '{$nombre}' caducará automáticamente en 15 segundos. Recarga después de ese tiempo para comprobarlo.",
                    ['cookies' => CookieHelper::all()]
                );
                break;

            case 'eliminar':
                $nombre = (string) ($input['nombre'] ?? '');
                if ($nombre === '') {
                    ResponseHelper::error('Especifica el nombre de la cookie a eliminar.', 400);
                }
                CookieHelper::delete($nombre);
                ResponseHelper::success("Cookie '{$nombre}' eliminada de inmediato.", [
                    'cookies' => CookieHelper::all(),
                ]);
                break;

            default:
                ResponseHelper::error('Acción de cookie no válida.', 400);
        }
    }
}