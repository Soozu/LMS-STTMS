<?php
function getWelcomeEmailTemplate($username, $password) {
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #8B0000;
                color: white;
                padding: 20px;
                text-align: center;
            }
            .content {
                padding: 20px;
                background-color: #f9f9f9;
            }
            .credentials {
                background-color: #fff;
                padding: 15px;
                margin: 20px 0;
                border-left: 4px solid #8B0000;
            }
            .footer {
                text-align: center;
                padding: 20px;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to STMA LMS</h1>
            </div>
            <div class="content">
                <p>Dear Student,</p>
                <p>Welcome to St. Thomas More Academy Learning Management System. Your account has been created successfully.</p>
                
                <div class="credentials">
                    <h3>Your Login Credentials</h3>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>Password:</strong> {$password}</p>
                </div>
                
                <p><strong>Important:</strong></p>
                <ul>
                    <li>Please change your password after your first login</li>
                    <li>Keep your credentials secure and do not share them with others</li>
                    <li>If you experience any issues, please contact your administrator</li>
                </ul>
                
                <p>You can access the LMS at: <a href="http://localhost/LMS-STMA">STMA LMS</a></p>
            </div>
            <div class="footer">
                <p>This is an automated message, please do not reply.</p>
                <p>&copy; 2024 St. Thomas More Academy. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}
?> 