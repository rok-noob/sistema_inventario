<?php

declare(strict_types=1);

namespace App\Helpers;

class ResponseHelper
{
    public static function json(bool $success, string $message, mixed $data = null, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    public static function success(string $message, mixed $data = null, int $statusCode = 200): void
    {
        self::json(true, $message, $data, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, mixed $data = null): void
    {
        self::json(false, $message, $data, $statusCode);
    }
}