<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;
use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static bool $envLoaded = false;

    private function __construct() {}
    private function __clone() {}

    private static function loadEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        $rootPath = dirname(__DIR__, 2);
        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->safeLoad();
        }

        self::$envLoaded = true;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::loadEnv();

            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db   = $_ENV['DB_NAME'] ?? 'sistema_inventario';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new RuntimeException('Error de conexión a la base de datos: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}