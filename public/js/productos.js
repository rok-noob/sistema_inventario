document.addEventListener('DOMContentLoaded', () => {
    const tablaProductos = document.getElementById('tablaProductos');
    const alertContainer = document.getElementById('alertContainer');
    const modalAlertContainer = document.getElementById('modalAlertContainer');
    const modalProducto = new bootstrap.Modal(document.getElementById('modalProducto'));
    const formProducto = document.getElementById('formProducto');
    const buscador = document.getElementById('buscadorProductos');
    const filtroCategoria = document.getElementById('filtroCategoria');

    let listaProductos = [];

    const escapeHtml = (str) => {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const formatoMoneda = (valor) => {
        return Number(valor).toLocaleString('es-MX', {
            style: 'currency',
            currency: 'MXN',
        });
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
        const catId = Number(filtroCategoria.value);

        const filtrados = listaProductos.filter((prod) => {
            const coincideTexto =
                prod.sku.toLowerCase().includes(termino) ||
                prod.nombre.toLowerCase().includes(termino) ||
                (prod.descripcion || '').toLowerCase().includes(termino);
            const coincideCat = catId === 0 || Number(prod.categoria_id) === catId;
            return coincideTexto && coincideCat;
        });

        if (filtrados.length === 0) {
            tablaProductos.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron productos con esos filtros.</td></tr>';
            return;
        }

        tablaProductos.innerHTML = filtrados.map((prod) => {
            const activo = Number(prod.activo) === 1;
            const stockTotal = Number(prod.stock_total);
            const stockMin = Number(prod.stock_minimo);
            const bajoStock = stockTotal <= stockMin;

            const accionesHtml = PUEDE_GESTIONAR
                ? `
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar" data-id="${Number(prod.id)}" title="Editar">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${Number(prod.id)}" data-nombre="${escapeHtml(prod.nombre)}" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                `
                : '<span class="badge text-bg-light text-muted border">Solo lectura</span>';

            return `
                <tr>
                    <td><span class="badge text-bg-dark font-monospace">${escapeHtml(prod.sku)}</span></td>
                    <td>
                        <div class="fw-bold">${escapeHtml(prod.nombre)}</div>
                        ${prod.descripcion ? `<div class="text-muted small text-truncate" style="max-width: 280px;">${escapeHtml(prod.descripcion)}</div>` : ''}
                    </td>
                    <td><span class="badge text-bg-light border text-dark">${escapeHtml(prod.categoria_nombre)}</span></td>
                    <td class="text-end fw-semibold">${formatoMoneda(prod.precio_unitario)}</td>
                    <td class="text-center">
                        <span class="badge ${bajoStock ? 'text-bg-danger' : 'text-bg-success'}" title="${bajoStock ? 'Stock en nivel mínimo o agotado' : 'Stock saludable'}">
                            ${stockTotal}
                        </span>
                        <small class="text-muted">/ ${stockMin}</small>
                    </td>
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

    const cargarProductos = async () => {
        try {
            const res = await fetch(`${BASE_URL}/api/productos`);
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'No se pudieron cargar los productos.', 'danger');
                return;
            }

            listaProductos = result.data?.productos || [];
            renderizarTabla();
        } catch (error) {
            mostrarAlerta('Error de red al obtener los productos.', 'danger');
        }
    };

    buscador.addEventListener('input', renderizarTabla);
    filtroCategoria.addEventListener('change', renderizarTabla);

    const btnNuevo = document.getElementById('btnNuevoProducto');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => {
            modalAlertContainer.innerHTML = '';
            formProducto.reset();
            document.getElementById('productoId').value = '0';
            document.getElementById('productoPrecio').value = '0.00';
            document.getElementById('productoStockMinimo').value = '5';
            document.getElementById('productoActivo').value = '1';
            document.getElementById('modalProductoTitulo').textContent = 'Nuevo Producto';
            modalProducto.show();
        });
    }

    tablaProductos.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('.btn-editar');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (btnEditar) {
            const id = btnEditar.dataset.id;
            modalAlertContainer.innerHTML = '';

            try {
                const res = await fetch(`${BASE_URL}/api/productos/detalle?id=${encodeURIComponent(id)}`);
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo cargar el producto.', 'danger');
                    return;
                }

                const prod = result.data.producto;
                document.getElementById('productoId').value = prod.id;
                document.getElementById('productoSku').value = prod.sku;
                document.getElementById('productoNombre').value = prod.nombre;
                document.getElementById('productoCategoriaId').value = String(prod.categoria_id);
                document.getElementById('productoPrecio').value = Number(prod.precio_unitario).toFixed(2);
                document.getElementById('productoStockMinimo').value = String(prod.stock_minimo);
                document.getElementById('productoActivo').value = String(prod.activo);
                document.getElementById('productoDescripcion').value = prod.descripcion || '';
                document.getElementById('modalProductoTitulo').textContent = `Editar Producto: ${prod.sku}`;
                modalProducto.show();
            } catch (error) {
                mostrarAlerta('Error de conexión al consultar el producto.', 'danger');
            }
        }

        if (btnEliminar) {
            const id = Number(btnEliminar.dataset.id);
            const nombre = btnEliminar.dataset.nombre;

            if (!confirm(`¿Seguro que deseas eliminar el producto "${nombre}"?`)) {
                return;
            }

            try {
                const res = await fetch(`${BASE_URL}/api/productos/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                const result = await res.json();

                if (!res.ok || !result.success) {
                    mostrarAlerta(result.message || 'No se pudo eliminar el producto.', 'danger');
                    return;
                }

                mostrarAlerta(result.message, 'success');
                cargarProductos();
            } catch (error) {
                mostrarAlerta('Error de red al intentar eliminar.', 'danger');
            }
        }
    });

    formProducto.addEventListener('submit', async (e) => {
        e.preventDefault();
        modalAlertContainer.innerHTML = '';

        const btnGuardar = document.getElementById('btnGuardarProducto');
        const id = Number(document.getElementById('productoId').value);
        const sku = document.getElementById('productoSku').value.trim().toUpperCase();
        const nombre = document.getElementById('productoNombre').value.trim();
        const categoria_id = Number(document.getElementById('productoCategoriaId').value);
        const precio_unitario = parseFloat(document.getElementById('productoPrecio').value);
        const stock_minimo = parseInt(document.getElementById('productoStockMinimo').value, 10);
        const activo = Number(document.getElementById('productoActivo').value);
        const descripcion = document.getElementById('productoDescripcion').value.trim();

        if (!sku || !nombre || !categoria_id) {
            mostrarAlerta('Completa el SKU, nombre y categoría obligatorios.', 'warning', true);
            return;
        }

        if (Number.isNaN(precio_unitario) || precio_unitario < 0 || Number.isNaN(stock_minimo) || stock_minimo < 0) {
            mostrarAlerta('Verifica que el precio y el stock mínimo sean números válidos mayores o iguales a 0.', 'warning', true);
            return;
        }

        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const res = await fetch(`${BASE_URL}/api/productos/guardar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id,
                    sku,
                    nombre,
                    categoria_id,
                    precio_unitario,
                    stock_minimo,
                    activo,
                    descripcion,
                }),
            });
            const result = await res.json();

            if (!res.ok || !result.success) {
                mostrarAlerta(result.message || 'Error al guardar el producto.', 'danger', true);
                return;
            }

            modalProducto.hide();
            mostrarAlerta(result.message, 'success');
            cargarProductos();
        } catch (error) {
            mostrarAlerta('Error de conexión con el servidor.', 'danger', true);
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        }
    });

    cargarProductos();
});