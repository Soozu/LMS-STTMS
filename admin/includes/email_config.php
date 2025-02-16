<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function sendStudentCredentials($studentEmail, $studentName, $lrn, $password) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jeanelleegalasinao@gmail.com';
        $mail->Password = 'vndlpswdtxiwshyq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('jeanelleegalasinao@gmail.com', 'LMS Admin');
        $mail->addAddress($studentEmail, $studentName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your LMS Account Credentials';
        
        // Email body with modern red and white design
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
            <!-- Header with red background -->
            <div style='background-color: #cc0000; padding: 30px; border-radius: 8px 8px 0 0;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px; text-align: center;'>Welcome to LMS-STMA!</h1>
            </div>
            
            <!-- Content section -->
            <div style='padding: 30px; background-color: #ffffff;'>
                <p style='color: #333333; font-size: 16px; line-height: 1.5;'>Dear <strong>{$studentName}</strong>,</p>
                <p style='color: #333333; font-size: 16px; line-height: 1.5;'>Your account has been created successfully. Here are your login credentials:</p>
                
                <!-- Credentials box -->
                <div style='background-color: #f8f8f8; border-left: 4px solid #cc0000; padding: 20px; margin: 25px 0; border-radius: 4px;'>
                    <p style='margin: 0 0 10px 0; color: #333333;'>
                        <strong style='color: #cc0000;'>Username (LRN):</strong><br>
                        <span style='font-family: monospace; font-size: 16px; color: #666666;'>{$lrn}</span>
                    </p>
                    <p style='margin: 0; color: #333333;'>
                        <strong style='color: #cc0000;'>Password:</strong><br>
                        <span style='font-family: monospace; font-size: 16px; color: #666666;'>{$password}</span>
                    </p>
                </div>
                
                <!-- Security notice -->
                <div style='background-color: #fff3f3; padding: 15px; border-radius: 4px; margin-bottom: 20px;'>
                    <p style='color: #cc0000; margin: 0; font-size: 14px;'>
                        <strong>⚠️ Security Notice:</strong><br>
                        For your security, please change your password after your first login.
                    </p>
                </div>
                
                <!-- Help text -->
                <p style='color: #666666; font-size: 14px; line-height: 1.5;'>
                    If you have any questions or need assistance, please contact your administrator.
                </p>
            </div>
            
            <!-- Footer -->
            <div style='background-color: #f8f8f8; padding: 20px; border-radius: 0 0 8px 8px; border-top: 1px solid #eeeeee;'>
                <p style='color: #999999; font-size: 12px; text-align: center; margin: 0;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>";

        $mail->Body = $body;
        // Create plain text version
        $mail->AltBody = "
Welcome to LMS-STMA!

Dear {$studentName},

Your account has been created successfully. Here are your login credentials:

Username (LRN): {$lrn}
Password: {$password}

SECURITY NOTICE: For your security, please change your password after your first login.

If you have any questions or need assistance, please contact your administrator.

---
This is an automated message. Please do not reply to this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
} 