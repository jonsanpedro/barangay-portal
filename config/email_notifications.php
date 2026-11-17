
<?php
// config/email_notifications.php
// Install PHPMailer using:
// composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an email notification through SMTP.
 *
 * Returns true if the SMTP server accepts
 * the message for delivery.
 *
 * Returns false if sending fails or
 * SMTP credentials are not configured.
 */
function sendPortalEmail(
    string $recipient,
    string $subject,
    string $message
): bool {

    // STEP 1: Validate recipient email.
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log(
            'Email notification skipped: invalid recipient.'
        );
        return false;
    }

    // STEP 2: Check PHPMailer installation.
    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (!is_file($autoload)) {
        error_log(
            'Email notification skipped: PHPMailer is not installed.'
        );
        return false;
    }

    require_once $autoload;

    // STEP 3: Load SMTP configuration.
    $config = require __DIR__ . '/smtp_config.php';

    // STEP 4: Check SMTP credentials.
    // Skip sending if credentials are missing.
    if (
        empty($config['username']) ||
        empty($config['app_password']) ||
        str_starts_with($config['username'], 'YOUR_') ||
        str_starts_with($config['app_password'], 'YOUR_')
    ) {
        error_log(
            'Email notification skipped: SMTP credentials are not configured.'
        );
        return false;
    }

    // STEP 5: Initialize PHPMailer.
    $mail = new PHPMailer(true);

    try {

        // Configure SMTP.
        $mail->isSMTP();

        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];
        $mail->Password = $config['app_password'];

        // Enable STARTTLS encryption.
        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = (int) $config['port'];

        // Email settings.
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        // Sender information.
        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        // Recipient information.
        $mail->addAddress($recipient);

        // Send plain text email.
        $mail->isHTML(false);

        $mail->Subject = $subject;
        $mail->Body = $message;

        // Send the email.
        $mail->send();

        return true;

    } catch (Exception $e) {

        // Log the failure without exposing
        // SMTP details to website visitors.
        error_log(
            'Portal email delivery failed: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}
