<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\EvidenciaFtpDAO;
use App\DAO\MovimientoDAO;
use App\Helpers\FtpHelper;
use App\Helpers\ResponseHelper;
use Throwable;

class FtpController
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    private const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip',
    ];

    public function index(): void
    {
        $movimientoDao = new MovimientoDAO();
        $movimientos   = $movimientoDao->listarMovimientos(null, null, 200);

        $usuario  = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];

        require __DIR__ . '/../Views/ftp/index.php';
    }

    public function explorar(): void
    {
        $subruta = trim((string) ($_GET['subruta'] ?? ''));

        try {
            $ftp          = new FtpHelper();
            $evidenciaDao = new EvidenciaFtpDAO();
            $items        = $ftp->listarContenido($subruta);

            foreach ($items as &$item) {
                $item['evidencia'] = null;
                if ($item['tipo'] === 'archivo') {
                    $registro = $evidenciaDao->obtenerPorRutaRemota($item['ruta_remota']);
                    if ($registro !== null) {
                        $item['evidencia'] = [
                            'id'            => (int) $registro['id'],
                            'movimiento_id' => (int) $registro['movimiento_id'],
                            'fecha_subida'  => $registro['fecha_subida'],
                        ];
                    }
                }
            }
            unset($item);

            ResponseHelper::success('Directorio remoto cargado correctamente.', [
                'base_dir'       => $ftp->getBaseDir(),
                'subruta_actual' => trim(str_replace('\\', '/', $subruta), '/'),
                'items'          => $items,
            ]);
        } catch (Throwable $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function listarEvidencias(): void
    {
        $movimientoId = (int) ($_GET['movimiento_id'] ?? 0);

        try {
            $evidenciaDao = new EvidenciaFtpDAO();
            $lista        = $evidenciaDao->listarTodas($movimientoId > 0 ? $movimientoId : null);

            ResponseHelper::success('Evidencias registradas obtenidas.', $lista);
        } catch (Throwable $e) {
            ResponseHelper::error('Error al consultar evidencias: ' . $e->getMessage(), 500);
        }
    }

    public function crearCarpeta(): void
    {
        $input         = $this->obtenerPayloadJson();
        $subrutaPadre  = trim((string) ($input['subruta_padre'] ?? ''), "/\\ \t\n\r\0\x0B");
        $nombreCarpeta = trim((string) ($input['nombre_carpeta'] ?? ''));

        if ($nombreCarpeta === '') {
            ResponseHelper::error('El nombre de la carpeta es obligatorio.', 422);
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{1,80}$/', $nombreCarpeta)) {
            ResponseHelper::error(
                'El nombre de carpeta solo puede contener letras, números, guiones (-) y guiones bajos (_), máximo 80 caracteres.',
                422
            );
        }

        $subrutaDestino = $subrutaPadre === ''
            ? $nombreCarpeta
            : "{$subrutaPadre}/{$nombreCarpeta}";

        try {
            $ftp        = new FtpHelper();
            $rutaRemota = $ftp->crearDirectorio($subrutaDestino);

            ResponseHelper::success("Carpeta '{$nombreCarpeta}' creada correctamente en el servidor FTP.", [
                'ruta_remota' => $rutaRemota,
            ], 201);
        } catch (Throwable $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function eliminarCarpeta(): void
    {
        $input   = $this->obtenerPayloadJson();
        $subruta = trim((string) ($input['subruta'] ?? ''), "/\\ \t\n\r\0\x0B");

        if ($subruta === '') {
            ResponseHelper::error('Debes especificar la carpeta que deseas eliminar.', 422);
        }

        try {
            $ftp = new FtpHelper();
            $ftp->eliminarDirectorio($subruta);

            ResponseHelper::success('Carpeta vacía eliminada del servidor FTP.');
        } catch (Throwable $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function subir(): void
    {
        $movimientoId = (int) ($_POST['movimiento_id'] ?? 0);
        $subruta      = trim((string) ($_POST['subruta'] ?? ''), "/\\ \t\n\r\0\x0B");
        $usuarioId    = (int) ($_SESSION['usuario']['id'] ?? 0);

        if ($movimientoId <= 0) {
            ResponseHelper::error('Debes seleccionar un movimiento operativo válido.', 422);
        }

        if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
            ResponseHelper::error('No se recibió ningún archivo para subir.', 422);
        }

        $archivo = $_FILES['archivo'];
        $error   = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            ResponseHelper::error($this->traducirErrorSubida($error), 422);
        }

        $tamanoBytes    = (int) ($archivo['size'] ?? 0);
        $nombreOriginal = (string) ($archivo['name'] ?? '');
        $tmpName        = (string) ($archivo['tmp_name'] ?? '');

        if ($tamanoBytes <= 0 || $tamanoBytes > self::MAX_FILE_SIZE) {
            ResponseHelper::error('El archivo debe pesar entre 1 byte y 10 MB.', 422);
        }

        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            ResponseHelper::error(
                'Extensión no permitida. Formatos válidos: ' . implode(', ', self::ALLOWED_EXTENSIONS) . '.',
                422
            );
        }

        try {
            // Verificar que el movimiento exista en BD
            $movimientoDao = new MovimientoDAO();
            $movimiento    = $movimientoDao->obtenerPorId($movimientoId);
            if ($movimiento === null) {
                ResponseHelper::error("El movimiento #{$movimientoId} no existe.", 404);
            }

            // Sanitizar nombre y evitar colisiones
            $baseSinExt = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $limpio     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseSinExt) ?: 'evidencia';
            $limpio     = trim((string) preg_replace('/_+/', '_', $limpio), '_');
            $limpio     = substr($limpio, 0, 60);

            $nombreFinal = sprintf('mov%d_%s_%s.%s', $movimientoId, date('Ymd_His'), $limpio, $extension);

            // Si no se eligió una carpeta específica en el explorador, se organiza por movimiento
            $carpetaDestino = $subruta !== '' ? $subruta : "movimiento_{$movimientoId}";
            $subrutaArchivo = "{$carpetaDestino}/{$nombreFinal}";

            $ftp        = new FtpHelper();
            $rutaRemota = $ftp->subirArchivo($tmpName, $subrutaArchivo);

            $evidenciaDao = new EvidenciaFtpDAO();
            try {
                $evidenciaId = $evidenciaDao->registrar(
                    $movimientoId,
                    $nombreFinal,
                    $rutaRemota,
                    $tamanoBytes,
                    $usuarioId
                );
            } catch (Throwable $dbError) {
                // Revertir subida en FTP si falla el registro en MySQL
                try {
                    $ftp->eliminarArchivo($rutaRemota);
                } catch (Throwable) {
                }
                throw $dbError;
            }

            ResponseHelper::success('Evidencia subida al servidor FTP y vinculada al movimiento.', [
                'id'             => $evidenciaId,
                'nombre_archivo' => $nombreFinal,
                'ruta_remota'    => $rutaRemota,
            ], 201);
        } catch (Throwable $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function descargar(): void
    {
        $id         = (int) ($_GET['id'] ?? 0);
        $rutaRemota = trim((string) ($_GET['ruta_remota'] ?? ''));

        $tmpFile = null;

        try {
            $evidenciaDao  = new EvidenciaFtpDAO();
            $nombreDescarga = '';

            if ($id > 0) {
                $evidencia = $evidenciaDao->obtenerPorId($id);
                if ($evidencia === null) {
                    http_response_code(404);
                    echo 'Evidencia no encontrada en base de datos.';
                    return;
                }
                $rutaRemota     = (string) $evidencia['ruta_remota'];
                $nombreDescarga = (string) $evidencia['nombre_archivo'];
            } elseif ($rutaRemota !== '') {
                $nombreDescarga = basename(str_replace('\\', '/', $rutaRemota));
            } else {
                http_response_code(400);
                echo 'Parámetros insuficientes para descargar el archivo.';
                return;
            }

            $ftp     = new FtpHelper();
            $tmpFile = $ftp->descargarArchivoTemporal($rutaRemota);

            $mimeType = mime_content_type($tmpFile) ?: 'application/octet-stream';
            $tamano   = filesize($tmpFile);

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . rawurlencode($nombreDescarga) . '"; filename*=UTF-8\'\'' . rawurlencode($nombreDescarga));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            if ($tamano !== false) {
                header('Content-Length: ' . $tamano);
            }

            readfile($tmpFile);
        } catch (Throwable $e) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error al descargar desde FTP: ' . $e->getMessage();
        } finally {
            if ($tmpFile !== null && is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    public function eliminarArchivo(): void
    {
        $input      = $this->obtenerPayloadJson();
        $id         = (int) ($input['id'] ?? 0);
        $rutaRemota = trim((string) ($input['ruta_remota'] ?? ''));

        try {
            $evidenciaDao = new EvidenciaFtpDAO();

            if ($id > 0) {
                $evidencia = $evidenciaDao->obtenerPorId($id);
                if ($evidencia === null) {
                    ResponseHelper::error('El registro de evidencia no existe.', 404);
                }
                $rutaRemota = (string) $evidencia['ruta_remota'];
            }

            if ($rutaRemota === '') {
                ResponseHelper::error('Debes indicar el archivo a eliminar.', 422);
            }

            $ftp = new FtpHelper();
            $ftp->eliminarArchivo($rutaRemota);

            if ($id > 0) {
                $evidenciaDao->eliminar($id);
            } else {
                $evidenciaDao->eliminarPorRutaRemota($rutaRemota);
            }

            ResponseHelper::success('Archivo eliminado correctamente del servidor FTP.');
        } catch (Throwable $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerPayloadJson(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }

    private function traducirErrorSubida(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor.',
            UPLOAD_ERR_PARTIAL   => 'El archivo se subió de forma incompleta. Intenta de nuevo.',
            UPLOAD_ERR_NO_FILE   => 'No seleccionaste ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor PHP.',
            UPLOAD_ERR_CANT_WRITE => 'Error de escritura en disco temporal.',
            default              => 'Error desconocido al recibir el archivo.',
        };
    }
}