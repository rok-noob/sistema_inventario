document.addEventListener('DOMContentLoaded', () => {
    const formLogin = document.getElementById('formLogin');
    const alertContainer = document.getElementById('alertContainer');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnAceptarCookies = document.getElementById('btnAceptarCookies');

    const mostrarAlerta = (mensaje, tipo = 'danger') => {
        alertContainer.innerHTML = `
            <div class="alert alert-${tipo} py-2 small" role="alert">
                ${mensaje}
            </div>
        `;
    };

    if (formLogin) {
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertContainer.innerHTML = '';

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const recordar = document.getElementById('recordar').checked;

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verificando...';

            try {
                const response = await fetch(`${BASE_URL}/api/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password, recordar })
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo iniciar sesión.');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Entrar al sistema';
                    return;
                }

                mostrarAlerta(result.message, 'success');
                window.location.href = result.data.redirect;
            } catch (error) {
                mostrarAlerta('Error de conexión con el servidor.');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Entrar al sistema';
            }
        });
    }

    if (btnAceptarCookies) {
        btnAceptarCookies.addEventListener('click', async () => {
            try {
                const res = await fetch(`${BASE_URL}/api/cookies`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion: 'aceptar' })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('cookieBanner')?.remove();
                }
            } catch (err) {
                console.error('Error al guardar cookie:', err);
            }
        });
    }
});