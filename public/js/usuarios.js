document.addEventListener('DOMContentLoaded', () => {
    const tablaUsuarios = document.getElementById('tablaUsuarios');
    const alertContainer = document.getElementById('alertContainer');
    const modalAlertContainer = document.getElementById('modalAlertContainer');
    const modalElement = document.getElementById('modalUsuario');
    const modalUsuario = new bootstrap.Modal(modalElement);
    const formUsuario = document.getElementById('formUsuario');

    const escapeHtml = (str) => {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const mostrarAlerta = (mensaje, tipo = 'success', enModal = false) => {
        const contenedor = enModal ? modalAlertContainer : alertContainer;
        contenedor.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show small" role="alert">
                ${escapeHtml(mensaje)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
    };

    const cargarUsuarios = async () => {
        try {
            const res = await fetch(`${BASE_URL}/api/usuarios`);
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'No se pudieron cargar los usuarios.', 'danger');
                return;
            }

            const usuarios = result.data?.usuarios || [];
            if (usuarios.length === 0) {
                tablaUsuarios.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay usuarios registrados.</td></tr>';
                return;
            }

            tablaUsuarios.innerHTML = usuarios.map((u) => {
                const esMismoUsuario = Number(u.id) === Number(CURRENT_USER_ID);
                const badgeEstado = Number(u.activo) === 1
                    ? '<span class="badge text-bg-success">Activo</span>'
                    : '<span class="badge text-bg-secondary">Inactivo</span>';

                return `
                    <tr>
                        <td class="fw-semibold text-muted">#${Number(u.id)}</td>
                        <td class="fw-bold">
                            ${escapeHtml(u.nombre)}
                            ${esMismoUsuario ? '<span class="badge bg-warning text-dark ms-1">Tú</span>' : ''}
                        </td>
                        <td>${escapeHtml(u.email)}</td>
                        <td><span class="badge text-bg-primary">${escapeHtml(u.rol_nombre)}</span></td>
                        <td class="text-center">${badgeEstado}</td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${Number(u.id)}" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger btn-eliminar"
                                data-id="${Number(u.id)}"
                                data-nombre="${escapeHtml(u.nombre)}"
                                ${esMismoUsuario ? 'disabled title="No puedes eliminar tu propio usuario"' : 'title="Eliminar"'}
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (error) {
            mostrarAlerta('Error de red al obtener los usuarios.', 'danger');
        }
    };

    // Modal para nuevo usuario
    document.getElementById('btnNuevoUsuario').addEventListener('click', () => {
        modalAlertContainer.innerHTML = '';
        formUsuario.reset();
        document.getElementById('usuarioId').value = '0';
        document.getElementById('usuarioActivo').checked = true;
        document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo Usuario';
        document.getElementById('labelPassword').textContent = 'Contraseña *';
        document.getElementById('ayudaPassword').textContent = 'Mínimo 8 caracteres (obligatoria al crear).';
        modalUsuario.show();
    });

    // Delegación para Editar y Eliminar
    tablaUsuarios.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('.btn-editar');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (btnEditar) {
            const id = btnEditar.dataset.id;
            modalAlertContainer.innerHTML = '';
            formUsuario.reset();

            try {
                const res = await fetch(`${BASE_URL}/api/usuarios/detalle?id=${encodeURIComponent(id)}`);
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo cargar el usuario.', 'danger');
                    return;
                }

                const u = result.data.usuario;
                document.getElementById('usuarioId').value = u.id;
                document.getElementById('usuarioNombre').value = u.nombre;
                document.getElementById('usuarioEmail').value = u.email;
                document.getElementById('usuarioRol').value = u.rol_id;
                document.getElementById('usuarioActivo').checked = Number(u.activo) === 1;
                document.getElementById('modalUsuarioTitulo').textContent = `Editar Usuario: ${u.nombre}`;
                document.getElementById('labelPassword').textContent = 'Nueva contraseña (opcional)';
                document.getElementById('ayudaPassword').textContent = 'Déjalo en blanco para conservar su contraseña actual.';

                modalUsuario.show();
            } catch (error) {
                mostrarAlerta('Error de conexión al consultar el usuario.', 'danger');
            }
        }

        if (btnEliminar && !btnEliminar.disabled) {
            const id = Number(btnEliminar.dataset.id);
            const nombre = btnEliminar.dataset.nombre;

            if (!confirm(`¿Seguro que deseas eliminar al usuario "${nombre}"?`)) {
                return;
            }

            try {
                const res = await fetch(`${BASE_URL}/api/usuarios/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo eliminar el usuario.', 'danger');
                    return;
                }

                mostrarAlerta(result.message, 'success');
                cargarUsuarios();
            } catch (error) {
                mostrarAlerta('Error de red al intentar eliminar el usuario.', 'danger');
            }
        }
    });

    // Guardar usuario
    formUsuario.addEventListener('submit', async (e) => {
        e.preventDefault();
        modalAlertContainer.innerHTML = '';

        const btnGuardar = document.getElementById('btnGuardarUsuario');
        const id = Number(document.getElementById('usuarioId').value);
        const nombre = document.getElementById('usuarioNombre').value.trim();
        const email = document.getElementById('usuarioEmail').value.trim();
        const rol_id = Number(document.getElementById('usuarioRol').value);
        const password = document.getElementById('usuarioPassword').value;
        const activo = document.getElementById('usuarioActivo').checked ? 1 : 0;

        if (!nombre || !email || rol_id <= 0) {
            mostrarAlerta('Completa nombre, correo y rol.', 'warning', true);
            return;
        }

        if (id === 0 && password.length < 8) {
            mostrarAlerta('La contraseña inicial debe tener al menos 8 caracteres.', 'warning', true);
            return;
        }

        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const res = await fetch(`${BASE_URL}/api/usuarios/guardar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, nombre, email, rol_id, password, activo }),
            });
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'No se pudo guardar el usuario.', 'danger', true);
                return;
            }

            modalUsuario.hide();
            mostrarAlerta(result.message, 'success');
            cargarUsuarios();
        } catch (error) {
            mostrarAlerta('Error de conexión con el servidor.', 'danger', true);
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        }
    });

    cargarUsuarios();
});