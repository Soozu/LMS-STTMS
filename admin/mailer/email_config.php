<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

// Add this at the top of the file for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

function sendCredentialsEmail($studentEmail, $studentName, $lrn, $password) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jeanelleegalasinao@gmail.com';
        $mail->Password   = 'vndlpswdtxiwshyq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('jeanelleegalasinao@gmail.com', 'STMA LMS Admin');
        $mail->addAddress($studentEmail, $studentName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your STMA LMS Account Credentials';
        
        // Email body
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #8B0000; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>Welcome to STMA LMS</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f9f9f9;'>
                <p>Dear <strong>{$studentName}</strong>,</p>
                
                <p>Your account has been created in the STMA Learning Management System. Here are your login credentials:</p>
                
                <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>LRN (Username):</strong> {$lrn}</p>
                    <p style='margin: 5px 0;'><strong>Password:</strong> {$password}</p>
                </div>
                
                <p><strong>Important:</strong></p>
                <ul>
                    <li>Please change your password after your first login</li>
                    <li>Keep your credentials secure and do not share them with anyone</li>
                    <li>If you have any issues logging in, please contact your administrator</li>
                </ul>
                
                <p>You can access the LMS at: <a href='http://localhost/LMS-STMA/login.php'>STMA LMS Login</a></p>
            </div>
            
            <div style='text-align: center; padding: 20px; color: #666;'>
                <p>This is an automated message, please do not reply to this email.</p>
            </div>
        </div>";

        $mail->Body = $body;
        $mail->AltBody = "Welcome to STMA LMS\n\nYour credentials:\nLRN (Username): {$lrn}\nPassword: {$password}\n\nPlease change your password after first login.";

        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP DEBUG: $str");
        };

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        error_log("Detailed Error: " . $mail->ErrorInfo);
        return false;
    }
} 