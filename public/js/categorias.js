document.addEventListener('DOMContentLoaded', () => {
    const tablaCategorias = document.getElementById('tablaCategorias');
    const alertContainer = document.getElementById('alertContainer');
    const modalAlertContainer = document.getElementById('modalAlertContainer');
    const modalCategoria = new bootstrap.Modal(document.getElementById('modalCategoria'));
    const formCategoria = document.getElementById('formCategoria');
    const buscador = document.getElementById('buscadorCategorias');

    let listaCategorias = [];

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
        const filtradas = listaCategorias.filter((cat) =>
            cat.nombre.toLowerCase().includes(termino) ||
            (cat.descripcion || '').toLowerCase().includes(termino)
        );

        if (filtradas.length === 0) {
            tablaCategorias.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron categorías.</td></tr>';
            return;
        }

        tablaCategorias.innerHTML = filtradas.map((cat) => {
            const activa = Number(cat.activo) === 1;
            const accionesHtml = PUEDE_GESTIONAR
                ? `
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${Number(cat.id)}" title="Editar">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${Number(cat.id)}" data-nombre="${escapeHtml(cat.nombre)}" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                `
                : '<span class="badge text-bg-light text-muted border">Solo lectura</span>';

            return `
                <tr>
                    <td class="fw-semibold text-muted">#${Number(cat.id)}</td>
                    <td class="fw-bold">${escapeHtml(cat.nombre)}</td>
                    <td class="text-muted small">${escapeHtml(cat.descripcion || 'Sin descripción')}</td>
                    <td class="text-center"><span class="badge text-bg-info">${Number(cat.total_productos)}</span></td>
                    <td class="text-center">
                        <span class="badge ${activa ? 'text-bg-success' : 'text-bg-secondary'}">
                            ${activa ? 'Activa' : 'Inactiva'}
                        </span>
                    </td>
                    <td class="text-end pe-3">${accionesHtml}</td>
                </tr>
            `;
        }).join('');
    };

    const cargarCategorias = async () => {
        try {
            const res = await fetch(`${BASE_URL}/api/categorias`);
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'No se pudieron cargar las categorías.', 'danger');
                return;
            }

            listaCategorias = result.data?.categorias || [];
            renderizarTabla();
        } catch (error) {
            mostrarAlerta('Error de red al obtener las categorías.', 'danger');
        }
    };

    buscador.addEventListener('input', renderizarTabla);

    const btnNueva = document.getElementById('btnNuevaCategoria');
    if (btnNueva) {
        btnNueva.addEventListener('click', () => {
            modalAlertContainer.innerHTML = '';
            formCategoria.reset();
            document.getElementById('categoriaId').value = '0';
            document.getElementById('categoriaActivo').value = '1';
            document.getElementById('modalCategoriaTitulo').textContent = 'Nueva Categoría';
            modalCategoria.show();
        });
    }

    tablaCategorias.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('.btn-editar');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (btnEditar) {
            const id = btnEditar.dataset.id;
            modalAlertContainer.innerHTML = '';

            try {
                const res = await fetch(`${BASE_URL}/api/categorias/detalle?id=${encodeURIComponent(id)}`);
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo cargar la categoría.', 'danger');
                    return;
                }

                const cat = result.data.categoria;
                document.getElementById('categoriaId').value = cat.id;
                document.getElementById('categoriaNombre').value = cat.nombre;
                document.getElementById('categoriaDescripcion').value = cat.descripcion || '';
                document.getElementById('categoriaActivo').value = String(cat.activo);
                document.getElementById('modalCategoriaTitulo').textContent = `Editar Categoría: ${cat.nombre}`;
                modalCategoria.show();
            } catch (error) {
                mostrarAlerta('Error de conexión al consultar la categoría.', 'danger');
            }
        }

        if (btnEliminar) {
            const id = Number(btnEliminar.dataset.id);
            const nombre = btnEliminar.dataset.nombre;

            if (!confirm(`¿Seguro que deseas eliminar la categoría "${nombre}"?`)) {
                return;
            }

            try {
                const res = await fetch(`${BASE_URL}/api/categorias/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo eliminar la categoría.', 'danger');
                    return;
                }

                mostrarAlerta(result.message, 'success');
                cargarCategorias();
            } catch (error) {
                mostrarAlerta('Error de red al intentar eliminar.', 'danger');
            }
        }
    });

    formCategoria.addEventListener('submit', async (e) => {
        e.preventDefault();
        modalAlertContainer.innerHTML = '';

        const btnGuardar = document.getElementById('btnGuardarCategoria');
        const id = Number(document.getElementById('categoriaId').value);
        const nombre = document.getElementById('categoriaNombre').value.trim();
        const descripcion = document.getElementById('categoriaDescripcion').value.trim();
        const activo = Number(document.getElementById('categoriaActivo').value);

        if (!nombre) {
            mostrarAlerta('El nombre de la categoría es obligatorio.', 'warning', true);
            return;
        }

        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const res = await fetch(`${BASE_URL}/api/categorias/guardar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, nombre, descripcion, activo }),
            });
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'Error al guardar la categoría.', 'danger', true);
                return;
            }

            modalCategoria.hide();
            mostrarAlerta(result.message, 'success');
            cargarCategorias();
        } catch (error) {
            mostrarAlerta('Error de conexión con el servidor.', 'danger', true);
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        }
    });

    cargarCategorias();
});