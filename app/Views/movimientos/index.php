<?php
$puedeEntrada       = in_array('movimientos.entrada', $permisos, true);
$puedeSalida        = in_array('movimientos.salida', $permisos, true);
$puedeTransferencia = in_array('movimientos.transferencia', $permisos, true);
$puedeRegistrar     = $puedeEntrada || $puedeSalida || $puedeTransferencia;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Inventario | Sistema de Inventario</title>
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
                            <a class="nav-link active" href="<?= BASE_URL ?>/movimientos">
                                <i class="bi bi-arrow-left-right me-1"></i> Movimientos
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
                <h1 class="h4 fw-bold mb-0"><i class="bi bi-arrow-left-right text-primary me-1"></i> Movimientos de Inventario</h1>
                <p class="text-muted small mb-0">Registro transaccional de entradas, salidas y transferencias entre almacenes.</p>
            </div>
            <?php if ($puedeRegistrar): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($puedeEntrada): ?>
                        <button type="button" class="btn btn-success fw-semibold btn-nuevo-movimiento" data-tipo="ENTRADA">
                            <i class="bi bi-box-arrow-in-down me-1"></i> Entrada
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeSalida): ?>
                        <button type="button" class="btn btn-danger fw-semibold btn-nuevo-movimiento" data-tipo="SALIDA">
                            <i class="bi bi-box-arrow-up me-1"></i> Salida
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeTransferencia): ?>
                        <button type="button" class="btn btn-primary fw-semibold btn-nuevo-movimiento" data-tipo="TRANSFERENCIA">
                            <i class="bi bi-arrow-left-right me-1"></i> Transferencia
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="alertContainer"></div>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="search" class="form-control border-start-0" id="buscadorMovimientos" placeholder="Filtrar por folio (#), usuario u observaciones...">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <select class="form-select" id="filtroTipo">
                            <option value="">Todos los tipos</option>
                            <option value="ENTRADA">Entradas</option>
                            <option value="SALIDA">Salidas</option>
                            <option value="TRANSFERENCIA">Transferencias</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <select class="form-select" id="filtroAlmacen">
                            <option value="0">Todos los almacenes</option>
                            <?php foreach ($almacenes as $alm): ?>
                                <option value="<?= (int) $alm['id'] ?>">
                                    <?= htmlspecialchars($alm['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla Historial -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Origen / Destino</th>
                                <th class="text-center">Partidas / Unid.</th>
                                <th class="text-end">Monto Total</th>
                                <th>Registró</th>
                                <th class="text-end pe-3" style="width: 90px;">Detalle</th>
                            </tr>
                        </thead>
                        <tbody id="tablaMovimientos">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Cargando historial de movimientos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Registrar Movimiento -->
    <?php if ($puedeRegistrar): ?>
        <div class="modal fade" id="modalMovimiento" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form id="formMovimiento" novalidate>
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="modalMovimientoTitulo">Registrar Movimiento</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="modalMovAlertContainer"></div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label for="movTipo" class="form-label fw-semibold">Tipo de Operación *</label>
                                    <select class="form-select" id="movTipo" required>
                                        <?php if ($puedeEntrada): ?>
                                            <option value="ENTRADA">ENTRADA (Ingreso a almacén)</option>
                                        <?php endif; ?>
                                        <?php if ($puedeSalida): ?>
                                            <option value="SALIDA">SALIDA (Despacho / Baja)</option>
                                        <?php endif; ?>
                                        <?php if ($puedeTransferencia): ?>
                                            <option value="TRANSFERENCIA">TRANSFERENCIA (Entre almacenes)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4 d-none" id="grupoAlmacenOrigen">
                                    <label for="movAlmacenOrigen" class="form-label fw-semibold">Almacén Origen *</label>
                                    <select class="form-select" id="movAlmacenOrigen">
                                        <option value="">Selecciona origen...</option>
                                        <?php foreach ($almacenes as $alm): ?>
                                            <option value="<?= (int) $alm['id'] ?>">
                                                <?= htmlspecialchars($alm['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4" id="grupoAlmacenDestino">
                                    <label for="movAlmacenDestino" class="form-label fw-semibold">Almacén Destino *</label>
                                    <select class="form-select" id="movAlmacenDestino">
                                        <option value="">Selecciona destino...</option>
                                        <?php foreach ($almacenes as $alm): ?>
                                            <option value="<?= (int) $alm['id'] ?>">
                                                <?= htmlspecialchars($alm['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="movObservaciones" class="form-label fw-semibold">Observaciones / Referencia (Factura, Orden, Motivo)</label>
                                <input type="text" class="form-control" id="movObservaciones" maxlength="500" placeholder="Ej. Compra factura #F-904 / Reabastecimiento sucursal norte">
                            </div>

                            <hr>

                            <!-- Constructor de partidas -->
                            <div class="card bg-light border mb-3">
                                <div class="card-body py-2">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12 col-md-5">
                                            <label for="selectProductoPartida" class="form-label small fw-semibold mb-1">Producto</label>
                                            <select class="form-select form-select-sm" id="selectProductoPartida">
                                                <option value="">Selecciona un producto...</option>
                                            </select>
                                        </div>
                                        <div class="col-4 col-md-2">
                                            <label for="inputCantidadPartida" class="form-label small fw-semibold mb-1">Cantidad</label>
                                            <input type="number" class="form-control form-control-sm" id="inputCantidadPartida" min="1" value="1">
                                        </div>
                                        <div class="col-5 col-md-3">
                                            <label for="inputCostoPartida" class="form-label small fw-semibold mb-1">Costo Unit. ($)</label>
                                            <input type="number" class="form-control form-control-sm" id="inputCostoPartida" step="0.01" min="0" value="0.00">
                                        </div>
                                        <div class="col-3 col-md-2 d-grid">
                                            <button type="button" class="btn btn-dark btn-sm fw-semibold" id="btnAgregarPartida">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>SKU</th>
                                            <th>Producto</th>
                                            <th class="text-center" style="width: 110px;">Cantidad</th>
                                            <th class="text-end" style="width: 140px;">Costo Unit.</th>
                                            <th class="text-end" style="width: 140px;">Subtotal</th>
                                            <th class="text-center" style="width: 60px;">Quitar</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaPartidasMovimiento">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">Aún no hay productos agregados a este movimiento.</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td colspan="2" class="text-end">Totales:</td>
                                            <td class="text-center" id="totalUnidadesPartidas">0</td>
                                            <td></td>
                                            <td class="text-end" id="totalMontoPartidas">$0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary fw-semibold" id="btnGuardarMovimiento">
                                <i class="bi bi-check2-circle me-1"></i> Confirmar y Actualizar Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Ver Detalle de Movimiento -->
    <div class="modal fade" id="modalDetalleMovimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="detalleMovTitulo">Detalle del Movimiento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="detalleMovBody">
                    <p class="text-center text-muted py-3">Cargando...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
        const PRODUCTOS_CATALOGO = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/movimientos.js"></script>
</body>
</html>