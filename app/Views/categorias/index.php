<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías | Sistema de Inventario</title>
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
                            <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-tags me-1"></i> Catálogos
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/productos"><i class="bi bi-box me-1"></i> Productos</a></li>
                                <li><a class="dropdown-item active" href="<?= BASE_URL ?>/categorias"><i class="bi bi-folder me-1"></i> Categorías</a></li>
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
                            <a class="nav-link" href="<?= BASE_URL ?>/usuarios">
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
                <h1 class="h4 fw-bold mb-0"><i class="bi bi-folder text-primary me-1"></i> Catálogo de Categorías</h1>
                <p class="text-muted small mb-0">Clasificación general para organizar los productos del inventario.</p>
            </div>
            <?php if (in_array('catalogos.gestionar', $permisos, true)): ?>
                <button type="button" class="btn btn-primary fw-semibold" id="btnNuevaCategoria">
                    <i class="bi bi-plus-lg me-1"></i> Nueva Categoría
                </button>
            <?php endif; ?>
        </div>

        <div id="alertContainer"></div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control border-start-0" id="buscadorCategorias" placeholder="Buscar categoría por nombre o descripción...">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th class="text-center">Productos</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3" style="width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCategorias">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Cargando categorías...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Crear / Editar Categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formCategoria" novalidate>
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="modalCategoriaTitulo">Nueva Categoría</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalAlertContainer"></div>
                        <input type="hidden" id="categoriaId" name="id" value="0">

                        <div class="mb-3">
                            <label for="categoriaNombre" class="form-label fw-semibold">Nombre *</label>
                            <input type="text" class="form-control" id="categoriaNombre" maxlength="100" placeholder="Ej. Herramientas Eléctricas" required>
                        </div>

                        <div class="mb-3">
                            <label for="categoriaDescripcion" class="form-label fw-semibold">Descripción</label>
                            <textarea class="form-control" id="categoriaDescripcion" rows="2" maxlength="255" placeholder="Detalle opcional de la categoría"></textarea>
                        </div>

                        <div class="mb-2">
                            <label for="categoriaActivo" class="form-label fw-semibold">Estado</label>
                            <select class="form-select" id="categoriaActivo">
                                <option value="1">Activa</option>
                                <option value="0">Inactiva</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold" id="btnGuardarCategoria">
                            <i class="bi bi-save me-1"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const PUEDE_GESTIONAR = <?= json_encode(in_array('catalogos.gestionar', $permisos, true)) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/categorias.js"></script>
</body>
</html>