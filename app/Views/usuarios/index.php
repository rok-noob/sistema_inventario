<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/dashboard">
                <i class="bi bi-box-seam me-1"></i> Inventario Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/dashboard">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <?php if (in_array('catalogos.ver', $permisos, true)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-tags me-1"></i> Catálogos
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/productos"><i class="bi bi-box me-1"></i> Productos</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/categorias"><i class="bi bi-folder me-1"></i> Categorías</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/almacenes"><i class="bi bi-building me-1"></i> Almacenes</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array('movimientos.ver', $permisos, true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/movimientos">
                                <i class="bi bi-arrow-left-right me-1"></i> Movimientos
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array('ftp.gestionar', $permisos, true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/ftp">
                                <i class="bi bi-hdd-network me-1"></i> Evidencias FTP
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array('reportes.exportar', $permisos, true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/reportes">
                                <i class="bi bi-file-earmark-bar-graph me-1"></i> Reportes
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array('usuarios.gestionar', $permisos, true)): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?= BASE_URL ?>/usuarios">
                                <i class="bi bi-people me-1"></i> Usuarios
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array('roles.gestionar', $permisos, true)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/roles">
                                <i class="bi bi-shield-check me-1"></i> Roles y Permisos
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-3 text-white">
                    <span class="small">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="badge bg-primary ms-1"><?= htmlspecialchars($usuario['rol_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                    <a href="<?= BASE_URL ?>/logout" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-0"><i class="bi bi-people text-primary me-1"></i> Gestión de Usuarios</h1>
                <p class="text-muted small mb-0">Administra las cuentas del sistema, sus roles y estado de acceso.</p>
            </div>
            <button type="button" class="btn btn-primary fw-semibold" id="btnNuevoUsuario">
                <i class="bi bi-person-plus me-1"></i> Nuevo Usuario
            </button>
        </div>

        <div id="alertContainer"></div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Nombre</th>
                                <th>Correo Electrónico</th>
                                <th>Rol</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3" style="width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaUsuarios">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Cargando usuarios...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Crear / Editar Usuario -->
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formUsuario" novalidate>
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo Usuario</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalAlertContainer"></div>
                        <input type="hidden" id="usuarioId" name="id" value="0">

                        <div class="mb-3">
                            <label for="usuarioNombre" class="form-label fw-semibold">Nombre completo *</label>
                            <input type="text" class="form-control" id="usuarioNombre" name="nombre" maxlength="100" required>
                        </div>

                        <div class="mb-3">
                            <label for="usuarioEmail" class="form-label fw-semibold">Correo electrónico *</label>
                            <input type="email" class="form-control" id="usuarioEmail" name="email" maxlength="120" required>
                        </div>

                        <div class="mb-3">
                            <label for="usuarioRol" class="form-label fw-semibold">Rol asignado *</label>
                            <select class="form-select" id="usuarioRol" name="rol_id" required>
                                <option value="">Selecciona un rol...</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= (int) $rol['id'] ?>">
                                        <?= htmlspecialchars($rol['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="usuarioPassword" class="form-label fw-semibold" id="labelPassword">Contraseña *</label>
                            <input type="password" class="form-control" id="usuarioPassword" name="password" minlength="8" placeholder="Mínimo 8 caracteres">
                            <div class="form-text" id="ayudaPassword">Obligatoria al crear un usuario nuevo.</div>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="usuarioActivo" name="activo" checked>
                            <label class="form-check-label fw-semibold" for="usuarioActivo">Usuario activo (puede iniciar sesión)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold" id="btnGuardarUsuario">
                            <i class="bi bi-save me-1"></i> Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const CURRENT_USER_ID = <?= (int) $usuario['id'] ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/usuarios.js"></script>
</body>
</html>