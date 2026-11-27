<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidencias FTP | Sistema de Inventario</title>
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
                            <a class="nav-link active" href="<?= BASE_URL ?>/ftp">
                                <i class="bi bi-hdd-network me-1"></i> Evidencias FTP
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
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-0"><i class="bi bi-hdd-network text-primary me-1"></i> Gestor Remoto de Evidencias (FTP)</h1>
                <p class="text-muted small mb-0">Administración directa de carpetas y respaldos documentales en servidor remoto.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-dark fw-semibold" id="btnAbrirModalCarpeta">
                    <i class="bi bi-folder-plus me-1"></i> Nueva Carpeta
                </button>
                <button type="button" class="btn btn-primary fw-semibold" id="btnAbrirModalSubir">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Subir Evidencia
                </button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Explorador Remoto FTP -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSubirNivel" disabled title="Subir un nivel">
                        <i class="bi bi-arrow-90deg-up"></i> Subir nivel
                    </button>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small" id="breadcrumbFtp">
                            <li class="breadcrumb-item active">/evidencias</li>
                        </ol>
                    </nav>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefrescarFtp">
                    <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Tamaño</th>
                                <th>Fecha Servidor</th>
                                <th>Movimiento Vinculado</th>
                                <th class="text-end pe-3" style="width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaExploradorFtp">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Conectando al servidor FTP...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Historial en Base de Datos de Evidencias Vinculadas -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <span class="fw-semibold"><i class="bi bi-database-check text-success me-1"></i> Bitácora de Evidencias Vinculadas (MySQL)</span>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" id="filtroMovimientoEvidencia" style="min-width: 240px;">
                        <option value="0">Todos los movimientos</option>
                        <?php foreach ($movimientos as $mov): ?>
                            <option value="<?= (int) $mov['id'] ?>">
                                #<?= (int) $mov['id'] ?> — <?= htmlspecialchars($mov['tipo'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(substr((string) $mov['fecha'], 0, 10), ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Folio Mov.</th>
                                <th>Archivo</th>
                                <th>Ruta Remota</th>
                                <th>Tamaño</th>
                                <th>Subido por</th>
                                <th>Fecha Subida</th>
                                <th class="text-end pe-3" style="width: 130px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaBitacoraEvidencias">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Cargando registros...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Crear Carpeta -->
    <div class="modal fade" id="modalNuevaCarpeta" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formNuevaCarpeta" novalidate>
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bi bi-folder-plus me-1"></i> Crear Carpeta en FTP</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Ubicación actual:</label>
                            <div class="form-control form-control-sm bg-light font-monospace" id="lblRutaPadreCarpeta">/</div>
                        </div>
                        <div class="mb-3">
                            <label for="inputNombreCarpeta" class="form-label fw-semibold">Nombre de la carpeta *</label>
                            <input type="text" class="form-control" id="inputNombreCarpeta" maxlength="80" placeholder="Ej. facturas_noviembre_2026" required>
                            <div class="form-text">Solo letras, números, guiones (-) y guiones bajos (_).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark fw-semibold" id="btnGuardarCarpeta">
                            <i class="bi bi-check2-circle me-1"></i> Crear Carpeta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Subir Evidencia -->
    <div class="modal fade" id="modalSubirEvidencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formSubirEvidencia" enctype="multipart/form-data" novalidate>
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-cloud-arrow-up me-1"></i> Subir Evidencia al Servidor FTP</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalSubirAlert"></div>

                        <div class="mb-3">
                            <label for="selectMovimientoSubida" class="form-label fw-semibold">Movimiento Operativo Asociado *</label>
                            <select class="form-select" id="selectMovimientoSubida" name="movimiento_id" required>
                                <option value="">Selecciona un folio de movimiento...</option>
                                <?php foreach ($movimientos as $mov): ?>
                                    <option value="<?= (int) $mov['id'] ?>">
                                        #<?= (int) $mov['id'] ?> — <?= htmlspecialchars($mov['tipo'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $mov['fecha'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Carpeta Destino en FTP</label>
                            <input type="text" class="form-control bg-light font-monospace" id="inputDestinoSubida" readonly>
                            <div class="form-text">Si estás en la raíz, se creará automáticamente <code>movimiento_{ID}</code>.</div>
                        </div>

                        <div class="mb-3">
                            <label for="inputArchivoEvidencia" class="form-label fw-semibold">Archivo de Evidencia (Máx. 10 MB) *</label>
                            <input type="file" class="form-control" id="inputArchivoEvidencia" name="archivo" required>
                            <div class="form-text">Formatos permitidos: PDF, JPG, PNG, WEBP, DOC/DOCX, XLS/XLSX, CSV, TXT, ZIP.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-semibold" id="btnConfirmarSubida">
                            <i class="bi bi-upload me-1"></i> Subir Archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/ftp.js"></script>
</body>
</html>