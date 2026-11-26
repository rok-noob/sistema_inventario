(() => {
    'use strict';

    let baseDirRemoto = '/evidencias';
    let subrutaActual = '';

    const alertContainer = document.getElementById('alertContainer');
    const tablaExplorador = document.getElementById('tablaExploradorFtp');
    const tablaBitacora = document.getElementById('tablaBitacoraEvidencias');
    const breadcrumbFtp = document.getElementById('breadcrumbFtp');
    const btnSubirNivel = document.getElementById('btnSubirNivel');
    const btnRefrescarFtp = document.getElementById('btnRefrescarFtp');
    const filtroMovimiento = document.getElementById('filtroMovimientoEvidencia');

    const modalCarpetaEl = document.getElementById('modalNuevaCarpeta');
    const modalCarpeta = new bootstrap.Modal(modalCarpetaEl);
    const formNuevaCarpeta = document.getElementById('formNuevaCarpeta');
    const inputNombreCarpeta = document.getElementById('inputNombreCarpeta');
    const lblRutaPadreCarpeta = document.getElementById('lblRutaPadreCarpeta');

    const modalSubirEl = document.getElementById('modalSubirEvidencia');
    const modalSubir = new bootstrap.Modal(modalSubirEl);
    const formSubirEvidencia = document.getElementById('formSubirEvidencia');
    const modalSubirAlert = document.getElementById('modalSubirAlert');
    const selectMovimientoSubida = document.getElementById('selectMovimientoSubida');
    const inputDestinoSubida = document.getElementById('inputDestinoSubida');
    const inputArchivoEvidencia = document.getElementById('inputArchivoEvidencia');

    function mostrarAlerta(mensaje, tipo = 'success', contenedor = alertContainer) {
        contenedor.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show shadow-sm" role="alert">
                ${escapeHtml(mensaje)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
    }

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    function formatearBytes(bytes) {
        const num = Number(bytes) || 0;
        if (num === 0) return '0 B';
        const unidades = ['B', 'KB', 'MB', 'GB'];
        const i = Math.min(Math.floor(Math.log(num) / Math.log(1024)), unidades.length - 1);
        const valor = num / Math.pow(1024, i);
        return `${valor.toFixed(i === 0 ? 0 : 2)} ${unidades[i]}`;
    }

    function renderizarBreadcrumb() {
        btnSubirNivel.disabled = (subrutaActual === '');

        if (subrutaActual === '') {
            breadcrumbFtp.innerHTML = `<li class="breadcrumb-item active font-monospace">${escapeHtml(baseDirRemoto)}</li>`;
            return;
        }

        const partes = subrutaActual.split('/');
        let html = `<li class="breadcrumb-item"><a href="#" class="text-decoration-none font-monospace" data-subruta="">${escapeHtml(baseDirRemoto)}</a></li>`;

        let acumulada = '';
        partes.forEach((parte, idx) => {
            acumulada = acumulada === '' ? parte : `${acumulada}/${parte}`;
            if (idx === partes.length - 1) {
                html += `<li class="breadcrumb-item active font-monospace">${escapeHtml(parte)}</li>`;
            } else {
                html += `<li class="breadcrumb-item"><a href="#" class="text-decoration-none font-monospace" data-subruta="${escapeHtml(acumulada)}">${escapeHtml(parte)}</a></li>`;
            }
        });

        breadcrumbFtp.innerHTML = html;
    }

    async function cargarExplorador(subruta = '') {
        tablaExplorador.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Consultando directorio en el servidor FTP...
                </td>
            </tr>
        `;

        try {
            const url = `${BASE_URL}/api/ftp/explorar?subruta=${encodeURIComponent(subruta)}`;
            const resp = await fetch(url);
            const json = await resp.json();

            if (!resp.ok || !json.success) {
                throw new Error(json.message || 'No se pudo leer el directorio remoto.');
            }

            baseDirRemoto = json.data.base_dir || '/evidencias';
            subrutaActual = json.data.subruta_actual || '';
            renderizarBreadcrumb();

            const items = json.data.items || [];
            if (items.length === 0) {
                tablaExplorador.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-folder2-open fs-4 d-block mb-1"></i>
                            Esta carpeta está vacía.
                        </td>
                    </tr>
                `;
                return;
            }

            tablaExplorador.innerHTML = items.map((item) => {
                if (item.tipo === 'carpeta') {
                    return `
                        <tr>
                            <td>
                                <a href="#" class="fw-semibold text-decoration-none abrir-carpeta" data-subruta="${escapeHtml(item.ruta_relativa)}">
                                    <i class="bi bi-folder-fill text-warning me-2"></i>${escapeHtml(item.nombre)}
                                </a>
                            </td>
                            <td><span class="badge bg-warning-subtle text-warning-emphasis border">Carpeta</span></td>
                            <td class="text-muted">—</td>
                            <td class="small text-muted">${escapeHtml(item.fecha)}</td>
                            <td class="text-muted">—</td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-carpeta"
                                        data-subruta="${escapeHtml(item.ruta_relativa)}"
                                        data-nombre="${escapeHtml(item.nombre)}"
                                        title="Eliminar carpeta vacía">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }

                const badgeMov = item.evidencia
                    ? `<span class="badge bg-info-subtle text-info-emphasis border">Movimiento #${item.evidencia.movimiento_id}</span>`
                    : `<span class="text-muted small">Sin vínculo BD</span>`;

                const downloadUrl = `${BASE_URL}/api/ftp/descargar?ruta_remota=${encodeURIComponent(item.ruta_remota)}`;

                return `
                    <tr>
                        <td>
                            <i class="bi bi-file-earmark-text text-primary me-2"></i>
                            <span class="fw-medium">${escapeHtml(item.nombre)}</span>
                        </td>
                        <td><span class="badge bg-light text-dark border">Archivo</span></td>
                        <td class="small">${formatearBytes(item.tamano)}</td>
                        <td class="small text-muted">${escapeHtml(item.fecha)}</td>
                        <td>${badgeMov}</td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="${downloadUrl}" class="btn btn-outline-success" title="Descargar desde FTP">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-eliminar-archivo"
                                        data-ruta="${escapeHtml(item.ruta_remota)}"
                                        data-nombre="${escapeHtml(item.nombre)}"
                                        title="Eliminar archivo del FTP">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (err) {
            tablaExplorador.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i> ${escapeHtml(err.message)}
                    </td>
                </tr>
            `;
        }
    }

    async function cargarBitacora() {
        const movId = Number(filtroMovimiento.value) || 0;
        tablaBitacora.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-3 text-muted">Cargando evidencias vinculadas...</td>
            </tr>
        `;

        try {
            const url = `${BASE_URL}/api/ftp/evidencias?movimiento_id=${movId}`;
            const resp = await fetch(url);
            const json = await resp.json();

            if (!resp.ok || !json.success) {
                throw new Error(json.message || 'Error al cargar bitácora.');
            }

            const lista = json.data || [];
            if (lista.length === 0) {
                tablaBitacora.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No hay evidencias registradas con ese filtro.</td>
                    </tr>
                `;
                return;
            }

            tablaBitacora.innerHTML = lista.map((ev) => {
                const downloadUrl = `${BASE_URL}/api/ftp/descargar?id=${ev.id}`;
                return `
                    <tr>
                        <td><span class="badge bg-dark">#${ev.movimiento_id}</span> <small class="text-muted">${escapeHtml(ev.movimiento_tipo)}</small></td>
                        <td class="fw-semibold">${escapeHtml(ev.nombre_archivo)}</td>
                        <td class="small font-monospace text-muted">${escapeHtml(ev.ruta_remota)}</td>
                        <td class="small">${formatearBytes(ev.tamano_bytes)}</td>
                        <td class="small">${escapeHtml(ev.usuario_nombre)}</td>
                        <td class="small text-muted">${escapeHtml(ev.fecha_subida)}</td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="${downloadUrl}" class="btn btn-outline-success" title="Descargar">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-eliminar-evidencia-bd"
                                        data-id="${ev.id}"
                                        data-nombre="${escapeHtml(ev.nombre_archivo)}"
                                        title="Eliminar archivo">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (err) {
            tablaBitacora.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-3 text-danger">${escapeHtml(err.message)}</td>
                </tr>
            `;
        }
    }

    // Navegación por carpetas
    breadcrumbFtp.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-subruta]');
        if (!link) return;
        e.preventDefault();
        cargarExplorador(link.dataset.subruta || '');
    });

    btnSubirNivel.addEventListener('click', () => {
        if (subrutaActual === '') return;
        const partes = subrutaActual.split('/');
        partes.pop();
        cargarExplorador(partes.join('/'));
    });

    btnRefrescarFtp.addEventListener('click', () => {
        cargarExplorador(subrutaActual);
        cargarBitacora();
    });

    filtroMovimiento.addEventListener('change', () => {
        cargarBitacora();
    });

    // Delegación de eventos en tabla explorador
    tablaExplorador.addEventListener('click', async (e) => {
        const linkCarpeta = e.target.closest('.abrir-carpeta');
        if (linkCarpeta) {
            e.preventDefault();
            cargarExplorador(linkCarpeta.dataset.subruta || '');
            return;
        }

        const btnDelCarpeta = e.target.closest('.btn-eliminar-carpeta');
        if (btnDelCarpeta) {
            const subruta = btnDelCarpeta.dataset.subruta || '';
            const nombre = btnDelCarpeta.dataset.nombre || '';
            if (!confirm(`¿Eliminar la carpeta vacía "${nombre}" del servidor FTP?`)) return;

            try {
                const resp = await fetch(`${BASE_URL}/api/ftp/carpeta/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subruta }),
                });
                const json = await resp.json();
                if (!resp.ok || !json.success) {
                    throw new Error(json.message || 'No se pudo eliminar la carpeta.');
                }
                mostrarAlerta(json.message, 'success');
                cargarExplorador(subrutaActual);
            } catch (err) {
                mostrarAlerta(err.message, 'danger');
            }
            return;
        }

        const btnDelArchivo = e.target.closest('.btn-eliminar-archivo');
        if (btnDelArchivo) {
            const rutaRemota = btnDelArchivo.dataset.ruta || '';
            const nombre = btnDelArchivo.dataset.nombre || '';
            if (!confirm(`¿Eliminar permanentemente "${nombre}" del servidor FTP?`)) return;

            try {
                const resp = await fetch(`${BASE_URL}/api/ftp/archivo/eliminar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ruta_remota: rutaRemota }),
                });
                const json = await resp.json();
                if (!resp.ok || !json.success) {
                    throw new Error(json.message || 'No se pudo eliminar el archivo.');
                }
                mostrarAlerta(json.message, 'success');
                cargarExplorador(subrutaActual);
                cargarBitacora();
            } catch (err) {
                mostrarAlerta(err.message, 'danger');
            }
        }
    });

    // Delegación de eventos en bitácora MySQL
    tablaBitacora.addEventListener('click', async (e) => {
        const btnDel = e.target.closest('.btn-eliminar-evidencia-bd');
        if (!btnDel) return;

        const id = Number(btnDel.dataset.id) || 0;
        const nombre = btnDel.dataset.nombre || '';
        if (!confirm(`¿Eliminar la evidencia "${nombre}" del servidor FTP y de la bitácora?`)) return;

        try {
            const resp = await fetch(`${BASE_URL}/api/ftp/archivo/eliminar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            });
            const json = await resp.json();
            if (!resp.ok || !json.success) {
                throw new Error(json.message || 'No se pudo eliminar la evidencia.');
            }
            mostrarAlerta(json.message, 'success');
            cargarExplorador(subrutaActual);
            cargarBitacora();
        } catch (err) {
            mostrarAlerta(err.message, 'danger');
        }
    });

    // Modal Crear Carpeta
    document.getElementById('btnAbrirModalCarpeta').addEventListener('click', () => {
        formNuevaCarpeta.reset();
        lblRutaPadreCarpeta.textContent = subrutaActual === '' ? baseDirRemoto : `${baseDirRemoto}/${subrutaActual}`;
        modalCarpeta.show();
    });

    formNuevaCarpeta.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nombreCarpeta = inputNombreCarpeta.value.trim();
        if (!nombreCarpeta) {
            alert('Escribe el nombre de la carpeta.');
            return;
        }

        const btn = document.getElementById('btnGuardarCarpeta');
        btn.disabled = true;

        try {
            const resp = await fetch(`${BASE_URL}/api/ftp/carpeta/crear`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subruta_padre: subrutaActual,
                    nombre_carpeta: nombreCarpeta,
                }),
            });
            const json = await resp.json();
            if (!resp.ok || !json.success) {
                throw new Error(json.message || 'Error al crear la carpeta.');
            }
            modalCarpeta.hide();
            mostrarAlerta(json.message, 'success');
            cargarExplorador(subrutaActual);
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });

    // Modal Subir Evidencia
    function actualizarTextoDestinoSubida() {
        const movId = selectMovimientoSubida.value;
        if (subrutaActual !== '') {
            inputDestinoSubida.value = `${baseDirRemoto}/${subrutaActual}`;
        } else if (movId) {
            inputDestinoSubida.value = `${baseDirRemoto}/movimiento_${movId}`;
        } else {
            inputDestinoSubida.value = `${baseDirRemoto}/movimiento_{ID}`;
        }
    }

    document.getElementById('btnAbrirModalSubir').addEventListener('click', () => {
        formSubirEvidencia.reset();
        modalSubirAlert.innerHTML = '';
        actualizarTextoDestinoSubida();
        modalSubir.show();
    });

    selectMovimientoSubida.addEventListener('change', actualizarTextoDestinoSubida);

    formSubirEvidencia.addEventListener('submit', async (e) => {
        e.preventDefault();
        modalSubirAlert.innerHTML = '';

        if (!selectMovimientoSubida.value) {
            mostrarAlerta('Selecciona un movimiento operativo.', 'warning', modalSubirAlert);
            return;
        }
        if (!inputArchivoEvidencia.files || inputArchivoEvidencia.files.length === 0) {
            mostrarAlerta('Selecciona un archivo para subir.', 'warning', modalSubirAlert);
            return;
        }

        const formData = new FormData();
        formData.append('movimiento_id', selectMovimientoSubida.value);
        formData.append('subruta', subrutaActual);
        formData.append('archivo', inputArchivoEvidencia.files[0]);

        const btn = document.getElementById('btnConfirmarSubida');
        btn.disabled = true;

        try {
            const resp = await fetch(`${BASE_URL}/api/ftp/subir`, {
                method: 'POST',
                body: formData,
            });
            const json = await resp.json();
            if (!resp.ok || !json.success) {
                throw new Error(json.message || 'Error al subir la evidencia.');
            }
            modalSubir.hide();
            mostrarAlerta(json.message, 'success');
            cargarExplorador(subrutaActual);
            cargarBitacora();
        } catch (err) {
            mostrarAlerta(err.message, 'danger', modalSubirAlert);
        } finally {
            btn.disabled = false;
        }
    });

    // Carga inicial
    cargarExplorador('');
    cargarBitacora();
})();