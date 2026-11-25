<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal | Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/dashboard">
                <i class="bi bi-box-seam me-1"></i> Inventario Pro
            </a>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="small d-none d-sm-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="badge bg-primary ms-1"><?= htmlspecialchars($usuario['rol_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <a href="<?= BASE_URL ?>/logout" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="row g-4">
            <!-- Tarjeta de Sesión y Permisos RBAC -->
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-shield-lock text-primary me-1"></i> Sesión Activa y Permisos (RBAC)
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Usuario:</strong> <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-1"><strong>Correo:</strong> <?= htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-3"><strong>Rol asignado:</strong> <?= htmlspecialchars($usuario['rol_nombre'], ENT_QUOTES, 'UTF-8') ?></p>

                        <h6 class="fw-semibold small text-uppercase text-muted">Permisos habilitados para este rol:</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($permisos as $permiso): ?>
                                <span class="badge text-bg-success"><?= htmlspecialchars($permiso, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta de Gestión Integral de Cookies -->
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cookie text-warning me-1"></i> Gestión de Cookies (CookieHelper)</span>
                    </div>
                    <div class="card-body">
                        <div id="cookieAlert"></div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="accionCookie('crear_prueba')">
                                <i class="bi bi-plus-circle"></i> Crear cookie prueba
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="accionCookie('caducar', 'preferencia_vista')">
                                <i class="bi bi-hourglass-split"></i> Caducar en 15s
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Recargar lectura
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Valor</th>
                                        <th class="text-center" style="width: 80px;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaCookies">
                                    <?php if (empty($cookiesActivas)): ?>
                                        <tr><td colspan="3" class="text-center text-muted small">No hay cookies personalizadas activas.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($cookiesActivas as $nombre => $valor): ?>
                                            <tr>
                                                <td class="fw-semibold small"><?= htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="small text-break"><?= htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-danger py-0 px-2" onclick="accionCookie('eliminar', <?= htmlspecialchars(json_encode((string) $nombre), ENT_QUOTES, 'UTF-8') ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;

        async function accionCookie(accion, nombre = '') {
            const alertBox = document.getElementById('cookieAlert');
            try {
                const res = await fetch(`${BASE_URL}/api/cookies`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion, nombre })
                });
                const data = await res.json();

                alertBox.innerHTML = `<div class="alert alert-${data.success ? 'success' : 'danger'} py-1 px-2 small">${data.message}</div>`;

                if (data.success && data.data && data.data.cookies) {
                    const tbody = document.getElementById('tablaCookies');
                    const entries = Object.entries(data.data.cookies);
                    if (entries.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted small">No hay cookies personalizadas activas.</td></tr>';
                    } else {
                        tbody.innerHTML = entries.map(([k, v]) => `
                            <tr>
                                <td class="fw-semibold small">${k}</td>
                                <td class="small text-break">${v}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-danger py-0 px-2" onclick="accionCookie('eliminar', '${k}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('');
                    }
                }
            } catch (e) {
                alertBox.innerHTML = '<div class="alert alert-danger py-1 px-2 small">Error de red.</div>';
            }
        }
    </script>
</body>
</html>