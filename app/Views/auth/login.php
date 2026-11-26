<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light d-flex flex-column min-vh-100">

    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-9 col-md-6 col-lg-4">
                <div class="card bg-body-tertiary text-dark shadow-lg border-0 rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                                <i class="bi bi-box-seam fs-3"></i>
                            </div>
                            <h1 class="h4 fw-bold mb-1">Control de Inventario</h1>
                            <p class="text-muted small mb-0">Inicia sesión para gestionar almacenes y movimientos</p>
                        </div>

                        <div id="alertContainer"></div>

                        <form id="formLogin" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        placeholder="admin@inventario.local"
                                        value="<?= htmlspecialchars((string) $emailRecordado, ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        placeholder="••••••••"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="recordar"
                                        name="recordar"
                                        <?= $emailRecordado !== '' ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label small" for="recordar">
                                        Recordar correo (Cookie)
                                    </label>
                                </div>
                                <a href="<?= BASE_URL ?>/recuperar" class="small text-decoration-none">
                                    ¿Olvidaste tu clave?
                                </a>
                            </div>

                            <button type="submit" id="btnSubmit" class="btn btn-primary w-100 fw-semibold py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar al sistema
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if (!$cookiesAceptadas): ?>
    <div id="cookieBanner" class="bg-secondary text-white py-3 px-4 border-top border-light-subtle">
        <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
            <span class="small">
                <i class="bi bi-cookie me-1"></i>
                Este sistema utiliza cookies funcionales para recordar tu usuario y preferencias de interfaz.
            </span>
            <button type="button" id="btnAceptarCookies" class="btn btn-sm btn-light fw-semibold">
                Aceptar cookies
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/auth.js"></script>
</body>
</html>