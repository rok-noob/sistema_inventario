document.addEventListener('DOMContentLoaded', () => {
    const tablaAlmacenes = document.getElementById('tablaAlmacenes');
    const alertContainer = document.getElementById('alertContainer');
    const modalAlertContainer = document.getElementById('modalAlertContainer');
    const modalAlmacen = new bootstrap.Modal(document.getElementById('modalAlmacen'));
    const formAlmacen = document.getElementById('formAlmacen');
    const buscador = document.getElementById('buscadorAlmacenes');

    let listaAlmacenes = [];

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

    const renderizarTabla = () => {
        const termino = buscador.value.trim().toLowerCase();
        const filtrados = listaAlmacenes.filter((alm) =>
            alm.nombre.toLowerCase().includes(termino) ||
            (alm.ubicacion || '').toLowerCase().includes(termino)
        );

        if (filtrados.length === 0) {
            tablaAlmacenes.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron almacenes.</td></tr>';
            return;
        }

        tablaAlmacenes.innerHTML = filtrados.map((alm) => {
            const activo = Number(alm.activo) === 1;
            const accionesHtml = PUEDE_GESTIONAR
                ? `
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${Number(alm.id)}" title="Editar">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${Number(alm.id)}" data-nombre="${escapeHtml(alm.nombre)}" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                `
                : '<span class="badge text-bg-light text-muted border">Solo lectura</span>';

            return `
                <tr>
                    <td class="fw-semibold text-muted">#${Number(alm.id)}</td>
                    <td class="fw-bold">${escapeHtml(alm.nombre)}</td>
                    <td class="text-muted small">${escapeHtml(alm.ubicacion || 'Sin ubicación especificada')}</td>
                    <td class="text-center"><span class="badge text-bg-secondary">${Number(alm.total_productos)}</span></td>
                    <td class="text-center"><span class="badge text-bg-info">${Number(alm.total_unidades)}</span></td>
                    <td class="text-center">
                        <span class="badge ${activo ? 'text-bg-success' : 'text-bg-secondary'}">
                            ${activo ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                    <td class="text-end pe-3">${accionesHtml}</td>
                </tr>
            `;
        }).join('');
    };

    const cargarAlmacenes = async () => {
        try {
            const res = await fetch(`${BASE_URL}/api/almacenes`);
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'No se pudieron cargar los almacenes.', 'danger');
                return;
            }

            listaAlmacenes = result.data?.almacenes || [];
            renderizarTabla();
        } catch (error) {
            mostrarAlerta('Error de red al obtener los almacenes.', 'danger');
        }
    };

    buscador.addEventListener('input', renderizarTabla);

    const btnNuevo = document.getElementById('btnNuevoAlmacen');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => {
            modalAlertContainer.innerHTML = '';
            formAlmacen.reset();
            document.getElementById('almacenId').value = '0';
            document.getElementById('almacenActivo').value = '1';
            document.getElementById('modalAlmacenTitulo').textContent = 'Nuevo Almacén';
            modalAlmacen.show();
        });
    }

    tablaAlmacenes.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('.btn-editar');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (btnEditar) {
            const id = btnEditar.dataset.id;
            modalAlertContainer.innerHTML = '';

            try {
                const res = await fetch(`${BASE_URL}/api/almacenes/detalle?id=${encodeURIComponent(id)}`);
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo cargar el almacén.', 'danger');
                    return;
                }

                const alm = result.data.almacen;
                document.getElementById('almacenId').value = alm.id;
                document.getElementById('almacenNombre').value = alm.nombre;
                document.getElementById('almacenUbicacion').value = alm.ubicacion || '';
                document.getElementById('almacenActivo').value = String(alm.activo);
                document.getElementById('modalAlmacenTitulo').textContent = `Editar Almacén: ${alm.nombre}`;
                modalAlmacen.show();
            } catch (error) {
                mostrarAlerta('Error de conexión al consultar el almacén.', 'danger');
            }
        }

        if (btnEliminar) {
            const id = Number(btnEliminar.dataset.id);
            const nombre = btnEliminar.dataset.nombre;

            if (!confirm(`¿Seguro que deseas eliminar el almacén "${nombre}"?`)) {
                return;
            }

            try {
                const res = await fetch(`${BASE_URL}/api/almacenes/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo eliminar el almacén.', 'danger');
                    return;
                }

                mostrarAlerta(result.message, 'success');
                cargarAlmacenes();
            } catch (error) {
                mostrarAlerta('Error de red al intentar eliminar.', 'danger');
            }
        }
    });

    formAlmacen.addEventListener('submit', async (e) => {
        e.preventDefault();
        modalAlertContainer.innerHTML = '';

        const btnGuardar = document.getElementById('btnGuardarAlmacen');
        const id = Number(document.getElementById('almacenId').value);
        const nombre = document.getElementById('almacenNombre').value.trim();
        const ubicacion = document.getElementById('almacenUbicacion').value.trim();
        const activo = Number(document.getElementById('almacenActivo').value);

        if (!nombre) {
            mostrarAlerta('El nombre del almacén es obligatorio.', 'warning', true);
            return;
        }

        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const res = await fetch(`${BASE_URL}/api/almacenes/guardar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, nombre, ubicacion, activo }),
            });
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'Error al guardar el almacén.', 'danger', true);
                return;
            }

            modalAlmacen.hide();
            mostrarAlerta(result.message, 'success');
            cargarAlmacenes();
        } catch (error) {
            mostrarAlerta('Error de conexión con el servidor.', 'danger', true);
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        }
    });

    cargarAlmacenes();
});