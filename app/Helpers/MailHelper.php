<?php

declare(strict_types=1);

namespace App\Helpers;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class MailHelper
{
    /**
     * Lee una variable cargada por vlucas/phpdotenv (createImmutable usa $_ENV / $_SERVER).
     */
    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Envía un correo HTML genérico usando la configuración SMTP del .env.
     */
    public static function enviarCorreo(
        string $destinatarioEmail,
        string $destinatarioNombre,
        string $asunto,
        string $htmlBody,
        string $altBody = ''
    ): bool {
        $host = self::env('MAIL_HOST', 'smtp.gmail.com');
        $port = (int) self::env('MAIL_PORT', '587');
        $user = self::env('MAIL_USER');
        $pass = self::env('MAIL_PASS');
        $fromName = self::env('MAIL_FROM_NAME', self::env('APP_NAME', 'Sistema de Inventario'));

        if ($user === '' || $pass === '') {
            error_log('MailHelper: Faltan credenciales MAIL_USER o MAIL_PASS en el archivo .env');
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->Port = $port;
            $mail->SMTPSecure = ($port === 465)
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($user, $fromName);
            $mail->addAddress($destinatarioEmail, $destinatarioNombre);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

            return $mail->send();
        } catch (MailException $e) {
            error_log('Error PHPMailer al enviar a ' . $destinatarioEmail . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía el correo con el enlace único de recuperación de contraseña.
     */
    public static function enviarRecuperacion(
        string $destinatarioEmail,
        string $destinatarioNombre,
        string $enlaceRecuperacion
    ): bool {
        $appName = htmlspecialchars(self::env('APP_NAME', 'Sistema de Inventario'), ENT_QUOTES, 'UTF-8');
        $nombreSeguro = htmlspecialchars($destinatarioNombre, ENT_QUOTES, 'UTF-8');
        $enlaceSeguro = htmlspecialchars($enlaceRecuperacion, ENT_QUOTES, 'UTF-8');

        $asunto = 'Recuperación de contraseña - ' . self::env('APP_NAME', 'Sistema de Inventario');

        $htmlBody = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 24px; color: #212529;">
            <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 28px; border: 1px solid #dee2e6;">
                <h2 style="margin-top: 0; color: #0d6efd;">{$appName}</h2>
                <p>Hola, <strong>{$nombreSeguro}</strong>:</p>
                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el siguiente botón para crear una nueva:</p>
                <p style="text-align: center; margin: 28px 0;">
                    <a href="{$enlaceSeguro}" style="background-color: #0d6efd; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">
                        Restablecer contraseña
                    </a>
                </p>
                <p style="font-size: 13px; color: #6c757d;">
                    Este enlace expirará en <strong>1 hora</strong>. Si tú no solicitaste este cambio, ignora este correo y tu contraseña actual seguirá intacta.
                </p>
                <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">
                <p style="font-size: 12px; color: #6c757d; word-break: break-all;">
                    Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
                    {$enlaceSeguro}
                </p>
            </div>
        </body>
        </html>
        HTML;

        $altBody = "Hola {$destinatarioNombre},\n\n"
            . "Usa el siguiente enlace para restablecer tu contraseña (expira en 1 hora):\n"
            . "{$enlaceRecuperacion}\n\n"
            . "Si no solicitaste este cambio, ignora este mensaje.";

        return self::enviarCorreo($destinatarioEmail, $destinatarioNombre, $asunto, $htmlBody, $altBody);
    }
}