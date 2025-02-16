<?php
// Remove debug logging
$phpmailer_files = [
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php'
];

foreach ($phpmailer_files as $file) {
    if (!file_exists($file)) {
        error_log("Missing PHPMailer file: " . $file);
        die("PHPMailer file not found: " . basename($file) . ". Please run 'composer install'");
    }
    require_once $file;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->SMTPDebug = 0; // Disable debug output
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jeanelleegalasinao@gmail.com';
        $mail->Password   = 'vndlpswdtxiwshyq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('jeanelleegalasinao@gmail.com', 'STMA LMS');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}
?> 