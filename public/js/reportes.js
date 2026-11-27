(() => {
    'use strict';

    const alertContainer = document.getElementById('alertContainer');
    const formFiltros = document.getElementById('formFiltrosReporte');
    const inputFechaInicio = document.getElementById('filtroFechaInicio');
    const inputFechaFin = document.getElementById('filtroFechaFin');
    const selectTipo = document.getElementById('filtroTipo');
    const selectAlmacen = document.getElementById('filtroAlmacen');
    const btnLimpiar = document.getElementById('btnLimpiarFiltros');

    const btnExportarExcel = document.getElementById('btnExportarExcel');
    const btnExportarPdf = document.getElementById('btnExportarPdf');
    const btnExportarCsv = document.getElementById('btnExportarCsv');

    const kpiTotalMovimientos = document.getElementById('kpiTotalMovimientos');
    const kpiEntradas = document.getElementById('kpiEntradas');
    const kpiSalidas = document.getElementById('kpiSalidas');
    const kpiTransferencias = document.getElementById('kpiTransferencias');
    const kpiUnidades = document.getElementById('kpiUnidades');
    const kpiMontoTotal = document.getElementById('kpiMontoTotal');

    const badgeTotalRegistros = document.getElementById('badgeTotalRegistros');
    const tablaPrevisualizacion = document.getElementById('tablaPrevisualizacionReporte');

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    function mostrarAlerta(mensaje, tipo = 'danger') {
        alertContainer.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show shadow-sm" role="alert">
                ${escapeHtml(mensaje)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
    }

    function formatearMoneda(valor) {
        const num = Number(valor) || 0;
        return '$' + num.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function construirQueryString() {
        const params = new URLSearchParams();

        if (inputFechaInicio.value) {
            params.set('fecha_inicio', inputFechaInicio.value);
        }
        if (inputFechaFin.value) {
            params.set('fecha_fin', inputFechaFin.value);
        }
        if (selectTipo.value) {
            params.set('tipo', selectTipo.value);
        }
        if (Number(selectAlmacen.value) > 0) {
            params.set('almacen_id', selectAlmacen.value);
        }

        return params.toString();
    }

    function actualizarEnlacesExportacion(queryString) {
        const sufijo = queryString !== '' ? `?${queryString}` : '';
        btnExportarExcel.href = `${BASE_URL}/api/reportes/excel${sufijo}`;
        btnExportarPdf.href = `${BASE_URL}/api/reportes/pdf${sufijo}`;
        btnExportarCsv.href = `${BASE_URL}/api/reportes/csv${sufijo}`;
    }

    function renderizarBadgeTipo(tipo) {
        if (tipo === 'ENTRADA') {
            return '<span class="badge bg-success-subtle text-success-emphasis border">ENTRADA</span>';
        }
        if (tipo === 'SALIDA') {
            return '<span class="badge bg-danger-subtle text-danger-emphasis border">SALIDA</span>';
        }
        if (tipo === 'TRANSFERENCIA') {
            return '<span class="badge bg-primary-subtle text-primary-emphasis border">TRANSFERENCIA</span>';
        }
        return `<span class="badge bg-secondary">${escapeHtml(tipo)}</span>`;
    }

    function renderizarRutaAlmacenes(row) {
        const origen = row.almacen_origen_nombre ? escapeHtml(row.almacen_origen_nombre) : '—';
        const destino = row.almacen_destino_nombre ? escapeHtml(row.almacen_destino_nombre) : '—';

        if (row.tipo === 'ENTRADA') {
            return `<span class="text-muted small">Ingreso →</span> <strong>${destino}</strong>`;
        }
        if (row.tipo === 'SALIDA') {
            return `<strong>${origen}</strong> <span class="text-muted small">→ Salida</span>`;
        }
        return `<strong>${origen}</strong> <i class="bi bi-arrow-right mx-1 text-primary"></i> <strong>${destino}</strong>`;
    }

    async function cargarPrevisualizacion() {
        alertContainer.innerHTML = '';
        const queryString = construirQueryString();
        actualizarEnlacesExportacion(queryString);

        tablaPrevisualizacion.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Generando vista previa...
                </td>
            </tr>
        `;

        try {
            const sufijo = queryString !== '' ? `?${queryString}` : '';
            const resp = await fetch(`${BASE_URL}/api/reportes/previsualizar${sufijo}`);
            const json = await resp.json();

            if (!resp.ok || !json.success) {
                throw new Error(json.message || 'No se pudo cargar el reporte.');
            }

            const registros = json.data.registros || [];
            const resumen = json.data.resumen || {};

            kpiTotalMovimientos.textContent = Number(resumen.total_movimientos || 0).toLocaleString('es-MX');
            kpiEntradas.textContent = Number(resumen.entradas || 0).toLocaleString('es-MX');
            kpiSalidas.textContent = Number(resumen.salidas || 0).toLocaleString('es-MX');
            kpiTransferencias.textContent = Number(resumen.transferencias || 0).toLocaleString('es-MX');
            kpiUnidades.textContent = Number(resumen.total_unidades || 0).toLocaleString('es-MX');
            kpiMontoTotal.textContent = formatearMoneda(resumen.monto_total || 0);

            badgeTotalRegistros.textContent = `${registros.length} registro${registros.length === 1 ? '' : 's'}`;

            if (registros.length === 0) {
                tablaPrevisualizacion.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            No se encontraron movimientos operativos con los filtros seleccionados.
                        </td>
                    </tr>
                `;
                return;
            }

            tablaPrevisualizacion.innerHTML = registros.map((row) => `
                <tr>
                    <td class="fw-bold">#${Number(row.id)}</td>
                    <td class="small text-muted text-nowrap">${escapeHtml(row.fecha)}</td>
                    <td>${renderizarBadgeTipo(row.tipo)}</td>
                    <td class="small">${renderizarRutaAlmacenes(row)}</td>
                    <td class="small">${escapeHtml(row.productos_resumen || '—')}</td>
                    <td class="text-center fw-semibold">${Number(row.total_unidades)}</td>
                    <td class="text-end fw-semibold">${formatearMoneda(row.monto_total)}</td>
                    <td class="small">${escapeHtml(row.usuario_nombre)}</td>
                    <td class="small text-muted">${escapeHtml(row.observaciones || '—')}</td>
                </tr>
            `).join('');
        } catch (err) {
            mostrarAlerta(err.message, 'danger');
            tablaPrevisualizacion.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">${escapeHtml(err.message)}</td>
                </tr>
            `;
        }
    }

    formFiltros.addEventListener('submit', (e) => {
        e.preventDefault();
        if (inputFechaInicio.value && inputFechaFin.value && inputFechaInicio.value > inputFechaFin.value) {
            mostrarAlerta('La fecha de inicio no puede ser mayor que la fecha fin.', 'warning');
            return;
        }
        cargarPrevisualizacion();
    });

    btnLimpiar.addEventListener('click', () => {
        formFiltros.reset();
        cargarPrevisualizacion();
    });

    // Carga inicial al abrir /reportes
    cargarPrevisualizacion();
})();