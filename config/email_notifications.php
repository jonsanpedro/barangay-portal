
<?php
// NEW FILE: config/email_notifications.php
// Install PHPMailer first:
// composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Returns true only if Gmail accepted the
 * message for delivery.
 */
function sendPortalEmail(
    string $recipient,
    string $subject,
    string $message
): bool {

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log(
            'Email notification skipped: invalid recipient.'
        );
        return false;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (!is_file($autoload)) {
        error_log(
            'Email notification skipped: PHPMailer is not installed.'
        );
        return false;
    }

    require_once $autoload;

    $config = require __DIR__ . '/smtp_config.php';

    if (
        str_starts_with($config['username'], 'YOUR_') ||
        str_starts_with($config['app_password'], 'YOUR_')
    ) {
        error_log(
            'Email notification skipped: configure Gmail credentials.'
        );
        return false;
    }

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];
        $mail->Password = $config['app_password'];

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = (int) $config['port'];

        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress($recipient);

        $mail->isHTML(false);

        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();

        return true;

    } catch (Exception $e) {

        // Never log passwords or expose SMTP
        // details to residents.
        error_log(
            'Portal email delivery failed: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}
