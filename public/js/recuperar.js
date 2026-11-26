document.addEventListener('DOMContentLoaded', () => {
    const alertContainer = document.getElementById('alertContainer');
    const formRecuperar = document.getElementById('formRecuperar');
    const formRestablecer = document.getElementById('formRestablecer');

    const mostrarAlerta = (mensaje, tipo = 'danger') => {
        if (!alertContainer) return;
        alertContainer.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show small" role="alert">
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
    };

    // 1. Flujo de solicitud de enlace (/recuperar -> POST /api/recuperar)
    if (formRecuperar) {
        formRecuperar.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btnSubmit = document.getElementById('btnSubmit');
            const email = document.getElementById('email').value.trim();

            if (!email) {
                mostrarAlerta('Escribe tu correo electrónico.', 'warning');
                return;
            }

            const textoOriginal = btnSubmit.innerHTML;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando correo...';

            try {
                const response = await fetch(`${BASE_URL}/api/recuperar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email }),
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo procesar la solicitud.', 'danger');
                    return;
                }

                mostrarAlerta(result.message, 'success');
                formRecuperar.reset();
            } catch (error) {
                mostrarAlerta('Error de conexión con el servidor. Verifica Apache en XAMPP.', 'danger');
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = textoOriginal;
            }
        });
    }

    // 2. Flujo de cambio de contraseña (/restablecer -> POST /api/restablecer)
    if (formRestablecer) {
        formRestablecer.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btnSubmit = document.getElementById('btnSubmit');
            const token = document.getElementById('token').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            if (password.length < 8) {
                mostrarAlerta('La contraseña debe tener al menos 8 caracteres.', 'warning');
                return;
            }

            if (password !== passwordConfirm) {
                mostrarAlerta('Las contraseñas no coinciden.', 'warning');
                return;
            }

            const textoOriginal = btnSubmit.innerHTML;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            try {
                const response = await fetch(`${BASE_URL}/api/restablecer`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token,
                        password,
                        password_confirm: passwordConfirm,
                    }),
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo restablecer la contraseña.', 'danger');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = textoOriginal;
                    return;
                }

                mostrarAlerta(result.message + ' Redirigiendo al login...', 'success');

                setTimeout(() => {
                    window.location.href = result.data?.redirect || `${BASE_URL}/login`;
                }, 1800);
            } catch (error) {
                mostrarAlerta('Error de conexión con el servidor.', 'danger');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = textoOriginal;
            }
        });
    }
});