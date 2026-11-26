<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | Sistema de Inventario</title>
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
                            <div class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                                <i class="bi bi-key fs-3"></i>
                            </div>
                            <h1 class="h4 fw-bold mb-1">Recuperar Contraseña</h1>
                            <p class="text-muted small mb-0">
                                Escribe tu correo registrado y te enviaremos un enlace temporal para cambiar tu clave.
                            </p>
                        </div>

                        <div id="alertContainer"></div>

                        <form id="formRecuperar" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">Correo electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        placeholder="usuario@dominio.com"
                                        required
                                    >
                                </div>
                            </div>

                            <button type="submit" id="btnSubmit" class="btn btn-primary w-100 fw-semibold py-2 mb-3">
                                <i class="bi bi-send me-1"></i> Enviar enlace de recuperación
                            </button>

                            <div class="text-center">
                                <a href="<?= BASE_URL ?>/login" class="small text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i> Volver al inicio de sesión
                                </a>
                            </div>
                        </form>
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