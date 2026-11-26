document.addEventListener('DOMContentLoaded', () => {
    const tablaRoles = document.getElementById('tablaRoles');
    const alertContainer = document.getElementById('alertContainer');
    const modalAlertContainer = document.getElementById('modalAlertContainer');
    const modalElement = document.getElementById('modalRol');
    const modalRol = new bootstrap.Modal(modalElement);
    const formRol = document.getElementById('formRol');

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

    const limpiarChecks = () => {
        document.querySelectorAll('.check-permiso').forEach((chk) => {
            chk.checked = false;
        });
    };

    const cargarRoles = async () => {
        try {
            const res = await fetch(`${BASE_URL}/api/roles`);
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'No se pudieron cargar los roles.', 'danger');
                return;
            }

            const roles = result.data?.roles || [];
            if (roles.length === 0) {
                tablaRoles.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay roles registrados.</td></tr>';
                return;
            }

            tablaRoles.innerHTML = roles.map((rol) => {
                const esAdminBase = Number(rol.id) === 1;
                return `
                    <tr>
                        <td class="fw-semibold text-muted">#${Number(rol.id)}</td>
                        <td class="fw-bold">${escapeHtml(rol.nombre)}</td>
                        <td class="text-muted small">${escapeHtml(rol.descripcion || 'Sin descripción')}</td>
                        <td class="text-center">
                            <span class="badge text-bg-info">${Number(rol.total_permisos)} permiso(s)</span>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-secondary">${Number(rol.total_usuarios)} usuario(s)</span>
                        </td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${Number(rol.id)}" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger btn-eliminar"
                                data-id="${Number(rol.id)}"
                                data-nombre="${escapeHtml(rol.nombre)}"
                                ${esAdminBase ? 'disabled title="El rol Administrador base no se puede eliminar"' : 'title="Eliminar"'}
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (error) {
            mostrarAlerta('Error de red al obtener los roles.', 'danger');
        }
    };

    // Abrir modal para crear
    document.getElementById('btnNuevoRol').addEventListener('click', () => {
        modalAlertContainer.innerHTML = '';
        formRol.reset();
        document.getElementById('rolId').value = '0';
        document.getElementById('modalRolTitulo').textContent = 'Nuevo Rol';
        limpiarChecks();
        modalRol.show();
    });

    // Botones auxiliares de selección masiva de permisos
    document.getElementById('btnMarcarTodos').addEventListener('click', () => {
        document.querySelectorAll('.check-permiso').forEach((chk) => { chk.checked = true; });
    });

    document.getElementById('btnDesmarcarTodos').addEventListener('click', limpiarChecks);

    // Delegación de eventos en botones Editar y Eliminar
    tablaRoles.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('.btn-editar');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (btnEditar) {
            const id = btnEditar.dataset.id;
            modalAlertContainer.innerHTML = '';
            limpiarChecks();

            try {
                const res = await fetch(`${BASE_URL}/api/roles/detalle?id=${encodeURIComponent(id)}`);
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo cargar el rol.', 'danger');
                    return;
                }

                const rol = result.data.rol;
                document.getElementById('rolId').value = rol.id;
                document.getElementById('rolNombre').value = rol.nombre;
                document.getElementById('rolDescripcion').value = rol.descripcion || '';
                document.getElementById('modalRolTitulo').textContent = `Editar Rol: ${rol.nombre}`;

                const permisosIds = (rol.permisos || []).map(Number);
                document.querySelectorAll('.check-permiso').forEach((chk) => {
                    chk.checked = permisosIds.includes(Number(chk.value));
                });

                modalRol.show();
            } catch (error) {
                mostrarAlerta('Error de conexión al consultar el detalle del rol.', 'danger');
            }
        }

        if (btnEliminar && !btnEliminar.disabled) {
            const id = Number(btnEliminar.dataset.id);
            const nombre = btnEliminar.dataset.nombre;

            if (!confirm(`¿Seguro que deseas eliminar el rol "${nombre}"?`)) {
                return;
            }

            try {
                const res = await fetch(`${BASE_URL}/api/roles/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo eliminar el rol.', 'danger');
                    return;
                }

                mostrarAlerta(result.message, 'success');
                cargarRoles();
            } catch (error) {
                mostrarAlerta('Error de red al intentar eliminar el rol.', 'danger');
            }
        }
    });

    // Guardar (Crear o Actualizar)
    formRol.addEventListener('submit', async (e) => {
        e.preventDefault();
        modalAlertContainer.innerHTML = '';

        const btnGuardar = document.getElementById('btnGuardarRol');
        const id = Number(document.getElementById('rolId').value);
        const nombre = document.getElementById('rolNombre').value.trim();
        const descripcion = document.getElementById('rolDescripcion').value.trim();
        const permisos = Array.from(document.querySelectorAll('.check-permiso:checked')).map((chk) => Number(chk.value));

        if (!nombre) {
            mostrarAlerta('El nombre del rol es obligatorio.', 'warning', true);
            return;
        }

        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const res = await fetch(`${BASE_URL}/api/roles/guardar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, nombre, descripcion, permisos }),
            });
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'Error al guardar el rol.', 'danger', true);
                return;
            }

            modalRol.hide();
            mostrarAlerta(result.message, 'success');
            cargarRoles();
        } catch (error) {
            mostrarAlerta('Error de conexión con el servidor.', 'danger', true);
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        }
    });

    cargarRoles();
});