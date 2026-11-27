document.addEventListener('DOMContentLoaded', () => {
    const tablaMovimientos = document.getElementById('tablaMovimientos');
    const buscadorMovimientos = document.getElementById('buscadorMovimientos');
    const filtroTipo = document.getElementById('filtroTipo');
    const filtroAlmacen = document.getElementById('filtroAlmacen');
    const alertContainer = document.getElementById('alertContainer');

    // Elementos del modal de registro (pueden no existir si el rol es solo Auditor)
    const modalMovimientoEl = document.getElementById('modalMovimiento');
    const modalMovimiento = modalMovimientoEl ? new bootstrap.Modal(modalMovimientoEl) : null;
    const modalMovimientoTitulo = document.getElementById('modalMovimientoTitulo');
    const modalMovAlertContainer = document.getElementById('modalMovAlertContainer');
    const formMovimiento = document.getElementById('formMovimiento');
    const movTipo = document.getElementById('movTipo');
    const grupoAlmacenOrigen = document.getElementById('grupoAlmacenOrigen');
    const grupoAlmacenDestino = document.getElementById('grupoAlmacenDestino');
    const movAlmacenOrigen = document.getElementById('movAlmacenOrigen');
    const movAlmacenDestino = document.getElementById('movAlmacenDestino');
    const movObservaciones = document.getElementById('movObservaciones');

    const selectProductoPartida = document.getElementById('selectProductoPartida');
    const inputCantidadPartida = document.getElementById('inputCantidadPartida');
    const inputCostoPartida = document.getElementById('inputCostoPartida');
    const btnAgregarPartida = document.getElementById('btnAgregarPartida');
    const tablaPartidasMovimiento = document.getElementById('tablaPartidasMovimiento');
    const totalUnidadesPartidas = document.getElementById('totalUnidadesPartidas');
    const totalMontoPartidas = document.getElementById('totalMontoPartidas');
    const btnGuardarMovimiento = document.getElementById('btnGuardarMovimiento');

    // Modal detalle
    const modalDetalleEl = document.getElementById('modalDetalleMovimiento');
    const modalDetalle = new bootstrap.Modal(modalDetalleEl);
    const detalleMovTitulo = document.getElementById('detalleMovTitulo');
    const detalleMovBody = document.getElementById('detalleMovBody');

    let movimientosCache = [];
    let partidas = [];
    let stockOrigenMap = new Map();

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function formatMoney(value) {
        return '$' + Number(value || 0).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showAlert(container, message, type = 'success') {
        container.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show py-2" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
    }

    function badgeTipo(tipo) {
        if (tipo === 'ENTRADA') {
            return '<span class="badge bg-success"><i class="bi bi-box-arrow-in-down me-1"></i>ENTRADA</span>';
        }
        if (tipo === 'SALIDA') {
            return '<span class="badge bg-danger"><i class="bi bi-box-arrow-up me-1"></i>SALIDA</span>';
        }
        return '<span class="badge bg-primary"><i class="bi bi-arrow-left-right me-1"></i>TRANSFERENCIA</span>';
    }

    function describirRutaAlmacenes(mov) {
        if (mov.tipo === 'ENTRADA') {
            return `<span class="text-muted">Proveedor / Externo</span> <i class="bi bi-arrow-right mx-1 text-success"></i> <strong>${escapeHtml(mov.almacen_destino_nombre)}</strong>`;
        }
        if (mov.tipo === 'SALIDA') {
            return `<strong>${escapeHtml(mov.almacen_origen_nombre)}</strong> <i class="bi bi-arrow-right mx-1 text-danger"></i> <span class="text-muted">Despacho / Baja</span>`;
        }
        return `<strong>${escapeHtml(mov.almacen_origen_nombre)}</strong> <i class="bi bi-arrow-right mx-1 text-primary"></i> <strong>${escapeHtml(mov.almacen_destino_nombre)}</strong>`;
    }

    async function cargarMovimientos() {
        const params = new URLSearchParams();
        if (filtroTipo.value) {
            params.set('tipo', filtroTipo.value);
        }
        if (filtroAlmacen.value && filtroAlmacen.value !== '0') {
            params.set('almacen_id', filtroAlmacen.value);
        }

        const queryString = params.toString() ? `?${params.toString()}` : '';

        try {
            const response = await fetch(`${BASE_URL}/api/movimientos${queryString}`);
            const result = await response.json();

            if (!response.ok || !result.success) {
                showAlert(alertContainer, result.message || 'No se pudo cargar el historial.', 'danger');
                return;
            }

            movimientosCache = result.data || [];
            renderTablaMovimientos();
        } catch (error) {
            showAlert(alertContainer, 'Error de conexión al cargar los movimientos.', 'danger');
        }
    }

    function renderTablaMovimientos() {
        const texto = buscadorMovimientos.value.trim().toLowerCase();

        const filtrados = movimientosCache.filter((m) => {
            if (!texto) return true;
            const folio = `#${m.id}`;
            return (
                folio.includes(texto) ||
                String(m.id).includes(texto) ||
                (m.usuario_nombre || '').toLowerCase().includes(texto) ||
                (m.observaciones || '').toLowerCase().includes(texto) ||
                (m.almacen_origen_nombre || '').toLowerCase().includes(texto) ||
                (m.almacen_destino_nombre || '').toLowerCase().includes(texto)
            );
        });

        if (filtrados.length === 0) {
            tablaMovimientos.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No hay movimientos registrados con los filtros seleccionados.</td>
                </tr>
            `;
            return;
        }

        tablaMovimientos.innerHTML = filtrados.map((m) => `
            <tr>
                <td class="fw-bold text-dark">#${Number(m.id)}</td>
                <td class="small text-muted">${escapeHtml(m.fecha)}</td>
                <td>${badgeTipo(m.tipo)}</td>
                <td class="small">${describirRutaAlmacenes(m)}</td>
                <td class="text-center">
                    <span class="badge bg-secondary">${Number(m.total_partidas)} prod.</span>
                    <span class="badge bg-light text-dark border">${Number(m.total_unidades)} u.</span>
                </td>
                <td class="text-end fw-semibold">${formatMoney(m.monto_total)}</td>
                <td class="small">${escapeHtml(m.usuario_nombre)}</td>
                <td class="text-end pe-3">
                    <button type="button" class="btn btn-sm btn-outline-dark btn-ver-detalle" data-id="${Number(m.id)}" title="Ver detalle">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    async function verDetalleMovimiento(id) {
        detalleMovTitulo.textContent = `Detalle del Movimiento #${id}`;
        detalleMovBody.innerHTML = '<p class="text-center text-muted py-4">Consultando partidas...</p>';
        modalDetalle.show();

        try {
            const response = await fetch(`${BASE_URL}/api/movimientos/detalle?id=${encodeURIComponent(id)}`);
            const result = await response.json();

            if (!response.ok || !result.success) {
                detalleMovBody.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(result.message || 'Error al cargar detalle.')}</div>`;
                return;
            }

            const mov = result.data;
            const detalles = mov.detalles || [];
            let totalUnidades = 0;
            let totalMonto = 0;

            const filas = detalles.map((d) => {
                const cant = Number(d.cantidad);
                const sub = Number(d.subtotal);
                totalUnidades += cant;
                totalMonto += sub;

                return `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(d.sku)}</td>
                        <td>${escapeHtml(d.producto_nombre)}</td>
                        <td class="text-center">${cant}</td>
                        <td class="text-end">${formatMoney(d.costo_unitario)}</td>
                        <td class="text-end fw-semibold">${formatMoney(sub)}</td>
                    </tr>
                `;
            }).join('');

            detalleMovBody.innerHTML = `
                <div class="row g-2 mb-3 small">
                    <div class="col-6 col-md-4"><strong>Tipo:</strong> ${badgeTipo(mov.tipo)}</div>
                    <div class="col-6 col-md-4"><strong>Fecha:</strong> ${escapeHtml(mov.fecha)}</div>
                    <div class="col-12 col-md-4"><strong>Registró:</strong> ${escapeHtml(mov.usuario_nombre)}</div>
                    <div class="col-12"><strong>Ruta:</strong> ${describirRutaAlmacenes(mov)}</div>
                    <div class="col-12"><strong>Observaciones:</strong> ${mov.observaciones ? escapeHtml(mov.observaciones) : '<span class="text-muted">Sin observaciones</span>'}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Costo Unit.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2" class="text-end">Total:</td>
                                <td class="text-center">${totalUnidades}</td>
                                <td></td>
                                <td class="text-end">${formatMoney(totalMonto)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;
        } catch (error) {
            detalleMovBody.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión al consultar el detalle.</div>';
        }
    }

    // Lógica del Modal de Registro (solo si el usuario tiene algún permiso de escritura)
    if (modalMovimiento && formMovimiento) {
        function poblarSelectorProductos() {
            const tipo = movTipo.value;
            const tieneOrigen = (tipo === 'SALIDA' || tipo === 'TRANSFERENCIA') && Number(movAlmacenOrigen.value) > 0;

            const opciones = ['<option value="">Selecciona un producto...</option>'];
            PRODUCTOS_CATALOGO.forEach((p) => {
                const id = Number(p.id);
                let extra = '';
                if (tieneOrigen) {
                    const disp = stockOrigenMap.has(id) ? stockOrigenMap.get(id) : 0;
                    extra = ` (Disp: ${disp})`;
                }
                opciones.push(
                    `<option value="${id}" data-precio="${Number(p.precio_unitario)}">[${escapeHtml(p.sku)}] ${escapeHtml(p.nombre)}${extra}</option>`
                );
            });

            selectProductoPartida.innerHTML = opciones.join('');
            inputCostoPartida.value = '0.00';
        }

        async function cargarStockOrigen() {
            stockOrigenMap.clear();
            const origenId = Number(movAlmacenOrigen.value);

            if (origenId <= 0) {
                poblarSelectorProductos();
                return;
            }

            try {
                const response = await fetch(`${BASE_URL}/api/movimientos/stock-almacen?almacen_id=${origenId}`);
                const result = await response.json();
                if (response.ok && result.success && Array.isArray(result.data)) {
                    result.data.forEach((row) => {
                        stockOrigenMap.set(Number(row.producto_id), Number(row.stock_actual));
                    });
                }
            } catch (error) {
                // Si falla la consulta auxiliar, el backend igual valida stock en la transacción
            }

            poblarSelectorProductos();
        }

        function actualizarInterfazPorTipo() {
            const tipo = movTipo.value;
            modalMovAlertContainer.innerHTML = '';

            if (tipo === 'ENTRADA') {
                modalMovimientoTitulo.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i> Registrar Entrada de Inventario';
                grupoAlmacenOrigen.classList.add('d-none');
                grupoAlmacenDestino.classList.remove('d-none');
                inputCostoPartida.readOnly = false;
                stockOrigenMap.clear();
                poblarSelectorProductos();
            } else if (tipo === 'SALIDA') {
                modalMovimientoTitulo.innerHTML = '<i class="bi bi-box-arrow-up me-1"></i> Registrar Salida de Inventario';
                grupoAlmacenOrigen.classList.remove('d-none');
                grupoAlmacenDestino.classList.add('d-none');
                inputCostoPartida.readOnly = true;
                cargarStockOrigen();
            } else {
                modalMovimientoTitulo.innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Registrar Transferencia entre Almacenes';
                grupoAlmacenOrigen.classList.remove('d-none');
                grupoAlmacenDestino.classList.remove('d-none');
                inputCostoPartida.readOnly = true;
                cargarStockOrigen();
            }
        }

        function renderPartidas() {
            if (partidas.length === 0) {
                tablaPartidasMovimiento.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">Aún no hay productos agregados a este movimiento.</td>
                    </tr>
                `;
                totalUnidadesPartidas.textContent = '0';
                totalMontoPartidas.textContent = '$0.00';
                return;
            }

            let sumUnidades = 0;
            let sumMonto = 0;

            tablaPartidasMovimiento.innerHTML = partidas.map((item, idx) => {
                const subtotal = item.cantidad * item.costo_unitario;
                sumUnidades += item.cantidad;
                sumMonto += subtotal;

                return `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(item.sku)}</td>
                        <td>${escapeHtml(item.nombre)}</td>
                        <td class="text-center">${item.cantidad}</td>
                        <td class="text-end">${formatMoney(item.costo_unitario)}</td>
                        <td class="text-end fw-semibold">${formatMoney(subtotal)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-partida" data-index="${idx}" title="Quitar">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            totalUnidadesPartidas.textContent = String(sumUnidades);
            totalMontoPartidas.textContent = formatMoney(sumMonto);
        }

        document.querySelectorAll('.btn-nuevo-movimiento').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tipoSeleccionado = btn.getAttribute('data-tipo');
                formMovimiento.reset();
                partidas = [];
                renderPartidas();

                if (tipoSeleccionado) {
                    movTipo.value = tipoSeleccionado;
                }
                actualizarInterfazPorTipo();
                modalMovimiento.show();
            });
        });

        movTipo.addEventListener('change', () => {
            partidas = [];
            renderPartidas();
            actualizarInterfazPorTipo();
        });

        movAlmacenOrigen.addEventListener('change', () => {
            if (movTipo.value === 'SALIDA' || movTipo.value === 'TRANSFERENCIA') {
                cargarStockOrigen();
            }
        });

        selectProductoPartida.addEventListener('change', () => {
            const option = selectProductoPartida.selectedOptions[0];
            if (!option || !option.value) {
                inputCostoPartida.value = '0.00';
                return;
            }
            const precio = Number(option.getAttribute('data-precio') || 0);
            inputCostoPartida.value = precio.toFixed(2);
        });

        btnAgregarPartida.addEventListener('click', () => {
            modalMovAlertContainer.innerHTML = '';
            const productoId = Number(selectProductoPartida.value);
            const cantidad = Number(inputCantidadPartida.value);
            const costoUnitario = Number(inputCostoPartida.value);

            if (productoId <= 0) {
                showAlert(modalMovAlertContainer, 'Selecciona un producto válido.', 'warning');
                return;
            }
            if (!Number.isInteger(cantidad) || cantidad <= 0) {
                showAlert(modalMovAlertContainer, 'La cantidad debe ser un número entero mayor a 0.', 'warning');
                return;
            }
            if (Number.isNaN(costoUnitario) || costoUnitario < 0) {
                showAlert(modalMovAlertContainer, 'El costo unitario no puede ser negativo.', 'warning');
                return;
            }

            const prod = PRODUCTOS_CATALOGO.find((p) => Number(p.id) === productoId);
            if (!prod) {
                showAlert(modalMovAlertContainer, 'Producto no encontrado en el catálogo.', 'danger');
                return;
            }

            const existente = partidas.find((p) => p.producto_id === productoId);
            if (existente) {
                existente.cantidad += cantidad;
                if (movTipo.value === 'ENTRADA') {
                    existente.costo_unitario = costoUnitario;
                }
            } else {
                partidas.push({
                    producto_id: productoId,
                    sku: prod.sku,
                    nombre: prod.nombre,
                    cantidad: cantidad,
                    costo_unitario: costoUnitario
                });
            }

            selectProductoPartida.value = '';
            inputCantidadPartida.value = '1';
            inputCostoPartida.value = '0.00';
            renderPartidas();
        });

        tablaPartidasMovimiento.addEventListener('click', (e) => {
            const btnQuitar = e.target.closest('.btn-quitar-partida');
            if (!btnQuitar) return;

            const idx = Number(btnQuitar.getAttribute('data-index'));
            partidas.splice(idx, 1);
            renderPartidas();
        });

        formMovimiento.addEventListener('submit', async (e) => {
            e.preventDefault();
            modalMovAlertContainer.innerHTML = '';

            if (partidas.length === 0) {
                showAlert(modalMovAlertContainer, 'Agrega al menos un producto antes de confirmar.', 'warning');
                return;
            }

            const payload = {
                tipo: movTipo.value,
                almacen_origen_id: movAlmacenOrigen.value ? Number(movAlmacenOrigen.value) : null,
                almacen_destino_id: movAlmacenDestino.value ? Number(movAlmacenDestino.value) : null,
                observaciones: movObservaciones.value.trim(),
                items: partidas.map((p) => ({
                    producto_id: p.producto_id,
                    cantidad: p.cantidad,
                    costo_unitario: p.costo_unitario
                }))
            };

            btnGuardarMovimiento.disabled = true;

            try {
                const response = await fetch(`${BASE_URL}/api/movimientos/registrar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    showAlert(modalMovAlertContainer, result.message || 'No se pudo registrar el movimiento.', 'danger');
                    return;
                }

                modalMovimiento.hide();
                showAlert(alertContainer, result.message, 'success');
                await cargarMovimientos();
            } catch (error) {
                showAlert(modalMovAlertContainer, 'Error de red al procesar la transacción.', 'danger');
            } finally {
                btnGuardarMovimiento.disabled = false;
            }
        });
    }

    // Eventos de filtros y tabla principal
    buscadorMovimientos.addEventListener('input', renderTablaMovimientos);
    filtroTipo.addEventListener('change', cargarMovimientos);
    filtroAlmacen.addEventListener('change', cargarMovimientos);

    tablaMovimientos.addEventListener('click', (e) => {
        const btnDetalle = e.target.closest('.btn-ver-detalle');
        if (!btnDetalle) return;
        const id = Number(btnDetalle.getAttribute('data-id'));
        if (id > 0) {
            verDetalleMovimiento(id);
        }
    });

    cargarMovimientos();
});