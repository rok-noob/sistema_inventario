<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña | Sistema de Inventario</title>
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
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                                <i class="bi bi-shield-lock fs-3"></i>
                            </div>
                            <h1 class="h4 fw-bold mb-1">Nueva Contraseña</h1>
                            <?php if ($tokenValido): ?>
                                <p class="text-muted small mb-0">
                                    Cuenta: <strong><?= htmlspecialchars((string) $emailUsuario, ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div id="alertContainer"></div>

                        <?php if (!$tokenValido): ?>
                            <div class="alert alert-danger small" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Este enlace de recuperación no es válido, ya fue utilizado o expiró (vigencia de 1 hora).
                            </div>
                            <a href="<?= BASE_URL ?>/recuperar" class="btn btn-primary w-100 fw-semibold mb-2">
                                Solicitar un enlace nuevo
                            </a>
                            <div class="text-center mt-2">
                                <a href="<?= BASE_URL ?>/login" class="small text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i> Volver al login
                                </a>
                            </div>
                        <?php else: ?>
                            <form id="formRestablecer" novalidate>
                                <input
                                    type="hidden"
                                    id="token"
                                    name="token"
                                    value="<?= htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') ?>"
                                >

                                <div class="mb-3">
                                    <label for="password" class="form-label fw-semibold">Nueva contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="password"
                                            name="password"
                                            placeholder="Mínimo 8 caracteres"
                                            minlength="8"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="password_confirm" class="form-label fw-semibold">Confirmar contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="password_confirm"
                                            name="password_confirm"
                                            placeholder="Repite tu contraseña"
                                            minlength="8"
                                            required
                                        >
                                    </div>
                                </div>

                                <button type="submit" id="btnSubmit" class="btn btn-success w-100 fw-semibold py-2 mb-3">
                                    <i class="bi bi-check-circle me-1"></i> Guardar nueva contraseña
                                </button>

                                <div class="text-center">
                                    <a href="<?= BASE_URL ?>/login" class="small text-decoration-none">
                                        <i class="bi bi-arrow-left me-1"></i> Cancelar y volver al login
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/recuperar.js"></script>
</body>
</html>