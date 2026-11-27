<?php

declare(strict_types=1);

namespace App\Helpers;

use FTP\Connection;
use RuntimeException;

class FtpHelper
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $baseDir;
    private ?Connection $conn = null;

    public function __construct()
    {
        $this->host    = trim((string) ($_ENV['FTP_HOST'] ?? getenv('FTP_HOST') ?: ''));
        $this->port    = (int) ($_ENV['FTP_PORT'] ?? getenv('FTP_PORT') ?: 21);
        $this->user    = trim((string) ($_ENV['FTP_USER'] ?? getenv('FTP_USER') ?: ''));
        $this->pass    = (string) ($_ENV['FTP_PASS'] ?? getenv('FTP_PASS') ?: '');
        $base          = trim((string) ($_ENV['FTP_BASE_DIR'] ?? getenv('FTP_BASE_DIR') ?: '/evidencias'));
        $this->baseDir = '/' . trim(str_replace('\\', '/', $base), '/');
    }

    public function __destruct()
    {
        $this->desconectar();
    }

    public function conectar(): void
    {
        if ($this->conn !== null) {
            return;
        }

        if ($this->host === '' || $this->user === '') {
            throw new RuntimeException('Faltan credenciales FTP (FTP_HOST o FTP_USER) en el archivo .env.');
        }

        $conexion = @ftp_connect($this->host, $this->port, 15);
        if ($conexion === false) {
            throw new RuntimeException("No se pudo establecer conexión con el servidor FTP ({$this->host}:{$this->port}).");
        }

        if (!@ftp_login($conexion, $this->user, $this->pass)) {
            @ftp_close($conexion);
            throw new RuntimeException('Credenciales FTP incorrectas (usuario o contraseña rechazados).');
        }

        // Modo pasivo obligatorio para atravesar firewalls y NAT en hosting/XAMPP
        @ftp_pasv($conexion, true);
        $this->conn = $conexion;

        // Garantizar que exista el directorio base configurado
        $this->asegurarDirectorioAbsoluto($this->baseDir);
    }

    public function desconectar(): void
    {
        if ($this->conn !== null) {
            @ftp_close($this->conn);
            $this->conn = null;
        }
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * Limpia una subruta relativa evitando directory traversal (..) y devuelve la ruta remota completa.
     */
    public function resolverRutaRemota(string $subruta = ''): string
    {
        $limpia = trim(str_replace('\\', '/', $subruta), '/');
        if ($limpia === '') {
            return $this->baseDir;
        }

        $segmentos = explode('/', $limpia);
        $seguros   = [];

        foreach ($segmentos as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                throw new RuntimeException('Ruta FTP no válida.');
            }
            $seguros[] = $seg;
        }

        return empty($seguros)
            ? $this->baseDir
            : $this->baseDir . '/' . implode('/', $seguros);
    }

    /**
     * Lista carpetas y archivos dentro de una subruta relativa al directorio base.
     *
     * @return array<int, array{nombre: string, tipo: string, tamano: int, fecha: string, ruta_relativa: string, ruta_remota: string}>
     */
    public function listarContenido(string $subruta = ''): array
    {
        $this->conectar();
        assert($this->conn !== null);

        $rutaObjetivo  = $this->resolverRutaRemota($subruta);
        $subrutaLimpia = trim(str_replace('\\', '/', $subruta), '/');

        $rawList = @ftp_rawlist($this->conn, $rutaObjetivo);
        $items   = [];

        if (is_array($rawList)) {
            foreach ($rawList as $linea) {
                // Formato estándar UNIX: drwxr-xr-x 2 user group 4096 Nov 26 11:30 nombre
                if (!preg_match('/^([d\-l])[rwxstST\-]{9}\s+\d+\s+\S+\s+\S+\s+(\d+)\s+(\w+\s+\d+\s+[\d:]+)\s+(.+)$/', $linea, $m)) {
                    continue;
                }

                $nombre = trim($m[4]);
                if ($nombre === '.' || $nombre === '..') {
                    continue;
                }

                $esCarpeta    = ($m[1] === 'd');
                $tamano       = $esCarpeta ? 0 : (int) $m[2];
                $fecha        = trim($m[3]);
                $rutaRelativa = $subrutaLimpia === '' ? $nombre : "{$subrutaLimpia}/{$nombre}";
                $rutaRemota   = "{$rutaObjetivo}/{$nombre}";

                $items[] = [
                    'nombre'        => $nombre,
                    'tipo'          => $esCarpeta ? 'carpeta' : 'archivo',
                    'tamano'        => $tamano,
                    'fecha'         => $fecha,
                    'ruta_relativa' => $rutaRelativa,
                    'ruta_remota'   => $rutaRemota,
                ];
            }
        } else {
            // Respaldo con ftp_nlist si el servidor no soporta ftp_rawlist
            $nombres = @ftp_nlist($this->conn, $rutaObjetivo);
            if ($nombres === false) {
                throw new RuntimeException("No se pudo leer el contenido del directorio remoto: {$rutaObjetivo}");
            }

            foreach ($nombres as $entrada) {
                $nombre = basename(str_replace('\\', '/', $entrada));
                if ($nombre === '' || $nombre === '.' || $nombre === '..') {
                    continue;
                }

                $rutaRemota   = "{$rutaObjetivo}/{$nombre}";
                $rutaRelativa = $subrutaLimpia === '' ? $nombre : "{$subrutaLimpia}/{$nombre}";
                $tamano       = @ftp_size($this->conn, $rutaRemota);
                $esCarpeta    = ($tamano === -1);

                $items[] = [
                    'nombre'        => $nombre,
                    'tipo'          => $esCarpeta ? 'carpeta' : 'archivo',
                    'tamano'        => $esCarpeta ? 0 : (int) $tamano,
                    'fecha'         => 'N/D',
                    'ruta_relativa' => $rutaRelativa,
                    'ruta_remota'   => $rutaRemota,
                ];
            }
        }

        // Ordenar: primero carpetas alfabéticamente, luego archivos alfabéticamente
        usort($items, static function (array $a, array $b): int {
            if ($a['tipo'] !== $b['tipo']) {
                return $a['tipo'] === 'carpeta' ? -1 : 1;
            }
            return strcasecmp($a['nombre'], $b['nombre']);
        });

        return $items;
    }

    /**
     * Crea un directorio (soporta subcarpetas anidadas) y devuelve su ruta remota completa.
     */
    public function crearDirectorio(string $subruta): string
    {
        $this->conectar();
        $rutaRemota = $this->resolverRutaRemota($subruta);

        if ($rutaRemota === $this->baseDir) {
            throw new RuntimeException('Debes indicar el nombre de la carpeta a crear.');
        }

        $this->asegurarDirectorioAbsoluto($rutaRemota);
        return $rutaRemota;
    }

    /**
     * Elimina un directorio remoto vacío usando ftp_rmdir.
     */
    public function eliminarDirectorio(string $subruta): void
    {
        $this->conectar();
        assert($this->conn !== null);

        $rutaRemota = $this->resolverRutaRemota($subruta);
        if ($rutaRemota === $this->baseDir) {
            throw new RuntimeException('No puedes eliminar el directorio raíz de evidencias.');
        }

        if (!@ftp_rmdir($this->conn, $rutaRemota)) {
            throw new RuntimeException('No se pudo eliminar la carpeta. Asegúrate de que esté completamente vacía.');
        }
    }

    /**
     * Sube un archivo local al servidor FTP y devuelve su ruta remota completa.
     */
    public function subirArchivo(string $rutaLocalTmp, string $subrutaDestino): string
    {
        if (!is_file($rutaLocalTmp)) {
            throw new RuntimeException('El archivo temporal local no existe o no es legible.');
        }

        $this->conectar();
        assert($this->conn !== null);

        $rutaRemota   = $this->resolverRutaRemota($subrutaDestino);
        $carpetaPadre = dirname($rutaRemota);
        $this->asegurarDirectorioAbsoluto($carpetaPadre);

        if (!@ftp_put($this->conn, $rutaRemota, $rutaLocalTmp, FTP_BINARY)) {
            throw new RuntimeException("Error al subir el archivo al servidor FTP en {$rutaRemota}.");
        }

        return $rutaRemota;
    }

    /**
     * Descarga un archivo remoto a una ruta temporal local y devuelve dicha ruta local.
     */
    public function descargarArchivoTemporal(string $rutaRemota): string
    {
        $this->conectar();
        assert($this->conn !== null);

        $rutaLimpia = '/' . trim(str_replace('\\', '/', $rutaRemota), '/');
        if (!str_starts_with($rutaLimpia, $this->baseDir . '/')) {
            throw new RuntimeException('Acceso denegado: el archivo está fuera del directorio de evidencias.');
        }

        $tmpDir = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('No se pudo preparar el directorio temporal local para la descarga.');
        }

        $tmpFile = tempnam($tmpDir, 'ftp_ev_');
        if ($tmpFile === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal local para la descarga.');
        }

        if (!@ftp_get($this->conn, $tmpFile, $rutaLimpia, FTP_BINARY)) {
            @unlink($tmpFile);
            throw new RuntimeException('No se pudo descargar el archivo desde el servidor FTP (puede que ya no exista).');
        }

        return $tmpFile;
    }

    /**
     * Elimina un archivo remoto usando ftp_delete.
     */
    public function eliminarArchivo(string $rutaRemota): void
    {
        $this->conectar();
        assert($this->conn !== null);

        $rutaLimpia = '/' . trim(str_replace('\\', '/', $rutaRemota), '/');
        if (!str_starts_with($rutaLimpia, $this->baseDir . '/')) {
            throw new RuntimeException('Acceso denegado: ruta de archivo fuera del directorio permitido.');
        }

        if (!@ftp_delete($this->conn, $rutaLimpia)) {
            throw new RuntimeException('No se pudo eliminar el archivo en el servidor FTP.');
        }
    }

    private function asegurarDirectorioAbsoluto(string $rutaAbsoluta): void
    {
        assert($this->conn !== null);

        $segmentos = explode('/', trim($rutaAbsoluta, '/'));
        $acumulada = '';

        foreach ($segmentos as $seg) {
            if ($seg === '') {
                continue;
            }
            $acumulada .= '/' . $seg;

            // Intentar entrar; si falla, crear la carpeta
            if (!@ftp_chdir($this->conn, $acumulada)) {
                if (!@ftp_mkdir($this->conn, $acumulada)) {
                    throw new RuntimeException("No se pudo crear o acceder al directorio FTP: {$acumulada}");
                }
            }
        }

        // Volver a la raíz FTP para no alterar rutas relativas posteriores
        @ftp_chdir($this->conn, '/');
    }
}