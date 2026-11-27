<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Operativos | Sistema de Inventario</title>
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
                            <a class="nav-link active" href="<?= BASE_URL ?>/reportes">
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
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph text-primary me-1"></i> Reportes Dinámicos de Inventario</h1>
                <p class="text-muted small mb-0">Filtra movimientos operativos y exporta informes en Excel (.xlsx), PDF o CSV.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= BASE_URL ?>/api/reportes/excel" class="btn btn-success fw-semibold" id="btnExportarExcel">
                    <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
                </a>
                <a href="<?= BASE_URL ?>/api/reportes/pdf" class="btn btn-danger fw-semibold" id="btnExportarPdf">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                </a>
                <a href="<?= BASE_URL ?>/api/reportes/csv" class="btn btn-dark fw-semibold" id="btnExportarCsv">
                    <i class="bi bi-filetype-csv me-1"></i> Exportar CSV
                </a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Filtros Dinámicos -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form id="formFiltrosReporte" class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label for="filtroFechaInicio" class="form-label small fw-semibold mb-1">Fecha Inicio</label>
                        <input type="date" class="form-control form-control-sm" id="filtroFechaInicio">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="filtroFechaFin" class="form-label small fw-semibold mb-1">Fecha Fin</label>
                        <input type="date" class="form-control form-control-sm" id="filtroFechaFin">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="filtroTipo" class="form-label small fw-semibold mb-1">Tipo de Movimiento</label>
                        <select class="form-select form-select-sm" id="filtroTipo">
                            <option value="">Todos los tipos</option>
                            <option value="ENTRADA">Entradas</option>
                            <option value="SALIDA">Salidas</option>
                            <option value="TRANSFERENCIA">Transferencias</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="filtroAlmacen" class="form-label small fw-semibold mb-1">Almacén</label>
                        <select class="form-select form-select-sm" id="filtroAlmacen">
                            <option value="0">Todos los almacenes</option>
                            <?php foreach ($almacenes as $alm): ?>
                                <option value="<?= (int) $alm['id'] ?>">
                                    <?= htmlspecialchars($alm['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold flex-grow-1" id="btnFiltrarReporte">
                            <i class="bi bi-funnel me-1"></i> Filtrar
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltros" title="Limpiar filtros">
                            <i class="bi bi-eraser"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tarjetas de Resumen KPI -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body py-2 px-2">
                        <div class="fs-5 fw-bold text-dark" id="kpiTotalMovimientos">0</div>
                        <div class="small text-muted">Movimientos</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body py-2 px-2">
                        <div class="fs-5 fw-bold text-success" id="kpiEntradas">0</div>
                        <div class="small text-muted">Entradas</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body py-2 px-2">
                        <div class="fs-5 fw-bold text-danger" id="kpiSalidas">0</div>
                        <div class="small text-muted">Salidas</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body py-2 px-2">
                        <div class="fs-5 fw-bold text-primary" id="kpiTransferencias">0</div>
                        <div class="small text-muted">Transferencias</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body py-2 px-2">
                        <div class="fs-5 fw-bold text-dark" id="kpiUnidades">0</div>
                        <div class="small text-muted">Unidades</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body py-2 px-2">
                        <div class="fs-5 fw-bold text-dark" id="kpiMontoTotal">$0.00</div>
                        <div class="small text-muted">Valor Total</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla Previsualización -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold small text-uppercase text-muted">Vista Previa del Informe</span>
                <span class="badge bg-secondary" id="badgeTotalRegistros">0 registros</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Origen / Destino</th>
                                <th>Productos</th>
                                <th class="text-center">Unid.</th>
                                <th class="text-end">Monto Total</th>
                                <th>Registró</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPrevisualizacionReporte">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Cargando datos del reporte...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/reportes.js"></script>
</body>
</html>