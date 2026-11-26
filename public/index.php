<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AlmacenController;
use App\Controllers\AuthController;
use App\Controllers\CategoriaController;
use App\Controllers\MovimientoController;
use App\Controllers\ProductoController;
use App\Controllers\RolController;
use App\Controllers\UsuarioController;
use App\Helpers\CookieHelper;
use App\Helpers\ResponseHelper;
use Dotenv\Dotenv;

// 1. Cargar variables de entorno (.env)
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// 2. Configuración regional y sesión
date_default_timezone_set('America/Mexico_City');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Detectar ruta base automáticamente (funciona en XAMPP y en hosting raíz)
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
define('BASE_URL', $basePath);

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = '/' . ltrim(substr($requestUri, strlen(BASE_URL)), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 4. Rutas públicas que NO requieren haber iniciado sesión
$publicRoutes = [
    '/login',
    '/api/login',
    '/recuperar',
    '/api/recuperar',
    '/restablecer',
    '/api/restablecer',
    '/api/cookies',
    '/test-router',
];

$isApiRequest = str_starts_with($route, '/api/');

// 5. Verificar autenticación si la ruta es privada
if (!in_array($route, $publicRoutes, true) && empty($_SESSION['usuario'])) {
    if ($isApiRequest) {
        ResponseHelper::error('No has iniciado sesión o tu sesión expiró.', 401);
    }
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// 6. Tabla de rutas: 'METODO /ruta' => [Controlador::class, 'metodo', 'permiso.requerido' | null]
$routes = [
    // Autenticación, Dashboard y Cookies
    'GET /login'               => [AuthController::class, 'showLogin', null],
    'POST /api/login'          => [AuthController::class, 'login', null],
    'GET /logout'              => [AuthController::class, 'logout', null],
    'GET /dashboard'           => [AuthController::class, 'dashboard', null],
    'POST /api/cookies'        => [AuthController::class, 'gestionarCookies', null],
    'GET /recuperar'           => [AuthController::class, 'showRecuperar', null],
    'POST /api/recuperar'      => [AuthController::class, 'solicitarRecuperacion', null],
    'GET /restablecer'         => [AuthController::class, 'showRestablecer', null],
    'POST /api/restablecer'    => [AuthController::class, 'restablecerPassword', null],

    // CRUD de Roles y Permisos (RBAC)
    'GET /roles'               => [RolController::class, 'index', 'roles.gestionar'],
    'GET /api/roles'           => [RolController::class, 'listar', 'roles.gestionar'],
    'GET /api/roles/detalle'   => [RolController::class, 'obtener', 'roles.gestionar'],
    'POST /api/roles/guardar'  => [RolController::class, 'guardar', 'roles.gestionar'],
    'POST /api/roles/eliminar' => [RolController::class, 'eliminar', 'roles.gestionar'],

    // CRUD de Usuarios (RBAC)
    'GET /usuarios'               => [UsuarioController::class, 'index', 'usuarios.gestionar'],
    'GET /api/usuarios'           => [UsuarioController::class, 'listar', 'usuarios.gestionar'],
    'GET /api/usuarios/detalle'   => [UsuarioController::class, 'obtener', 'usuarios.gestionar'],
    'POST /api/usuarios/guardar'  => [UsuarioController::class, 'guardar', 'usuarios.gestionar'],
    'POST /api/usuarios/eliminar' => [UsuarioController::class, 'eliminar', 'usuarios.gestionar'],

    // Catálogo de Categorías
    'GET /categorias'               => [CategoriaController::class, 'index', 'catalogos.ver'],
    'GET /api/categorias'           => [CategoriaController::class, 'listar', 'catalogos.ver'],
    'GET /api/categorias/detalle'   => [CategoriaController::class, 'obtener', 'catalogos.ver'],
    'POST /api/categorias/guardar'  => [CategoriaController::class, 'guardar', 'catalogos.gestionar'],
    'POST /api/categorias/eliminar' => [CategoriaController::class, 'eliminar', 'catalogos.gestionar'],

    // Catálogo de Almacenes
    'GET /almacenes'               => [AlmacenController::class, 'index', 'catalogos.ver'],
    'GET /api/almacenes'           => [AlmacenController::class, 'listar', 'catalogos.ver'],
    'GET /api/almacenes/detalle'   => [AlmacenController::class, 'obtener', 'catalogos.ver'],
    'POST /api/almacenes/guardar'  => [AlmacenController::class, 'guardar', 'catalogos.gestionar'],
    'POST /api/almacenes/eliminar' => [AlmacenController::class, 'eliminar', 'catalogos.gestionar'],

    // Catálogo de Productos
    'GET /productos'               => [ProductoController::class, 'index', 'catalogos.ver'],
    'GET /api/productos'           => [ProductoController::class, 'listar', 'catalogos.ver'],
    'GET /api/productos/detalle'   => [ProductoController::class, 'obtener', 'catalogos.ver'],
    'POST /api/productos/guardar'  => [ProductoController::class, 'guardar', 'catalogos.gestionar'],
    'POST /api/productos/eliminar' => [ProductoController::class, 'eliminar', 'catalogos.gestionar'],

    // Movimientos de Inventario (Entradas, Salidas y Transferencias)
    'GET /movimientos'                   => [MovimientoController::class, 'index', 'movimientos.ver'],
    'GET /api/movimientos'               => [MovimientoController::class, 'listar', 'movimientos.ver'],
    'GET /api/movimientos/detalle'       => [MovimientoController::class, 'obtener', 'movimientos.ver'],
    'GET /api/movimientos/stock-almacen' => [MovimientoController::class, 'stockAlmacen', 'movimientos.ver'],
    'POST /api/movimientos/registrar'    => [MovimientoController::class, 'registrar', 'movimientos.ver'],
];

$routeKey = "{$method} {$route}";

// Redirección raíz por defecto
if ($route === '/' && $method === 'GET') {
    $destino = !empty($_SESSION['usuario']) ? '/dashboard' : '/login';
    header('Location: ' . BASE_URL . $destino);
    exit;
}

if (!isset($routes[$routeKey])) {
    if ($isApiRequest) {
        ResponseHelper::error("Ruta no encontrada: {$routeKey}", 404);
    }
    http_response_code(404);
    echo '<h1>404 - Página no encontrada</h1>';
    exit;
}

[$controllerClass, $actionMethod, $requiredPermission] = $routes[$routeKey];

// 7. Filtro RBAC (Control de Acceso Basado en Roles y Permisos)
if ($requiredPermission !== null) {
    $userPermissions = $_SESSION['permisos'] ?? [];
    if (!in_array($requiredPermission, $userPermissions, true)) {
        if ($isApiRequest) {
            ResponseHelper::error(
                "Acceso denegado. Se requiere el permiso: {$requiredPermission}",
                403
            );
        }
        http_response_code(403);
        echo "<h1>403 - Acceso Denegado</h1><p>No tienes permiso ({$requiredPermission}) para ver esto.</p>";
        exit;
    }
}

// Handler temporal de prueba para validar .htaccess + index.php + CookieHelper
if ($controllerClass === null && $actionMethod === 'testRouter') {
    CookieHelper::set('test_inventario', 'funcionando_al_cien', 1);
    ResponseHelper::success('Front Controller, .htaccess y CookieHelper jalando al puro centavo.', [
        'ruta_detectada' => $route,
        'base_url'       => BASE_URL,
        'cookie_leida'   => CookieHelper::get('test_inventario'),
    ]);
}

// 8. Instanciar controlador y ejecutar acción
$controller = new $controllerClass();
$controller->$actionMethod();